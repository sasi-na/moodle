<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AJAX endpoint: change my own email address / mobile number with a one-time code.
 * POST action=send|verify, field=email|phone, value, code, sesskey.
 *
 * @package   theme_baz
 */

define('AJAX_SCRIPT', true);
require(__DIR__ . '/../../config.php');

header('Content-Type: application/json; charset=utf-8');
require_login(null, false);
$out = ['ok' => false];
try {
    if (isguestuser() || !confirm_sesskey()) {
        throw new moodle_exception('invalidsesskey');
    }
    $action = required_param('action', PARAM_ALPHA);
    $field = required_param('field', PARAM_ALPHA);
    if (!isset(\theme_baz\local\contact::FIELDS[$field])) {
        throw new moodle_exception('invalidparameter');
    }
    if ($action === 'left') {
        $out = ['ok' => true, 'left' => \theme_baz\local\contact::changes_left($field)];
        echo json_encode($out);
        exit;
    }
    $value = required_param('value', PARAM_RAW_TRIMMED);
    if ($action === 'send') {
        $out = ['ok' => true] + \theme_baz\local\contact::send($field, $value);
    } else if ($action === 'verify') {
        \theme_baz\local\contact::verify($field, $value, required_param('code', PARAM_RAW_TRIMMED));
        $out = ['ok' => true, 'message' => get_string('ct_done_' . $field, 'theme_baz')];
    } else {
        throw new moodle_exception('invalidparameter');
    }
} catch (moodle_exception $e) {
    $out = ['ok' => false, 'error' => $e->getMessage()];
} catch (Throwable $e) {
    debugging('contact_otp: ' . $e->getMessage(), DEBUG_DEVELOPER);
    $out = ['ok' => false, 'error' => get_string('ct_error', 'theme_baz')];
}
echo json_encode($out);
