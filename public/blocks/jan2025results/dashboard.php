<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

require_once('../../config.php');
require_once($CFG->dirroot . '/blocks/jan2025results/block_jan2025results.php');

require_login();

$PAGE->set_url(new moodle_url('/blocks/jan2025results/dashboard.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('pluginname', 'block_jan2025results'));
$PAGE->set_heading(get_string('pluginname', 'block_jan2025results'));

// Access Control Logic
// We can instantiate the block class to use its check method, 
// but block_base expects a record. So we'll reuse the logic here.
// Ideally this should be in a helper class (locallib.php), but for now we keep it simple.

$can_access = false;
$config = get_config('block_jan2025results');

// 1. Site Admins always have access
if (is_siteadmin()) {
    $can_access = true;
}

// 2. Check Allowed Roles
if (!$can_access && !empty($config->allowed_roles)) {
    $allowed_roles = explode(',', $config->allowed_roles);
    list($insql, $inparams) = $DB->get_in_or_equal($allowed_roles);
    $sql = "SELECT r.shortname 
            FROM {role_assignments} ra 
            JOIN {role} r ON r.id = ra.roleid
            WHERE ra.userid = ? AND ra.roleid $insql";
    $params = array_merge([$USER->id], $inparams);
    
    $matched_roles = $DB->get_fieldset_sql($sql, $params);
    
    if ($matched_roles) {
        foreach ($matched_roles as $role_shortname) {
            if ($role_shortname !== 'student') {
                $can_access = true;
                break;
            }
        }
    }
}

// 3. Check Student Batches
if (!$can_access) {
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
            $user_batch_norm = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $userdata->data));
            
            foreach ($allowed_batches as $batch) {
                if (empty($batch)) continue;
                
                // Normalize config batch
                $batch_norm = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $batch));
                
                if (strpos($user_batch_norm, $batch_norm) !== false) {
                    $can_access = true;
                    break;
                }
            }
        }
    }
}

if (!$can_access) {
    print_error('accessdenied', 'admin');
}

echo $OUTPUT->header();

?>

<div class="container-fluid p-4">
    <div class="card">
        <div class="card-body text-center p-5">
            <h2 class="card-title mb-4">Examination Results</h2>
            <p class="card-text lead mb-4">
                Welcome to the result dashboard. 
                <?php if (is_siteadmin()): ?>
                    <br><small class="text-muted">(You are viewing this as an Administrator)</small>
                <?php endif; ?>
            </p>
            
            <div class="alert alert-info">
                Result data integration is pending. This is a placeholder for the actual result display.
            </div>
            
            <div class="mt-4">
                <a href="<?php echo $CFG->wwwroot; ?>" class="btn btn-primary">Back to Home</a>
            </div>
        </div>
    </div>
</div>

<?php
echo $OUTPUT->footer();
