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
 * One-time-code (OTP) change of a person's own email address or mobile number.
 *
 * The state lives in a user preference (no database tables). A code is valid for 5 minutes; asking
 * for it again inside those 5 minutes sends the SAME code, only after they pass is a new one made.
 *
 * @package   theme_baz
 */
class contact {

    /** @var int Seconds an OTP stays valid (and stays the same when re-sent). */
    const TTL = 300;
    /** @var int Wrong tries before the field is locked. */
    const MAXTRIES = 5;
    /** @var int Lock time after too many wrong tries. */
    const LOCKTIME = 900;
    /** @var int Minimum seconds between two sends (anti-spam). */
    const COOLDOWN = 20;

    /** @var string[] field => user table column. */
    const FIELDS = ['email' => 'email', 'phone' => 'phone2'];

    /**
     * Allowed number of successful changes per person PER MONTH for each of email and mobile (0 = unlimited).
     *
     * @return int
     */
    public static function max_changes(): int {
        $v = get_config('theme_baz', 'ct_max_changes');
        return ($v === false || $v === null || $v === '') ? 5 : max(0, (int) $v);
    }

    /**
     * @param string $field
     * @return int How many changes this person has already made.
     */
    public static function changes_used(string $field): int {
        // Stored as "YYYYMM:count"; a new calendar month starts again from 0.
        $raw = (string) get_user_preferences('theme_baz_chg_' . $field, '');
        $parts = explode(':', $raw);
        return (count($parts) === 2 && $parts[0] === date('Ym')) ? (int) $parts[1] : 0;
    }

    /**
     * @param string $field
     * @return int|null Changes still allowed this month, or null when there is no limit.
     */
    public static function changes_left(string $field): ?int {
        $max = self::max_changes();
        return $max > 0 ? max(0, $max - self::changes_used($field)) : null;
    }

    /**
     * @param string $field
     * @throws \moodle_exception When the person has used up all allowed changes.
     */
    protected static function check_limit(string $field): void {
        $max = self::max_changes();
        if ($max > 0 && self::changes_used($field) >= $max) {
            throw new \moodle_exception('ct_limit', 'theme_baz', '', $max);
        }
    }

    /**
     * @param string $field
     * @return stdClass|null
     */
    protected static function load(string $field): ?\stdClass {
        $raw = get_user_preferences('theme_baz_otp_' . $field, '');
        $s = $raw !== '' ? json_decode($raw) : null;
        return is_object($s) ? $s : null;
    }

    /**
     * @param string $field
     * @param \stdClass|null $s
     */
    protected static function save(string $field, ?\stdClass $s): void {
        if ($s === null) {
            unset_user_preference('theme_baz_otp_' . $field);
        } else {
            set_user_preference('theme_baz_otp_' . $field, json_encode($s));
        }
    }

    /**
     * Check and clean a new value.
     *
     * @param string $field
     * @param string $value
     * @return string The cleaned value
     * @throws \moodle_exception With a readable message
     */
    public static function clean(string $field, string $value): string {
        global $DB, $USER, $CFG;
        $value = trim($value);
        if ($field === 'email') {
            $value = \core_text::strtolower($value);
            if (!validate_email($value)) {
                throw new \moodle_exception('ct_bademail', 'theme_baz');
            }
            if ($value === \core_text::strtolower($USER->email)) {
                throw new \moodle_exception('ct_same', 'theme_baz');
            }
            if (empty($CFG->allowaccountssameemail)) {
                $other = $DB->record_exists_select('user', 'LOWER(email) = :e AND id <> :id AND deleted = 0',
                    ['e' => $value, 'id' => $USER->id]);
                if ($other) {
                    throw new \moodle_exception('ct_taken', 'theme_baz');
                }
            }
            return $value;
        }
        $digits = preg_replace('/[^0-9+]/', '', $value);
        if (!preg_match('/^\+?[0-9]{8,15}$/', $digits)) {
            throw new \moodle_exception('ct_badphone', 'theme_baz');
        }
        if ($digits === preg_replace('/[^0-9+]/', '', (string) $USER->phone2)) {
            throw new \moodle_exception('ct_same', 'theme_baz');
        }
        return $digits;
    }

