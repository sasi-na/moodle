<?php
defined('MOODLE_INTERNAL') || die();

class block_support extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_support');
    }

    public function get_content() {
        global $PAGE, $USER;

        if ($this->content !== null) {
            return $this->content;
        }


        $this->page->requires->css('/blocks/support/styles.css');

        $user_data = [
    'username'  => $USER->username ?? '',
    'phone' => !empty($USER->phone1)
    ? $USER->phone1
    : (!empty($USER->phone2)
        ? $USER->phone2
        : ($USER->profile['mobile'] ?? '')
    ),
    'PEN_no'    =>
        !empty($USER->profile['provisional_enrollment_number'])
        ? $USER->profile['provisional_enrollment_number']
        : ($USER->profile['enrolmentnumber'] ?? ''),
    'USN'       => $USER->profile['usn'] ?? '',
    'firstname' => $USER->firstname ?? '',
    'lastname'  => $USER->lastname ?? '',
    'program'   =>
        !empty($USER->profile['main_program'])
        ? $USER->profile['main_program']
        : (!empty($USER->profile['mainprogram'])
            ? $USER->profile['mainprogram']
            : (!empty($USER->profile['program_registered_for'])
                ? $USER->profile['program_registered_for']
                : (!empty($USER->profile['programregister'])
                    ? $USER->profile['programregister']
                        : ''))),
    'elective'  => $USER->profile['elective'] ?? '',
    'batch'     => !empty($USER->profile['cohort_batch'])
        ? $USER->profile['cohort_batch']
        : ($USER->profile['batch'] ?? ''),
];

        $user_data_json = json_encode($user_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        //$user_json = urlencode(json_encode($user_data));
        $user_json = '';
        $user_full_json = base64_encode(
            json_encode($USER, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $emailPrefix = '';
        if (!empty($USER->email) && strpos($USER->email, '@') !== false) {
            $emailPrefix = explode('@', $USER->email)[0];
        }

        $password = $emailPrefix . '@123';

        $params = [
            'email'          => $USER->email,
            'password'       => $password,
            'g'              => 'Mw==',
            'user_json'      => $user_json,
        ];

        $url = new moodle_url(
            'https://portal.onlinejain.com/support/dashboard/tickets/created',
            $params
        );

        $button = html_writer::link(
            $url,
            get_string('supportbutton', 'block_support'),
            [
                'class'  => 'btn btn-primary support-main-button',
                'target' => '_blank'
            ]
        );

        $this->content = new stdClass();
        $this->content->text = html_writer::div(
            $button,
            'support-block-button text-center'
        );
        $user_for_js = json_encode(
       $USER,
      JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $js = <<<JS
document.addEventListener("DOMContentLoaded", function () {
     const mainButton = document.querySelector('.support-main-button');
    if (!mainButton) return;
    const supportUrl = mainButton.href; 
    document.querySelectorAll("a[href*='support.onlinejain.com']").forEach(function(link) {
        link.href = supportUrl;
        link.target = '_blank';
        link.textContent = "Click here to raise a ticket";
    });
     const moodleUser = $user_for_js;
    console.log("Moodle USER Object:", moodleUser);
});
JS;

        $this->page->requires->js_init_code($js);

        return $this->content;
    }
}
