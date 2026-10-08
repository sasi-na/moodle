<?php
namespace local_ams\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event triggered when an existing student account is updated via the AMS web service.
 *
 * The `other` payload carries: action, matchedby (how the account was matched),
 * source (which WS function), and fields (list of changed fields, when known).
 *
 * @package    local_ams
 */
class student_updated extends \core\event\base {

    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'user';
    }

    public static function get_name() {
        return get_string('event_student_updated', 'local_ams');
    }

    public function get_description() {
        $matchedby = $this->other['matchedby'] ?? '';
        $via = $matchedby ? " (matched by {$matchedby})" : '';
        return "The AMS web service (user '{$this->userid}') updated the student account with id '{$this->objectid}'{$via}.";
    }

    public function get_url() {
        return new \moodle_url('/user/profile.php', ['id' => $this->objectid]);
    }

    public static function get_objectid_mapping() {
        return ['db' => 'user', 'restore' => 'user'];
    }
}
