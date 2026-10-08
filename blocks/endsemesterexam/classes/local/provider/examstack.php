<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Examstack exam provider.
 *
 * @package   block_endsemesterexam
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_endsemesterexam\local\provider;

use block_endsemesterexam\local\config;

defined('MOODLE_INTERNAL') || die();

/**
 * One-step SSO: POST the candidate's email to the ERP, receive a short-lived
 * access token, append it to the exam app's SSO URL.
 *
 * The exchange is server-side so the API key never reaches the browser.
 *
 * This is the handshake the plugin originally shipped with, hardcoded. Its
 * former literals remain here as defaults so a site that has configured nothing
 * behaves exactly as before -- but only as a complete set, and only when no
 * endpoint has been named. A site that names its own endpoint must supply its
 * own key, so no institution can end up sending its learners to another's
 * portal by configuring half of this.
 */
class examstack implements provider {

    /** @var string Endpoint this plugin shipped with, before the values were settings. */
    const LEGACY_ENDPOINT = 'https://dev.api-erp.onlinejain.com/api/v1/auth/sso-login/';

    /** @var string API key this plugin shipped with. */
    const LEGACY_APIKEY = '83fd25f4-0e77-4af2-9f7e-9af6a39e64f9';

    /** @var string Origin this plugin shipped with; the API enforces it as a source gate. */
    const LEGACY_ORIGIN = 'https://learn.onlinejain.com';

    /** @var string Exam app SSO URL this plugin shipped with; the token is appended. */
    const LEGACY_PORTAL = 'https://exams.onlinejain.com/sso-login/?token=';

    /**
     * Endpoint, key, origin and portal URL.
     *
     * @return array ['endpoint','apikey','origin','portalurl']
     * @throws \moodle_exception When an endpoint is named without a key or portal URL.
     */
    protected function settings(): array {
        $endpoint = config::str('ssoendpoint');

        if ($endpoint === '') {
            // Nothing configured: the historical behaviour, as a complete set.
            return [
                'endpoint'  => self::LEGACY_ENDPOINT,
                'apikey'    => self::LEGACY_APIKEY,
                'origin'    => self::LEGACY_ORIGIN,
                'portalurl' => self::LEGACY_PORTAL,
            ];
        }

        $apikey = config::str('ssoapikey');
        $portalurl = config::str('examportalurl');
        if ($apikey === '' || $portalurl === '') {
            throw new \moodle_exception('examnotconfigured', 'block_endsemesterexam');
        }

        return [
            'endpoint'  => $endpoint,
            'apikey'    => $apikey,
            'origin'    => config::str('ssoorigin'),
            'portalurl' => $portalurl,
        ];
    }

    #[\Override]
    public function exam_url(string $identifier, \stdClass $user): string {
        $settings = $this->settings();

        $headers = ['Content-Type: application/json', 'X-API-KEY: ' . $settings['apikey']];
        if ($settings['origin'] !== '') {
            $headers[] = 'Origin: ' . $settings['origin'];
        }

        $curl = new \curl();
        $curl->setHeader($headers);
        $curl->setopt(['CURLOPT_TIMEOUT' => 30]);
        $response = $curl->post($settings['endpoint'], json_encode(['email' => $user->email]));
        $httpcode = (int) ($curl->get_info()['http_code'] ?? 0);

        $data = $response ? json_decode($response, true) : null;
        $access = $data['data']['access'] ?? null;

        if ($httpcode !== 200 || empty($access)) {
            throw new \moodle_exception('ssofailed', 'block_endsemesterexam', '', null,
                'Examstack SSO failed: http=' . $httpcode);
        }

        return $settings['portalurl'] . rawurlencode((string) $access);
    }
}
