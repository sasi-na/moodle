<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_block_livesessionstats_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2026052601) {
        // Add block as a sticky site-level block on all course view pages.
        // This makes it appear automatically in every course without manual setup.
        $systemcontext = context_system::instance();

        $exists = $DB->record_exists('block_instances', [
            'blockname'        => 'livesessionstats',
            'parentcontextid'  => $systemcontext->id,
            'pagetypepattern'  => 'course-view-*',
        ]);

        if (!$exists) {
            $instance = new stdClass();
            $instance->blockname        = 'livesessionstats';
            $instance->parentcontextid  = $systemcontext->id;
            $instance->showinsubcontexts = 1;
            $instance->pagetypepattern  = 'course-view-*';
            $instance->subpagepattern   = null;
            $instance->defaultregion    = 'side-pre';
            $instance->defaultweight    = 5;
            $instance->configdata       = '';
            $instance->timecreated      = time();
            $instance->timemodified     = time();
            $DB->insert_record('block_instances', $instance);
        }

        upgrade_plugin_savepoint(true, 2026052601, 'block', 'livesessionstats');
    }

    return true;
}
