<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('customteams', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$customteams = $DB->get_record('customteams', ['id' => $cm->instance], '*', MUST_EXIST);

// Login
require_course_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/customteams:view', $context);

// Page setup
$PAGE->set_url('/mod/customteams/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($customteams->name));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($customteams->name));

$now = time();
$start = $customteams->timestart;
$end = $customteams->timeend ?? 0;

$btnid = 'join-btn';
$msgid = 'join-msg';

// Track endpoint
$trackurl = new moodle_url('/mod/customteams/track.php', ['id' => $cm->id]);

if (!empty($customteams->externalurl)) {

    $joinurl = addslashes($customteams->externalurl);
    $trackurljs = $trackurl->out(false);
    $jointext = addslashes('🔗 ' . get_string('joinmeeting', 'customteams'));

    if ($now < $start) {

        // Disabled button
        echo html_writer::tag('button',
            '🔒 ' . get_string('meetingnotavailable', 'customteams'),
            [
                'id' => $btnid,
                'class' => 'btn btn-secondary',
                'disabled' => 'disabled'
            ]
        );

        echo html_writer::div('Starts at: ' . userdate($start), 'text-muted mt-2');
        echo html_writer::div('Starting in: <span id="'.$msgid.'"></span>', 'text-info mt-1');

        // 🔥 AUTO ENABLE + TRACK
        echo "<script>
        (function() {
            const startTime = " . ($start * 1000) . ";
            const btn = document.getElementById('$btnid');
            const msg = document.getElementById('$msgid');

            function joinMeeting() {
                fetch('$trackurljs', {method: 'POST'})
                .finally(() => {
                    window.open('$joinurl', '_blank');
                });
            }

            function updateTimer() {
                const now = Date.now();
                const diff = startTime - now;

                if (diff <= 0) {
                    btn.outerHTML = '<button onclick=\"joinMeeting()\" class=\"btn btn-primary\">$jointext</button>';
                    msg.innerHTML = 'Meeting is live';
                    clearInterval(timer);
                    return;
                }

                const mins = Math.floor(diff / 60000);
                const secs = Math.floor((diff % 60000) / 1000);

                msg.innerHTML = mins + 'm ' + secs + 's';
            }

            updateTimer();
            const timer = setInterval(updateTimer, 1000);
            window.joinMeeting = joinMeeting;
        })();
        </script>";

    } elseif ($end && $now > $end) {

        echo html_writer::tag('button',
            '⛔ Meeting ended',
            ['class' => 'btn btn-secondary', 'disabled' => 'disabled']
        );

    } else {

        // Active meeting with tracking
        echo html_writer::tag('button',
            '🔗 ' . get_string('joinmeeting', 'customteams'),
            [
                'class' => 'btn btn-primary',
                'onclick' => "joinMeeting()"
            ]
        );

        echo "<script>
        function joinMeeting() {
            fetch('$trackurljs', {method: 'POST'})
            .finally(() => {
                window.open('$joinurl', '_blank');
            });
        }
        </script>";
    }

} else {
    echo $OUTPUT->notification(
        get_string('meetingnotavailable', 'customteams'),
        'notifyproblem'
    );
}

echo $OUTPUT->footer();