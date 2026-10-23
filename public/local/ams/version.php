<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_ams';
$plugin->version   = 20260604001;        // 2026-06-04: get_student_status resolves enrolment_no via enrolmentnumber profile field (was broken: queried unset user.idnumber)
$plugin->requires  = 2022112800;        // Moodle 4.1+
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.6.1';           // enrolment_no lookup fix


