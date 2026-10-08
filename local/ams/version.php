<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_ams';
$plugin->version   = 20260730002;        // Version: July 30, 2026 - narrow update path; mainprogram -> program_registered_for
$plugin->requires  = 2022112800;        // Moodle 4.1+
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.13.0';          // update mode only touches email/username, mobile, gender, dob, parents, nationality


