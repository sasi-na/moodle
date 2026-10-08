<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade steps for local_zoomattendance.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_zoomattendance_upgrade($oldversion) {

    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026060104) {

        /*
        |----------------------------------------------------------------------
        | Persisted attendance status.
        |
        | Store whether each attendance row meets the 90% threshold
        | (total_duration vs session_duration) so the block, reports and
        | dashboards share one consistent definition. Backfill existing rows.
        |----------------------------------------------------------------------
        */

        $table = new xmldb_table('local_zoom_attendance_summary');

        $field = new xmldb_field(
            'attended',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'session_duration'
        );

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Backfill: attended when >=90% of session watched; when the session
        // duration is unknown, count any logged time as attended.
        $DB->execute("
            UPDATE {local_zoom_attendance_summary}
               SET attended = CASE
                   WHEN session_duration > 0
                        AND total_duration >= 0.9 * session_duration THEN 1
                   WHEN session_duration <= 0
                        AND total_duration > 0 THEN 1
                   ELSE 0
               END
        ");

        upgrade_plugin_savepoint(
            true,
            2026060104,
            'local',
            'zoomattendance'
        );
    }

    if ($oldversion < 2026060105) {

        /*
        |----------------------------------------------------------------------
        | Attendance threshold lowered to 50%.
        |
        | Recompute the stored attended flag for all existing rows so history
        | reflects the new >=50% rule.
        |----------------------------------------------------------------------
        */

        $DB->execute("
            UPDATE {local_zoom_attendance_summary}
               SET attended = CASE
                   WHEN session_duration > 0
                        AND total_duration >= 0.5 * session_duration THEN 1
                   WHEN session_duration <= 0
                        AND total_duration > 0 THEN 1
                   ELSE 0
               END
        ");

        upgrade_plugin_savepoint(
            true,
            2026060105,
            'local',
            'zoomattendance'
        );
    }

    if ($oldversion < 2026061100) {

        /*
        |----------------------------------------------------------------------
        | Direct-join flow.
        |
        | Three tables: calendar-event -> Zoom URL map, the meeting registry
        | synced from Zoom (registration hash -> meeting id), and cached
        | per-user registrant join links. Also seed the new admin settings
        | from the values previously hardcoded so the pipeline keeps working.
        |----------------------------------------------------------------------
        */

        $table = new xmldb_table('local_zoom_event_join');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('event_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('zoom_url', XMLDB_TYPE_CHAR, '500', null, XMLDB_NOTNULL, null, null);
        $table->add_field('register_hash', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '');
        $table->add_field('meeting_id', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('event_idx', XMLDB_INDEX_UNIQUE, ['event_id']);
        $table->add_index('hash_idx', XMLDB_INDEX_NOTUNIQUE, ['register_hash']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_zoom_meeting_registry');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('meeting_id', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('topic', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
        $table->add_field('host_email', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
        $table->add_field('register_hash', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '');
        $table->add_field('start_time', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('meeting_idx', XMLDB_INDEX_UNIQUE, ['meeting_id']);
        $table->add_index('hash_idx', XMLDB_INDEX_NOTUNIQUE, ['register_hash']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_zoom_join_links');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('meeting_id', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('join_url', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('usermeeting_idx', XMLDB_INDEX_UNIQUE, ['userid', 'meeting_id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // (Historically this seeded settings from hardcoded credentials. The
        // fallbacks have been removed — credentials are admin-settings only —
        // so there is nothing to seed; installs configure them in the UI.)

        upgrade_plugin_savepoint(
            true,
            2026061100,
            'local',
            'zoomattendance'
        );
    }

    if ($oldversion < 2026061101) {

        /*
        |----------------------------------------------------------------------
        | Meeting live-status gate.
        |
        | Track whether each meeting is currently running, driven by the
        | meeting.started / meeting.ended webhooks with a throttled API
        | double-check, so join.php can hold students on a waiting page
        | until the host actually starts the session.
        |----------------------------------------------------------------------
        */

        $table = new xmldb_table('local_zoom_meeting_registry');

        $fields = [
            new xmldb_field('live_status', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, '', 'start_time'),
            new xmldb_field('status_time', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'live_status'),
            new xmldb_field('status_checked_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'status_time'),
        ];

        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_plugin_savepoint(
            true,
            2026061101,
            'local',
            'zoomattendance'
        );
    }

    if ($oldversion < 2026061500) {

        /*
        |----------------------------------------------------------------------
        | Per-session moderation on the live-attendance list.
        |
        | Two teacher actions on view.php (tab=attendance): "Dismiss session"
        | (hide it from the list) and "Mark all present" (grant joined students
        | full-session duration). Both record an audit message in `comments`.
        |
        | local_zoom_event_map predates the plugin's XMLDB schema (it is created
        | outside install.xml), so guard every step with existence checks.
        |----------------------------------------------------------------------
        */

        $table = new xmldb_table('local_zoom_event_map');

        if ($dbman->table_exists($table)) {

            $skipped = new xmldb_field('skipped', XMLDB_TYPE_INTEGER, '1', null,
                XMLDB_NOTNULL, null, '0', 'timecreated');
            if (!$dbman->field_exists($table, $skipped)) {
                $dbman->add_field($table, $skipped);
            }

            $comments = new xmldb_field('comments', XMLDB_TYPE_TEXT, null, null,
                null, null, null, 'skipped');
            if (!$dbman->field_exists($table, $comments)) {
                $dbman->add_field($table, $comments);
            }
        }

        upgrade_plugin_savepoint(
            true,
            2026061500,
            'local',
            'zoomattendance'
        );
    }

    if ($oldversion < 2026061700) {

        /*
        |----------------------------------------------------------------------
        | Scheduler-managed meetings.
        |
        | Flag the event-join rows created by the in-LMS scheduler so the
        | calendar event observers only update/delete meetings this feature
        | created on Zoom — never meetings made manually and merely mapped.
        |----------------------------------------------------------------------
        */

        $table = new xmldb_table('local_zoom_event_join');
        $field = new xmldb_field('managed', XMLDB_TYPE_INTEGER, '1', null,
            XMLDB_NOTNULL, null, '0', 'meeting_id');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(
            true,
            2026061700,
            'local',
            'zoomattendance'
        );
    }

    return true;
}
