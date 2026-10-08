<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade steps for local_videotracker.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_videotracker_upgrade($oldversion) {

    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026060101) {

        /*
        |----------------------------------------------------------------------
        | Faculty approval flow.
        |
        | The summary status now means:
        |   0 = Pending review
        |   1 = Approved
        |   2 = Rejected
        |
        | Previously summaries were auto-accepted (default 1). Flip the column
        | default to Pending so new submissions await faculty review.
        |----------------------------------------------------------------------
        */

        $table = new xmldb_table('local_zoom_recording_summary');

        $field = new xmldb_field(
            'status',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0'
        );

        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_default($table, $field);
        }

        upgrade_plugin_savepoint(
            true,
            2026060101,
            'local',
            'videotracker'
        );
    }

    if ($oldversion < 2026060102) {

        /*
        |----------------------------------------------------------------------
        | Resubmit-after-rejection.
        |
        | Track how many times a student has submitted a summary so we can
        | allow exactly one resubmit after a rejection. 1 = first submission,
        | 2 = resubmitted once (no further resubmits allowed).
        |----------------------------------------------------------------------
        */

        $table = new xmldb_table('local_zoom_recording_summary');

        $field = new xmldb_field(
            'attempts',
            XMLDB_TYPE_INTEGER,
            '2',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'submittedat'
        );

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(
            true,
            2026060102,
            'local',
            'videotracker'
        );
    }

    return true;
}
