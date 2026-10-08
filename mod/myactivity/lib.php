<?php
defined('MOODLE_INTERNAL') || die();
 
/**
* Add a new instance of the activity.
*/
function myactivity_add_instance($data, $mform) {
    global $DB;
    $data->timemodified = time();
    return $DB->insert_record('myactivity', $data);
}
 
/**
* Update an existing instance.
*/
function myactivity_update_instance($data, $mform) {
    global $DB;
    $data->id = $data->instance;
    $data->timemodified = time();
    return $DB->update_record('myactivity', $data);
}
 
/**
* Delete an instance.
*/
function myactivity_delete_instance($id) {
    global $DB;
    if (!$record = $DB->get_record('myactivity', ['id' => $id])) {
        return false;
    }
    $DB->delete_records('myactivity', ['id' => $id]);
    return true;
}
 
/**
* Supports features.
*/
function myactivity_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}
