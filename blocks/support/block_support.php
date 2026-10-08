<?php
defined('MOODLE_INTERNAL') || die();

use local_commonutils\user_helper;

class block_support extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_support');
    }

    public function get_content() {
        global $PAGE, $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $cohort = user_helper::get_single_profile_field(
            $USER->id,
            'cohort_batch'
        );

        if (!empty($cohort) && $cohort === 'Jan, 2026 - TP') {
            $this->content = new stdClass();
            $this->content->text = '';
            return $this->content;
        }

        $this->page->requires->css('/blocks/support/styles.css');

        /*
        |--------------------------------------------------------------------------
        | Learner Success Manager (LSM) details
        |--------------------------------------------------------------------------
        | Rendered only when BOTH profile fields are populated.
        */
        $lsm = user_helper::get_multi_profile_fields(
            $USER->id,
            ['lsm_name', 'lsmnumber']
        );
        $lsmname   = trim($lsm['lsm_name'] ?? '');
        $lsmnumber = trim($lsm['lsmnumber'] ?? '');

        $allowedcategories = [174];

        /*
        |--------------------------------------------------------------------------
        | Existing conditional support portal code (kept unchanged)
        |--------------------------------------------------------------------------
        */

        /*if ($this->users_in_allowed_category($USER->id, $allowedcategories)) {

            $user_data = [
                'username'  => $USER->username ?? '',
                'phone' => !empty($USER->phone1)
                    ? $USER->phone1
                    : (!empty($USER->phone2)
                        ? $USER->phone2
                        : ($USER->profile['mobile'] ?? '')
                    ),
                'PEN_no' =>
                    !empty($USER->profile['provisional_enrollment_number'])
                    ? $USER->profile['provisional_enrollment_number']
                    : ($USER->profile['enrolmentnumber'] ?? ''),
                'USN' => $USER->profile['usn'] ?? '',
                'firstname' => $USER->firstname ?? '',
                'lastname'  => $USER->lastname ?? '',
                'program' =>
                    !empty($USER->profile['main_program'])
                    ? $USER->profile['main_program']
                    : (!empty($USER->profile['mainprogram'])
                        ? $USER->profile['mainprogram']
                        : (!empty($USER->profile['program_registered_for'])
                            ? $USER->profile['program_registered_for']
                            : (!empty($USER->profile['programregister'])
                                ? $USER->profile['programregister']
                                : ''
                            )
                        )
                    ),
                'elective'  => $USER->profile['elective'] ?? '',
                'batch' =>
                    !empty($USER->profile['cohort_batch'])
                    ? $USER->profile['cohort_batch']
                    : ($USER->profile['batch'] ?? ''),
            ];

            $user_data_json = json_encode(
                $user_data,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            $user_json = '';

            $user_full_json = base64_encode(
                json_encode(
                    $USER,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                )
            );

            $emailPrefix = '';
            if (!empty($USER->email) && strpos($USER->email, '@') !== false) {
                $emailPrefix = explode('@', $USER->email)[0];
            }

            $password = $emailPrefix . '@123';

            $params = [
                'email'     => $USER->email,
                'password'  => $password,
                'g'         => 'Mw==',
                'user_json' => $user_json,
            ];

            // Per-site ticket portal. Default preserves the previous hardcoded
            // Jain URL so that site is unaffected; see blocks/support/settings.php.
            $portalurl = (string) get_config('block_support', 'portalticketurl');
            if ($portalurl === '') {
                $portalurl = 'https://portal.onlinejain.com/support/dashboard/tickets/created';
            }

            $url = new moodle_url(
                $portalurl,
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
    if (!mainButton) {
        return;
    }

    const supportUrl = mainButton.href;

    document.querySelectorAll(
        "a[href*='support.onlinejain.com']"
    ).forEach(function(link) {
        link.href = supportUrl;
        link.target = '_blank';
        link.textContent = "Click here to raise a ticket";
    });

    const moodleUser = $user_for_js;
    console.log("Moodle USER Object:", moodleUser);

});
JS;

        }*/
        // else {

        /*
        |--------------------------------------------------------------------------
        | Support Button
        |--------------------------------------------------------------------------
        */

        $supportbutton = html_writer::tag(
            'button',
            get_string('supportbutton', 'block_support'),
            [
                'class' => 'btn btn-primary support-main-button',
                'id'    => 'support-main-button',
                'type'  => 'button',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Learner Escalation SOP Button
        |--------------------------------------------------------------------------
        */

        // Not every site uses this document, so it is optional. Sites that set
        // nothing keep it, which is the previous behaviour.
        $sopbutton = '';
        if (get_config('block_support', 'showescalationsop') !== '0') {
            $soppdfurl = new moodle_url(
                '/blocks/support/assets/SOP.pdf'
            );

            $sopbutton = html_writer::link(
                $soppdfurl,
                get_string('sopbutton', 'block_support'),
                [
                    'class' => 'btn btn-primary support-sop-button',
                    'target' => '_blank',
                    'rel' => 'noopener noreferrer'
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | LSM Contact Button (shown only when both fields are set)
        |--------------------------------------------------------------------------
        */

        $lsmbuttonhtml = '';

        $isonboarding = (strtolower($lsmname) === 'onboarding support');

        if ($isonboarding) {
            // Onboarding Team: fixed message and number; shown regardless of lsmnumber.
            $lsmmessage = get_string('lsmmessage_onboarding', 'block_support');
            $lsmlabel   = 'Onboarding Support - 08062910210';

            $lsmbuttonhtml = html_writer::tag(
                'button',
                s($lsmlabel),
                [
                    'class' => 'btn btn-primary support-lsm-button',
                    'id'    => 'lsm-contact-button',
                    'type'  => 'button',
                    'data-lsm-message' => $lsmmessage,
                ]
            );
        } else if ($lsmname !== '' && $lsmnumber !== '') {
            $lsmmessage = get_string('lsmmessage', 'block_support', (object) [
                'name'   => $lsmname,
                'number' => $lsmnumber,
            ]);

            $lsmbuttonhtml = html_writer::tag(
                'button',
                s($lsmname . ' - ' . '+918748900900'),
                [
                    'class' => 'btn btn-primary support-lsm-button',
                    'id'    => 'lsm-contact-button',
                    'type'  => 'button',
                    'data-lsm-message' => $lsmmessage,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Combined Block Content
        |--------------------------------------------------------------------------
        */

        $buttonshtml = html_writer::div(
            $lsmbuttonhtml .
            $supportbutton .
            $sopbutton,
            'support-block-button text-center'
        );

        $this->content = new stdClass();
        $this->content->text = $buttonshtml;

        /*
        |--------------------------------------------------------------------------
        | JavaScript
        |--------------------------------------------------------------------------
        */

        $js = <<<JS
(function () {

    function initSupportBlock() {

    /*
    |--------------------------------------------------------------------------
    | Support Button Click
    |--------------------------------------------------------------------------
    */

    const supportButton = document.getElementById(
        "support-main-button"
    );

    if (supportButton) {
        supportButton.addEventListener("click", function () {
            window.open(
                M.cfg.wwwroot + '/blocks/support/support_api.php',
                '_blank'
            );
        });
    }

    /*
    |--------------------------------------------------------------------------
    | LSM Contact Button -> popup with message
    |--------------------------------------------------------------------------
    */

    const lsmButton = document.getElementById("lsm-contact-button");

    if (lsmButton) {

        const closeLsmModal = function () {
            const overlay = document.getElementById("lsm-modal-overlay");
            if (overlay) {
                overlay.remove();
            }
            document.removeEventListener("keydown", onLsmKeydown);
        };

        const onLsmKeydown = function (e) {
            if (e.key === "Escape") {
                closeLsmModal();
            }
        };

        lsmButton.addEventListener("click", function () {

            // Avoid stacking multiple modals.
            if (document.getElementById("lsm-modal-overlay")) {
                return;
            }

            const message = lsmButton.getAttribute("data-lsm-message") || "";

            const overlay = document.createElement("div");
            overlay.id = "lsm-modal-overlay";
            overlay.className = "lsm-modal-overlay";

            const modal = document.createElement("div");
            modal.className = "lsm-modal";

            const closeBtn = document.createElement("button");
            closeBtn.type = "button";
            closeBtn.className = "lsm-modal-close";
            closeBtn.setAttribute("aria-label", "Close");
            closeBtn.innerHTML = "&times;";

            const body = document.createElement("div");
            body.className = "lsm-modal-body";
            body.textContent = message;

            modal.appendChild(closeBtn);
            modal.appendChild(body);
            overlay.appendChild(modal);
            document.body.appendChild(overlay);

            closeBtn.addEventListener("click", closeLsmModal);
            overlay.addEventListener("click", function (e) {
                if (e.target === overlay) {
                    closeLsmModal();
                }
            });
            document.addEventListener("keydown", onLsmKeydown);
        });
    }

    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initSupportBlock);
    } else {
        initSupportBlock();
    }

})();
JS;

        // }

        $this->page->requires->js_init_code($js);

        return $this->content;
    }

    private function users_in_allowed_category(
        $userid,
        $allowedcategories
    ) {
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
            JOIN {enrol} e
                ON e.id = ue.enrolid
            JOIN {course} c
                ON c.id = e.courseid
            JOIN {course_categories} cc
                ON cc.id = c.category
            WHERE ue.userid = ?
            AND (" . implode(' OR ', $conditions) . ")
        ";

        array_unshift($params, $userid);

        return $DB->record_exists_sql($sql, $params);
    }
}