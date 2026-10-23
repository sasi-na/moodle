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
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace block_zoomonline;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/guzzlehttp/guzzle/src/Client.php');
require_once($CFG->libdir . '/filelib.php');

/**
 * Process Zoom meetings task.
 *
 * @package    block_zoomonline
 * @copyright  2024 Ciaran Mac Donncha
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_zoomonline_zoom_helper {

    /**
     * @var array|null Stores the cached access token and its timestamp.
     */
    private static $cachedtoken = null;

    /**
     * @var int The expiry time for the access token in seconds (set to 3500 seconds or ~58 minutes).
     */
    private static $tokenexpirytime = 3500; // Token is valid for 1 hour, subtract a buffer.

    /**
     * Create a new Zoom meeting.
     *
     * @param string $hostemail The host email for the meeting.
     * @param string $name The name of the meeting.
     * @return string|null The Zoom meeting ID.
     * @throws \moodle_exception
     */
    public static function block_zoomonline_create_meeting($hostemail, $name) {
        $accesskey = self::block_zoomonline_get_current_access_key();
        // Retrieve the timezone setting from Moodle configuration.
        $timezone = get_config('block_zoomonline', 'timezone');
        $postdata = [
                "topic" => $name,
                "type" => "3",
                "timezone" => $timezone ?: 'Europe/Dublin',  // Use the configured value or fallback to default.
                "schedule_for" => $hostemail,
                "agenda" => $name,
                "settings" => [
                        "use_pmi" => false,
                        "auto_recording" => "cloud",
                        "approval_type" => 2,  // Automatically approve registrants.
                        "join_before_host" => false,  // Ensure participants cannot join before the host.
                        "allow_multiple_devices" => true,  // Allow participants to join from multiple devices.
                        "disable_join_by_browser" => true,  // Disable "Join from Browser".
                ],
        ];

        $postfields = json_encode($postdata);

        do {
            $url = "users/$hostemail/meetings";
            $response = self::executezoomapirequest($url, 'POST', $accesskey, $postfields);

            // If token has expired, generate a new one and retry.
            if (isset($response['error']) && $response['error'] == '401') {
                $accesskey = self::generatenewtoken();
            } else {
                // Break loop if success or any other error occurs.
                break;
            }
        } while (true);

        if (isset($response['code']) && $response['code'] == 1001 &&
                strpos($response['message'], 'User does not exist') !== false) {
            return null;  // Gracefully return nothing.
        }

        if (isset($response['id'])) {
            return $response['id'];
        }

        debugging('Error in Zoom meeting creation response: ' . json_encode($response), DEBUG_DEVELOPER);
        throw new \moodle_exception('zoomapierror', 'block_zoomonline', '', null, 'Error in Zoom API response');
    }

    /**
     * Get the current Zoom access token, regenerating if expired.
     *
     * @return string The current access token.
     * @throws \moodle_exception
     */
    public static function block_zoomonline_get_current_access_key() {
        global $DB;
        // Check if we already have a cached token within the execution context.
        if (self::$cachedtoken !== null && (time() - self::$cachedtoken['timestamp']) < self::$tokenexpirytime) {
            return self::$cachedtoken['access_token'];
        }
        // Fetch the latest access token from the database.
        $sqlselect = "SELECT id, current, access_token FROM {block_zoomonline_token} ORDER BY id DESC LIMIT 1";
        $record = $DB->get_record_sql($sqlselect);
        // If the token is expired or not found, generate a new one.
        if (!$record || (time() - $record->current > self::$tokenexpirytime)) {
            $newtoken = self::generatenewtoken();
            self::$cachedtoken = ['access_token' => $newtoken, 'timestamp' => time()];
            return $newtoken;
        }
        // Cache the token for the duration of the request and return it.
        self::$cachedtoken = ['access_token' => $record->access_token, 'timestamp' => $record->current];
        return $record->access_token;
    }

    /**
     * Generate a new Zoom access token.
     *
     * @return string The new access token.
     * @throws \moodle_exception
     */
    public static function generatenewtoken() {
        global $DB;
        [$clientid, $clientsecret, $accountid] = self::getzoomcredentials();

        try {
            $lastfiveids = $DB->get_records_sql_menu(
                    "SELECT id, id FROM {block_zoomonline_token} ORDER BY id DESC LIMIT 5"
            );
            if (!empty($lastfiveids)) {
                [$whereclause, $params] = $DB->get_in_or_equal(array_keys($lastfiveids), SQL_PARAMS_QM, '', false);
                $DB->delete_records_select(
                        'block_zoomonline_token',
                        "id $whereclause",
                        $params
                );
            }
            $client = new \GuzzleHttp\Client(['base_uri' => 'https://zoom.us']);
            $response = $client->request('POST', '/oauth/token', [
                    'query' => [
                            'grant_type' => 'account_credentials',
                            'account_id' => $accountid,
                    ],
                    'headers' => [
                            'Authorization' => 'Basic ' . base64_encode("$clientid:$clientsecret"),
                            'Content-Type' => 'application/json',
                    ],
            ]);

            $contents = json_decode($response->getBody()->getContents());
            $accesstoken = $contents->access_token ?? null;
            $currenttime = time();

            if (!empty($accesstoken)) {
                // Store the token in the database.
                $record = new \stdClass();
                $record->access_token = $accesstoken;
                $record->current = $currenttime;
                $DB->insert_record('block_zoomonline_token', $record);
                // Update the cached token with the new token.
                self::$cachedtoken = ['access_token' => $accesstoken, 'timestamp' => $currenttime];
                return $accesstoken;
            } else {
                throw new \moodle_exception('invalidresponse', 'block_zoomonline', '', null,
                        'Invalid token response from Zoom API');
            }
        } catch (\Exception $e) {
            debugging('Zoom API token generation failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            throw new \moodle_exception('tokenerror', 'block_zoomonline', '', null, $e->getMessage());
        }
    }

    /**
     * Retrieves the Zoom API credentials from the plugin configuration.
     *
     * @return array An array containing the client ID, client secret, and account ID.
     * @throws \moodle_exception If any of the required credentials are missing.
     */
    private static function getzoomcredentials() {
        $clientid = get_config('block_zoomonline', 'client_id');
        $clientsecret = get_config('block_zoomonline', 'client_secret');
        $accountid = get_config('block_zoomonline', 'account_id');
        if (empty($clientid) || empty($clientsecret) || empty($accountid)) {
            self::handleapierror('Missing Zoom API credentials');
        }
        return [$clientid, $clientsecret, $accountid];
    }

    /**
     * Handles API errors by logging them and optionally throwing an exception.
     *
     * @param string $errormessage The error message to log and potentially display.
     * @param array $response The full API response, if available.
     * @param bool $throwexception Whether to throw a \moodle_exception or not.
     * @throws \moodle_exception If $throwexception is true.
     */
    public static function handleapierror($errormessage, $response = [], $throwexception = true) {
        // Log the error message to Moodle's debug log.
        debugging('Zoom API Error: ' . $errormessage, DEBUG_DEVELOPER);
        // Optionally, log more detailed information from the API response if available.
        if (!empty($response)) {
            debugging('Zoom API Response: ' . json_encode($response), DEBUG_DEVELOPER);
        }
        if (!$response) {
            debugging('Error in Zoom API response: null or invalid response.', DEBUG_DEVELOPER);
            throw new \moodle_exception('zoomapierror', 'block_zoomonline', '', null, 'Empty response from Zoom API.');
        }
        $friendlyerrormessage = get_string('zoomapierror', 'block_zoomonline', $errormessage);
        if ($throwexception) {
            throw new \moodle_exception('zoomapierror', 'block_zoomonline', '', null, $friendlyerrormessage);
        }
    }

    /**
     * Executes a Zoom API request using Moodle's curl class.
     *
     * @param string $url API URL (relative to the base Zoom API URL).
     * @param string $method HTTP method (GET, POST, etc.).
     * @param string $accesskey Access token.
     * @param mixed|null $postoptions POST data if applicable.
     * @return array The decoded response from the Zoom API.
     * @throws \moodle_exception
     */
    private static function executezoomapirequest($url, $method, $accesskey, $postoptions = null) {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php'); // Include Moodle's curl class.
        $curl = new \curl();
        $headers = [
                'Authorization: Bearer ' . $accesskey,
                'Content-Type: application/json',
        ];
        $options = [
                'CURLOPT_HTTPHEADER' => $headers, // Add headers.
                'CURLOPT_TIMEOUT' => 30,         // Set timeout.
                'CURLOPT_RETURNTRANSFER' => true, // Expect a response.
        ];
        if (strtoupper($method) === 'POST') {
            $response = $curl->post("https://api.zoom.us/v2/$url", $postoptions, $options);
        } else if (strtoupper($method) === 'GET') {
            $response = $curl->get("https://api.zoom.us/v2/$url", null, $options);
        } else if (strtoupper($method) === 'DELETE') {
            $curl->delete("https://api.zoom.us/v2/$url", null, $options);
            return;
        }
        // Decode the JSON response.
        $decodedresponse = json_decode($response, true);
        // If JSON decoding fails, throw an exception with the raw response.
        if ($decodedresponse === null) {
            throw new \moodle_exception('zoomapierror', 'block_zoomonline', '', null, 'Invalid JSON response: ' . $response);
        }
        return $decodedresponse;
    }

    /**
     * Check if a Zoom meeting exists.
     *
     * @param string $meetingid The Zoom meeting ID.
     * @param string $hostemail The host email for the meeting.
     * @return bool True if the meeting exists, false otherwise.
     */
    public static function block_zoomonline_check_meeting_exists($meetingid, $hostemail) {
        $accesskey = self::block_zoomonline_get_current_access_key();
        $response = self::executezoomapirequest("meetings/$meetingid", 'GET', $accesskey);
        if ($response && isset($response['id']) && isset($response['host_email'])) {
            return (trim($response['id']) === trim($meetingid) &&
                    strtolower(trim($response['host_email'])) === strtolower(trim($hostemail)));
        }
        return false;
    }

    /**
     * Get meeting data from Zoom API.
     *
     * @param string $meetingid
     * @return array|null Meeting data or null if error.
     */
    public static function block_zoomonline_get_meeting_data($meetingid) {
        $accesskey = self::block_zoomonline_get_current_access_key();
        $response = self::executezoomapirequest("report/meetings/$meetingid", 'GET', $accesskey);
        // Check if the response is already an array.
        if (is_array($response)) {
            // Return the array directly since it’s already decoded.
            return $response;
        }
        // Otherwise, decode the JSON string.
        return $response ? json_decode($response, true) : null;
    }

    /**
     * Retrieves the participants of a Zoom meeting.
     *
     * @param string $meetingid The ID of the Zoom meeting.
     * @param string $nextpagetoken The token for the next page of results, if any.
     * @return array The participant data from the Zoom API.
     */
    public static function block_zoomonline_get_participants($meetingid, $nextpagetoken = "") {
        $accesskey = self::block_zoomonline_get_current_access_key();
        // Construct the URL with the next_page_token if it exists.
        $url = "report/meetings/$meetingid/participants";
        if (!empty($nextpagetoken)) {
            $url .= "?next_page_token=" . urlencode($nextpagetoken); // Properly append next_page_token.
        }
        // Execute the Zoom API request.
        $response = self::executezoomapirequest($url, 'GET', $accesskey);
        return $response;
    }

    /**
     * Fetch Zoom meeting recordings.
     *
     * @param string $meetingid The Zoom meeting ID.
     * @return array The list of recording files.
     */
    public static function block_zoomonline_get_meeting_recordings($meetingid) {

        $accesskey = self::block_zoomonline_get_current_access_key();
        $response = self::executezoomapirequest("meetings/$meetingid/recordings", 'GET', $accesskey);
        return $response['recording_files'] ?? [];
    }

    /**
     * Delete Zoom meeting recording.
     *
     * @param string $meetingid The Zoom meeting ID.
     * @param string $videoid The recording video ID.
     */
    public static function block_zoomonline_delete_zoom_recording($meetingid, $videoid) {
        $accesskey = self::block_zoomonline_get_current_access_key();
        self::executezoomapirequest("meetings/$meetingid/recordings/$videoid?action=trash", 'DELETE', $accesskey);
    }

    /**
     * Retrieves the current Zoom meeting status.
     *
     * @param string $meetingid The ID of the Zoom meeting.
     * @return array|null The meeting status data or null if an error occurred.
     */
    public static function block_zoomonline_get_meeting_status($meetingid) {
        $accesskey = self::block_zoomonline_get_current_access_key();
        $response = self::executezoomapirequest("/meetings/$meetingid", 'GET', $accesskey);
        return $response ?? null;
    }

    /**
     * Retrieves metrics for a specific Zoom meeting.
     *
     * @param string $meetingid The ID of the Zoom meeting.
     * @return array|null The meeting metrics data or null if an error occurred.
     */
    public static function block_zoomonline_get_meeting_metrics($meetingid) {
        $accesskey = self::block_zoomonline_get_current_access_key();
        $response = self::executezoomapirequest("/metrics/meetings/$meetingid", 'GET', $accesskey);
        return $response ?? null;
    }

    /**
     * Downloads a Zoom video file from a given URL.
     *
     * @param string $url The URL of the video to download.
     * @param string $accesskey The Zoom access token for authentication.
     * @param string $videoid The unique identifier of the video.
     * @return string|null The path to the downloaded file, or null if the download failed.
     */
    public static function block_zoomonline_download_video($url, $accesskey, $videoid) {
        global $CFG;

        // Get the configured local storage folder from plugin settings
        $localfolder = get_config('block_zoomonline', 'localfolder');

        // If set, store the video inside moodledata; otherwise, use a temporary request directory
        if (!empty($localfolder)) {
            $storagepath = $CFG->dataroot . '/' . trim($localfolder, '/');
        } else {
            $storagepath = make_request_directory();
        }

        // Ensure the storage directory exists
        if (!file_exists($storagepath)) {
            mkdir($storagepath, 0777, true);
        }

        // Define the file path
        $tempfilepath = $storagepath . DIRECTORY_SEPARATOR . $videoid . ".mp4";

        // Initialize cURL for download
        $curl = new \curl();
        $curl->setopt([
                'CURLOPT_SSL_VERIFYPEER' => false,
                'CURLOPT_FOLLOWLOCATION' => true,
                'CURLOPT_REFERER' => $url,
        ]);

        // Set up parameters
        $params = ['access_token' => $accesskey];
        $curlurl = $url . '?' . http_build_query($params);

        // Download the video
        $curl->download_one($curlurl, null, ['filepath' => $tempfilepath]);

        // Verify if the file exists and is not empty
        if (file_exists($tempfilepath) && filesize($tempfilepath) > 0) {
            return $tempfilepath;
        } else {
            return null;
        }
    }

}
