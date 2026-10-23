<?php
class block_endsemesterexam extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_endsemesterexam');
    }

    public function applicable_formats() {
        return ['my' => true]; // Dashboard only (optional)
    }

    public function get_content() {
        global $USER, $PAGE;

        if ($this->content !== null) {
            return $this->content;
        }

        $PAGE->requires->css('/blocks/endsemesterexam/style.css');

        $this->content = new stdClass();
        $this->content->text = '';
        
        // ✅ ALWAYS show for site admins
        if (is_siteadmin($USER)) {
            return $this->render_exam_button();
        }

        // ✅ Allowed course categories
        $allowedcategories = [2, 17];

        if (!$this->user_in_allowed_category($USER->id, $allowedcategories)) {
            return $this->content; // Hidden for others
        }

        return $this->render_exam_button();
    }

    private function render_exam_button() {
        $url = new moodle_url('/blocks/endsemesterexam/redirect.php');

        $this->content->text = '
            <div>
                <a href="' . $url . '" class="btn btn-endexam">
                    ' . get_string('endsemesterexam', 'block_endsemesterexam') . '
                </a>
            </div>
        ';

        return $this->content;
    }

    private function user_in_allowed_category($userid, $allowedcategories) {
        global $DB;

        if (empty($allowedcategories)) {
            return false;
        }

        $conditions = [];
        $params = [];

        foreach ($allowedcategories as $catid) {
            $conditions[] = "cc.path LIKE ?";
            $params[] = '%/' . $catid . '/%';
        }

        $sql = "
            SELECT 1
            FROM {user_enrolments} ue
            JOIN {enrol} e ON e.id = ue.enrolid
            JOIN {course} c ON c.id = e.courseid
            JOIN {course_categories} cc ON cc.id = c.category
            WHERE ue.userid = ?
            AND (" . implode(' OR ', $conditions) . ")
        ";

        array_unshift($params, $userid);

        return $DB->record_exists_sql($sql, $params);
    }

}
