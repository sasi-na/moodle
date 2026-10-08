<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_helpdesk\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use moodleform;

/**
 * Raise a support ticket.
 *
 * @package    local_helpdesk
 * @copyright  2026 onwards
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class raise_form extends moodleform {

    /** @var int Description limit, matched by a counter in the template. */
    public const MAX_DESCRIPTION = 2000;

    /**
     * Form fields. Identity is taken from the session, never asked for.
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('select', 'category', get_string('field_category', 'local_helpdesk'),
            self::categories());
        $mform->addRule('category', null, 'required', null, 'client');

        $mform->addElement('text', 'subject', get_string('field_subject', 'local_helpdesk'),
            ['maxlength' => 255, 'size' => 60]);
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', null, 'required', null, 'client');

        // Optional, and only shown when there is something to choose. Naming the
        // course is what lets the Program Manager forward to the TA or faculty
        // member attached to it rather than guessing from a flat list.
        $courses = self::student_courses();
        if (count($courses) > 1) {
            $mform->addElement('select', 'courseid',
                get_string('field_course', 'local_helpdesk'), $courses);
            $mform->setType('courseid', PARAM_INT);
            $mform->addHelpButton('courseid', 'field_course', 'local_helpdesk');
        }

        $mform->addElement('textarea', 'description', get_string('field_description', 'local_helpdesk'),
            ['rows' => 8, 'cols' => 60, 'maxlength' => self::MAX_DESCRIPTION]);
        $mform->setType('description', PARAM_TEXT);
        $mform->addRule('description', null, 'required', null, 'client');

        $mform->addElement('filemanager', 'attachments',
            get_string('field_attachments', 'local_helpdesk'), null, self::attachment_options());

        $this->add_action_buttons(true, get_string('submitticket', 'local_helpdesk'));
    }

    /**
     * Server-side checks the client cannot be trusted to have done.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if (trim($data['subject'] ?? '') === '') {
            $errors['subject'] = get_string('err_required', 'local_helpdesk');
        }
        $description = trim($data['description'] ?? '');
        if ($description === '') {
            $errors['description'] = get_string('err_required', 'local_helpdesk');
        } else if (\core_text::strlen($description) > self::MAX_DESCRIPTION) {
            $errors['description'] = get_string('err_toolong', 'local_helpdesk', self::MAX_DESCRIPTION);
        }
        if (!array_key_exists($data['category'] ?? '', self::categories())) {
            $errors['category'] = get_string('err_required', 'local_helpdesk');
        }

        return $errors;
    }

    /**
     * Categories a student can choose.
     *
     * @return array
     */
    public static function categories(): array {
        return [
            'academic'       => get_string('cat_academic', 'local_helpdesk'),
            'technical'      => get_string('cat_technical', 'local_helpdesk'),
            'administrative' => get_string('cat_administrative', 'local_helpdesk'),
            'other'          => get_string('cat_other', 'local_helpdesk'),
        ];
    }

    /**
     * Attachment limits.
     *
     * @return array
     */
    /**
     * Courses the current user is enrolled in, for the optional course selector.
     *
     * @return array Course id to short display name, with a "not course specific" first entry.
     */
    public static function student_courses(): array {
        global $USER;

        $out = [0 => get_string('field_course_none', 'local_helpdesk')];
        foreach (enrol_get_users_courses($USER->id, true, 'id, shortname, fullname') as $course) {
            $out[(int) $course->id] = format_string($course->fullname);
        }
        return $out;
    }

    public static function attachment_options(): array {
        return [
            'subdirs'        => 0,
            'maxfiles'       => 3,
            'maxbytes'       => 10 * 1024 * 1024,
            'accepted_types' => ['.png', '.jpg', '.jpeg', '.pdf'],
        ];
    }
}
