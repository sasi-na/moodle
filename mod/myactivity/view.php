<?php
// File: mod/myactivity/view.php

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT); // Course module ID
$cm = get_coursemodule_from_id('myactivity', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$activity = $DB->get_record('myactivity', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$PAGE->set_url('/mod/myactivity/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($activity->name));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($activity->name));

// Clickable button
echo html_writer::tag('p', '<a href="#" id="getEmailBtn" class="btn btn-primary">Test</a>');

// Placeholder for output / messages
echo html_writer::div('', 'email-output', [
    'aria-live' => 'polite',
    'style' => 'margin-top:1em; font-weight:500;',
]);

// Show module intro and content
echo format_module_intro('myactivity', $activity, $cm->id);
echo html_writer::div('This is your custom activity output area!');

$ajaxurl = new moodle_url('/mod/myactivity/ajax.php');

// ✅ JavaScript for AJAX + Response handling
$js = <<<JS
(function() {
    const btn = document.getElementById('getEmailBtn');
    const out = document.querySelector('.email-output');
    if (!btn || !out) return;

    btn.addEventListener('click', function(e) {
        e.preventDefault();
        out.textContent = 'Connecting to ISDCpro...';
        out.style.color = '#333';

        fetch('{$ajaxurl->out(false)}', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            method: 'GET'
        })
        .then(res => {
            if (!res.ok) throw new Error('Network error: ' + res.status);
            return res.json();
        })
        .then(data => {
            console.log('API Response:', data);

            // ✅ Success case: redirect to ISDCpro
            if (data?.parsed_response?.success === "200" && data?.parsed_response?.url) {
                const loginUrl = data.parsed_response.url;
                window.open(loginUrl, '_blank');
                out.textContent = 'Redirecting you to ISDCpro...';
                out.style.color = 'green';
            } 
            // ❌ Error case: show only message
            else if (data?.message) {
                out.textContent = data.message;
                out.style.color = 'red';
            } 
            else if (data?.parsed_response?.message) {
                out.textContent = data.parsed_response.message;
                out.style.color = 'red';
            } 
            // ⚠️ Unexpected response
            else {
                out.textContent = 'Unexpected response received.';
                out.style.color = 'orange';
            }
        })
        .catch(err => {
            console.error(err);
            out.textContent = 'Error contacting ISDCpro: ' + err.message;
            out.style.color = 'red';
        });
    });
})();
JS;

$PAGE->requires->js_init_code($js);

echo $OUTPUT->footer();

