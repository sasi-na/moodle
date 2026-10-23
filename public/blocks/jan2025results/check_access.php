<?php
require_once('../../config.php');
require_once($CFG->libdir . '/blocklib.php');
require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
require_once($CFG->dirroot . '/blocks/jan2025results/block_jan2025results.php');

require_login();

$PAGE->set_url(new moodle_url('/blocks/jan2025results/check_access.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Access Check');
$PAGE->set_heading('Access Check Diagnostic');

echo $OUTPUT->header();

echo "<h3>User Information</h3>";
echo "User ID: " . $USER->id . "<br>";
echo "Full Name: " . fullname($USER) . "<br>";
echo "Is Site Admin: " . (is_siteadmin() ? 'Yes' : 'No') . "<br>";

echo "<h3>Configuration</h3>";
$config = get_config('block_jan2025results');
echo "Allowed Roles (Raw): " . ($config->allowed_roles ?? 'Not Set') . "<br>";
echo "Allowed Batches (Raw): <pre>" . ($config->allowed_batches ?? 'Not Set') . "</pre>";

echo "<h3>Role Check</h3>";
if (!empty($config->allowed_roles)) {
    $allowed_roles = explode(',', $config->allowed_roles);
    echo "Allowed Role IDs: " . implode(', ', $allowed_roles) . "<br>";
    
    list($insql, $inparams) = $DB->get_in_or_equal($allowed_roles);
    $sql = "SELECT r.shortname 
            FROM {role_assignments} ra 
            JOIN {role} r ON r.id = ra.roleid
            WHERE ra.userid = ? AND ra.roleid $insql";
    $params = array_merge([$USER->id], $inparams);
    $matched_roles = $DB->get_fieldset_sql($sql, $params);
    
    if ($matched_roles) {
        echo "<strong>MATCH FOUND!</strong> User has roles: ";
        $unconditional_access = false;
        foreach ($matched_roles as $role_shortname) {
            echo $role_shortname . ", ";
            if ($role_shortname !== 'student') {
                $unconditional_access = true;
            }
        }
        echo "<br>";
        
        if ($unconditional_access) {
            echo "Access granted via Role (Non-student role found).<br>";
        } else {
            echo "Only 'student' role found. <strong>Proceeding to Batch Check...</strong><br>";
        }
    } else {
        echo "No matching roles found for this user.<br>";
    }
} else {
    echo "No allowed roles configured.<br>";
}

echo "<h3>Batch Check</h3>";
$allowed_batches_str = isset($config->allowed_batches) ? $config->allowed_batches : 'Jan 2025';
$allowed_batches = preg_split('/\r\n|\r|\n/', $allowed_batches_str);
$allowed_batches = array_map('trim', $allowed_batches);

$field = $DB->get_record('user_info_field', ['shortname' => 'cohort_batch']);
if (!$field) {
    $field = $DB->get_record('user_info_field', ['name' => 'Cohort/Batch']);
}

if ($field) {
    echo "Profile Field 'cohort_batch' found (ID: $field->id).<br>";
    $userdata = $DB->get_record('user_info_data', [
        'userid' => $USER->id,
        'fieldid' => $field->id
    ]);
    
    if ($userdata) {
        echo "User Data Raw: '" . $userdata->data . "'<br>";
        $user_batch_norm = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $userdata->data));
        echo "User Data Normalized: '" . $user_batch_norm . "'<br>";
        
        $match = false;
        foreach ($allowed_batches as $batch) {
            if (empty($batch)) continue;
            $batch_norm = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $batch));
            echo "Checking against config batch: '$batch' (Norm: '$batch_norm') ... ";
            
            if (strpos($user_batch_norm, $batch_norm) !== false) {
                echo "<strong>MATCH!</strong><br>";
                $match = true;
            } else {
                echo "No match.<br>";
            }
        }
    } else {
        echo "No data found for this user in the profile field.<br>";
    }
} else {
    echo "Profile field 'cohort_batch' or 'Cohort/Batch' NOT FOUND.<br>";
}

echo "<h3>Final Access Result</h3>";
$block = new block_jan2025results();
$access = $block->can_access();
echo "<strong>can_access() returns: " . ($access ? 'TRUE' : 'FALSE') . "</strong>";

echo $OUTPUT->footer();
