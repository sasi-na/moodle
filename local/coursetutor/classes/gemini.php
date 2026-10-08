<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_coursetutor;

/**
 * Minimal Google Gemini client.
 *
 * @package   local_coursetutor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gemini {

    /**
     * Key: own setting, else the key used for GeniAI.
     *
     * @return string
     */
    public static function apikey(): string {
        foreach ([['local_coursetutor', 'apikey'], ['geniaicontroller_gemini', 'apikey'], ['local_dttutorgemini', 'apikey']] as [$c, $n]) {
            $k = trim((string) get_config($c, $n));
            if (strlen($k) > 10) {
                return $k;
            }
        }
        return '';
    }

    /**
     * Whether a key exists.
     *
     * @return bool
     */
    public static function is_configured(): bool {
        return self::apikey() !== '';
    }

    /**
     * Generate an answer.
     *
     * @param string $system System instruction.
     * @param array $turns [['role' => 'user'|'assistant', 'content' => string], ...]
     * @param bool $json Ask Gemini to answer with JSON only.
     * @param int $maxtokens Maximum answer length.
     * @return string
     * @throws \moodle_exception
     */
    public static function generate(string $system, array $turns, bool $json = false, int $maxtokens = 4096): string {
        $contents = [];
        foreach ($turns as $t) {
            $text = trim((string) ($t['content'] ?? ''));
            if ($text === '') {
                continue;
            }
            $role = ($t['role'] ?? 'user') === 'assistant' ? 'model' : 'user';
            $last = count($contents) - 1;
            if ($last >= 0 && $contents[$last]['role'] === $role) {
                $contents[$last]['parts'][0]['text'] .= "\n\n" . $text;
            } else {
                $contents[] = ['role' => $role, 'parts' => [['text' => $text]]];
            }
        }
        if (!$contents || $contents[0]['role'] !== 'user') {
            array_unshift($contents, ['role' => 'user', 'parts' => [['text' => 'Hello']]]);
        }
        $post = [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => $contents,
            'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => $maxtokens],
        ];
        if ($json) {
            $post['generationConfig']['responseMimeType'] = 'application/json';
        }
        $key = self::apikey();
        $model = trim((string) get_config('local_coursetutor', 'model')) ?: 'gemini-3.5-flash-lite';
        $data = null;
        $code = 0;
        foreach (array_values(array_unique([$model, 'gemini-3.5-flash-lite'])) as $try) {
            for ($i = 0; $i < 2; $i++) {
                $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($try) . ':generateContent');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 170,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $key],
                    CURLOPT_POSTFIELDS => json_encode($post, JSON_UNESCAPED_UNICODE),
                ]);
                $raw = curl_exec($ch);
                $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                $data = json_decode((string) $raw, true);
                if (!in_array($code, [429, 500, 503], true)) {
                    break 2;
                }
                sleep(2);
            }
        }
        $text = '';
        foreach (($data['candidates'][0]['content']['parts'] ?? []) as $part) {
            if (isset($part['text']) && empty($part['thought'])) {
                $text .= $part['text'];
            }
        }
        if ($code !== 200 || trim($text) === '') {
            $msg = $data['error']['message'] ?? ($code === 429 ? 'The AI service is busy or the daily free limit is reached.' : 'No answer.');
            throw new \moodle_exception('aierror', 'local_coursetutor', '', $msg);
        }
        return trim($text);
    }
}
