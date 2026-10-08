<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Creates the Student ID Verifier role on fresh plugin install.
 */
function xmldb_local_studentid_install() {
    global $DB;

    if ($DB->record_exists('role', ['shortname' => 'studentidverifier'])) {
        return true;
    }

    $roleid = create_role(
        'Student ID Verifier',
        'studentidverifier',
        'Can review, approve, reject and generate student ID card requests. No access to other Moodle features.',
        ''  // empty archetype — no inherited capabilities
    );

    // Role can only be assigned at system level.
    set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);

    // Register this plugin's capabilities before assigning them.
    //
    // Moodle runs db/install.php BEFORE it processes db/access.php, so on a
    // fresh site the capabilities below do not exist yet and assign_capability()
    // fails hard:
    //
    //   Capability 'local/studentid:verify' was not found!
    //   This has to be fixed in code.
    //
    // On an existing site the capabilities are already registered, so this was
    // invisible -- it only breaks installing the plugin from scratch, which is
    // what standing up a new tenant does.
    update_capabilities('local_studentid');

    $syscontext = context_system::instance();
    assign_capability('local/studentid:verify',   CAP_ALLOW, $roleid, $syscontext->id);
    assign_capability('local/studentid:generate', CAP_ALLOW, $roleid, $syscontext->id);

    return true;
}
