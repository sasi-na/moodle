<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // Allowed Batches setting
    // We use a textarea because batch names may contain commas (e.g. "Jan, 2025"),
    // which makes comma-separated lists or standard multi-selects (which store as comma-separated) problematic.
    $settings->add(new admin_setting_configtextarea(
        'block_renewals/allowed_batches',
        get_string('allowed_batches', 'block_renewals'),
        get_string('allowed_batches_desc', 'block_renewals'),
        "July, 2025",
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
        'block_renewals/allowed_roles',
        get_string('allowed_roles', 'block_renewals'),
        get_string('allowed_roles_desc', 'block_renewals'),
        [], // Default empty
        $roleoptions
    ));

    // Payment URL setting
    $settings->add(new admin_setting_configtext(
        'block_renewals/payment_url',
        get_string('payment_url', 'block_renewals'),
        get_string('payment_url_desc', 'block_renewals'),
        'https://portal.onlinejain.com/login?next=/dashboard',
        PARAM_URL
    ));
}

