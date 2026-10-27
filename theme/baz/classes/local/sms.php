<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace theme_baz\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Pluggable SMS sender for the mobile-number OTP. Choose the provider and keys in
 * Site administration > Appearance > BAZ > Top Bar > SMS. With "None" nothing is sent and the
 * student is told SMS is not available yet (the code is never shown on screen).
 *
 * @package   theme_baz
 */
class sms {

    /**
     * @param string $number Digits with optional leading +
     * @param string $text
     * @return bool
     */
    /** @var string|null Code chosen by the provider itself (Twilio trial template), to be used as the OTP. */
    public static $sent_code = null;

    public static function send(string $number, string $text): bool {
        self::$sent_code = null;
        $c = get_config('theme_baz');
        $provider = $c->sms_provider ?? 'none';
        if ($provider === 'none' || $provider === '') {
            debugging('theme_baz SMS provider not set; OTP SMS to ' . substr($number, 0, 3) . '*** not sent.', DEBUG_DEVELOPER);
            return false;
        }
        $key = (string) ($c->sms_key ?? '');
        $sender = (string) ($c->sms_sender ?? '');
        $secret = (string) ($c->sms_secret ?? '');
        self::log('provider=' . $provider . ' keylen=' . strlen($key) . ' senderlen=' . strlen($sender) . ' secretlen=' . strlen($secret) . ' to=' . substr($number, 0, 3) . '***' . substr(preg_replace('/\D/', '', $number), -4));
        $plain = ltrim($number, '+');
        $otp = preg_match('/\b\d{6}\b/', $text, $mm) ? $mm[0] : '';
        try {
            switch ($provider) {
                case 'fast2sms':
                    $num = substr(preg_replace('/\D/', '', $plain), -10);
                    // 1) Fast2SMS OTP route (needs website verification on the Fast2SMS account).
                    $r = self::http('https://www.fast2sms.com/dev/bulkV2', ['route' => 'otp',
                        'variables_values' => $otp, 'numbers' => $num, 'flash' => 0], ['authorization: ' . $key]);
                    if ($r['code'] >= 200 && $r['code'] < 300 && preg_match('/"return"\s*:\s*true/i', $r['body'])) {
                        return true;
                    }
                    // 2) Fallback: Quick SMS route (works without website verification, costs more per SMS).
                    $r = self::http('https://www.fast2sms.com/dev/bulkV2', ['route' => 'q', 'message' => $text,
                        'numbers' => $num, 'flash' => 0], ['authorization: ' . $key]);
                    return $r['code'] >= 200 && $r['code'] < 300 && (bool) preg_match('/"return"\s*:\s*true/i', $r['body']);
                case 'textbee':
                    // Free: sends from your own Android phone via textbee.dev. Key = API key, Sender = optional Device ID.
                    $to = self::e164($number);
                    $url = trim($sender) !== ''
                        ? 'https://api.textbee.dev/api/v1/gateway/devices/' . rawurlencode(trim($sender)) . '/send-sms'
                        : 'https://api.textbee.dev/api/v1/gateway/send-sms';
                    $r = self::http($url, json_encode(['recipients' => [$to], 'message' => $text]),
                        ['x-api-key: ' . $key, 'Content-Type: application/json'], true);
                    return $r['code'] >= 200 && $r['code'] < 300;
                case 'msg91':
                    $r = self::http('https://control.msg91.com/api/v5/flow/', json_encode(['template_id' => $sender,
                        'short_url' => '0', 'recipients' => [['mobiles' => $plain, 'otp' => $otp]]]),
                        ['authkey: ' . $key, 'content-type: application/json'], true);
                    return $r['code'] >= 200 && $r['code'] < 300;
                case 'twilio':
                    $r = self::http('https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($key) . '/Messages.json',
                        ['To' => self::e164($number), 'From' => $sender, 'Body' => $text], [], false, $key . ':' . $secret);
                    if ($r['code'] === 400 && strpos($r['body'], '572006') !== false) {
                        // Twilio trial accounts only accept a predefined template NAME as the Body (e.g. sms_2fa).
                        // Twilio fills in its own code, so read it back from the response and use it as our OTP.
                        $r = self::http('https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($key) . '/Messages.json',
                            ['To' => self::e164($number), 'From' => $sender, 'Body' => 'sms_2fa'], [], false, $key . ':' . $secret);
                        $ok = $r['code'] >= 200 && $r['code'] < 300;
                        $j = json_decode($r['body'], true);
                        if ($ok && is_array($j) && preg_match('/\b\d{4,8}\b/', (string) ($j['body'] ?? ''), $m)) {
                            self::$sent_code = $m[0];
                            self::log('twilio trial template used; code read back from response');
                            return true;
                        }
                        self::log('twilio trial template sent=' . ($ok ? 'yes' : 'no') . ' but no code in response body');
                        return false;
                    }
                    return $r['code'] >= 200 && $r['code'] < 300;
            }
        } catch (\Throwable $e) {
            debugging('theme_baz SMS error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        return false;
    }

    /**
     * @return array ['code' => int, 'body' => string]
     */
    protected static function http(string $url, $data, array $headers, bool $json = false, string $userpwd = ''): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json ? $data : http_build_query($data, '', '&'), CURLOPT_HTTPHEADER => $headers]);
        if ($userpwd !== '') {
            curl_setopt($ch, CURLOPT_USERPWD, $userpwd);
        }
        $body = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        self::log('http=' . $code . ' curlerr=' . curl_error($ch) . ' body=' . substr($body, 0, 400));
        curl_close($ch);
        return ['code' => $code, 'body' => $body];
    }

    /**
     * Phone in international form: a bare 10-digit number is treated as Indian (+91).
     *
     * @param string $number
     * @return string
     */
    public static function e164(string $number): string {
        $d = preg_replace('/\D/', '', $number);
        if (strpos(trim($number), '+') !== 0) {
            if (strlen($d) === 10) {
                $d = '91' . $d;
            } else if (strlen($d) === 11 && $d[0] === '0') {
                $d = '91' . substr($d, 1);
            }
        }
        return '+' . $d;
    }

    /**
     * Twilio Verify mode: provider Twilio and the Sender field holds a Verify Service SID (starts with VA).
     * Twilio then creates, sends (using its own India-approved template) and checks the code itself.
     */
    public static function verify_mode(): bool {
        $c = get_config('theme_baz');
        return ($c->sms_provider ?? '') === 'twilio' && preg_match('/^VA[0-9a-f]{32}$/i', trim((string) ($c->sms_sender ?? ''))) === 1;
    }

    /**
     * @param string $number
     * @return bool
     */
    public static function verify_start(string $number): bool {
        $c = get_config('theme_baz');
        $r = self::http('https://verify.twilio.com/v2/Services/' . rawurlencode(trim($c->sms_sender)) . '/Verifications',
            ['To' => self::e164($number), 'Channel' => 'sms'], [], false, trim((string) $c->sms_key) . ':' . trim((string) $c->sms_secret));
        return $r['code'] >= 200 && $r['code'] < 300;
    }

    /**
     * @param string $number
     * @param string $code
     * @return bool
     */
    public static function verify_check(string $number, string $code): bool {
        $c = get_config('theme_baz');
        $r = self::http('https://verify.twilio.com/v2/Services/' . rawurlencode(trim($c->sms_sender)) . '/VerificationCheck',
            ['To' => self::e164($number), 'Code' => $code], [], false, trim((string) $c->sms_key) . ':' . trim((string) $c->sms_secret));
        return $r['code'] >= 200 && $r['code'] < 300 && (bool) preg_match('/"status"\s*:\s*"approved"/i', $r['body']);
    }

    /**
     * Private diagnostics (no keys, no codes) in moodledata/theme_baz_sms.log.
     */
    protected static function log(string $m): void {
        global $CFG;
        @file_put_contents($CFG->dataroot . '/theme_baz_sms.log', date('c') . ' ' . $m . "\n", FILE_APPEND);
    }
}
