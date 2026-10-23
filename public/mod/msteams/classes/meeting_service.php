<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

namespace mod_msteams;

defined('MOODLE_INTERNAL') || die();

use core\oauth2\api as oauth2_api;
use core\oauth2\issuer;
use core_user;
use stdClass;

/**
 * Creates Microsoft Teams meetings using Microsoft Graph.
 *
 * @package    mod_msteams
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class meeting_service {
    /**
     * Create a Teams meeting and return the stored metadata.
     *
     * @param stdClass $data
     * @return stdClass
     */
    public static function create_meeting(stdClass $data): stdClass {
        if (empty($data->startdatetime) || empty($data->enddatetime) || $data->enddatetime <= $data->startdatetime) {
            throw new \moodle_exception('meetingcreationinvalidtimes', 'mod_msteams');
        }

        $issuer = self::get_configured_issuer();
        try {
            $client = oauth2_api::get_system_oauth_client($issuer);
        } catch (\moodle_exception $e) {
            throw new \moodle_exception('meetingcreationoauthclientfailed', 'mod_msteams', '', self::get_issuer_name($issuer));
        }

        if (!$client) {
            throw new \moodle_exception('meetingcreationoauthclientfailed', 'mod_msteams', '', self::get_issuer_name($issuer));
        }

        $rest = new graph_rest($client);
        $payload = [
            'subject' => trim((string)$data->name),
            'startDateTime' => self::format_datetime((int)$data->startdatetime),
            'endDateTime' => self::format_datetime((int)$data->enddatetime),
            'lobbyBypassSettings' => [
                'scope' => !empty($data->requirelobby) ? 'organizer' : 'everyone',
            ],
        ];

        $attendees = self::build_attendees($data);
        if (!empty($attendees)) {
            $payload['participants'] = ['attendees' => $attendees];
            if (!empty($data->presenteruserid)) {
                $payload['allowedPresenters'] = 'roleIsPresenter';
                $payload['allowedLobbyAdmitters'] = 'organizerAndCoOrganizersAndPresenters';
            } else if (!empty($data->coorganizeruserid)) {
                $payload['allowedLobbyAdmitters'] = 'organizerAndCoOrganizers';
            }
        }

        $response = $rest->call('create_online_meeting', [], json_encode($payload));
        if (empty($response->id) || empty($response->joinWebUrl)) {
            throw new \moodle_exception('meetingcreationinvalidresponse', 'mod_msteams');
        }

        return (object) [
            'meetingid' => (string)$response->id,
            'externalurl' => (string)$response->joinWebUrl,
        ];
    }

    /**
     * Get the configured Microsoft issuer.
     *
     * @return issuer
     */
    private static function get_configured_issuer(): issuer {
        $issuerid = (int)get_config('msteams', 'issuerid');
        if (empty($issuerid)) {
            throw new \moodle_exception('meetingcreationnotconfigured', 'mod_msteams');
        }

        $issuer = oauth2_api::get_issuer($issuerid);
        if (!$issuer->get('enabled') || $issuer->get('servicetype') !== 'microsoft' || !$issuer->is_configured()) {
            throw new \moodle_exception('meetingcreationinvalidissuer', 'mod_msteams');
        }

        if (!$issuer->is_system_account_connected()) {
            throw new \moodle_exception('meetingcreationnosystemaccount', 'mod_msteams', '', self::get_issuer_name($issuer));
        }

        return $issuer;
    }

    /**
     * Get a readable name for an OAuth issuer.
     *
     * @param issuer $issuer
     * @return string
     */
    private static function get_issuer_name(issuer $issuer): string {
        return trim((string)$issuer->get('name')) ?: (string)$issuer->get('id');
    }

    /**
     * Build the Graph attendees payload from selected Moodle users.
     *
     * @param stdClass $data
     * @return array
     */
    private static function build_attendees(stdClass $data): array {
        $attendees = [];
        $selectedusers = [];

        if (!empty($data->coorganizeruserid)) {
            $selectedusers[(int)$data->coorganizeruserid] = 'coorganizer';
        }
        if (!empty($data->presenteruserid) && empty($selectedusers[(int)$data->presenteruserid])) {
            $selectedusers[(int)$data->presenteruserid] = 'presenter';
        }

        foreach ($selectedusers as $userid => $role) {
            $user = core_user::get_user($userid, 'id,firstname,lastname,email,deleted,suspended', MUST_EXIST);
            if (!empty($user->deleted) || !empty($user->suspended)) {
                throw new \moodle_exception('meetingparticipantinvaliduser', 'mod_msteams');
            }

            $email = trim((string)$user->email);
            if ($email === '') {
                throw new \moodle_exception('meetingparticipantmissingemail', 'mod_msteams', '', fullname($user));
            }

            $attendees[] = [
                'upn' => $email,
                'role' => $role,
                'identity' => [
                    'user' => [
                        'displayName' => fullname($user),
                    ],
                ],
            ];
        }

        return $attendees;
    }

    /**
     * Format a Moodle timestamp as a Graph API UTC datetime.
     *
     * @param int $timestamp
     * @return string
     */
    private static function format_datetime(int $timestamp): string {
        $datetime = new \DateTimeImmutable('@' . $timestamp);
        return $datetime->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s\\Z');
    }
}