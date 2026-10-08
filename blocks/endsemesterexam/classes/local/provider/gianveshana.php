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
 * Gianveshana exam provider.
 *
 * @package   block_endsemesterexam
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_endsemesterexam\local\provider;

use block_endsemesterexam\local\config;

defined('MOODLE_INTERNAL') || die();

/**
 * Two-step handshake: exchange the candidate's student code for a bearer token
 * under HTTP basic auth, then ask the exam-URL endpoint what that token unlocks.
 *
 * Unlike Examstack this provider identifies the candidate by student code
 * rather than by email address, so it requires an entitlement gate that yields
 * one -- the profile-field gate.
 *
 * There are no legacy defaults here: this handshake was never the plugin's
 * shipped behaviour, so a site selecting it supplies all four values or the
 * button fails closed.
 */
class gianveshana implements provider {

    /**
     * Token endpoint, exam-URL endpoint and the basic-auth pair.
     *
     * @return array ['tokenurl','examurl','user','password']
     * @throws \moodle_exception When any value is missing.
     */
    protected function settings(): array {
        $settings = [
            'tokenurl' => config::str('tokenendpoint'),
            'examurl'  => config::str('examurlendpoint'),
            'user'     => config::str('apiuser'),
            'password' => config::str('apipassword'),
        ];

        foreach ($settings as $value) {
            if ($value === '') {
                throw new \moodle_exception('examnotconfigured', 'block_endsemesterexam');
            }
        }

        return $settings;
    }

    #[\Override]
    public function exam_url(string $identifier, \stdClass $user): string {
        $settings = $this->settings();

        if ($identifier === '') {
            // The candidate is identified solely by student code here, so there
            // is no session to start without one. This is the learner's answer,
            // not a misconfiguration: they have no register number on file.
            throw new \moodle_exception('notentitled', 'block_endsemesterexam', '', null,
                'gianveshana provider reached with no student code for this user');
        }

        $token = $this->fetch_token($settings, $identifier);

        $curl = new \curl();
        $curl->setHeader(['Accept: application/json', 'Authorization: Bearer ' . $token]);
        $curl->setopt(['CURLOPT_TIMEOUT' => 30]);
        $response = $curl->get($settings['examurl']);
        $httpcode = (int) ($curl->get_info()['http_code'] ?? 0);

        $data = $response ? json_decode($response, true) : null;
        $url = is_array($data) && !empty($data['url']) ? trim((string) $data['url']) : '';

        if ($httpcode !== 200 || $url === '') {
            throw new \moodle_exception('ssofailed', 'block_endsemesterexam', '', null,
                'exam URL request failed: http=' . $httpcode);
        }

        return $url;
    }

    /**
     * Exchange a student code for a bearer token.
     *
     * @param array $settings
     * @param string $identifier Student code.
     * @return string
     * @throws \moodle_exception On a failed exchange or an unknown candidate.
     */
    protected function fetch_token(array $settings, string $identifier): string {
        $curl = new \curl();
        $curl->setHeader(['Content-Type: application/json', 'Accept: application/json']);
        $curl->setopt([
            'CURLOPT_USERPWD'  => $settings['user'] . ':' . $settings['password'],
            'CURLOPT_HTTPAUTH' => CURLAUTH_BASIC,
            'CURLOPT_TIMEOUT'  => 30,
        ]);
        $response = $curl->post($settings['tokenurl'], json_encode(['StudentCode' => $identifier]));
        $httpcode = (int) ($curl->get_info()['http_code'] ?? 0);

        $data = $response ? json_decode($response, true) : null;
        $token = is_array($data) && isset($data['Token']) ? trim((string) $data['Token']) : '';

        if ($httpcode !== 200 || $token === '') {
            throw new \moodle_exception('ssofailed', 'block_endsemesterexam', '', null,
                'token request failed: http=' . $httpcode);
        }

        // An unknown student code comes back as prose in the token field rather
        // than as an error status, so it has to be read rather than inferred.
        if (stripos($token, 'invalid') !== false) {
            throw new \moodle_exception('unknowncandidate', 'block_endsemesterexam');
        }

        return $token;
    }
}
