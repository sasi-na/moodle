<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_studentid_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026052500) {
        $table = new xmldb_table('local_studentid_requests');

        $field1 = new xmldb_field('aadhaar_front_path', XMLDB_TYPE_TEXT, null, null, null, null, null);
        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }

        $field2 = new xmldb_field('aadhaar_back_path', XMLDB_TYPE_TEXT, null, null, null, null, null);
        if (!$dbman->field_exists($table, $field2)) {
            $dbman->add_field($table, $field2);
        }

        upgrade_plugin_savepoint(true, 2026052500, 'local', 'studentid');
    }

    if ($oldversion < 2026052600) {
        $table = new xmldb_table('local_studentid_requests');

        // bloodgroup -> blood_group  (add new, copy, drop old)
        if ($dbman->field_exists($table, new xmldb_field('bloodgroup'))) {
            if (!$dbman->field_exists($table, new xmldb_field('blood_group'))) {
                $f = new xmldb_field('blood_group', XMLDB_TYPE_CHAR, '5', null, null, null, null);
                $dbman->add_field($table, $f);
                $DB->execute("UPDATE {local_studentid_requests} SET blood_group = bloodgroup");
            }
            $dbman->drop_field($table, new xmldb_field('bloodgroup'));
        }
        if (!$dbman->field_exists($table, new xmldb_field('blood_group'))) {
            $f = new xmldb_field('blood_group', XMLDB_TYPE_CHAR, '5', null, null, null, null);
            $dbman->add_field($table, $f);
        }

        // verifierid -> reviewed_by
        if ($dbman->field_exists($table, new xmldb_field('verifierid'))) {
            if (!$dbman->field_exists($table, new xmldb_field('reviewed_by'))) {
                $f = new xmldb_field('reviewed_by', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
                $dbman->add_field($table, $f);
                $DB->execute("UPDATE {local_studentid_requests} SET reviewed_by = verifierid");
            }
            $dbman->drop_field($table, new xmldb_field('verifierid'));
        }
        if (!$dbman->field_exists($table, new xmldb_field('reviewed_by'))) {
            $f = new xmldb_field('reviewed_by', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $dbman->add_field($table, $f);
        }

        // verifiercomment -> review_comment
        if ($dbman->field_exists($table, new xmldb_field('verifiercomment'))) {
            if (!$dbman->field_exists($table, new xmldb_field('review_comment'))) {
                $f = new xmldb_field('review_comment', XMLDB_TYPE_TEXT, null, null, null, null, null);
                $dbman->add_field($table, $f);
                $DB->execute("UPDATE {local_studentid_requests} SET review_comment = verifiercomment");
            }
            $dbman->drop_field($table, new xmldb_field('verifiercomment'));
        }
        if (!$dbman->field_exists($table, new xmldb_field('review_comment'))) {
            $f = new xmldb_field('review_comment', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $dbman->add_field($table, $f);
        }

        // timeupdated -> timemodified
        if ($dbman->field_exists($table, new xmldb_field('timeupdated'))) {
            if (!$dbman->field_exists($table, new xmldb_field('timemodified'))) {
                $f = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
                $dbman->add_field($table, $f);
                $DB->execute("UPDATE {local_studentid_requests} SET timemodified = timeupdated");
            }
            $dbman->drop_field($table, new xmldb_field('timeupdated'));
        }
        if (!$dbman->field_exists($table, new xmldb_field('timemodified'))) {
            $f = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $dbman->add_field($table, $f);
        }

        // timereviewed -> timesubmitted
        if ($dbman->field_exists($table, new xmldb_field('timereviewed'))) {
            if (!$dbman->field_exists($table, new xmldb_field('timesubmitted'))) {
                $f = new xmldb_field('timesubmitted', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
                $dbman->add_field($table, $f);
                $DB->execute("UPDATE {local_studentid_requests} SET timesubmitted = timereviewed");
            }
            $dbman->drop_field($table, new xmldb_field('timereviewed'));
        }
        if (!$dbman->field_exists($table, new xmldb_field('timesubmitted'))) {
            $f = new xmldb_field('timesubmitted', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $dbman->add_field($table, $f);
        }

        // Add photo_path if missing
        $f = new xmldb_field('photo_path', XMLDB_TYPE_TEXT, null, null, null, null, null);
        if (!$dbman->field_exists($table, $f)) {
            $dbman->add_field($table, $f);
        }

        // Add aadhaar columns if missing
        $f = new xmldb_field('aadhaar_front_path', XMLDB_TYPE_TEXT, null, null, null, null, null);
        if (!$dbman->field_exists($table, $f)) {
            $dbman->add_field($table, $f);
        }
        $f = new xmldb_field('aadhaar_back_path', XMLDB_TYPE_TEXT, null, null, null, null, null);
        if (!$dbman->field_exists($table, $f)) {
            $dbman->add_field($table, $f);
        }

        // Create local_studentid_cards if not present
        $cardstable = new xmldb_table('local_studentid_cards');
        if (!$dbman->table_exists($cardstable)) {
            $cardstable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $cardstable->add_field('requestid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $cardstable->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $cardstable->add_field('file_path', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $cardstable->add_field('idcard_number', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
            $cardstable->add_field('generated_by', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $cardstable->add_field('timegenerated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $cardstable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $cardstable->add_index('requestid_idx', XMLDB_INDEX_NOTUNIQUE, ['requestid']);
            $cardstable->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
            $dbman->create_table($cardstable);
        }

        // Create local_studentid_logs if not present
        $logstable = new xmldb_table('local_studentid_logs');
        if (!$dbman->table_exists($logstable)) {
            $logstable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $logstable->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $logstable->add_field('action', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
            $logstable->add_field('context', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $logstable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $logstable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $logstable->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
            $logstable->add_index('action_idx', XMLDB_INDEX_NOTUNIQUE, ['action']);
            $dbman->create_table($logstable);
        }

        upgrade_plugin_savepoint(true, 2026052600, 'local', 'studentid');
    }

    if ($oldversion < 2026052700) {
        // Placeholder — role creation moved to 2026052800 with hardcoded strings.
        upgrade_plugin_savepoint(true, 2026052700, 'local', 'studentid');
    }

    if ($oldversion < 2026052800) {
        // Create the Student ID Verifier role.
        // Use hardcoded strings — get_string() is unreliable during upgrades
        // because the language cache may not yet include the new strings.
        if (!$DB->record_exists('role', ['shortname' => 'studentidverifier'])) {
            $roleid = create_role(
                'Student ID Verifier',
                'studentidverifier',
                'Can review, approve, reject and generate student ID card requests. No access to other Moodle features.',
                ''   // empty archetype — no inherited capabilities
            );

            // Allow assignment at system context only.
            set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);

            $syscontext = context_system::instance();
            assign_capability('local/studentid:verify',   CAP_ALLOW, $roleid, $syscontext->id);
            assign_capability('local/studentid:generate', CAP_ALLOW, $roleid, $syscontext->id);
        }

        upgrade_plugin_savepoint(true, 2026052800, 'local', 'studentid');
    }

    if ($oldversion < 2026052900) {
        // The original local_studentid_logs table did not have a 'context' column
        // (the original schema used 'comment', 'oldstatus', 'newstatus', 'requestid').
        // Our log_action() method inserts a 'context' column, causing
        // "Unknown column 'context'" / "Error writing to database" on every write.
        $logstable = new xmldb_table('local_studentid_logs');
        $f = new xmldb_field('context', XMLDB_TYPE_TEXT, null, null, null, null, null);
        if (!$dbman->field_exists($logstable, $f)) {
            $dbman->add_field($logstable, $f);
        }

        upgrade_plugin_savepoint(true, 2026052900, 'local', 'studentid');
    }

    if ($oldversion < 2026053000) {
        // Refresh capability assignments so the 'user' archetype for
        // local/studentid:submit takes effect (allows all authenticated users
        // to access the plugin at system context).
        update_capabilities('local_studentid');

        upgrade_plugin_savepoint(true, 2026053000, 'local', 'studentid');
    }

    if ($oldversion < 2026053100) {
        // No schema changes — version bump to ensure previous upgrade steps
        // (capabilities, logs context column) are applied on all environments.
        upgrade_plugin_savepoint(true, 2026053100, 'local', 'studentid');
    }

    if ($oldversion < 2026053200) {
        $table = new xmldb_table('local_studentid_requests');
        $f = new xmldb_field('abc_id_path', XMLDB_TYPE_TEXT, null, null, null, null, null);
        if (!$dbman->field_exists($table, $f)) {
            $dbman->add_field($table, $f);
        }
        upgrade_plugin_savepoint(true, 2026053200, 'local', 'studentid');
    }

    if ($oldversion < 2026053300) {
        // Add city / state overrides to requests.
        $table = new xmldb_table('local_studentid_requests');
        $f = new xmldb_field('city', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        if (!$dbman->field_exists($table, $f)) {
            $dbman->add_field($table, $f);
        }
        $f = new xmldb_field('state', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        if (!$dbman->field_exists($table, $f)) {
            $dbman->add_field($table, $f);
        }

        // Add download tracking to cards.
        $cardstable = new xmldb_table('local_studentid_cards');
        $f = new xmldb_field('download_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($cardstable, $f)) {
            $dbman->add_field($cardstable, $f);
        }
        $f = new xmldb_field('last_downloaded', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        if (!$dbman->field_exists($cardstable, $f)) {
            $dbman->add_field($cardstable, $f);
        }

        upgrade_plugin_savepoint(true, 2026053300, 'local', 'studentid');
    }

    if ($oldversion < 2026060100) {
        $table = new xmldb_table('local_studentid_requests');

        // Country override (the country field is now a form dropdown).
        $f = new xmldb_field('country', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        if (!$dbman->field_exists($table, $f)) {
            $dbman->add_field($table, $f);
        }

        // Dedicated review timestamp so "approved/rejected today" stats reflect
        // the actual decision time and are not skewed by later timemodified bumps.
        $f = new xmldb_field('timereviewed', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        if (!$dbman->field_exists($table, $f)) {
            $dbman->add_field($table, $f);
            // Backfill from timemodified for already approved/rejected requests so
            // existing same-day decisions still appear in today's counts. For a
            // rejected request timemodified is exactly the rejection time.
            $DB->execute(
                "UPDATE {local_studentid_requests}
                    SET timereviewed = timemodified
                  WHERE status IN (:approved, :rejected)
                    AND (timereviewed IS NULL OR timereviewed = 0)",
                ['approved' => 'approved', 'rejected' => 'rejected']
            );
        }

        upgrade_plugin_savepoint(true, 2026060100, 'local', 'studentid');
    }

    return true;
}
