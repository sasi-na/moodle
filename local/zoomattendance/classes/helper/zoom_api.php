<?php
namespace local_zoomattendance\helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Thin Zoom Server-to-Server OAuth API client shared by the cron task,
 * the join endpoint and the meeting registry sync.
 *
 * Uses Moodle's \curl wrapper so proxy / curl-security settings are honoured,
 * caches the OAuth token in plugin config behind a lock (so a click stampede
 * at class start does not fan out to one OAuth request per visitor), and
 * negative-caches failures briefly so an outage cannot pin worker processes.
 *
 * The token is stored in config (DB-backed) rather than an application/file
 * cache deliberately: cron runs as root and the web server as www-data, so a
 * file-cache entry written by one user would be unreadable/unwritable by the
 * other. Config is shared cleanly across both.
 */
class zoom_api {

    /** Refresh the token this many seconds before it actually expires. */
    const TOKEN_SKEW = 120;

    /** Don't retry a failed token fetch for this long (seconds). */
    const TOKEN_FAIL_BACKOFF = 30;

    /** How long to wait for the refresh lock before failing soft. */
    const TOKEN_LOCK_WAIT = 5;

    /**
     * Read a plugin credential from config. Returns '' (trimmed) when unset.
     *
     * Credentials are admin-settings only — there are deliberately no
     * hardcoded fallbacks, so an unconfigured install fails cleanly instead
     * of authenticating against some other account's Zoom credentials.
     */
    private static function cfg(string $name): string {
        $value = get_config('local_zoomattendance', $name);
        return is_string($value) ? trim($value) : '';
    }

    /**
     * Ensure Moodle's \curl class is loaded. It lives in lib/filelib.php,
     * which is only auto-included in web requests — cron / CLI (where the
     * attendance task runs) must require it explicitly.
     */
    private static function ensure_curl(): void {
        global $CFG;
        if (!class_exists('curl')) {
            require_once($CFG->libdir . '/filelib.php');
        }
    }

    /**
     * Read the token cache.
     *
     * @return string|null token string if valid, '' if we are inside the
     *                     failure back-off window, null if a refresh is needed
     */
    private static function read_cache(): ?string {
        $cached = get_config('local_zoomattendance', 'tokencache');
        if (empty($cached)) {
            return null;
        }
        $parts = json_decode($cached, true);
        if (!is_array($parts)) {
            return null;
        }
        $expires = (int)($parts['expires'] ?? 0);
        if (!empty($parts['token']) && $expires > time() + self::TOKEN_SKEW) {
            return $parts['token'];
        }
        // Negative cache: a recent failure asked us not to hammer Zoom.
        if (empty($parts['token']) && $expires > time()) {
            return '';
        }
        return null;
    }

    /**
     * Get a valid access token, refreshing behind a lock if needed.
     */
    public static function get_token(): string {

        $cached = self::read_cache();
        if ($cached !== null) {
            return $cached;
        }

        // Serialise refresh so concurrent requests don't each call Zoom.
        $factory = \core\lock\lock_config::get_lock_factory('local_zoomattendance');
        $lock = $factory->get_lock('zoom_token', self::TOKEN_LOCK_WAIT);

        if (!$lock) {
            // Couldn't get the refresh lock quickly. Don't pile onto Zoom or
            // pin this worker waiting — use whatever is cached, else fail soft
            // for this request (the caller treats '' as a transient failure).
            return self::read_cache() ?? '';
        }

        try {
            // Another request may have refreshed while we waited for the lock.
            $cached = self::read_cache();
            if ($cached !== null) {
                return $cached;
            }
            return self::refresh_token();
        } finally {
            $lock->release();
        }
    }

    /**
     * Force the next call to fetch a brand-new token.
     */
    public static function purge_token(): void {
        unset_config('tokencache', 'local_zoomattendance');
    }

