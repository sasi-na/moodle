<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Proctoring';

// Capabilities.
$string['proctor:viewreport'] = 'View proctoring evidence';
$string['proctor:bypass']     = 'Bypass proctoring';

// Quiz settings.
$string['setting_enabled']              = 'Enable proctoring';
$string['setting_enabled_help']         = 'When enabled, students must consent before starting; the attempt then captures an identity snapshot, periodic webcam snapshots, and logs tab-switch / focus-loss / fullscreen-exit events for faculty review. Camera failure never blocks the exam.';
$string['setting_identityrequired']     = 'Require identity snapshot';
$string['setting_captureinterval']      = 'Snapshot interval';
$string['setting_facedetect']           = 'AI face checks';
$string['setting_facedetect_help']      = 'Detect no-face, multiple-faces, looking-away and identity-mismatch using an in-browser model, flagged for review.';
$string['setting_audiomonitor']         = 'Audio monitoring';
$string['setting_audiomonitor_help']    = 'Monitor microphone levels to flag talking/voices during the attempt (levels only, no full recording).';
$string['setting_screenrecord']         = 'Screen capture';
$string['setting_screenrecord_help']    = 'Periodically capture the shared screen. Requires the student to share their screen at start.';
$string['setting_lockdown']             = 'Browser lockdown';
$string['setting_lockdown_help']        = 'Block and log copy/paste, right-click, dev-tools and key shortcuts, force fullscreen, and detect multiple monitors.';

// Preflight / consent.
$string['proctored_notice'] = 'This is a proctored exam. Your webcam (and, if enabled, microphone and screen) will be monitored, AI face checks may run, and on-screen actions will be logged for review.';
$string['consent_info']     = 'This exam is proctored. By continuing you consent to: webcam identity verification and periodic snapshots; automated face checks; activity/keystroke-shortcut logging; and (where enabled) microphone-level monitoring and screen capture, for the duration of your attempt.';
$string['consent_label']    = 'I consent to being proctored (camera, microphone, screen and activity monitoring) during this exam.';
$string['consent_required'] = 'You must consent to proctoring to start this exam.';

// Privacy.
$string['privacy:metadata:quizaccess_proctor_snapshot']           = 'Webcam snapshots captured during proctored attempts.';
$string['privacy:metadata:quizaccess_proctor_snapshot:userid']    = 'The user captured.';
$string['privacy:metadata:quizaccess_proctor_snapshot:capturedat'] = 'When the snapshot was captured.';
$string['privacy:metadata:quizaccess_proctor_event']              = 'Proctoring incidents logged during attempts.';
$string['privacy:metadata:quizaccess_proctor_event:userid']       = 'The user the event belongs to.';
$string['privacy:metadata:quizaccess_proctor_event:eventtype']    = 'The type of incident.';
