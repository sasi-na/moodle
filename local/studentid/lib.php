<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Adds/removes nodes in the sidebar drawer navigation.
 * Note: this does NOT affect the primary nav bar — that is handled via
 * CSS + JS injection in local_studentid_render_navbar_output().
 */
function local_studentid_extend_navigation(global_navigation $nav) {
    global $USER;

    if (!isloggedin() || isguestuser()) {
        return;
    }

    if (\local_studentid\manager::is_verifier($USER->id)) {
        // Remove Dashboard and My Courses from the drawer for verifiers.
        foreach (['myhome', 'mycourses', 'courses'] as $key) {
            $node = $nav->find($key, null);
            if ($node) {
                $node->remove();
            }
        }

        $nav->add(
            get_string('verifierdashboard', 'local_studentid'),
            new moodle_url('/local/studentid/verifier/index.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'local_studentid_verifier',
            new pix_icon('i/report', '')
        );
        return;
    }

    // Any logged-in non-guest user who isn't a verifier is treated as a student,
    // but the e-ID Card link is restricted to the eligible batches.
    if (\local_studentid\manager::is_eligible_batch($USER->id)) {
        $nav->add(
            get_string('pluginname', 'local_studentid'),
            new moodle_url('/local/studentid/index.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'local_studentid_student',
            new pix_icon('i/badge', '')
        );
    }
}

/**
 * Injects nav items into the primary navigation bar via CSS + JS.
 *
 * extend_navigation() only affects the sidebar drawer in Moodle 5.x; the
 * primary nav (top bar) is built by a separate class that plugins cannot
 * hook into directly.  We therefore:
 *   - Verifiers : CSS hides Dashboard & My Courses; JS prepends
 *                 "Verifier Dashboard" as the first primary nav item.
 *   - Students  : JS injects "ID Card" immediately after "My Courses".
 */
function local_studentid_render_navbar_output() {
    global $PAGE, $USER;

    if (!isloggedin() || isguestuser()) {
        return '';
    }

    // The primary nav is injected client-side via an AMD module (no inline
    // script/style, CSP-friendly). Verifiers also get Dashboard / My Courses
    // hidden; students get an "e-ID Card" link after My Courses.
    if (\local_studentid\manager::is_verifier($USER->id)) {
        $PAGE->requires->js_call_amd('local_studentid/navinject', 'init', [
            'verifier',
            (new moodle_url('/local/studentid/verifier/index.php'))->out(false),
            get_string('verifierdashboard', 'local_studentid'),
        ]);
        return '';
    }

    // Students get the "e-ID Card" link only if they belong to an eligible batch.
    if (!\local_studentid\manager::is_eligible_batch($USER->id)) {
        return '';
    }

    $PAGE->requires->js_call_amd('local_studentid/navinject', 'init', [
        'student',
        (new moodle_url('/local/studentid/index.php'))->out(false),
        get_string('pluginname', 'local_studentid'),
    ]);
    return '';
}

/**
 * Redirects verifiers to their dashboard whenever they land on the Moodle
 * home page or /my/ dashboard. Called by require_login() on every page load.
 */
function local_studentid_after_require_login(
    $courseorid = null, $autologinguest = null, $cm = null,
    $setwantsurltome = null, $preventredirect = null
) {
    global $CFG;

    if (!isloggedin() || isguestuser() || $preventredirect || is_siteadmin()) {
        return;
    }

    global $USER;
    if (!\local_studentid\manager::is_verifier($USER->id)) {
        return;
    }

    // Determine the current request path, relative to wwwroot.
    $uri  = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
    $base = rtrim(parse_url($CFG->wwwroot, PHP_URL_PATH) ?? '', '/');
    $rel  = $base !== '' ? substr($uri, strlen($base)) : $uri;
    $rel  = rtrim('/' . ltrim($rel, '/'), '/') ?: '/';

    // Only redirect when the verifier lands on a home/dashboard page.
    // All other URLs (profile, logout, studentid pages, etc.) are left alone.
    $home_paths = ['/', '/index.php', '/my', '/my/index.php'];
    if (!in_array($rel, $home_paths)) {
        return;
    }

    redirect(new moodle_url('/local/studentid/verifier/index.php'));
}

/**
 * Pluginfile handler — serves photo, aadhaar, and idcard files.
 */
function local_studentid_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }

    require_login();

    if (!in_array($filearea, ['photo', 'idcard', 'aadhaar_front', 'aadhaar_back', 'abc_id', 'tenth_marks', 'olevel'])) {
        return false;
    }

    global $DB, $USER;
    $syscontext = context_system::instance();
    $itemid = (int) array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    if ($filearea === 'photo') {
        $canverify = has_capability('local/studentid:verify', $syscontext);
        if (!$canverify) {
            $request = $DB->get_record('local_studentid_requests', ['id' => $itemid]);
            if (!$request || $request->userid != $USER->id) {
                return false;
            }
        }
    }

    if ($filearea === 'aadhaar_front' || $filearea === 'aadhaar_back' || $filearea === 'abc_id' || $filearea === 'tenth_marks' || $filearea === 'olevel') {
        $canverify = has_capability('local/studentid:verify', $syscontext);
        if (!$canverify) {
            $request = $DB->get_record('local_studentid_requests', ['id' => $itemid]);
            if (!$request || $request->userid != $USER->id) {
                return false;
            }
        }
    }

    if ($filearea === 'idcard') {
        $canverify = has_capability('local/studentid:verify', $syscontext);
        if (!$canverify) {
            $card = $DB->get_record('local_studentid_cards', ['id' => $itemid]);
            if (!$card || $card->userid != $USER->id) {
                return false;
            }
        }
    }

    $fs = get_file_storage();
    $file = $fs->get_file($syscontext->id, 'local_studentid', $filearea, $itemid, $filepath, $filename);

    if (!$file) {
        return false;
    }

    send_stored_file($file, null, 0, $forcedownload, $options);
}
