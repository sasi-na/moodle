<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Settings, loaded by local_geniai when this controller is selected.
 *
 * @package   geniaicontroller_gemini
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if (!isset($settings) || !($settings instanceof admin_settingpage)) {
    return;
}

$settings->add(new admin_setting_heading(
    "geniaicontroller_gemini/heading",
    get_string("pluginname", "geniaicontroller_gemini"),
    get_string("settings_desc", "geniaicontroller_gemini")
));

$settings->add(new admin_setting_configpasswordunmask(
    "geniaicontroller_gemini/apikey",
    get_string("apikey", "geniaicontroller_gemini"),
    get_string("apikey_desc", "geniaicontroller_gemini"),
    ""
));

$settings->add(new admin_setting_configtext(
    "geniaicontroller_gemini/model",
    get_string("model", "geniaicontroller_gemini"),
    get_string("model_desc", "geniaicontroller_gemini"),
    "gemini-3.8-flash",
    PARAM_TEXT
));