    /**
     * Fetch a fresh token from Zoom and cache it (or negative-cache failure).
     */
    private static function refresh_token(): string {

        $accountid = self::cfg('zoom_account_id');
        $clientid = self::cfg('zoom_client_id');
        $secret = self::cfg('zoom_client_secret');

        if ($accountid === '' || $clientid === '' || $secret === '') {
            // Not configured yet. Back off (so this doesn't fan out to one
            // failed call per request) and point the admin at the settings.
            set_config('tokencache',
                json_encode(['token' => '', 'expires' => time() + self::TOKEN_FAIL_BACKOFF]),
                'local_zoomattendance');
            debugging('local_zoomattendance: Zoom credentials are not configured. '
                . 'Set zoom_account_id, zoom_client_id and zoom_client_secret under '
                . 'Site administration > Plugins > Local plugins > Zoom Attendance.', DEBUG_NORMAL);
            return '';
        }

        self::ensure_curl();
        $curl = new \curl();
        $curl->setopt(['CURLOPT_TIMEOUT' => 20, 'CURLOPT_CONNECTTIMEOUT' => 10]);
        $curl->setHeader('Authorization: Basic ' . base64_encode("{$clientid}:{$secret}"));
        $curl->setHeader('Content-Type: application/x-www-form-urlencoded');

        $res = $curl->post(
            "https://zoom.us/oauth/token?grant_type=account_credentials&account_id={$accountid}",
            ''
        );
        $status = (int)($curl->get_info()['http_code'] ?? 0);

        $data = json_decode((string)$res, true);

        if (empty($data['access_token'])) {
            // Back off so a stampede or outage cannot fan out to one OAuth
            // call per request.
            set_config('tokencache',
                json_encode(['token' => '', 'expires' => time() + self::TOKEN_FAIL_BACKOFF]),
                'local_zoomattendance');
            debugging('local_zoomattendance: Zoom token request failed (HTTP ' . $status
                . ', curl errno ' . $curl->errno . '): ' . substr((string)$res, 0, 300), DEBUG_NORMAL);
            return '';
        }

        set_config('tokencache', json_encode([
            'token' => $data['access_token'],
            'expires' => time() + (int)($data['expires_in'] ?? 3600)
        ]), 'local_zoomattendance');

        return $data['access_token'];
    }

    /**
     * Perform an API request against api.zoom.us/v2.
     *
     * @param string $method GET|POST
     * @param string $path path starting with /, may include query string
     * @param array|null $body JSON body for POST
     * @param bool $retry internal: allow one retry after a 401 token refresh
     * @return array ['status' => int http status, 'data' => array decoded body]
     */
    public static function request(string $method, string $path, ?array $body = null,
            bool $retry = true): array {

        $token = self::get_token();
        if (empty($token)) {
            return ['status' => 0, 'data' => []];
        }

        self::ensure_curl();
        $curl = new \curl();
        $curl->setopt(['CURLOPT_TIMEOUT' => 20, 'CURLOPT_CONNECTTIMEOUT' => 10]);
        $curl->setHeader('Authorization: Bearer ' . $token);

        $url = 'https://api.zoom.us/v2' . $path;

        switch ($method) {
            case 'POST':
                $curl->setHeader('Content-Type: application/json');
                $res = $curl->post($url, json_encode($body ?? []));
                break;
            case 'PATCH':
                $curl->setHeader('Content-Type: application/json');
                $res = $curl->patch($url, json_encode($body ?? []));
                break;
            case 'DELETE':
                $res = $curl->delete($url);
                break;
            default:
                $res = $curl->get($url);
        }

        $status = (int)($curl->get_info()['http_code'] ?? 0);

        // \curl returns an error string (not false) for transport/curlsecurity
        // failures; the http_code then stays 0, which we treat as a failure.
        if ($res === false || $curl->errno || $status === 0) {
            return ['status' => 0, 'data' => []];
        }

        // Token revoked/rotated mid-cache: drop it and retry once with a fresh one.
        if ($status === 401 && $retry) {
            self::purge_token();
            return self::request($method, $path, $body, false);
        }

        return ['status' => $status, 'data' => json_decode((string)$res, true) ?: []];
    }

    public static function get(string $path): array {
        return self::request('GET', $path);
    }

    public static function post(string $path, array $body): array {
        return self::request('POST', $path, $body);
    }

    public static function patch(string $path, array $body): array {
        return self::request('PATCH', $path, $body);
    }

    public static function delete(string $path): array {
        return self::request('DELETE', $path);
    }

    /**
     * Page through a list endpoint, collecting one array key across pages.
     *
     * @param string $path endpoint path (its own query string is preserved)
     * @param string $collection the response key holding the per-page array
     * @return array ['status' => int, 'items' => array] — status 200 means the
     *               full list was retrieved; anything else is a partial failure
     *               and callers MUST NOT treat 'items' as complete
     */
    public static function get_paginated(string $path, string $collection): array {
        $all = [];
        $next = '';
        $sep = (strpos($path, '?') === false) ? '?' : '&';

        do {
            $res = self::get($path . $sep . 'page_size=300&next_page_token=' . urlencode($next));
            if ($res['status'] !== 200) {
                return ['status' => $res['status'], 'items' => $all];
            }
            foreach ($res['data'][$collection] ?? [] as $item) {
                $all[] = $item;
            }
            $next = $res['data']['next_page_token'] ?? '';
        } while (!empty($next));

        return ['status' => 200, 'items' => $all];
    }
}
