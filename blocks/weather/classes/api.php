<?php
/**
 * Weather block - talks to the free Open-Meteo API (no key needed) and caches results.
 *
 * @package    block_weather
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_weather;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/filelib.php');

class api {

    /** Weather codes (WMO) mapped to a language string and a Font Awesome icon. */
    const CODES = [
        0 => ['wmo0', 'fa-sun'], 1 => ['wmo1', 'fa-sun'], 2 => ['wmo2', 'fa-cloud-sun'], 3 => ['wmo3', 'fa-cloud'],
        45 => ['wmo45', 'fa-smog'], 48 => ['wmo45', 'fa-smog'],
        51 => ['wmo51', 'fa-cloud-rain'], 53 => ['wmo51', 'fa-cloud-rain'], 55 => ['wmo51', 'fa-cloud-rain'],
        56 => ['wmo51', 'fa-cloud-rain'], 57 => ['wmo51', 'fa-cloud-rain'],
        61 => ['wmo61', 'fa-cloud-rain'], 63 => ['wmo63', 'fa-cloud-showers-heavy'], 65 => ['wmo65', 'fa-cloud-showers-heavy'],
        66 => ['wmo61', 'fa-cloud-rain'], 67 => ['wmo65', 'fa-cloud-showers-heavy'],
        71 => ['wmo71', 'fa-snowflake'], 73 => ['wmo71', 'fa-snowflake'], 75 => ['wmo75', 'fa-snowflake'], 77 => ['wmo71', 'fa-snowflake'],
        80 => ['wmo80', 'fa-cloud-sun-rain'], 81 => ['wmo80', 'fa-cloud-sun-rain'], 82 => ['wmo82', 'fa-cloud-showers-heavy'],
        85 => ['wmo71', 'fa-snowflake'], 86 => ['wmo75', 'fa-snowflake'],
        95 => ['wmo95', 'fa-bolt'], 96 => ['wmo99', 'fa-bolt'], 99 => ['wmo99', 'fa-bolt'],
    ];

    /**
     * Returns [label, icon] for a weather code.
     */
    public static function describe(int $code): array {
        [$str, $icon] = self::CODES[$code] ?? ['wmo3', 'fa-cloud'];
        return [get_string($str, 'block_weather'), $icon];
    }

    /**
     * Calls a URL and returns decoded JSON, or null.
     */
    protected static function get_json(string $url, array $params) {
        $curl = new \curl(['ignoresecurity' => true]); // Public weather API over HTTPS only.
        $curl->setopt(['CURLOPT_TIMEOUT' => 8, 'CURLOPT_CONNECTTIMEOUT' => 5]);
        $body = $curl->get($url, $params);
        if ($curl->get_errno() || empty($body)) {
            return null;
        }
        $data = json_decode($body);
        return is_object($data) ? $data : null;
    }

    /**
     * Finds latitude/longitude for a city name (cached).
     */
    public static function find_city(string $city) {
        $cache = \cache::make('block_weather', 'forecast');
        $key = 'city_' . sha1(\core_text::strtolower(trim($city)));
        if (($hit = $cache->get($key)) !== false) {
            return $hit;
        }
        $name = trim(explode(',', $city)[0]);
        $data = self::get_json('https://geocoding-api.open-meteo.com/v1/search',
            ['name' => $name, 'count' => 1, 'language' => 'en', 'format' => 'json']);
        if ($data === null) {
            return null; // Service unreachable - do not cache.
        }
        $place = false;
        if (!empty($data->results[0])) {
            $r = $data->results[0];
            $place = (object)['name' => $r->name, 'country' => $r->country ?? '', 'lat' => $r->latitude, 'lon' => $r->longitude];
        }
        $cache->set($key, $place);
        return $place;
    }

    /**
     * Returns the forecast for a place (cached for 30 minutes).
     */
    public static function forecast($place, string $units) {
        $cache = \cache::make('block_weather', 'forecast');
        $key = 'wx_' . sha1($place->lat . '_' . $place->lon . '_' . $units);
        if (($hit = $cache->get($key)) !== false) {
            return $hit;
        }
        $params = [
            'latitude' => $place->lat, 'longitude' => $place->lon, 'timezone' => 'auto', 'forecast_days' => 4,
            'current' => 'temperature_2m,relative_humidity_2m,apparent_temperature,weather_code,wind_speed_10m',
            'daily' => 'weather_code,temperature_2m_max,temperature_2m_min',
        ];
        if ($units === 'f') {
            $params['temperature_unit'] = 'fahrenheit';
        }
        $data = self::get_json('https://api.open-meteo.com/v1/forecast', $params);
        if ($data === null || empty($data->current)) {
            return null;
        }
        $data->fetched = time();
        $cache->set($key, $data);
        return $data;
    }

    /**
     * Builds the weather HTML for a city (or the site default) with the given template.
     * Returns a friendly message instead of failing if the weather service cannot be reached.
     */
    public static function render(string $city = '', string $template = 'block_weather/content'): string {
        global $OUTPUT;
        $city = $city ?: (get_config('block_weather', 'defaultcity') ?: 'Bengaluru');
        $units = get_config('block_weather', 'units') ?: 'c';
        $place = self::find_city($city);
        if ($place === false) {
            return $OUTPUT->notification(get_string('cityunknown', 'block_weather', s($city)), 'warning', false);
        }
        $wx = $place ? self::forecast($place, $units) : null;
        if (!$wx) {
            return $OUTPUT->notification(get_string('unavailable', 'block_weather'), 'info', false);
        }
        $deg = $units === 'f' ? '°F' : '°C';
        [$label, $icon] = self::describe((int)$wx->current->weather_code);
        $days = [];
        for ($i = 1; $i <= 3 && isset($wx->daily->time[$i]); $i++) {
            [$dlabel, $dicon] = self::describe((int)$wx->daily->weather_code[$i]);
            $days[] = [
                'day' => userdate(strtotime($wx->daily->time[$i]), '%a'),
                'icon' => $dicon, 'label' => $dlabel,
                'max' => round($wx->daily->temperature_2m_max[$i]), 'min' => round($wx->daily->temperature_2m_min[$i]),
            ];
        }
        $data = [
            'place' => $place->name . ($place->country ? ', ' . $place->country : ''),
            'icon' => $icon, 'label' => $label, 'deg' => $deg,
            'temp' => round($wx->current->temperature_2m),
            'feels' => get_string('feelslike', 'block_weather', round($wx->current->apparent_temperature) . $deg),
            'humidity' => round($wx->current->relative_humidity_2m) . '%',
            'wind' => round($wx->current->wind_speed_10m) . ' km/h',
            'todaymax' => isset($wx->daily->temperature_2m_max[0]) ? round($wx->daily->temperature_2m_max[0]) : '',
            'todaymin' => isset($wx->daily->temperature_2m_min[0]) ? round($wx->daily->temperature_2m_min[0]) : '',
            'days' => $days,
            'updated' => get_string('updated', 'block_weather', userdate($wx->fetched, get_string('strftimetime', 'langconfig'))),
        ];
        return $OUTPUT->render_from_template($template, $data);
    }

    /**
     * Weather card for the theme's Dashboard pages ('' when switched off or on any error).
     */
    public static function dashboard_widget(): string {
        if (!get_config('block_weather', 'showondashboard') && get_config('block_weather', 'showondashboard') !== false) {
            return '';
        }
        try {
            return self::render('', 'block_weather/widget');
        } catch (\Throwable $e) {
            debugging('block_weather widget: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return '';
        }
    }
}
