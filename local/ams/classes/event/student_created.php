<?php
namespace local_ams\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event triggered when a student account is created via the AMS web service.
 *
 * @package    local_ams
 */
class student_created extends \core\event\base {

    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'user';
    }

    public static function get_name() {
        return get_string('event_student_created', 'local_ams');
    }

    public function get_description() {
        return "The AMS web service (user '{$this->userid}') created the student account with id '{$this->objectid}'.";
    }

    public function get_url() {
        return new \moodle_url('/user/profile.php', ['id' => $this->objectid]);
    }

    public static function get_objectid_mapping() {
        return ['db' => 'user', 'restore' => 'user'];
    }
}
