<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade steps for quizaccess_proctor.
 *
 * @package quizaccess_proctor
 */
function xmldb_quizaccess_proctor_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026061700) {

        // New per-quiz capability toggles.
        $settings = new xmldb_table('quizaccess_proctor_settings');
        foreach ([
            'facedetect'   => 1,
            'audiomonitor' => 0,
            'screenrecord' => 0,
            'lockdown'     => 1,
        ] as $name => $default) {
            $field = new xmldb_field($name, XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, (string) $default, 'logevents');
            if (!$dbman->field_exists($settings, $field)) {
                $dbman->add_field($settings, $field);
            }
        }

        // Event severity + wider eventtype.
        $event = new xmldb_table('quizaccess_proctor_event');
        $severity = new xmldb_field('severity', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1', 'eventtype');
        if (!$dbman->field_exists($event, $severity)) {
            $dbman->add_field($event, $severity);
        }
        $eventtype = new xmldb_field('eventtype', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null, 'userid');
        $dbman->change_field_precision($event, $eventtype);

        // Per-attempt risk summary.
        $summary = new xmldb_table('quizaccess_proctor_summary');
        if (!$dbman->table_exists($summary)) {
            $summary->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $summary->add_field('attemptid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $summary->add_field('quizid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $summary->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $summary->add_field('riskscore', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $summary->add_field('flagcount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $summary->add_field('timecomputed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $summary->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $summary->add_index('attemptid', XMLDB_INDEX_UNIQUE, ['attemptid']);
            $summary->add_index('quizid', XMLDB_INDEX_NOTUNIQUE, ['quizid']);
            $dbman->create_table($summary);
        }

        upgrade_plugin_savepoint(true, 2026061700, 'quizaccess', 'proctor');
    }

    return true;
}
