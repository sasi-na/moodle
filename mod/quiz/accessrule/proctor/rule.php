<?php
defined('MOODLE_INTERNAL') || die();

use mod_quiz\local\access_rule_base;
use mod_quiz\quiz_settings;
use mod_quiz\form\preflight_check_form;

/**
 * In-house lightweight proctoring access rule.
 *
 * Opt-in per quiz, disabled by default. When enabled it requires a consent
 * preflight, then loads a monitor that captures an identity snapshot, periodic
 * webcam snapshots, and logs tab-switch / blur / fullscreen-exit events. It
 * never blocks submission on camera failure — evidence gaps are flagged in the
 * faculty review instead.
 *
 * @package   quizaccess_proctor
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizaccess_proctor extends access_rule_base {

    /**
     * Load per-quiz proctoring settings.
     *
     * @param int $quizid
     * @return \stdClass|null
     */
    public static function get_settings(int $quizid): ?\stdClass {
        global $DB;
        return $DB->get_record('quizaccess_proctor_settings', ['quizid' => $quizid]) ?: null;
    }

    public static function make(quiz_settings $quizobj, $timenow, $canignoretimelimits) {
        $settings = self::get_settings((int) $quizobj->get_quizid());
        if (!$settings || empty($settings->enabled)) {
            return null;
        }
        // Staff with the bypass capability are not proctored.
        if (has_capability('quizaccess/proctor:bypass', $quizobj->get_context())) {
            return null;
        }
        return new self($quizobj, $timenow);
    }

    public function description() {
        return [get_string('proctored_notice', 'quizaccess_proctor')];
    }

    public function is_preflight_check_required($attemptid) {
        global $SESSION;
        $quizid = (int) $this->quiz->id;
        return empty($SESSION->quizaccess_proctor_consent[$quizid]);
    }

    public function add_preflight_check_form_fields(preflight_check_form $quizform,
            MoodleQuickForm $mform, $attemptid) {
        $mform->addElement('header', 'proctorheader', get_string('pluginname', 'quizaccess_proctor'));
        $mform->addElement('static', 'proctorinfo', '', get_string('consent_info', 'quizaccess_proctor'));
        $mform->addElement('advcheckbox', 'proctorconsent', '', get_string('consent_label', 'quizaccess_proctor'));
    }

    public function validate_preflight_check($data, $files, $errors, $attemptid) {
        if (empty($data['proctorconsent'])) {
            $errors['proctorconsent'] = get_string('consent_required', 'quizaccess_proctor');
        }
        return $errors;
    }

    public function notify_preflight_check_passed($attemptid) {
        global $SESSION;
        $SESSION->quizaccess_proctor_consent[(int) $this->quiz->id] = true;
    }

    public function current_attempt_finished() {
        global $SESSION;
        unset($SESSION->quizaccess_proctor_consent[(int) $this->quiz->id]);
    }

    public function setup_attempt_page($page) {
        $settings = self::get_settings((int) $this->quiz->id);
        if (!$settings) {
            return;
        }
        $attemptid = optional_param('attempt', 0, PARAM_INT);
        $page->requires->js_call_amd('quizaccess_proctor/monitor', 'init', [self::client_config($settings, $attemptid)]);
    }

    /**
     * Build the monitor client config from settings (shared by the native attempt
     * page and the local_exams runner).
     *
     * @param \stdClass $settings
     * @param int $attemptid
     * @return array
     */
    public static function client_config(\stdClass $settings, int $attemptid): array {
        return [
            'attemptid'        => $attemptid,
            'interval'         => (int) $settings->captureinterval,
            'identityrequired' => (bool) $settings->identityrequired,
            'logevents'        => (bool) $settings->logevents,
            'facedetect'       => (bool) ($settings->facedetect ?? 1),
            'audiomonitor'     => (bool) ($settings->audiomonitor ?? 0),
            'screenrecord'     => (bool) ($settings->screenrecord ?? 0),
            'lockdown'         => (bool) ($settings->lockdown ?? 1),
        ];
    }

    // ---- Settings form ----------------------------------------------------

    public static function add_settings_form_fields(
            mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        global $DB;

        $mform->addElement('header', 'proctorhdr', get_string('pluginname', 'quizaccess_proctor'));

        $mform->addElement('selectyesno', 'proctor_enabled', get_string('setting_enabled', 'quizaccess_proctor'));
        $mform->addHelpButton('proctor_enabled', 'setting_enabled', 'quizaccess_proctor');
        $mform->setDefault('proctor_enabled', 0);

        $mform->addElement('selectyesno', 'proctor_identityrequired',
            get_string('setting_identityrequired', 'quizaccess_proctor'));
        $mform->setDefault('proctor_identityrequired', 1);
        $mform->hideIf('proctor_identityrequired', 'proctor_enabled', 'eq', 0);

        $intervals = [30 => '30s', 60 => '1m', 120 => '2m', 300 => '5m'];
        $mform->addElement('select', 'proctor_captureinterval',
            get_string('setting_captureinterval', 'quizaccess_proctor'), $intervals);
        $mform->setDefault('proctor_captureinterval', 60);
        $mform->hideIf('proctor_captureinterval', 'proctor_enabled', 'eq', 0);

        foreach (['facedetect' => 1, 'lockdown' => 1, 'audiomonitor' => 0, 'screenrecord' => 0] as $name => $def) {
            $mform->addElement('selectyesno', 'proctor_' . $name,
                get_string('setting_' . $name, 'quizaccess_proctor'));
            $mform->addHelpButton('proctor_' . $name, 'setting_' . $name, 'quizaccess_proctor');
            $mform->setDefault('proctor_' . $name, $def);
            $mform->hideIf('proctor_' . $name, 'proctor_enabled', 'eq', 0);
        }

        // Preload existing values when editing.
        $instance = $quizform->get_instance();
        if ($instance && ($settings = self::get_settings((int) $instance))) {
            $mform->setDefault('proctor_enabled', $settings->enabled);
            $mform->setDefault('proctor_identityrequired', $settings->identityrequired);
            $mform->setDefault('proctor_captureinterval', $settings->captureinterval);
            foreach (['facedetect', 'lockdown', 'audiomonitor', 'screenrecord'] as $name) {
                $mform->setDefault('proctor_' . $name, $settings->$name ?? 0);
            }
        }
    }

    public static function save_settings($quiz) {
        global $DB;

        $quizid = (int) $quiz->id;
        $enabled = !empty($quiz->proctor_enabled) ? 1 : 0;

        $existing = $DB->get_record('quizaccess_proctor_settings', ['quizid' => $quizid]);
        if (!$enabled && !$existing) {
            return;
        }

        $record = (object) [
            'quizid'           => $quizid,
            'enabled'          => $enabled,
            'identityrequired' => !empty($quiz->proctor_identityrequired) ? 1 : 0,
            'captureinterval'  => (int) ($quiz->proctor_captureinterval ?? 60),
            'maxsnapshots'     => 200,
            'logevents'        => 1,
            'facedetect'       => !empty($quiz->proctor_facedetect) ? 1 : 0,
            'audiomonitor'     => !empty($quiz->proctor_audiomonitor) ? 1 : 0,
            'screenrecord'     => !empty($quiz->proctor_screenrecord) ? 1 : 0,
            'lockdown'         => !empty($quiz->proctor_lockdown) ? 1 : 0,
            'timemodified'     => time(),
        ];
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('quizaccess_proctor_settings', $record);
        } else {
            $DB->insert_record('quizaccess_proctor_settings', $record);
        }
    }

    public static function delete_settings($quiz) {
        global $DB;
        $DB->delete_records('quizaccess_proctor_settings', ['quizid' => (int) $quiz->id]);
    }
}
