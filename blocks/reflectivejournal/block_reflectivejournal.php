<?php
defined('MOODLE_INTERNAL') || die();

class block_reflectivejournal extends block_base {

    public function init(): void {
        $this->title = get_string('pluginname', 'block_reflectivejournal');
    }

    /**
     * Only available inside courses. Teachers/managers add it manually
     * (turn editing on > "Add a block") to the courses that need it.
     */
    public function applicable_formats(): array {
        return ['course-view' => true];
    }

    public function instance_allow_multiple(): bool {
        return false;
    }

    public function has_config(): bool {
        return false;
    }

    public function get_content(): stdClass {
        global $PAGE, $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        $course = $this->page->course;

        // Must be inside a real course.
        if (empty($course) || $course->id == SITEID) {
            return $this->content;
        }

        $coursecontext = context_course::instance($course->id);

        // Show to any actively enrolled user, regardless of role.
        if (!is_enrolled($coursecontext, $USER->id, '', true)) {
            return $this->content;
        }

        $PAGE->requires->css('/blocks/reflectivejournal/style.css');
        $PAGE->requires->js_call_amd('block_reflectivejournal/button', 'init');

        $this->content->text = html_writer::div(
            html_writer::tag('button',
                get_string('openjournal', 'block_reflectivejournal'),
                ['type' => 'button', 'class' => 'btn reflectivejournal-btn']
            ),
            'block_reflectivejournal-wrapper'
        );

        return $this->content;
    }
}
