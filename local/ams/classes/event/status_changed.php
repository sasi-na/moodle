<?php
namespace local_ams\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event triggered when a student's account status (active/suspended) is changed
 * via the AMS web service. The `other` payload carries the new status.
 *
 * @package    local_ams
 */
class status_changed extends \core\event\base {

    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'user';
    }

    public static function get_name() {
        return get_string('event_status_changed', 'local_ams');
    }

    public function get_description() {
        $status = $this->other['status'] ?? '';
        return "The AMS web service (user '{$this->userid}') set the status of student account id '{$this->objectid}' to '{$status}'.";
    }

    public function get_url() {
        return new \moodle_url('/user/profile.php', ['id' => $this->objectid]);
    }

    public static function get_objectid_mapping() {
        return ['db' => 'user', 'restore' => 'user'];
    }
}
