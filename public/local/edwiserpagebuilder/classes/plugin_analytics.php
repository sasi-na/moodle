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

/**
 * Plugin analytics class for tracking installation and uninstallation.
 *
 * @package   local_edwiserpagebuilder
 * @copyright (c) 2024 WisdmLabs (https://wisdmlabs.com/) <support@wisdmlabs.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edwiserpagebuilder;

defined('MOODLE_INTERNAL') || die();

use curl;

/**
 * Plugin analytics class.
 *
 * Handles sending analytics data to the analytics server when plugin is installed or uninstalled.
 * Uses non-blocking approach with short timeout to avoid slowing down install/uninstall process.
 */
class plugin_analytics {

    /**
     * Analytics server URL for activation data.
     *
     * @var string
     */
    private $activation_url = 'https://edwiser.org/wp-json/analytics/v1/collect';

    /**
     * Analytics server URL for deactivation data.
     *
     * @var string
     */
    private $deactivation_url = 'https://edwiser.org/wp-json/analytics/v1/deactivate';

    /**
     * Plugin name.
     *
     * @var string
     */
    private $plugin_name = 'Edwiser Page Builder Free';

    /**
     * Plugin version.
     *
     * @var string
     */
    private $plugin_version = '';

    /**
     * Constructor.
     */
    public function __construct() {
        $this->plugin_version = $this->get_plugin_version();
    }

    /**
     * Send activation data to analytics server.
     *
     * This method collects user and site information and sends it to the analytics server.
     * Uses non-blocking approach with short timeout to avoid slowing down installation.
     *
     * @return void
     */
    public function send_activation_data() {
        try {
            $data = $this->prepare_activation_payload();
            if (!empty($data)) {
                $this->send_to_api($this->activation_url, $data);
            }
        } catch (\Exception $e) {
            // Log error but don't throw - don't break installation process.
            error_log('[Plugin Analytics] Activation data send failed: ' . $e->getMessage());
        }
    }

    /**
     * Send deactivation data to analytics server.
     *
     * This method collects user and site information and sends deactivation feedback
     * to the analytics server. Uses non-blocking approach with short timeout.
     *
     * @param string $reason Deactivation reason (optional).
     * @return void
     */
    public function send_deactivation_data($reason = 'Plugin uninstalled') {
        try {
            $data = $this->prepare_deactivation_payload($reason);
            if (!empty($data)) {
                $this->send_to_api($this->deactivation_url, $data);
            }
        } catch (\Exception $e) {
            // Log error but don't throw - don't break uninstallation process.
            error_log('[Plugin Analytics] Deactivation data send failed: ' . $e->getMessage());
        }
    }

    /**
     * Prepare activation payload data.
     *
     * Collects all necessary data for activation analytics.
     *
     * @return array Activation data payload.
     */
    private function prepare_activation_payload() {
        global $USER, $CFG, $SITE;

        $userdata = $this->get_user_data();
        $sitedata = $this->get_site_data();

        $data = [
            'email' => $sitedata['site_name']."@gmail.com",
            'first_name' => $userdata['first_name'],
            'last_name' => $userdata['last_name'],
            'website' => $sitedata['website'],
            'site_name' => $sitedata['site_name'],
            'wp_version' => '0',
            'php_version' => $sitedata['php_version'],
            'plugin_name' => $this->plugin_name,
            'plugin_version' => $this->plugin_version,
            'ip' => $this->get_user_ip(),
        ];

        return $data;
    }

    /**
     * Prepare deactivation payload data.
     *
     * Collects all necessary data for deactivation analytics.
     *
     * @param string $reason Deactivation reason.
     * @return array Deactivation data payload.
     */
    private function prepare_deactivation_payload($reason) {
        global $USER, $CFG;

        $userdata = $this->get_user_data();
        $sitedata = $this->get_site_data();

        $data = [
            'email' => $userdata['email'],
            'website' => $sitedata['website'],
            'plugin_name' => $this->plugin_name,
            'ip' => $this->get_user_ip(),
            'reason' => $reason,
        ];

        return $data;
    }

    /**
     * Get user data.
     *
     * Retrieves current user information. Falls back to admin user if no user is logged in.
     *
     * @return array User data (email, first_name, last_name).
     */
    private function get_user_data() {
        global $USER, $DB;

        $userdata = [
            'email' => '',
            'first_name' => '',
            'last_name' => '',
        ];

        // Try to get current user if logged in.
        if (isloggedin() && !isguestuser()) {
            $userdata['email'] = $USER->email ?? '';
            $userdata['first_name'] = $USER->firstname ?? '';
            $userdata['last_name'] = $USER->lastname ?? '';
        } else {
            // Fallback to site admin user.
            $admin = get_admin();
            if ($admin) {
                $userdata['email'] = $admin->email ?? '';
                $userdata['first_name'] = $admin->firstname ?? '';
                $userdata['last_name'] = $admin->lastname ?? '';
            }
        }

        return $userdata;
    }

    /**
     * Get site data.
     *
     * Retrieves site information including URL, name, and system versions.
     *
     * @return array Site data (website, site_name, moodle_version, php_version).
     */
    private function get_site_data() {
        global $CFG, $SITE;

        $sitedata = [
            'website' => $CFG->wwwroot ?? '',
            'site_name' => $SITE->fullname ?? $CFG->fullname ?? 'Moodle Site',
            'moodle_version' => $CFG->version ?? '',
            'php_version' => phpversion(),
        ];

        return $sitedata;
    }

    /**
     * Get user IP address.
     *
     * Detects user IP address from various HTTP headers.
     *
     * @return string IP address.
     */
    private function get_user_ip() {
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Handle comma-separated list of IPs.
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } else if (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
    }

    /**
     * Get plugin version from version.php file.
     *
     * @return string Plugin version.
     */
    private function get_plugin_version() {
        $plugin = new \stdClass();
        $versionfile = __DIR__ . '/../version.php';
        if (file_exists($versionfile)) {
            include($versionfile);
            if (isset($plugin->release)) {
                return $plugin->release;
            } else if (isset($plugin->version)) {
                return $plugin->version;
            }
        }
        return '0.0.0';
    }

    /**
     * Send data to analytics API.
     *
     * Uses curl with short timeout to avoid blocking install/uninstall process.
     * Fire-and-forget approach - doesn't wait for response.
     *
     * @param string $url API endpoint URL.
     * @param array $data Data to send.
     * @return void
     */
    private function send_to_api($url, $data) {
        $jsondata = json_encode($data);
        $curl = new curl();

        // Set short timeout to avoid blocking install/uninstall.
        // Fire and forget - don't wait for response.
        $curl->setopt([
            'CURLOPT_URL' => $url,
            'CURLOPT_CUSTOMREQUEST' => 'POST',
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_TIMEOUT' => 3, // 3 seconds max.
            'CURLOPT_CONNECTTIMEOUT' => 2, // 2 seconds to connect.
            'CURLOPT_HTTPHEADER' => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsondata)
            ]
        ]);

        // Execute post - fire and forget, don't check result.
        $curl->post($url, $jsondata);

        // Don't check result - if it fails, it fails silently.
        // We don't want to break install/uninstall process.
    }
}

