<?php
namespace quiz_proctorreview\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * The proctor review report stores no data of its own — it only displays
 * evidence owned by quizaccess_proctor.
 *
 * @package   quiz_proctorreview
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\null_provider {

    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
