<?php

require_once($CFG->libdir . '/formslib.php');

class filter_form extends \moodleform {

    public function definition() {

        global $DB;
        $page = $this->_customdata['page'];

        $mform = $this->_form;
        // Initial field
        $mform->addElement('hidden', 'page', 'yes');
        $mform->setType('page', PARAM_INT);
        $mform->setDefault('page', $page);
        // SQL query to retrieve sections
        $categories = $DB->get_records('course_categories', ['visible' => 1]);

        $courselist = ['' => 'Select Course'];
        $catlist = ['' => 'Select Category'];
        foreach ($categories as $cat) {

            $catlist[$cat->id] = $cat->name;
        }
//        $mform->addElement('text', 'email', get_string('selectemail', 'local_systemreports'));
        $displaylist = core_course_category::make_categories_list();
//       $displaylist[0] = 'Select Category';
        $displaylist = [0 => 'Select Category'] + $displaylist;

//        $mform->addElement('autocomplete', 'category', get_string('coursecategory'), $displaylist);
        $mform->addElement('select', 'categoryid', get_string('selectcategory', 'local_systemreports'), $displaylist);
        $mform->setType('categoryid', PARAM_INT);

        $mform->addElement('select', 'courseid', get_string('selectcourse', 'local_systemreports'), $courselist);
        $mform->setType('courseid', PARAM_INT);

        $buttonarray = array();
        $buttonarray[] = $mform->createElement('submit', 'submitbutton', get_string('getdata', 'local_systemreports'));
        $mform->addGroup($buttonarray, 'buttonar', '', ' ', false);
    }
}

class course_filter_form extends \moodleform {

    public function definition() {

        global $DB;
        $page = $this->_customdata['page'];

        $mform = $this->_form;
        // Initial field
        $mform->addElement('hidden', 'page', 'yes');
        $mform->setType('page', PARAM_INT);
        $mform->setDefault('page', $page);
        // SQL query to retrieve sections
        $courses = $DB->get_records('course', ['visible' => 1]);

        $courselist = ['' => 'Select Course'];
        $catlist = ['' => 'Select Category'];
//        foreach ($courses as $course) {
//
//            $courselist[$course->id] = $course->fullname .' ('.$course->shortname.')';
//        }
//        $mform->addElement('text', 'email', get_string('selectemail', 'local_systemreports'));
//        $mform->addElement('select', 'categoryid', get_string('selectcategory', 'local_systemreports'), $catlist);
//        $mform->setType('categoryid', PARAM_INT);
//

        $displaylist = core_course_category::make_categories_list('moodle/course:create');

//       $displaylist[0] = 'Select Category';
        $displaylist = [0 => 'Select Category'] + $displaylist;
//       echo '<pre>'; print_r($displaylist); die;
//        $mform->addElement('autocomplete', 'category', get_string('coursecategory'), $displaylist);
        $mform->addElement('select', 'categoryid', get_string('selectcategory', 'local_systemreports'), $displaylist);
        $mform->setType('categoryid', PARAM_INT);

        $mform->addElement('select', 'courseid', get_string('selectcourse', 'local_systemreports'), $courselist);
        $mform->setType('courseid', PARAM_INT);
        $mform->addRule('courseid', null, 'required', null, 'client');

        $mform->addElement('static', 'fromdate', get_string('fromdate', 'local_systemreports'), '<input type="date" class="form-control" id="fromdate" name="fromdate">');
        $mform->addElement('static', 'todate', get_string('todate', 'local_systemreports'), '<input type="date" class="form-control" id="todate" name="todate">');

        $buttonarray = array();
        $buttonarray[] = $mform->createElement('submit', 'submitbutton', get_string('getdata', 'local_systemreports'));
        $mform->addGroup($buttonarray, 'buttonar', '', ' ', false);
    }
}
