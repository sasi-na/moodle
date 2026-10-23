<?php
global $CFG;
require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

class block_jan2025results extends block_base {
    
    public function init() {
        $this->title = get_string('pluginname', 'block_jan2025results');
    }
    
    /**
     * Check if the current user can access the block/dashboard.
     * 
     * @return bool
     */
    public function can_access() {
        global $USER, $DB;

        // 1. Site Admins always have access
        if (is_siteadmin()) {
            return true;
        }

        // 2. Check Allowed Roles
        $config = get_config('block_jan2025results');
        if (!empty($config->allowed_roles)) {
            $allowed_roles = explode(',', $config->allowed_roles);
            // Check if user has any of these roles in ANY context
            // We need to know WHICH role matched. If it's just 'student', we still need to check batch.
            list($insql, $inparams) = $DB->get_in_or_equal($allowed_roles);
            $sql = "SELECT r.shortname 
                    FROM {role_assignments} ra 
                    JOIN {role} r ON r.id = ra.roleid
                    WHERE ra.userid = ? AND ra.roleid $insql";
            $params = array_merge([$USER->id], $inparams);
            
            $matched_roles = $DB->get_fieldset_sql($sql, $params);
            
            if ($matched_roles) {
                // If the user has any allowed role that is NOT 'student', grant access immediately.
                // If the user ONLY has 'student' (and it's allowed), we fall through to batch check.
                foreach ($matched_roles as $role_shortname) {
                    if ($role_shortname !== 'student') {
                        return true;
                    }
                }
            }
        }

        // 3. Check Student Batches
        $allowed_batches_str = isset($config->allowed_batches) ? $config->allowed_batches : 'Jan 2025';
        // Split by newline since it's a textarea
        $allowed_batches = preg_split('/\r\n|\r|\n/', $allowed_batches_str);
        $allowed_batches = array_map('trim', $allowed_batches);
        
        // Get the field ID for "Cohort/Batch"
        $field = $DB->get_record('user_info_field', ['shortname' => 'cohort_batch']);
        if (!$field) {
            $field = $DB->get_record('user_info_field', ['name' => 'Cohort/Batch']);
        }
        
        if ($field) {
            $userdata = $DB->get_record('user_info_data', [
                'userid' => $USER->id,
                'fieldid' => $field->id
            ]);
            
            if ($userdata && !empty($userdata->data)) {
                // Normalize user data: lowercase, remove all non-alphanumeric characters
                // "Jan, 2025" -> "jan2025" (Updated to handle commas and spaces)
                $user_batch_norm = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $userdata->data));
                
                foreach ($allowed_batches as $batch) {
                    if (empty($batch)) continue;
                    
                    // Normalize config batch
                    $batch_norm = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $batch));
                    
                    // Check if the normalized strings match (partial match allowed)
                    if (strpos($user_batch_norm, $batch_norm) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function get_content() {
        global $USER, $CFG;
        
        if ($this->content !== null) {
            return $this->content;
        }
        
        $this->content = new stdClass;
        $this->content->text = '';
        $this->content->footer = '';
        
        if (!$this->can_access()) {
            return $this->content;
        }
        
        // Get configured URL or default
        $config = get_config('block_jan2025results');
        $url_string = isset($config->result_url) ? trim($config->result_url) : '';
        
        if (empty($url_string)) {
            $url_string = '/blocks/jan2025results/dashboard.php';
        }
        
        // Handle external URLs without protocol
        if (strpos($url_string, 'www.') === 0 && strpos($url_string, 'http') === false) {
            $url_string = 'https://' . $url_string;
        }
        
        // If it starts with http/https, use it directly
        if (strpos($url_string, 'http') === 0) {
            $resultsurl = $url_string;
        } else {
            // Otherwise treat as internal Moodle URL
            $resultsurl = new moodle_url($url_string);
        }

        $resulttitle = 'Results Available';
        $resultdesc = 'Your examination results are now available.';
        
        // Generate the content
        $this->content->text = '
        <style>
            .jan2025-results-content {
                padding: 10px 0;
            }
            
            .jan2025-results-header {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 12px;
            }
            
            .jan2025-results-icon {
                font-size: 28px;
            }
            
            .jan2025-results-title {
                font-size: 16px;
                font-weight: 600;
                color: #333;
                margin: 0;
            }
            
            .jan2025-results-description {
                color: #666;
                font-size: 13px;
                margin: 0 0 12px 0;
                line-height: 1.4;
            }
            
            .jan2025-results-btn {
                display: block;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 10px 20px;
                border-radius: 6px;
                text-decoration: none;
                font-weight: 600;
                font-size: 14px;
                transition: all 0.3s ease;
                text-align: center;
                box-shadow: 0 3px 8px rgba(102, 126, 234, 0.3);
            }
            
            .jan2025-results-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
                color: white;
                text-decoration: none;
            }
        </style>
        
        <div class="jan2025-results-content">
            <div class="jan2025-results-header">
                <span class="jan2025-results-icon">🎓</span>
                <h4 class="jan2025-results-title">' . $resulttitle . '</h4>
            </div>
            <p class="jan2025-results-description">
                ' . $resultdesc . '
            </p>
            <a href="' . $resultsurl . '" class="jan2025-results-btn">
                Click Here for Results →
            </a>
        </div>';
        
        return $this->content;
    }
    
    public function applicable_formats() {
        return array('my' => true);
    }
    
    public function instance_allow_multiple() {
        return false;
    }
    
    public function has_config() {
        return true;
    }
}
