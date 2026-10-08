<?php
namespace quizaccess_proctor;

defined('MOODLE_INTERNAL') || die();

/**
 * Evidence storage for proctoring snapshots + events.
 *
 * Snapshots are stored via the File API in the quiz module context (filearea
 * "snapshot", itemid = snapshot row id). In production this is the place to
 * swap in direct-to-object-storage (presigned URLs) per the scaling plan; the
 * read/write surface here is deliberately small to make that swap localised.
 *
 * @package   quizaccess_proctor
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class storage {

    /** Max accepted decoded image size (bytes). */
    const MAX_BYTES = 524288; // 512 KB.

    /**
     * Decode and store a snapshot. Returns the new snapshot id, or 0 on reject.
     *
     * @param \context_module $context
     * @param int $quizid
     * @param int $attemptid
     * @param int $userid
     * @param string $type identity|periodic
     * @param string $dataurl A data: URL (image/jpeg or image/png).
     * @param int $capturedat
     * @return int
     */
    public static function store_snapshot(\context_module $context, int $quizid, int $attemptid,
            int $userid, string $type, string $dataurl, int $capturedat): int {
        global $DB;

        $binary = self::decode_dataurl($dataurl);
        if ($binary === null || strlen($binary) > self::MAX_BYTES) {
            return 0;
        }

        $type = ($type === 'identity') ? 'identity' : 'periodic';
        $filename = $type . '-' . $attemptid . '-' . $capturedat . '.jpg';

        $id = $DB->insert_record('quizaccess_proctor_snapshot', (object) [
            'quizid'      => $quizid,
            'attemptid'   => $attemptid,
            'userid'      => $userid,
            'type'        => $type,
            'filename'    => $filename,
            'capturedat'  => $capturedat,
            'timecreated' => time(),
        ]);

        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'quizaccess_proctor',
            'filearea'  => 'snapshot',
            'itemid'    => $id,
            'filepath'  => '/',
            'filename'  => $filename,
        ], $binary);

        return $id;
    }

    /**
     * How many periodic snapshots already exist for an attempt.
     *
     * @param int $attemptid
     * @return int
     */
    public static function count_periodic(int $attemptid): int {
        global $DB;
        return $DB->count_records('quizaccess_proctor_snapshot',
            ['attemptid' => $attemptid, 'type' => 'periodic']);
    }

    /**
     * Log a proctoring event.
     *
     * @param int $quizid
     * @param int $attemptid
     * @param int $userid
     * @param string $eventtype
     * @param int $occurredat
     * @param string|null $detail
     */
    public static function log_event(int $quizid, int $attemptid, int $userid,
            string $eventtype, int $occurredat, ?string $detail = null, int $severity = 1): void {
        global $DB;
        $DB->insert_record('quizaccess_proctor_event', (object) [
            'quizid'      => $quizid,
            'attemptid'   => $attemptid,
            'userid'      => $userid,
            'eventtype'   => $eventtype,
            'severity'    => max(0, min(3, $severity)),
            'occurredat'  => $occurredat,
            'detail'      => $detail,
            'timecreated' => time(),
        ]);
    }

    /**
     * Snapshots for an attempt (oldest first).
     *
     * @param int $attemptid
     * @return array
     */
    public static function get_snapshots(int $attemptid): array {
        global $DB;
        return $DB->get_records('quizaccess_proctor_snapshot', ['attemptid' => $attemptid], 'capturedat ASC');
    }

    /**
     * Events for an attempt (oldest first).
     *
     * @param int $attemptid
     * @return array
     */
    public static function get_events(int $attemptid): array {
        global $DB;
        return $DB->get_records('quizaccess_proctor_event', ['attemptid' => $attemptid], 'occurredat ASC');
    }

    /**
     * Decode a data: URL to raw bytes, or null if not a supported image.
     *
     * @param string $dataurl
     * @return string|null
     */
    protected static function decode_dataurl(string $dataurl): ?string {
        if (!preg_match('#^data:image/(jpeg|png);base64,#', $dataurl, $m)) {
            return null;
        }
        $b64 = substr($dataurl, strpos($dataurl, ',') + 1);
        $binary = base64_decode($b64, true);
        return $binary === false ? null : $binary;
    }
}
