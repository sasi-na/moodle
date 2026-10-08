<?php
defined('MOODLE_INTERNAL') || die();

use mod_customteams\account_map;
use mod_customteams\graph_helper;

/**
 * Feature support.
 */
function customteams_supports($feature) {
    switch ($feature) {
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;

        // The module has no intro column and the form has no intro editor.
        // Core defaults this to true when a module stays silent, and
        // update_moduleinfo()/get_moduleinfo_data() then read $data->intro and
        // $moduleinfo->introeditor unguarded (course/modlib.php:655, :803),
        // which errors as soon as an instance is edited. Say no explicitly.
        case FEATURE_MOD_INTRO:
            return false;

        default:
            return null;
    }
}

/**
 * Resolve the account that will own the meeting.
 *
 * The picker in mod_form supplies this. The two fallbacks exist so that an
 * unmapped course or a part-configured site fails with something a lecturer can
 * act on, rather than a blank exception:
 *   - the per-user 'microsoft_email' profile field, if the admin has opted in
 *   - otherwise an explicit error naming what is missing
 *
 * @param object $data submitted module data
 * @return string UPN
 */
function customteams_resolve_organizer($data) {
    global $USER;

    if (!empty($data->organizer)) {
        return $data->organizer;
    }

    if (get_config('customteams', 'allowprofilefallback')) {
        $email = customteams_get_microsoft_email($USER->id, false);
        if (!empty($email)) {
            return $email;
        }
    }

    throw new moodle_exception('errornoorganizer', 'customteams');
}

/**
 * Get Microsoft email from the user's profile field.
 *
 * Retained as an opt-in fallback: the account picker is now the primary path,
 * which removes the per-teacher data entry this used to require.
 *
 * @param int $userid
 * @param bool $throw whether a missing value is fatal
 * @return string|null
 */
function customteams_get_microsoft_email($userid, $throw = true) {
    global $DB;

    $sql = "SELECT d.data
              FROM {user_info_data} d
              JOIN {user_info_field} f ON f.id = d.fieldid
             WHERE f.shortname = 'microsoft_email'
               AND d.userid = ?";

    $email = $DB->get_field_sql($sql, [$userid]);

    if (empty($email)) {
        if ($throw) {
            throw new moodle_exception('errornomicrosoftemail', 'customteams');
        }
        return null;
    }

    return $email;
}

/**
 * CREATE
 */
function customteams_add_instance($data) {
    global $DB;

    $organizer = customteams_resolve_organizer($data);

    $meeting = graph_helper::create(
        $organizer,
        $data->name,
        $data->timestart,
        $data->timeend
    );

    $data->organizer = $organizer;
    $data->externalurl = $meeting['joinurl'];
    $data->meetingid = $meeting['meetingid'];
    $data->timecreated = time();

    $id = $DB->insert_record('customteams', $data);
    $data->id = $id;

    customteams_create_calendar_event($data);

    return $id;
}

/**
 * UPDATE (DELETE + RECREATE — REQUIRED)
 *
 * Note this invalidates the previous join URL, so anyone holding the old link
 * must reopen the activity.
 */
function customteams_update_instance($data) {
    global $DB, $CFG;

    require_once($CFG->dirroot . '/calendar/lib.php');

    $existing = $DB->get_record('customteams', ['id' => $data->instance], '*', MUST_EXIST);

    $organizer = customteams_resolve_organizer($data);

    // DELETE OLD TEAMS EVENT, from whichever account actually owns it.
    if (!empty($existing->meetingid)) {
        try {
            $owner = !empty($existing->organizer) ? $existing->organizer : $organizer;
            graph_helper::delete($owner, $existing->meetingid);
        } catch (Exception $e) {
            // Ignore failure: a stale event is better than blocking the edit.
            debugging('mod_customteams: could not delete old event: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    // CREATE NEW EVENT
    $meeting = graph_helper::create(
        $organizer,
        $data->name,
        $data->timestart,
        $data->timeend
    );

    $data->id = $data->instance;
    $data->organizer = $organizer;
    $data->externalurl = $meeting['joinurl'];
    $data->meetingid = $meeting['meetingid'];

    // UPDATE MOODLE CALENDAR
    $events = $DB->get_records('event', [
        'instance' => $data->id,
        'modulename' => 'customteams'
    ]);

    foreach ($events as $event) {
        $cal = \calendar_event::load($event);

        $cal->update((object)[
            'name' => $data->name,
            'description' => $data->externalurl,
            'timestart' => $data->timestart,
            'timeduration' => $data->timeend - $data->timestart
        ]);
    }

    $data->timemodified = time();

    return $DB->update_record('customteams', $data);
}

/**
 * DELETE
 */
function customteams_delete_instance($id) {
    global $DB, $CFG;

    require_once($CFG->dirroot . '/calendar/lib.php');

    if (!$data = $DB->get_record('customteams', ['id' => $id])) {
        return false;
    }

    // DELETE TEAMS EVENT
    if (!empty($data->meetingid) && !empty($data->organizer)) {
        try {
            graph_helper::delete($data->organizer, $data->meetingid);
        } catch (Exception $e) {
            // ignore
            debugging('mod_customteams: could not delete event: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    // DELETE MOODLE CALENDAR EVENTS
    $events = $DB->get_records('event', [
        'instance' => $data->id,
        'modulename' => 'customteams'
    ]);

    foreach ($events as $event) {
        $cal = \calendar_event::load($event);
        $cal->delete();
    }

    return $DB->delete_records('customteams', ['id' => $id]);
}

/**
 * CREATE MOODLE CALENDAR EVENT
 */
function customteams_create_calendar_event($data) {

    $event = new stdClass();

    $event->name = $data->name;

    // Clean description
    $event->description = 'Open activity to join the meeting';

    $event->courseid = $data->course;
    $event->groupid = 0;
    $event->userid = 0;

    // IMPORTANT
    $event->modulename = 'customteams';
    $event->instance = $data->id;

    $event->eventtype = 'course';

    $event->timestart = $data->timestart;
    $event->timeduration = $data->timeend - $data->timestart;

    $event->visible = 1;

    \calendar_event::create($event);
}
