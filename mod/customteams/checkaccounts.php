<?php
/**
 * Check every mapped Teams account against the Azure tenant.
 *
 * The CLI importer has --verify for this at import time. This is the same check
 * from the admin UI, for the map maintained through the settings page and for
 * the periodic "is anything stale?" question -- accounts get disabled or
 * renamed in Azure long after they were mapped here, and the first symptom is
 * otherwise a lecturer unable to create a class.
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use mod_customteams\account_map;
use mod_customteams\graph_helper;

admin_externalpage_setup('mod_customteams_checkaccounts');

$run = optional_param('run', 0, PARAM_BOOL);

$PAGE->set_title(get_string('checkaccounts', 'customteams'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('checkaccounts', 'customteams'));

$usage = account_map::usage();
$upns = array_keys($usage);

if (empty($upns)) {
    echo $OUTPUT->notification(get_string('checknoaccounts', 'customteams'), 'notifyproblem');
    echo $OUTPUT->footer();
    exit;
}

if (!$run) {
    // Do not run on page load: this makes live calls to Graph, and the page is
    // reachable from the settings tree where a stray click is cheap.
    echo html_writer::tag('p', get_string('checkintro', 'customteams', count($upns)));

    echo $OUTPUT->single_button(
        new moodle_url('/mod/customteams/checkaccounts.php', ['run' => 1, 'sesskey' => sesskey()]),
        get_string('checkrun', 'customteams'),
        'get'
    );

    echo $OUTPUT->footer();
    exit;
}

require_sesskey();

try {
    $results = graph_helper::check_accounts($upns);
} catch (moodle_exception $e) {
    // Almost always unconfigured or wrong credentials, which is a far more
    // useful thing to say than a stack trace.
    echo $OUTPUT->notification($e->getMessage(), 'notifyproblem');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('checkcolaccount', 'customteams'),
    get_string('checkcolstatus', 'customteams'),
    get_string('checkcolusedby', 'customteams'),
];
$table->attributes['class'] = 'generaltable';

$okcount = 0;

foreach ($upns as $upn) {
    $result = $results[$upn] ?? null;

    if ($result !== null && $result->ok) {
        $okcount++;
        $status = html_writer::span(get_string('checkok', 'customteams'), 'badge badge-success bg-success text-white');
    } else {
        $message = $result->message ?? get_string('checkfailed', 'customteams');
        $status = html_writer::span(get_string('checkproblem', 'customteams'), 'badge badge-danger bg-danger text-white')
            . ' ' . s($message);
    }

    $table->data[] = [
        s($upn),
        $status,
        s(implode(', ', $usage[$upn])),
    ];
}

$failed = count($upns) - $okcount;

if ($failed === 0) {
    echo $OUTPUT->notification(get_string('checkallok', 'customteams', count($upns)), 'notifysuccess');
} else {
    echo $OUTPUT->notification(get_string('checksummary', 'customteams', (object) [
        'failed' => $failed,
        'total'  => count($upns),
    ]), 'notifyproblem');
}

echo html_writer::table($table);

echo $OUTPUT->single_button(
    new moodle_url('/mod/customteams/checkaccounts.php'),
    get_string('checkback', 'customteams'),
    'get'
);

echo $OUTPUT->footer();
