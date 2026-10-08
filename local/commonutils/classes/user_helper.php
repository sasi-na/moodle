<?php

namespace local_commonutils;
defined('MOODLE_INTERNAL') || die();
class user_helper {

    /**
     * Get a single custom profile field value
     */
    public static function get_single_profile_field($userid, $shortname) {
        global $DB;

        return $DB->get_field_sql("
            SELECT uid.data
            FROM {user_info_data} uid
            JOIN {user_info_field} uif ON uif.id = uid.fieldid
            WHERE uid.userid = :userid
              AND uif.shortname = :shortname
        ", [
            'userid' => $userid,
            'shortname' => $shortname
        ]);
    }

    /**
     * Get multiple profile fields at once (OPTIMIZED)
     */
    public static function get_multi_profile_fields($userid, array $shortnames) {
        global $DB;

        list($insql, $params) = $DB->get_in_or_equal($shortnames, SQL_PARAMS_NAMED);

        $params['userid'] = $userid;

        $records = $DB->get_records_sql("
            SELECT uif.shortname, uid.data
            FROM {user_info_data} uid
            JOIN {user_info_field} uif ON uif.id = uid.fieldid
            WHERE uid.userid = :userid
              AND uif.shortname $insql
        ", $params);

        $result = [];
        foreach ($shortnames as $name) {
            $result[$name] = isset($records[$name]) ? $records[$name]->data : null;
        }

        return $result;
    }

    /**
     * Get a custom profile field's static definition text (its description) by shortname.
     *
     * Returns an object with ->description and ->descriptionformat, or false if the
     * field does not exist. This is the field-level help text shared by all users
     * (user_info_field.description), NOT a per-user value.
     */
    /**
     * A user's value for a profile field, together with its format.
     *
     * get_single_profile_field() returns only the data, which is enough for a text
     * field but not for a textarea: rendering one without its dataformat mangles the
     * markup. Returns null when the field or the user's value does not exist.
     *
     * @param int $userid
     * @param string $shortname
     * @return \stdClass|null Object with ->data and ->dataformat.
     */
    public static function get_profile_field_with_format($userid, $shortname) {
        global $DB;

        $record = $DB->get_record_sql("
            SELECT uid.data, uid.dataformat
            FROM {user_info_data} uid
            JOIN {user_info_field} uif ON uif.id = uid.fieldid
            WHERE uid.userid = :userid
              AND uif.shortname = :shortname
        ", [
            'userid' => $userid,
            'shortname' => $shortname
        ]);

        return $record ?: null;
    }

    public static function get_field_description($shortname) {
        global $DB;

        return $DB->get_record_sql("
            SELECT description, descriptionformat
            FROM {user_info_field}
            WHERE shortname = :shortname
        ", ['shortname' => $shortname]);
    }
}