    /**
     * Send (or re-send) the code for a new value.
     *
     * @param string $field email|phone
     * @param string $value
     * @return array ['expires' => seconds left, 'resent' => bool, 'channel' => string, 'wait' => int]
     */
    public static function send(string $field, string $value): array {
        global $USER;
        self::check_limit($field);
        $value = self::clean($field, $value);
        $now = time();
        $s = self::load($field);
        if ($s && !empty($s->lock) && $s->lock > $now) {
            throw new \moodle_exception('ct_locked', 'theme_baz', '', ceil(($s->lock - $now) / 60));
        }
        $resent = false;
        if ($s && $s->value === $value && $s->exp > $now) {
            // Same number/address and still inside the 5 minutes: the SAME code goes out again.
            if (!empty($s->sent) && $now - $s->sent < self::COOLDOWN) {
                throw new \moodle_exception('ct_wait', 'theme_baz', '', self::COOLDOWN - ($now - $s->sent));
            }
            $code = \core\encryption::decrypt($s->code);
            $resent = true;
        } else {
            $code = (string) random_int(100000, 999999);
            $s = (object) ['value' => $value, 'code' => \core\encryption::encrypt($code), 'exp' => $now + self::TTL,
                'tries' => 0, 'lock' => 0, 'sent' => 0];
        }
        $sent = false;
        $demo = null;
        if ($field === 'email') {
            $sent = self::send_email($value, $code);
            $channel = 'email';
        } else {
            if (sms::verify_mode()) {
                // Twilio Verify makes and checks the code itself.
                $s->code = \core\encryption::encrypt('VERIFY');
                $sent = sms::verify_start($value);
            } else {
                $sent = sms::send($value, get_string('ct_smstext', 'theme_baz', ['code' => $code, 'min' => 5]));
                if ($sent && sms::$sent_code) {
                    $s->code = \core\encryption::encrypt(sms::$sent_code);
                }
            }
            if (!$sent && get_config('theme_baz', 'sms_demo')) {
                // DEMO MODE (admin switch, off by default): no SMS could be sent, so show the code on screen.
                $s->code = \core\encryption::encrypt($code);
                $sent = true;
                $demo = $code;
            }
            $channel = 'sms';
        }
        if (!$sent) {
            throw new \moodle_exception($field === 'email' ? 'ct_mailfail' : 'ct_smsfail', 'theme_baz');
        }
        $s->sent = $now;
        self::save($field, $s);
        $res = ['expires' => max(1, $s->exp - $now), 'resent' => $resent, 'channel' => $channel, 'wait' => self::COOLDOWN];
        if ($demo !== null) {
            $res['demo'] = $demo;
        }
        return $res;
    }

    /**
     * @param string $to
     * @param string $code
     * @return bool
     */
    protected static function send_email(string $to, string $code): bool {
        global $USER, $SITE;
        $touser = clone $USER;
        $touser->email = $to;
        $touser->mailformat = 1;
        $a = ['code' => $code, 'min' => 5, 'site' => format_string($SITE->fullname), 'name' => fullname($USER)];
        $subject = get_string('ct_mailsubject', 'theme_baz', $a);
        $text = get_string('ct_mailtext', 'theme_baz', $a);
        $html = nl2br(s($text));
        // The site-wide "no email ever" safety switch (config.php) stays on for everything else;
        // only this one-time code is allowed through, for this single call.
        global $CFG;
        $was = $CFG->noemailever ?? null;
        $CFG->noemailever = false;
        try {
            $ok = (bool) email_to_user($touser, \core_user::get_noreply_user(), $subject, $text, $html);
        } finally {
            if ($was === null) {
                unset($CFG->noemailever);
            } else {
                $CFG->noemailever = $was;
            }
        }
        return $ok;
    }

    /**
     * Check the code; on success change the address / number.
     *
     * @param string $field
     * @param string $value
     * @param string $code
     */
    public static function verify(string $field, string $value, string $code): void {
        global $USER;
        self::check_limit($field);
        $value = self::clean($field, $value);
        $now = time();
        $s = self::load($field);
        if (!$s || $s->value !== $value) {
            throw new \moodle_exception('ct_nocode', 'theme_baz');
        }
        if (!empty($s->lock) && $s->lock > $now) {
            throw new \moodle_exception('ct_locked', 'theme_baz', '', ceil(($s->lock - $now) / 60));
        }
        if ($s->exp <= $now) {
            throw new \moodle_exception('ct_expired', 'theme_baz');
        }
        $code = preg_replace('/\D/', '', $code);
        $stored = \core\encryption::decrypt($s->code);
        $okcode = $stored === 'VERIFY' ? ($field === 'phone' && sms::verify_check($value, $code)) : hash_equals($stored, $code);
        if (!$okcode) {
            $s->tries++;
            if ($s->tries >= self::MAXTRIES) {
                $s->lock = $now + self::LOCKTIME;
                $s->tries = 0;
                self::save($field, $s);
                throw new \moodle_exception('ct_locked', 'theme_baz', '', ceil(self::LOCKTIME / 60));
            }
            self::save($field, $s);
            throw new \moodle_exception('ct_wrong', 'theme_baz', '', self::MAXTRIES - $s->tries);
        }
        require_once(__DIR__ . '/../../../../user/lib.php');
        $upd = (object) ['id' => $USER->id, self::FIELDS[$field] => $value];
        user_update_user($upd, false, true);
        $USER->{self::FIELDS[$field]} = $value;
        set_user_preference('theme_baz_chg_' . $field, date('Ym') . ':' . (self::changes_used($field) + 1));
        self::save($field, null);
    }
}
