<?php
namespace local_studentid\form;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

class request_form extends \moodleform {

    public function definition() {
        $mform = $this->_form;
        $data  = $this->_customdata;

        $studentdata = $data['studentdata'] ?? [];
        $isupdate    = !empty($data['requestid']);

        $mform->addElement('html', '<div class="alert alert-info mb-4">
            <i class="fa fa-info-circle mr-1"></i>
            Fields marked <em>pre-filled</em> are loaded from your academic record and cannot be edited here.
            Contact the academic office to update them.
        </div>');

        // ---- Read-only student information ----
        $mform->addElement('header', 'student_info_hdr', get_string('hdr_studentinfo', 'local_studentid'));
        $mform->setExpanded('student_info_hdr', true);

        $readonly_fields = [
            'fullname' => get_string('fullname', 'local_studentid'),
            'usn'      => get_string('usn', 'local_studentid'),
            'program'  => get_string('program', 'local_studentid'),
            'batch'    => get_string('batch', 'local_studentid'),
            'dob'      => get_string('dob', 'local_studentid'),
            'contact'  => get_string('contact', 'local_studentid'),
            'city'     => get_string('city', 'local_studentid'),
            'country'  => get_string('country', 'local_studentid'),
        ];

        foreach ($readonly_fields as $key => $label) {
            $value = htmlspecialchars($studentdata[$key] ?? '', ENT_QUOTES, 'UTF-8');
            $mform->addElement('static', $key . '_display', $label,
                '<span class="text-dark font-weight-bold">' . ($value ?: '<em class="text-muted">' . get_string('notset', 'local_studentid') . '</em>') . '</span>
                <small class="ml-2 badge badge-light">' . get_string('prefilled', 'local_studentid') . '</small>'
            );
        }

        // ---- User-editable fields ----
        $mform->addElement('header', 'request_details_hdr', get_string('hdr_requestdetails', 'local_studentid'));
        $mform->setExpanded('request_details_hdr', true);

        $bloodgroups = [
            ''    => get_string('choosedots'),
            'A+'  => 'A+',  'A-'  => 'A-',
            'B+'  => 'B+',  'B-'  => 'B-',
            'AB+' => 'AB+', 'AB-' => 'AB-',
            'O+'  => 'O+',  'O-'  => 'O-',
        ];
        $mform->addElement('select', 'blood_group', get_string('bloodgroup', 'local_studentid'), $bloodgroups);
        $mform->addRule('blood_group', get_string('errornobloodgroup', 'local_studentid'), 'required', null, 'client');
        $mform->setType('blood_group', PARAM_ALPHA);

        // Photo upload.
        $mform->addElement('header', 'photo_hdr', get_string('hdr_photo', 'local_studentid'));
        $mform->setExpanded('photo_hdr', true);

        $mform->addElement('html', '
        <div class="card mb-3">
          <div class="card-body bg-light">
            <h6 class="card-subtitle mb-2 text-muted">' . get_string('photo_guidelines_title', 'local_studentid') . '</h6>
            <ul class="small mb-0">
              <li>' . get_string('photo_guide_1', 'local_studentid') . '</li>
              <li>' . get_string('photo_guide_2', 'local_studentid') . '</li>
              <li>' . get_string('photo_guide_3', 'local_studentid') . '</li>
              <li>' . get_string('photo_guide_4', 'local_studentid') . '</li>
              <li>' . get_string('photo_guide_5', 'local_studentid') . '</li>
            </ul>
          </div>
        </div>');

        $maxbytes = (int) (get_config('local_studentid', 'maxphotosize') ?: 2097152);
        $mform->addElement('filepicker', 'photo', get_string('photo', 'local_studentid'), null, [
            'maxbytes'       => $maxbytes,
            'accepted_types' => ['.jpg', '.jpeg', '.png'],
        ]);
        $mform->addHelpButton('photo', 'photo', 'local_studentid');

        if (empty($isupdate)) {
            $mform->addRule('photo', get_string('errornophoto', 'local_studentid'), 'required', null, 'client');
        }

        // Show existing photo if updating.
        if ($isupdate && !empty($data['photo_url'])) {
            $mform->addElement('html', '
            <div class="form-group row fitem">
              <div class="col-md-3"></div>
              <div class="col-md-9">
                <p class="small text-muted mb-1">' . get_string('current_photo', 'local_studentid') . '</p>
                <img id="sid-current-photo" src="' . $data['photo_url'] . '"
                     style="max-width:120px; max-height:150px; border:2px solid #dee2e6; border-radius:6px; object-fit:cover;">
              </div>
            </div>');
        }

        // Hidden: requestid if editing.
        if ($isupdate) {
            $mform->addElement('hidden', 'requestid', $data['requestid']);
            $mform->setType('requestid', PARAM_INT);
        }

        $submitlabel = $isupdate ? get_string('updaterequest', 'local_studentid') : get_string('submitrequest', 'local_studentid');
        $this->add_action_buttons(true, $submitlabel);
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['blood_group']) && !in_array($data['blood_group'], ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])) {
            $errors['blood_group'] = get_string('errornobloodgroup', 'local_studentid');
        }

        return $errors;
    }
}
