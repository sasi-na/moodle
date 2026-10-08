<?php
namespace mod_customteams;

defined('MOODLE_INTERNAL') || die();

class graph_helper {

    /** Graph calls are cheap but not instant; do not hang a page load forever. */
    const TIMEOUT = 30;

    /**
     * App-only access token (client credentials).
     *
     * Credentials come from plugin settings, not from source. This codebase
     * builds a single image shared by every tenant in deploy/overlays/, so a
     * hardcoded secret would be shipped to all of them and could only be
     * rotated by a rebuild.
     */
    private static function token() {

        $tenant = trim((string) get_config('customteams', 'tenantid'));
        $client = trim((string) get_config('customteams', 'clientid'));
        $secret = (string) get_config('customteams', 'clientsecret');

        if ($tenant === '' || $client === '' || $secret === '') {
            throw new \moodle_exception('errornotconfigured', 'customteams');
        }

        $url = "https://login.microsoftonline.com/$tenant/oauth2/v2.0/token";

        $postfields = http_build_query([
            'client_id'     => $client,
            'client_secret' => $secret,
            'scope'         => 'https://graph.microsoft.com/.default',
            'grant_type'    => 'client_credentials',
        ]);

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postfields,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \moodle_exception('errorcurl', 'customteams', '', $error);
        }

        $res = json_decode($response, true);

        if (empty($res['access_token'])) {
            // The response body echoes the client secret back in some error
            // shapes, so surface only the stable error code.
            $code = $res['error'] ?? 'unknown_error';
            throw new \moodle_exception('errortoken', 'customteams', '', $code);
        }

