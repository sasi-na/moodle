<?php
// This file is part of Edwiser Page Builder Moodle Local Plugin.
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
 * Edwiser page builder license controller is necessary to activate, deactivate or renew license.
 *
 * @package   local_edwiserpagebuilder
 * @copyright Copyright (c) 2024 WisdmLabs. (http://www.wisdmlabs.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Edwiser page builder license controller class
 */
class edwiserpagebuilder_license_controller {

    /**
     *
     * @var string Short Name for plugin.
     */
    private $pluginshortname = '';

    /**
     *
     * @var string Slug to be used in url and functions name
     */
    private $pluginslug = '';

    /**
     *
     * @var string stores the current plugin version
     */
    private $pluginversion = '';

    /**
     *
     * @var string Handles the plugin name
     */
    private $pluginname = '';

    /**
     *
     * @var string  Stores the URL of store. Retrieves updates from
     *              this store
     */
    private $storeurl = '';

    /**
     *
     * @var string  Name of the Author
     */
    private $authorname = '';

    /**
     * Response data
     * @var array
     */
    public static $responsedata;

    /**
     * Developer Note: This variable is used everywhere to check license information and verify the data.
     * Change the Name of this variable in this file wherever it appears and also remove this comment
     * After you are done with adding Licensing
     * @var array
     */
    public $wdmedwiserpagebuilderdata = array (
        // Plugins short name appears on the License Menu Page.
        'plugin_short_name' => 'Edwiser Page Builder For Moodle',
        // Data in db. License is checked using two options viz edd_<slug>_license_key and edd_<slug>_license_status.
        'plugin_slug' => 'edwiser-page-builder-for-moodle', // This slug is used to store the
        // This should be similar to Version tag mentioned in Plugin headers.
        'plugin_version' => '1.0.0', // Current Version of the plugin.
        // Under this Name product should be created on WisdmLabs Site.
        'plugin_name' => 'Edwiser Page Builder For Moodle',
        // Url where program pings to check if update is available and license validity.
        'store_url' => 'https://edwiser.org/check-update',
        // Author Name.
        'author_name' => 'WisdmLabs',
    );

    /**
     * Constructor
     */
    public function __construct() {
        $this->authorname       = $this->wdmedwiserpagebuilderdata[ 'author_name' ];
        $this->pluginname       = $this->wdmedwiserpagebuilderdata[ 'plugin_name' ];
        $this->pluginshortname = $this->wdmedwiserpagebuilderdata[ 'plugin_short_name' ];
        $this->pluginslug       = $this->wdmedwiserpagebuilderdata[ 'plugin_slug' ];
        $this->pluginversion    = $this->wdmedwiserpagebuilderdata[ 'plugin_version' ];
        $this->storeurl         = $this->wdmedwiserpagebuilderdata[ 'store_url' ];
    }

    /**
     * The function parses the response come from the edwiser.org
     * on activation and determines is status of the license key.
     *
     *
     * @param  object $licensedata the response retune by the activation request.
     * @return String               returns the license key status
     */
    public function status_update($licensedata) {
        global $DB;

        $status = "";
        if ((empty($licensedata->success)) && isset($licensedata->error) && ($licensedata->error == "expired")) {
            $status = 'expired';
        } else if ($licensedata->license == 'invalid' && isset($licensedata->error) && $licensedata->error == "disabled") {
            $status = 'disabled';
        } else if ($licensedata->license == 'invalid' && isset($licensedata->error)
            && $licensedata->error == "no_activations_left") {
            $status = 'no_activations_left';
        } else if ($licensedata->license == 'failed') {
            $status = 'failed';
             $GLOBALS[ 'wdm_license_activation_failed' ] = true;
        } else {
            $status = $licensedata->license;
        }

        // Delete previous license status.
        try {
            $DB->delete_records_select(
                'config_plugins',
                'name = :name',
                array('name' => 'edd_' . $this->pluginslug . '_license_status')
            );
        } catch (dml_exception $e) {
            $rcd = 0;
            // Keep catch empty if no record found.
        }

        $dataobject = new stdClass();
        $dataobject->plugin = 'local_edwiserpagebuilder';
        $dataobject->name = 'edd_' . $this->pluginslug . '_license_status';
        $dataobject->value = $status;

        $DB->insert_record('config_plugins', $dataobject);

        return $status;
    }

    /**
     * Check if there is no license data in response
     * @param  object $licensedata         License data
     * @param  int    $currentresponsecode Current response code from Edwiser site
     * @param  int    $validresponsecode   Valid response code
     * @return bool                        True if license is valid
     */
    public function check_if_no_data($licensedata, $currentresponsecode, $validresponsecode) {
        global $DB;

        if ($licensedata == null ||
            !in_array($currentresponsecode, $validresponsecode)
        ) {
            // @codingStandardsIgnoreLine
            $GLOBALS[ 'wdm_server_null_response' ] = true;

            // Delete previous record.
            try {
                $DB->delete_records_select(
                    'config_plugins',
                    'name = :name',
                    array('name' => 'wdm_' . $this->pluginslug . '_license_trans')
                );
            } catch (dml_exception $e) {
                $rcd = 0;
                // Keep catch empty if no record found.
            }

            // Insert new license trans.
            $dataobject = new stdClass();
            $dataobject->plugin = 'local_edwiserpagebuilder';
            $dataobject->name = 'wdm_' . $this->pluginslug . '_license_trans';
            $dataobject->value = serialize(array('server_did_not_respond', time() + (60 * 60 * 24)));
            $DB->insert_record('config_plugins', $dataobject);
            return false;
        }
        return true;
    }

    /**
     * Activate theme license
     */
    public function activate_license() {
        global $DB, $CFG;
        $licensekey = trim($_POST['edd_' . $this->pluginslug . '_license_key']);

        if ($licensekey) {
            // Delete existing license key record if exists
            try {
                $DB->delete_records_select(
                    'config_plugins',
                    'name = :name',
                    array('name' => 'edd_' . $this->pluginslug . '_license_key')
                );
            } catch (dml_exception $e) {
                // Keep catch empty if no record found
            }

            // Store license key in database
            $dataobject = new stdClass();
            $dataobject->plugin = 'local_edwiserpagebuilder';
            $dataobject->name = 'edd_' . $this->pluginslug . '_license_key';
            $dataobject->value = $licensekey;
            $DB->insert_record('config_plugins', $dataobject);

            // Make API call to validate license
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => $this->storeurl,
                CURLOPT_POST => 1,
                CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'].' - '.$CFG->wwwroot,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_POSTFIELDS => array(
                    'edd_action' => 'activate_license',
                    'license' => $licensekey,
                    'item_name' => urlencode($this->pluginname),
                    'current_version' => $this->pluginversion,
                    'url' => urlencode($CFG->wwwroot),
                )
            ));

            // Process response and update status
            $resp = curl_exec($curl);
            $currentresponsecode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            $licensedata = json_decode($resp);
            $validresponsecode = array('200', '301');

            $isdataavailable = $this->check_if_no_data($licensedata, $currentresponsecode, $validresponsecode);

            if ($isdataavailable) {
                $licensestatus = $this->status_update($licensedata);
                $this->set_transient_on_activation($licensestatus);
            }
        }
    }

    /**
     * Set transient on activation
     * @param string $licensestatus License status
     */
    public function set_transient_on_activation($licensestatus) {
        global $DB;

        $transexpired = false;

        // Check license trans.
        $transvar = $DB->get_field_select(
            'config_plugins',
            'value',
            'name = :name',
            array('name' => 'wdm_' . $this->pluginslug . '_license_trans'),
            IGNORE_MISSING
        );

        if ($transvar) {
            $transvar = unserialize($transvar);

            if (is_array($transvar) && time() > $transvar[1] && $transvar[1] > 0) {
                $transexpired = true;

                // Delete previous record.
                try {
                    $DB->delete_records_select(
                        'config_plugins',
                        'name = :name',
                        array('name' => 'wdm_' . $this->pluginslug . '_license_trans')
                    );
                } catch (dml_exception $e) {
                    $rcd = 0;
                    // Keep catch empty if no record found.
                }
            }
        } else {
            $transexpired = true;
        }

        if ($transexpired == false) {
            // Delete previous license trans.
            try {
                $DB->delete_records_select(
                    'config_plugins',
                    'name = :name',
                    array('name' => 'wdm_' . $this->pluginslug . '_license_trans')
                );
            } catch (dml_exception $e) {
                $rcd = 0;
                // Keep catch empty if no record found.
            }

            if (! empty($licensestatus)) {
                if ($licensestatus == 'valid') {
                    $time = time() + 60 * 60 * 24 * 7;
                } else {
                    $time = time() + 60 * 60 * 24;
                }

                // Insert new license trans.
                $dataobject = new stdClass();
                $dataobject->plugin = 'local_edwiserpagebuilder';
                $dataobject->name = 'wdm_' . $this->pluginslug . '_license_trans';
                $dataobject->value = serialize(array($licensestatus, $time));
                $DB->insert_record('config_plugins', $dataobject);
            }
        }
    }

    /**
     * Deactivate theme license
     */
    public function deactivate_license() {
        global $DB, $CFG;

        $wpeplicensekey = $DB->get_field_select(
            'config_plugins',
            'value',
            'name = :name',
            array('name' => 'edd_' . $this->pluginslug . '_license_key'),
            IGNORE_MISSING
        );

        if (!empty($wpeplicensekey)) {
            // Make API call to deactivate license
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => $this->storeurl,
                CURLOPT_POST => 1,
                CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'].' - '.$CFG->wwwroot,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_POSTFIELDS => array(
                    'edd_action' => 'deactivate_license',
                    'license' => $wpeplicensekey,
                    'item_name' => urlencode($this->pluginname),
                    'current_version' => $this->pluginversion,
                    'url' => urlencode($CFG->wwwroot),
                )
            ));

            // Process response and update status
            $resp = curl_exec($curl);
            $currentresponsecode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            $licensedata = json_decode($resp);
            $validresponsecode = array('200', '301');

            $isdataavailable = $this->check_if_no_data($licensedata, $currentresponsecode, $validresponsecode);

            if ($isdataavailable) {
                if ($licensedata->license == 'deactivated' || $licensedata->license == 'failed') {
                    // Delete previous record
                    try {
                        $DB->delete_records_select(
                            'config_plugins',
                            'name = :name',
                            array('name' => 'edd_' . $this->pluginslug . '_license_status')
                        );
                    } catch (dml_exception $e) {
                        // Keep catch empty if no record found
                    }

                    // Update license status in database
                    $dataobject = new stdClass();
                    $dataobject->plugin = 'local_edwiserpagebuilder';
                    $dataobject->name = 'edd_' . $this->pluginslug . '_license_status';
                    $dataobject->value = 'deactivated';
                    $DB->insert_record('config_plugins', $dataobject);

                    // Update transient data
                    try {
                        $DB->delete_records_select(
                            'config_plugins',
                            'name = :name',
                            array('name' => 'wdm_' . $this->pluginslug . '_license_trans')
                        );
                    } catch (dml_exception $e) {
                        // Keep catch empty if no record found
                    }

                    $dataobject = new stdClass();
                    $dataobject->plugin = 'local_edwiserpagebuilder';
                    $dataobject->name = 'wdm_' . $this->pluginslug . '_license_trans';
                    $dataobject->value = serialize(array('deactivated', 0));
                    $DB->insert_record('config_plugins', $dataobject);
                }
            }
        }
    }

    /**
     * Validate and add data to database
     */
    public function add_data() {
        if (is_siteadmin()) {
            // Check if submission came from license page
            if (!isset($_POST['onRapidGraderLicensePage']) || $_POST['onRapidGraderLicensePage'] == 0) {
                $_POST['onRapidGraderLicensePage'] = false;
                return;
            }

            // Get license key from form
            if (empty(@$_POST['edd_' . $this->pluginslug .'_license_key'])) {
                $lk = 'empty';
            } else {
                $lk = @$_POST['edd_' . $this->pluginslug .'_license_key'];
            }

            // Handle license activation
            if (isset($_POST['edd_' . $this->pluginslug . '_license_activate'])) {
                set_config('licensekey', $lk, 'block_edwiser_grader');
                set_config('licensekeyactivate', @$_POST['edd_' . $this->pluginslug . '_license_activate'], 'block_edwiser_grader');
                return $this->activate_license();
            }
            // Handle license deactivation
            else if (isset($_POST['edd_' . $this->pluginslug . '_license_deactivate'])) {
                set_config('licensekey', $lk, 'block_edwiser_grader');
                set_config('licensekeydeactivate', @$_POST['edd_' . $this->pluginslug . '_license_deactivate'], 'block_edwiser_grader');
                return $this->deactivate_license();
            }
        }
    }

    /**
     * Get data from database
     * @return string License status
     */
    public function get_data_from_db() {
        global $DB, $CFG;

        if (null !== self::$responsedata) {
            return self::$responsedata;
        }

        $transexpired = false;

        $gettrans = $DB->get_field_select(
            'config_plugins',
            'value',
            'name = :name',
            array('name' => 'wdm_' . $this->pluginslug . '_license_trans'),
            IGNORE_MISSING
        );
        if ($gettrans) {
            $gettrans = unserialize($gettrans);
            if (is_array($gettrans) && time() > $gettrans[1] && $gettrans[1] > 0) {
                $transexpired = true;
                // Delete previous license trans.
                try {
                    $lusers = self::edd_get_users_from_api();
                    if (!empty($lusers) && isset($lusers->users)) {
                        set_config('epb_edwiser-pagebuilder_licensed_users', serialize($lusers->users), 'local_edwiserpagebuilder');
                    }
                    $DB->delete_records_select(
                        'config_plugins',
                        'name = :name',
                        array('name' => 'wdm_' . $this->pluginslug . '_license_trans')
                    );
                } catch (dml_exception $e) {
                    $rcd = 0;
                    // Keep catch empty if no record found.
                }
            }
        } else {
            $transexpired = true;
        }

        if ($transexpired == true) {
            $licensekey = $DB->get_field_select(
                'config_plugins',
                'value',
                'name = :name',
                array('name' => 'edd_' . $this->pluginslug . '_license_key'),
                IGNORE_MISSING
            );

            if ($licensekey) {
                // Get cURL resource.
                $curl = curl_init();

                curl_setopt_array($curl, array(
                    CURLOPT_RETURNTRANSFER => 1,
                    CURLOPT_URL => $this->storeurl,
                    CURLOPT_POST => 1,
                    CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'].' - '.$CFG->wwwroot,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_POSTFIELDS => array(
                        'edd_action' => 'check_license',
                        'license' => $licensekey,
                        'item_name' => urlencode($this->pluginname),
                        'current_version' => $this->pluginversion,
                        'url' => urlencode($CFG->wwwroot),
                    )
                ));
                // Send the request & save response to $resp.
                $resp = curl_exec($curl);

                $currentresponsecode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

                // Close request to clear up some resources.
                curl_close($curl);

                $licensedata = json_decode($resp);

                $validresponsecode = array( '200', '301' );

                if ($licensedata == null || ! in_array($currentresponsecode, $validresponsecode)) {
                    // If server does not respond, read current license information.
                    $licensestatus = $DB->get_field_select(
                        'config_plugins',
                        'value',
                        'name = :name',
                        array('name' => 'edd_' . $this->pluginslug . '_license_status'),
                        IGNORE_MISSING
                    );

                    if (empty($licensedata)) {
                        // Insert new license transient.
                        $dataobject = new stdClass();
                        $dataobject->plugin = 'local_edwiserpagebuilder';
                        $dataobject->name = 'wdm_' . $this->pluginslug . '_license_trans';
                        $dataobject->value = serialize(array('server_did_not_respond', time() + (60 * 60 * 24)));
                        $DB->insert_record('config_plugins', $dataobject);
                    }
                } else {
                    $licensestatus = $licensedata->license;
                }

                if (empty($licensestatus)) {
                    return;
                }

                if (isset($licensedata->license) && ! empty($licensedata->license)) {
                    // Delete previous record.
                    try {
                        $DB->delete_records_select(
                            'config_plugins',
                            'name = :name',
                            array('name' => 'edd_' . $this->pluginslug . '_license_status')
                        );
                    } catch (dml_exception $e) {
                        $rcd = 0;
                        // Keep catch empty if no record found.
                    }

                    $dataobject = new stdClass();
                    $dataobject->plugin = 'local_edwiserpagebuilder';
                    $dataobject->name = 'edd_' . $this->pluginslug . '_license_status';
                    $dataobject->value = $licensestatus;
                    $DB->insert_record('config_plugins', $dataobject);
                }

                $this->set_response_data($licensestatus, $this->pluginslug, true);
                return self::$responsedata;
            }
        } else {
            $licensestatus = $DB->get_field_select(
                'config_plugins',
                'value',
                'name = :name',
                array('name' => 'edd_' . $this->pluginslug . '_license_status'),
                IGNORE_MISSING
            );

            $this->set_response_data($licensestatus, $this->pluginslug);
            return self::$responsedata;
        }
    }

    /**
     * Set response data to plugin config
     * @param string  $licensestatus License status
     * @param string  $pluginslug    Plugin slug
     * @param boolean $settransient  Transient data
     */
    public function set_response_data($licensestatus, $pluginslug, $settransient = false) {
        global $DB;

        if ($licensestatus == 'valid') {
            self::$responsedata = 'available';
        } else if ($licensestatus == 'expired') {
            self::$responsedata = 'available';
        } else {
            self::$responsedata  = 'unavailable';
        }

        if ($settransient) {
            if ($licensestatus == 'valid') {
                $time = 60 * 60 * 24 * 7;
            } else {
                $time = 60 * 60 * 24;
            }

            // Delete previous record.
            try {
                $DB->delete_records_select(
                    'config_plugins',
                    'name = :name',
                    array('name' => 'wdm_' . $pluginslug . '_license_trans')
                );
            } catch (dml_exception $e) {
                $rcd = 0;
                // Keep catch empty if no record found.
            }

            // Insert new license transient.
            $dataobject = new stdClass();
            $dataobject->plugin = 'local_edwiserpagebuilder';
            $dataobject->name = 'wdm_' . $pluginslug . '_license_trans';
            $dataobject->value = serialize(array($licensestatus, time() + (60 * 60 * 24)));
            $DB->insert_record('config_plugins', $dataobject);
        }
    }

    /**
     * Get licensed users from api call
     * @return array Users list
     */
    public function edd_get_users_from_api() {
        global $DB, $CFG;
        $pluginslug = $this->pluginslug;
        $licensekey = $DB->get_field_select(
            'config_plugins',
            'value',
            'name = :name',
            array('name' => 'edd_' . $pluginslug .'_license_key'),
            IGNORE_MISSING
        );
        $status = $DB->get_field_select(
            'config_plugins',
            'value',
            'name = :name',
            array('name' => 'edd_' . $pluginslug . '_license_status'),
            IGNORE_MISSING
        );
        if ($licensekey && $status == 'valid') {
            $site = parse_url($CFG->wwwroot, PHP_URL_HOST).''.parse_url($CFG->wwwroot, PHP_URL_PATH);
            $curl = curl_init();
            // Set some options - we are passing in a useragent too here.
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://edwiser.org/wp-json/wdm-eddc/v1/potential-users/get?key=" . $licensekey . "&site=" . $site,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_POSTFIELDS => ""
            ]);
            // Send the request & save response to $resp.
            $resp = curl_exec($curl);
            // Close request to clear up some resources.
            curl_close($curl);
            return json_decode($resp);
        }
    }
}
