<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_dttutorgemini;

use local_dttutor\httpclient\ai_client;

/**
 * Replacement for the DataCurso client: sessions/history live in Moodle, answers come from Gemini.
 *
 * @package   local_dttutorgemini
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client implements ai_client {

    /**
     * Gemini key (own setting, or the one GeniAI uses).
     *
     * @return string
     */
    public static function apikey(): string {
        $key = trim((string) get_config('local_dttutorgemini', 'apikey'));
        if ($key === '') {
            $key = trim((string) get_config('geniaicontroller_gemini', 'apikey'));
        }
        return $key;
    }

    /**
     * Whether a key and the shared secret exist.
     *
     * @return bool
     */
    public static function is_configured(): bool {
        return strlen(self::apikey()) > 10 && strlen((string) get_config('local_dttutorgemini', 'secret')) > 10;
    }

    /**
     * Emulates the DataCurso chat session API.
     *
     * @param string $method
     * @param string $path
     * @param array $body
     * @return array|null
     */
    public function request(string $method, string $path, array $body = []): ?array {
        global $DB;
        $route = parse_url($path, PHP_URL_PATH);
        parse_str((string) parse_url($path, PHP_URL_QUERY), $query);

        if ($method === 'POST' && str_starts_with($route, '/chat/start')) {
            return ['session_id' => 'lg-' . bin2hex(random_bytes(12)), 'session_ttl_seconds' => 30 * DAYSECS];
        }
        if ($method === 'POST' && str_starts_with($route, '/chat/messages/append')) {
            $DB->insert_record('local_dttutorgemini_msg', (object) [
                'sessionid' => substr((string) ($body['session_id'] ?? ''), 0, 64),
                'role' => ($body['role'] ?? '') === 'assistant' ? 'assistant' : 'user',
                'content' => (string) ($body['content'] ?? ''),
                'timecreated' => time(),
            ]);
            return ['success' => true];
        }
        if ($method === 'GET' && $route === '/chat/history') {
            $sid = (string) ($query['session_id'] ?? '');
            $limit = max(1, min(100, (int) ($query['limit'] ?? 20)));
            $offset = max(0, (int) ($query['offset'] ?? 0));
            $total = $DB->count_records('local_dttutorgemini_msg', ['sessionid' => $sid]);
            $records = $DB->get_records('local_dttutorgemini_msg', ['sessionid' => $sid], 'id ASC', '*', $offset, $limit);
            $messages = [];
            foreach ($records as $r) {
                $messages[] = ['id' => (string) $r->id, 'role' => $r->role, 'content' => $r->content, 'timestamp' => (int) $r->timecreated];
            }
            return ['success' => true, 'session_id' => $sid, 'total_messages' => $total, 'messages' => $messages,
                'pagination' => ['limit' => $limit, 'offset' => $offset, 'has_more' => $offset + $limit < $total]];
        }
        if ($method === 'DELETE' && str_starts_with($route, '/chat/session/')) {
            $DB->delete_records('local_dttutorgemini_msg', ['sessionid' => substr($route, strlen('/chat/session/'))]);
            return ['success' => true];
        }
        return ['success' => true];
    }

    /**
     * Tutor AI posts to {base}/provider/chat/completions: this plugin's endpoint.
     *
     * @return string
     */
    public function get_base_url(): string {
        global $CFG;
        return $CFG->wwwroot . '/local/dttutorgemini';
    }

    /**
     * Sent as the License-Key header; the endpoint only answers requests carrying it.
     *
     * @return string
     */
    public function get_license_key(): string {
        return (string) get_config('local_dttutorgemini', 'secret');
    }

    /**
     * Not used.
     *
     * @return string
     */
    public function get_site_id(): string {
        return '';
    }

    /**
     * Not used.
     *
     * @return array
     */
    public function get_rate_limit_headers(): array {
        return [];
    }
}
