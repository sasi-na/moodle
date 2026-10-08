<?php
/**
 * Weather block - English strings.
 *
 * @package    block_weather
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Weather';
$string['weather:addinstance'] = 'Add a new Weather block';
$string['weather:myaddinstance'] = 'Add a new Weather block to the Dashboard';
$string['cachedef_forecast'] = 'Weather forecasts and city lookups';
$string['privacy:metadata'] = 'The Weather block does not store personal data. It sends the configured city name to the Open-Meteo weather service.';
$string['config_city'] = 'City';
$string['config_city_help'] = 'Name of the city to show the weather for, for example "Bengaluru" or "Mumbai, India". Leave empty to use the site default.';
$string['config_title'] = 'Block title';
$string['defaultcity'] = 'Default city';
$string['defaultcity_desc'] = 'City shown when a Weather block has no city of its own.';
$string['units'] = 'Temperature units';
$string['units_desc'] = 'Celsius or Fahrenheit.';
$string['celsius'] = 'Celsius (°C)';
$string['fahrenheit'] = 'Fahrenheit (°F)';
$string['cityunknown'] = 'Could not find the city "{$a}". Edit the block and check the spelling.';
$string['unavailable'] = 'Weather is not available right now. Please try again later.';
$string['feelslike'] = 'Feels like {$a}';
$string['humidity'] = 'Humidity';
$string['wind'] = 'Wind';
$string['today'] = 'Today';
$string['updated'] = 'Updated {$a}';
$string['source'] = 'Weather data by Open-Meteo.com';
$string['wmo0'] = 'Clear sky';
$string['wmo1'] = 'Mainly clear';
$string['wmo2'] = 'Partly cloudy';
$string['wmo3'] = 'Overcast';
$string['wmo45'] = 'Fog';
$string['wmo51'] = 'Light drizzle';
$string['wmo61'] = 'Light rain';
$string['wmo63'] = 'Rain';
$string['wmo65'] = 'Heavy rain';
$string['wmo71'] = 'Light snow';
$string['wmo75'] = 'Heavy snow';
$string['wmo80'] = 'Rain showers';
$string['wmo82'] = 'Heavy showers';
$string['wmo95'] = 'Thunderstorm';
$string['wmo99'] = 'Thunderstorm with hail';
$string['showondashboard'] = 'Show weather on the Dashboard';
$string['showondashboard_desc'] = 'Shows a weather card at the top of every user\'s Dashboard (student, teacher and admin views) for the default city.';
