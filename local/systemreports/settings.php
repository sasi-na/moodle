<?php

defined('MOODLE_INTERNAL') || die;

$ADMIN->add(
        'reportbuilder', // Root is the top menu.
        new admin_externalpage(
                'local_report_link', // Unique name.
                get_string('pluginname', 'local_systemreports'), // Human name.
                new moodle_url('/local/systemreports/datalist.php?page=course_progress') // Link.
                
        )
);

$settings = null;
