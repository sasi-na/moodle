<?php
defined('MOODLE_INTERNAL') || die();

use mod_quiz\local\reports\report_base;

/**
 * Faculty proctoring-evidence review report.
 *
 * Lists attempts with a risk score + flag count, and drills into a per-attempt
 * evidence timeline (identity pinned, snapshots + screen frames + events
 * interleaved chronologically and coloured by severity).
 *
 * @package   quiz_proctorreview
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_proctorreview_report extends report_base {

    public function display($quiz, $cm, $course) {
        global $OUTPUT, $PAGE, $DB;

        $context = context_module::instance($cm->id);
        require_capability('quizaccess/proctor:viewreport', $context);

        $PAGE->set_url(new moodle_url('/mod/quiz/report.php',
            ['id' => $cm->id, 'mode' => 'proctorreview']));

        $attemptid = optional_param('attemptid', 0, PARAM_INT);

        // If proctoring is not enabled for this quiz there is normally no evidence to review.
        // Fall back to the standard quiz workflow rather than showing an empty report:
        // a single attempt goes to the normal attempt review, the list to the Grades report.
        // Guard: if evidence was captured previously (e.g. proctoring later disabled) keep
        // showing it so historical evidence is never hidden.
        $settings = $DB->get_record('quizaccess_proctor_settings', ['quizid' => $quiz->id]);
        if (empty($settings) || empty($settings->enabled)) {
            if (!$this->quiz_has_evidence($quiz->id)) {
                if ($attemptid) {
                    redirect(new moodle_url('/mod/quiz/review.php', ['attempt' => $attemptid]));
                }
                redirect(new moodle_url('/mod/quiz/report.php', ['id' => $cm->id, 'mode' => 'overview']),
                    get_string('notenabledredirect', 'quiz_proctorreview'), null,
                    \core\output\notification::NOTIFY_INFO);
            }
        }

        $this->print_header_and_tabs($cm, $course, $quiz, 'proctorreview');

        if ($attemptid) {
            $this->display_attempt($context, $cm, $quiz, $attemptid);
        } else {
            $this->display_list($context, $cm, $quiz);
        }

        // Footer is printed by the report dispatcher (mod/quiz/report.php).
    }

    /** Whether any proctoring evidence (snapshot or event) exists for this quiz. */
    protected function quiz_has_evidence(int $quizid): bool {
        global $DB;
        $params = ['quizid' => $quizid];
        return $DB->record_exists_sql(
            "SELECT 1 FROM {quizaccess_proctor_event} e
               JOIN {quiz_attempts} qa ON qa.id = e.attemptid
              WHERE qa.quiz = :quizid", $params)
            || $DB->record_exists_sql(
            "SELECT 1 FROM {quizaccess_proctor_snapshot} s
               JOIN {quiz_attempts} qa ON qa.id = s.attemptid
              WHERE qa.quiz = :quizid", $params);
    }

    /** Risk score (0-100) + severity band from a summed severity. */
    protected static function risk(int $risksum): array {
        $score = min(100, $risksum * 5);
        if ($score >= 60) {
            return [$score, 'danger', 'High'];
        } else if ($score >= 25) {
            return [$score, 'warn', 'Medium'];
        } else if ($score > 0) {
            return [$score, 'primary', 'Low'];
        }
        return [0, 'success', 'Clear'];
    }

    /** Human label for an event type. */
    protected static function eventlabel(string $type): string {
        $map = [
            'noface' => 'No face', 'multiface' => 'Multiple faces', 'lookingaway' => 'Looking away',
            'faceunavailable' => 'Face checks unavailable', 'identitymismatch' => 'Identity mismatch',
            'tabswitch' => 'Tab switch', 'hidden' => 'Tab hidden', 'visible' => 'Tab visible',
            'blur' => 'Window left', 'focus' => 'Window focus', 'fullscreenexit' => 'Left fullscreen',
            'clipboard' => 'Copy/paste', 'rightclick' => 'Right click', 'keyshortcut' => 'Key shortcut',
            'printscreen' => 'Print screen', 'devtoolskey' => 'Dev-tools key', 'devtoolsopen' => 'Dev-tools open',
            'multimonitor' => 'Multiple monitors', 'resize' => 'Window resized', 'inactivity' => 'Inactivity',
            'offline' => 'Went offline', 'online' => 'Back online', 'audiovoice' => 'Voice detected',
            'screensharestopped' => 'Stopped screen share', 'cameralost' => 'Camera lost',
            'cameradenied' => 'Camera denied', 'micdenied' => 'Mic denied', 'screendenied' => 'Screen denied',
            'fingerprint' => 'Device info',
        ];
        return $map[$type] ?? ucfirst($type);
    }

    protected function display_list($context, $cm, $quiz): void {
        global $DB, $OUTPUT;

        $sql = "SELECT qa.id, qa.userid, qa.attempt, qa.state, qa.timefinish,
                       " . $DB->sql_fullname('u.firstname', 'u.lastname') . " AS fullname,
                       (SELECT COUNT(1) FROM {quizaccess_proctor_snapshot} s WHERE s.attemptid = qa.id) AS snaps,
                       (SELECT COUNT(1) FROM {quizaccess_proctor_event} e WHERE e.attemptid = qa.id) AS events,
                       (SELECT COALESCE(SUM(e2.severity), 0) FROM {quizaccess_proctor_event} e2 WHERE e2.attemptid = qa.id) AS risksum
                  FROM {quiz_attempts} qa
                  JOIN {user} u ON u.id = qa.userid
                 WHERE qa.quiz = :quizid AND qa.preview = 0
              ORDER BY risksum DESC, qa.timefinish DESC, qa.id DESC";
        $rows = $DB->get_records_sql($sql, ['quizid' => $quiz->id]);

        if (!$rows) {
            echo $OUTPUT->notification(get_string('noattempts', 'quiz_proctorreview'), 'info');
            return;
        }

        $table = new html_table();
        $table->head = [
            get_string('student', 'quiz_proctorreview'),
            get_string('attempt', 'quiz_proctorreview'),
            get_string('risk', 'quiz_proctorreview'),
            get_string('events', 'quiz_proctorreview'),
            get_string('snapshots', 'quiz_proctorreview'),
            '',
        ];
        foreach ($rows as $r) {
            [$score, $band, $label] = self::risk((int) $r->risksum);
            $badge = html_writer::span($label . ' (' . $score . ')', 'badge bg-' .
                ($band === 'danger' ? 'danger' : ($band === 'warn' ? 'warning' : ($band === 'primary' ? 'info' : 'success'))));
            $url = new moodle_url('/mod/quiz/report.php',
                ['id' => $cm->id, 'mode' => 'proctorreview', 'attemptid' => $r->id]);
            $table->data[] = [
                $r->fullname,
                $r->attempt,
                $badge,
                $r->events,
                $r->snaps,
                html_writer::link($url, get_string('review', 'quiz_proctorreview')),
            ];
        }
        echo html_writer::table($table);
    }

    protected function display_attempt($context, $cm, $quiz, int $attemptid): void {
        global $DB, $OUTPUT;

        $DB->get_record('quiz_attempts', ['id' => $attemptid, 'quiz' => $quiz->id], '*', MUST_EXIST);

        $back = new moodle_url('/mod/quiz/report.php', ['id' => $cm->id, 'mode' => 'proctorreview']);
        echo html_writer::link($back, get_string('backtolist', 'quiz_proctorreview'));

        $snapshots = \quizaccess_proctor\storage::get_snapshots($attemptid);
        $events = \quizaccess_proctor\storage::get_events($attemptid);

        if (!$snapshots && !$events) {
            echo $OUTPUT->notification(get_string('noevidence', 'quiz_proctorreview'), 'info');
            return;
        }

        // Risk summary banner.
        $risksum = 0;
        foreach ($events as $e) {
            $risksum += (int) $e->severity;
        }
        [$score, $band, $label] = self::risk($risksum);
        echo html_writer::div(
            html_writer::tag('strong', get_string('risk', 'quiz_proctorreview') . ': ') .
            html_writer::span($label . ' — ' . $score . '/100', 'badge bg-' .
                ($band === 'danger' ? 'danger' : ($band === 'warn' ? 'warning' : ($band === 'primary' ? 'info' : 'success'))) .
                ' ml-1') .
            ' &middot; ' . count($events) . ' ' . get_string('events', 'quiz_proctorreview') .
            ' &middot; ' . count($snapshots) . ' ' . get_string('snapshots', 'quiz_proctorreview'),
            '', ['style' => 'margin:10px 0;font-size:1.05rem;']);

        // Identity pinned.
        echo $OUTPUT->heading(get_string('identity', 'quiz_proctorreview'), 4);
        $hasidentity = false;
        echo html_writer::start_div('', ['style' => 'display:flex;flex-wrap:wrap;gap:10px;']);
        foreach ($snapshots as $snap) {
            if ($snap->type === 'identity') {
                $hasidentity = true;
                echo $this->snapshot_img($context, $snap, 200);
            }
        }
        echo html_writer::end_div();
        if (!$hasidentity) {
            echo html_writer::tag('p', '—');
        }

        // Merged chronological timeline (snapshots + events).
        echo $OUTPUT->heading(get_string('timeline', 'quiz_proctorreview'), 4);
        $items = [];
        foreach ($snapshots as $snap) {
            if ($snap->type === 'identity') {
                continue;
            }
            $items[] = ['t' => (int) $snap->capturedat, 'kind' => 'snap', 'data' => $snap];
        }
        foreach ($events as $e) {
            $items[] = ['t' => (int) $e->occurredat, 'kind' => 'event', 'data' => $e];
        }
        usort($items, function($a, $b) {
            return $a['t'] <=> $b['t'];
        });

        echo html_writer::start_div('', ['style' => 'display:flex;flex-wrap:wrap;gap:10px;align-items:flex-start;']);
        foreach ($items as $it) {
            if ($it['kind'] === 'snap') {
                echo $this->snapshot_img($context, $it['data'], $it['data']->type === 'screen' ? 240 : 130);
            } else {
                echo $this->event_chip($it['data']);
            }
        }
        echo html_writer::end_div();
    }

    /** A severity-coloured event chip for the timeline. */
    protected function event_chip($e): string {
        $sev = (int) $e->severity;
        $bg = $sev >= 3 ? '#fbeaea' : ($sev === 2 ? '#fdf2e2' : ($sev === 1 ? '#eef0fe' : '#e7f6ec'));
        $fg = $sev >= 3 ? '#dc2626' : ($sev === 2 ? '#d97706' : ($sev === 1 ? '#4f46e5' : '#16a34a'));
        $label = self::eventlabel($e->eventtype);
        $time = userdate($e->occurredat, '%H:%M:%S');
        return html_writer::div(
            html_writer::tag('div', $label, ['style' => 'font-weight:600;font-size:.82rem;']) .
            html_writer::tag('small', $time),
            '', ['style' => 'background:' . $bg . ';color:' . $fg . ';border-radius:10px;padding:8px 12px;text-align:center;min-width:96px;']);
    }

    protected function snapshot_img($context, $snap, int $width): string {
        $url = moodle_url::make_pluginfile_url($context->id, 'quizaccess_proctor', 'snapshot',
            $snap->id, '/', $snap->filename);
        $border = $snap->type === 'screen' ? '2px solid #0ea5e9' : '1px solid #e6e9f0';
        $img = html_writer::empty_tag('img', ['src' => $url->out(false),
            'style' => 'width:' . $width . 'px;border-radius:8px;display:block;border:' . $border . ';']);
        $caption = html_writer::tag('small', userdate($snap->capturedat, '%H:%M:%S') .
            ($snap->type === 'screen' ? ' · screen' : ''));
        return html_writer::div($img . $caption, '', ['style' => 'text-align:center;']);
    }
}
