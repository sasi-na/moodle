<?php

class block_examdashboard extends block_base {

    public function init() {
        $this->title = 'Exam Dashboard';
    }

    public function get_content() {
        global $DB, $USER, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        // 🔹 Check if student exists in your table
        $records = $DB->get_records('anveshana_exam_question_marks', ['email' => $USER->email]);

        if (empty($records)) {
            $this->content = new stdClass();
            $this->content->text = ''; // hide block
            return $this->content;
        }

        // 🔹 Get unique exams
        $exams = [];
        foreach ($records as $r) {
            $exams[$r->exam_code] = $r->exam_name;
        }

        $data = [
            'exams' => []
        ];

        foreach ($exams as $code => $name) {
            $data['exams'][] = [
                'exam_name' => $name,
                'exam_code' => $code
            ];
        }

        $this->page->requires->js_call_amd('block_examdashboard/modal', 'init');

        $this->content = new stdClass();
        $this->content->text = $OUTPUT->render_from_template(
            'block_examdashboard/examdashboard',
            $data
        );

        return $this->content;
    }
}
