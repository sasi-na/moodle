<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Upgrade code for local_ams
 *
 * @package    local_ams
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Define profile field visibility constants if not already defined
if (!defined('PROFILE_VISIBLE_ALL')) {
    define('PROFILE_VISIBLE_ALL', '2');
}
if (!defined('PROFILE_VISIBLE_PRIVATE')) {
    define('PROFILE_VISIBLE_PRIVATE', '1');
}
if (!defined('PROFILE_VISIBLE_NONE')) {
    define('PROFILE_VISIBLE_NONE', '0');
}

/**
 * Upgrade the local_ams plugin
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool result
 */
function xmldb_local_ams_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2025120300) {

        // Create a category for AMS custom fields if it doesn't exist
        $categoryname = 'AMS Student Information';
        $category = $DB->get_record('user_info_category', ['name' => $categoryname]);
        
        if (!$category) {
            $category = new stdClass();
            $category->name = $categoryname;
            $category->sortorder = $DB->count_records('user_info_category') + 1;
            $category->id = $DB->insert_record('user_info_category', $category);
        }

        // Define custom profile fields
        $fields = [
            [
                'shortname' => 'dob',
                'name' => 'Birth Date',
                'datatype' => 'datetime',
                'description' => 'Student date of birth',
                'required' => 0,
                'visible' => 2,
                'param1' => '1970',
                'param2' => '2030',
                'param3' => '1',
            ],
            [
                'shortname' => 'gender',
                'name' => 'Gender',
                'datatype' => 'menu',
                'description' => 'Student gender',
                'required' => 0,
                'visible' => 2,
                'param1' => "Male\nFemale\nOther\nPrefer not to say",
            ],
            [
                'shortname' => 'mobile',
                'name' => 'Mobile Number',
                'datatype' => 'text',
                'description' => 'Student mobile number',
                'required' => 0,
                'visible' => 2,
                'param1' => '30',
                'param2' => '15',
            ],
            [
                'shortname' => 'enrolmentnumber',
                'name' => 'Provisional Enrollment Number',
                'datatype' => 'text',
                'description' => 'Student enrollment number',
                'required' => 0,
                'visible' => 2,
                'param1' => '30',
                'param2' => '50',
            ],
            [
                'shortname' => 'mainprogram',
                'name' => 'Main Program',
                'datatype' => 'text',
                'description' => 'Main academic program',
                'required' => 0,
                'visible' => 2,
                'param1' => '50',
                'param2' => '100',
            ],
            [
                'shortname' => 'programregister',
                'name' => 'Program Registered For',
                'datatype' => 'text',
                'description' => 'Program registered for',
                'required' => 0,
                'visible' => 2,
                'param1' => '50',
                'param2' => '100',
            ],
            [
                'shortname' => 'elective',
                'name' => 'Elective',
                'datatype' => 'text',
                'description' => 'Student elective subject',
                'required' => 0,
                'visible' => 2,
                'param1' => '50',
                'param2' => '100',
            ],
            [
                'shortname' => 'usn',
                'name' => 'University Seat Number(USN)',
                'datatype' => 'text',
                'description' => 'University Seat Number',
                'required' => 0,
                'visible' => 2,
                'param1' => '30',
                'param2' => '50',
            ],
            [
                'shortname' => 'batch',
                'name' => 'Cohort/Batch',
                'datatype' => 'text',
                'description' => 'Student batch',
                'required' => 0,
                'visible' => 2,
                'param1' => '30',
                'param2' => '50',
            ],
            [
                'shortname' => 'fathername',
                'name' => 'Father Name',
                'datatype' => 'text',
                'description' => 'Father\'s name',
                'required' => 0,
                'visible' => 2,
                'param1' => '50',
                'param2' => '100',
            ],
            [
                'shortname' => 'mothername',
                'name' => 'Mother Name',
                'datatype' => 'text',
                'description' => 'Mother\'s name',
                'required' => 0,
                'visible' => 2,
                'param1' => '50',
                'param2' => '100',
            ],
            [
                'shortname' => 'nationality',
                'name' => 'Nationality',
                'datatype' => 'text',
                'description' => 'Student nationality',
                'required' => 0,
                'visible' => 2,
                'param1' => '30',
                'param2' => '50',
            ],
            [
                'shortname' => 'debid',
                'name' => 'DEB ID',
                'datatype' => 'text',
                'description' => 'DEB identification number',
                'required' => 0,
                'visible' => 2,
                'param1' => '30',
                'param2' => '50',
            ],
            [
                'shortname' => 'abcid',
                'name' => 'ABC ID',
                'datatype' => 'text',
                'description' => 'ABC identification number',
                'required' => 0,
                'visible' => 2,
                'param1' => '30',
                'param2' => '50',
            ],
        ];

        // Create fields if they don't exist
        $sortorder = 1;
        foreach ($fields as $fielddata) {
            // Check if field already exists
            if (!$DB->record_exists('user_info_field', ['shortname' => $fielddata['shortname']])) {
                $field = new stdClass();
                $field->shortname = $fielddata['shortname'];
                $field->name = $fielddata['name'];
                $field->datatype = $fielddata['datatype'];
                $field->description = $fielddata['description'];
                $field->descriptionformat = FORMAT_HTML;
                $field->categoryid = $category->id;
                $field->sortorder = $sortorder;
                $field->required = $fielddata['required'];
                $field->locked = 0;
                $field->visible = $fielddata['visible'];
                $field->forceunique = 0;
                $field->signup = 0;
                $field->defaultdata = '';
                $field->defaultdataformat = FORMAT_HTML;
                $field->param1 = $fielddata['param1'] ?? '';
                $field->param2 = $fielddata['param2'] ?? '';
                $field->param3 = $fielddata['param3'] ?? '';
                $field->param4 = '';
                $field->param5 = '';
                
                $DB->insert_record('user_info_field', $field);
            }
            $sortorder++;
        }

        upgrade_plugin_savepoint(true, 2025120300, 'local', 'ams');
    }

    return true;
}
