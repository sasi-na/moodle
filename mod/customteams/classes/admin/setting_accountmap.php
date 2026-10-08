<?php
namespace mod_customteams\admin;

defined('MOODLE_INTERNAL') || die();

use mod_customteams\account_map;

/**
 * Admin-editable view of the shared Teams account map.
 *
 * The map itself lives in {customteams_accounts}, because the picker queries it
 * per form render and it carries an active flag that keeps retired accounts
 * resolvable for meetings already booked against them. This setting is an
 * editing surface over that table, not a second copy of it: get_setting()
 * renders the table as JSON and write_setting() syncs the JSON back. Nothing is
 * stored in config_plugins, so the two cannot drift.
 *
 * The CLI importer remains the way to load a whole intake from the master
 * sheet; this is for the one-off changes -- a mailbox retired, a new elective,
 * a typo -- that should not need shell access to a production pod.
 */
class setting_accountmap extends \admin_setting_configtextarea {

    public function __construct() {
        parent::__construct(
            'customteams/accountmap',
            get_string('settings_accountmap', 'customteams'),
            get_string('settings_accountmap_desc', 'customteams'),
            '{}',
            PARAM_RAW,
            '80',
            '20'
        );
    }

    /**
     * Render the live map rather than a stored string.
     */
    public function get_setting() {
        return account_map::export_json();
    }

    /**
     * Validate and apply. Returns '' on success, or a message, in which case
     * Moodle keeps the submitted text on screen and saves nothing.
     */
    public function write_setting($data) {
        // admin_write_settings() calls every setting on the page, changed or
        // not, so saving the client secret would otherwise re-sync the whole
        // map and bump timemodified on every row. The sync is idempotent, but
        // there is no reason to run it.
        if (trim((string) $data) === trim((string) $this->get_setting())) {
            return '';
        }

        list($rows, $errors) = account_map::parse_json($data);

        if (!empty($errors)) {
            // Report every problem at once: fixing a 100-line map one error per
            // save is not a reasonable way to spend an afternoon.
            return implode(\html_writer::empty_tag('br'), $errors);
        }

        account_map::sync($rows, true);

        return '';
    }
}
