<?php
/**
 * Learner landing: the registration forms currently open to this user.
 * A fallback for the primary discovery route (a dashboard banner link).
 *
 * @package   local_eventreg
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require(__DIR__ . '/../../config.php');

use local_eventreg\manager;

require_login();
$context = context_system::instance();
$userid = (int) $USER->id;

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/eventreg/index.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('availableforms', 'local_eventreg'));
$PAGE->set_heading(get_string('availableforms', 'local_eventreg'));

$forms = manager::open_forms_for_user($userid);

// Which of these the user has already registered on.
$registered = [];
if ($forms) {
    list($insql, $params) = $DB->get_in_or_equal(array_map(fn($f) => $f->id, $forms), SQL_PARAMS_NAMED);
    $params['uid'] = $userid;
    $registered = array_flip($DB->get_fieldset_select('local_eventreg_submissions', 'formid',
        "formid $insql AND userid = :uid", $params));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('availableforms', 'local_eventreg'));

if (!$forms) {
    echo $OUTPUT->notification(get_string('noavailableforms', 'local_eventreg'), 'info');
} else {
    echo html_writer::start_tag('ul', ['class' => 'list-group']);
    foreach ($forms as $form) {
        $done = isset($registered[$form->id]);
        $link = html_writer::link(
            new moodle_url('/local/eventreg/view.php', ['id' => $form->id]),
            format_string($form->title));
        $badge = $done
            ? html_writer::span(get_string('registered', 'local_eventreg'), 'badge badge-success')
            : html_writer::span(get_string('register', 'local_eventreg'), 'badge badge-primary');
        echo html_writer::tag('li',
            $link . ' ' . $badge,
            ['class' => 'list-group-item d-flex justify-content-between align-items-center']);
    }
    echo html_writer::end_tag('ul');
}

echo $OUTPUT->footer();