        return $res['access_token'];
    }

    /**
     * Headers
     */
    private static function headers($token) {
        return [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ];
    }

    /**
     * Get Azure AD user ID (organizer)
     */
    private static function get_user_id($email, $token) {

        $url = "https://graph.microsoft.com/v1.0/users/" . rawurlencode($email);

        $res = self::call('GET', $url, $token);

        if (empty($res['id'])) {
            throw new \moodle_exception('erroruserlookup', 'customteams', '', s($email));
        }

        return $res['id'];
    }

    /**
     * Check a list of accounts against the tenant in as few calls as possible.
     *
     * Graph's JSON batching takes up to 20 subrequests per call, so the 99
     * accounts in Jain's map cost five requests rather than 99. Checking them
     * one at a time would put the admin page well past any sensible timeout.
     *
     * accountEnabled is reported alongside existence: a disabled account
     * resolves perfectly well through /users but cannot own a meeting, and that
     * is the failure that looks like a bug rather than a configuration problem.
     *
     * @param array $upns
     * @return array upn => object {ok, status, enabled, message}
     */
    public static function check_accounts(array $upns) {

        $upns = array_values(array_unique(array_filter(array_map('trim', $upns), 'strlen')));

        if (empty($upns)) {
            return [];
        }

        $token = self::token();
        $results = [];

        foreach (array_chunk($upns, 20) as $chunk) {

            $requests = [];
            foreach ($chunk as $i => $upn) {
                $requests[] = [
                    'id'     => (string) $i,
                    'method' => 'GET',
                    'url'    => '/users/' . rawurlencode($upn) . '?$select=id,userPrincipalName,accountEnabled',
                ];
            }

            $res = self::call('POST', 'https://graph.microsoft.com/v1.0/$batch', $token,
                ['requests' => $requests]);

            $results += self::parse_batch_responses($chunk, $res);
        }

        return $results;
    }

    /**
     * Turn one /$batch response into per-account results.
     *
     * Split out from check_accounts() so the mapping can be tested without a
     * tenant: the id-to-account correlation and the partial-response case are
     * where this would quietly go wrong, and both are invisible in a live run
     * that happens to succeed.
     *
     * @param array $chunk accounts sent, indexed as their request ids
     * @param array $res decoded batch response
     * @return array upn => object {ok, status, enabled, message}
     */
    public static function parse_batch_responses(array $chunk, $res) {

        $results = [];

        if (empty($res['responses']) || !is_array($res['responses'])) {
            // The batch itself failed -- bad token, throttling, network. Report
            // it against every account rather than leaving them silently absent.
            $message = $res['error']['message'] ?? get_string('checknoresponse', 'customteams');

            foreach ($chunk as $upn) {
                $results[$upn] = (object) [
                    'ok'      => false,
                    'status'  => 0,
                    'enabled' => null,
                    'message' => $message,
                ];
            }

            return $results;
        }

        $seen = [];

        foreach ($res['responses'] as $response) {
            $i = (int) ($response['id'] ?? -1);

            // Graph does not promise to return responses in request order, and
            // ids are the only thing tying a result back to an account.
            if (!isset($chunk[$i])) {
                continue;
            }

            $upn = $chunk[$i];
            $seen[$i] = true;
            $status = (int) ($response['status'] ?? 0);
            $body = $response['body'] ?? [];

            if ($status === 200 && !empty($body['id'])) {
                $enabled = array_key_exists('accountEnabled', $body) ? (bool) $body['accountEnabled'] : null;

                $results[$upn] = (object) [
                    'ok'      => $enabled !== false,
                    'status'  => $status,
                    'enabled' => $enabled,
                    'message' => $enabled === false ? get_string('checkdisabled', 'customteams') : '',
                ];
            } else {
                $results[$upn] = (object) [
                    'ok'      => false,
                    'status'  => $status,
                    'enabled' => null,
                    'message' => $status === 404
                        ? get_string('checknotfound', 'customteams')
                        : ($body['error']['message'] ?? get_string('checkfailed', 'customteams')),
                ];
            }
        }

        // Graph may return fewer responses than requests.
        foreach ($chunk as $i => $upn) {
            if (!isset($seen[$i])) {
                $results[$upn] = (object) [
                    'ok'      => false,
                    'status'  => 0,
                    'enabled' => null,
                    'message' => get_string('checknoresponse', 'customteams'),
                ];
            }
        }

        return $results;
    }

    /**
     * CREATE TEAMS MEETING (Calendar-based)
     *
     * @param string $email UPN of the account that will own the meeting
     */
    public static function create($email, $subject, $start, $end, $attendees = [], $teachers = []) {

        $token = self::token();

        $userid = self::get_user_id($email, $token);

        $url = "https://graph.microsoft.com/v1.0/users/$userid/events";

        $timezone = trim((string) get_config('customteams', 'timezone'));
        if ($timezone === '') {
            $timezone = 'Asia/Kolkata';
        }

        $payload = [
            "subject" => $subject,

            "start" => [
                "dateTime" => self::format_local($start, $timezone),
                "timeZone" => $timezone
            ],

            "end" => [
                "dateTime" => self::format_local($end, $timezone),
                "timeZone" => $timezone
            ],

            // This makes it a Teams meeting
            "isOnlineMeeting" => true,
            "onlineMeetingProvider" => "teamsForBusiness"
        ];

        $res = self::call('POST', $url, $token, $payload);

        if (empty($res['id'])) {
            throw new \moodle_exception('errormeetingcreate', 'customteams', '',
                s($res['error']['message'] ?? 'unknown error'));
        }

        $joinurl = $res['onlineMeeting']['joinUrl'] ?? '';

        if (!empty($joinurl) && get_config('customteams', 'autorecord')) {
            try {
                self::enable_auto_recording($userid, $joinurl, $token);
            } catch (\Throwable $e) {
                // Non-fatal: meeting is created, recording policy just won't be set.
                debugging('mod_customteams: auto-recording not applied: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        return [
            'meetingid' => $res['id'], // This is EVENT ID now
            'joinurl' => $joinurl
        ];
    }

    /**
     * Render a Unix timestamp as wall-clock time in the given zone.
     *
     * date() formats in PHP's default timezone, which is whatever
     * core_date::set_default_server_timezone() settled on -- UTC on this fleet,
     * since deploy/base/config/php.ini sets date.timezone=UTC and the overlays
     * do not set $CFG->timezone. Pairing a UTC wall-clock string with a
     * "timeZone": "Asia/Kolkata" label told Graph the wrong time, and every
     * meeting landed 5.5 hours late. Convert explicitly instead of depending on
     * the server's zone happening to match the configured one.
     */
    private static function format_local($timestamp, $timezone) {
        $date = new \DateTime('@' . (int) $timestamp);

        try {
            $date->setTimezone(new \DateTimeZone($timezone));
        } catch (\Exception $e) {
            throw new \moodle_exception('errorbadtimezone', 'customteams', '', s($timezone));
        }

        return $date->format('Y-m-d\TH:i:s');
    }

    /**
     * Find the onlineMeeting by join URL and enable auto-recording.
     *
     * Requires OnlineMeetings.ReadWrite.All AND a Teams application access
     * policy granted to the organizer account -- app-only calls to
     * /users/{id}/onlineMeetings are refused without the policy, which is why
     * this is best-effort and never blocks meeting creation.
     */
    private static function enable_auto_recording($userid, $joinurl, $token) {
        $filter = rawurlencode("joinWebUrl eq '$joinurl'");
        $url = "https://graph.microsoft.com/v1.0/users/$userid/onlineMeetings?\$filter=$filter";
        $res = self::call('GET', $url, $token);

        if (empty($res['value'][0]['id'])) {
            return;
        }

        $meetingid = rawurlencode($res['value'][0]['id']);
        $patchurl = "https://graph.microsoft.com/v1.0/users/$userid/onlineMeetings/$meetingid";
        self::call('PATCH', $patchurl, $token, ['recordAutomatically' => true]);
    }

    /**
     * DELETE MEETING (must use EVENTS endpoint)
     */
    public static function delete($email, $meetingid) {

        if (empty($meetingid)) {
            return;
        }

        $token = self::token();

        $userid = self::get_user_id($email, $token);

        // FIX: use events endpoint (NOT onlineMeetings)
        $url = "https://graph.microsoft.com/v1.0/users/$userid/events/$meetingid";

        self::call('DELETE', $url, $token);
    }

    /**
     * CURL CALL
     */
    private static function call($method, $url, $token, $payload = null) {

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => self::headers($token),
        ]);

        if (!empty($payload)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \moodle_exception('errorcurl', 'customteams', '', $error);
        }

        return json_decode($response, true);
    }
}
