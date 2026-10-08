<?php
namespace local_exams\runner;

defined('MOODLE_INTERNAL') || die();

/**
 * HMAC-signed exam token.
 *
 * Binds the runner session to {attemptid, userid, quizid, server start time,
 * time limit}. The server clock is authoritative — the client cannot extend its
 * own time or move the session to another attempt/user by tampering.
 *
 * @package   local_exams
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class token {

    /** Get (creating once) the signing secret. */
    protected static function secret(): string {
        $secret = get_config('local_exams', 'tokensecret');
        if (!$secret) {
            $secret = bin2hex(random_bytes(32));
            set_config('tokensecret', $secret, 'local_exams');
        }
        return $secret;
    }

    protected static function b64(string $raw): string {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    protected static function unb64(string $enc): string {
        return base64_decode(strtr($enc, '-_', '+/'));
    }

    /**
     * Sign a payload.
     *
     * @param array $payload
     * @return string
     */
    public static function sign(array $payload): string {
        $body = self::b64(json_encode($payload));
        $sig = self::b64(hash_hmac('sha256', $body, self::secret(), true));
        return $body . '.' . $sig;
    }

    /**
     * Verify a token and return its payload, or null if invalid/expired.
     *
     * @param string $token
     * @return array|null
     */
    public static function verify(string $token): ?array {
        if (substr_count($token, '.') !== 1) {
            return null;
        }
        [$body, $sig] = explode('.', $token, 2);
        $expected = self::b64(hash_hmac('sha256', $body, self::secret(), true));
        if (!hash_equals($expected, $sig)) {
            return null;
        }
        $payload = json_decode(self::unb64($body), true);
        if (!is_array($payload)) {
            return null;
        }
        if (!empty($payload['exp']) && time() > (int) $payload['exp']) {
            return null;
        }
        return $payload;
    }
}
