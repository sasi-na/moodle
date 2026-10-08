<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade steps for local_eventreg.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_eventreg_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026090701) {
        // A text format for the field label and its help text, so both can be authored
        // in an editor and carry paragraphs and line breaks. Before this the label was
        // plain text rendered through format_string(), which meant a multi-line label -
        // payment instructions, bank details - arrived on the learner's form as one
        // flattened run of text.
        //
        // DEFAULT 0 is FORMAT_MOODLE, and it is load-bearing rather than arbitrary.
        // Every existing row holds plain text with real newlines in it; FORMAT_MOODLE
        // converts those to line breaks, FORMAT_HTML does not. Defaulting to HTML would
        // leave every label written before today still rendering as one line, which is
        // the exact bug this change exists to fix. New content saved from the editor
        // carries its own format (HTML) and overwrites the default per row.
        $table = new xmldb_table('local_eventreg_fields');

        $field = new xmldb_field('labelformat', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'label');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('helptextformat', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'helptext');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026090701, 'local', 'eventreg');
    }

    return true;
}
