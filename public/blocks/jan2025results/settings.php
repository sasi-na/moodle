<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    // Allowed Batches setting
    // We use a textarea because batch names may contain commas (e.g. "Jan, 2025"),
    // which makes comma-separated lists or standard multi-selects (which store as comma-separated) problematic.
    $settings->add(new admin_setting_configtextarea(
        'block_jan2025results/allowed_batches',
        get_string('allowed_batches', 'block_jan2025results'),
        get_string('allowed_batches_desc', 'block_jan2025results'),
        "Jan, 2025",
        PARAM_TEXT
    ));

    // Allowed Roles setting
    // Get all roles
    $allroles = role_get_names(null, ROLENAME_ORIGINAL);
    $roleoptions = [];
    foreach ($allroles as $role) {
        $roleoptions[$role->id] = $role->localname;
    }

    $settings->add(new admin_setting_configmultiselect(
        'block_jan2025results/allowed_roles',
        get_string('allowed_roles', 'block_jan2025results'),
        get_string('allowed_roles_desc', 'block_jan2025results'),
        [], // Default empty
        $roleoptions
    ));

    // Result URL setting
    $settings->add(new admin_setting_configtext(
        'block_jan2025results/result_url',
        get_string('result_url', 'block_jan2025results'),
        get_string('result_url_desc', 'block_jan2025results'),
        '/blocks/jan2025results/dashboard.php',
        PARAM_TEXT
    ));
}
