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

namespace local_rpreport;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Conditional;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds and delivers the Research Project submission-status workbook.
 *
 * A "research project" course is any course whose full name begins with
 * "00." and mentions "Project" (e.g. "00. Research Project - <faculty>").
 * Each such course runs the same four submission phases — Synopsis, Interim
 * Report, Final Report and Video Presentation — implemented as assignments in
 * the current courses and as quizzes in the older batch (B*RP) courses. For
 * every enrolled student we report, per phase: Yes (turned in), Draft (started
 * but not submitted), No (nothing) or N/A (the course has no such phase); then the
 * marks awarded for each phase and the course total they add up to.
 *
 * The marks are read straight from the gradebook, and the total is the course
 * total grade item - the figure Moodle itself computes from the four phases - so
 * the report never has to re-implement the arithmetic. The current July-2024
 * Semester IV courses are marked out of 15 + 15 + 50 + 20 = 100; the older
 * batches are not, which is why the maximum is reported alongside the total
 * rather than assumed.
 *
 * @package   local_rpreport
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report {

    /** @var string SQL LIKE pattern that selects the research project courses. */
    const COURSE_PATTERN = '00.%Project%';

    /** @var string[] The four canonical phases, in report order. */
    const PHASES = ['Synopsis', 'Interim', 'Final', 'Video'];

    /** @var array Human column headings for the four phases. */
    const PHASE_LABELS = [
        'Synopsis' => 'Synopsis',
        'Interim'  => 'Interim Report',
        'Final'    => 'Final Report',
        'Video'    => 'Video Presentation',
    ];

    /**
     * Map an activity name to one of the four canonical phases.
     *
     * @param string $name activity name
     * @return string|null phase key, or null if it is not a phase activity
     */
    public static function classify(string $name): ?string {
        $n = \core_text::strtolower($name);
        if (strpos($n, 'synopsis') !== false) {
            return 'Synopsis';
        }
        if (strpos($n, 'interim') !== false) {
            return 'Interim';
        }
        if (strpos($n, 'video') !== false) {
            return 'Video';
        }
        if (strpos($n, 'report') !== false || strpos($n, 'final') !== false) {
            return 'Final';
        }
        return null;
    }

    /**
     * Return the list of valid recipient email addresses from plugin config.
     *
     * @return string[] unique, validated email addresses
     */
    public static function get_recipients(): array {
        $raw = (string) get_config('local_rpreport', 'recipients');
        $parts = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        $emails = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '' && validate_email($p)) {
                $emails[strtolower($p)] = $p;
            }
        }
        return array_values($emails);
    }

    /**
     * Build the whole report as a two-dimensional array (header first).
     *
     * @return array{header:string[], rows:array[], summary:array} the report data
     */
    public static function build_rows(): array {
        global $DB;

        $header = [
            'Course Code', 'Course Name', 'Name', 'Email', 'USN',
            'Enrollment Number', 'Program', 'Elective', 'Batch',
            'Synopsis', 'Interim Report', 'Final Report', 'Video Presentation',
            'Synopsis Marks', 'Interim Report Marks', 'Final Report Marks',
            'Video Presentation Marks', 'Total', 'Total Out Of',
        ];

        // Custom profile fields: id => alias.
        $fmap = [1 => 'enroll', 2 => 'usn', 5 => 'mprog', 6 => 'prog', 7 => 'elective', 10 => 'batch'];
        list($fsql, $fparams) = $DB->get_in_or_equal(array_keys($fmap));

        $courses = $DB->get_records_sql(
            "SELECT id, shortname, fullname
               FROM {course}
              WHERE fullname LIKE ?
           ORDER BY fullname, id",
            [self::COURSE_PATTERN]);

        $rows = [];
        $summary = [
            'courses'  => count($courses),
            'students' => 0,
            'phases'   => [],
        ];
        foreach (self::PHASES as $b) {
            $summary['phases'][$b] = ['Yes' => 0, 'Draft' => 0, 'No' => 0, 'N/A' => 0, 'Marks' => 0];
        }
        $summary['totals'] = 0;

        foreach ($courses as $c) {
            // Group the course's phase activities (assignments and quizzes) by phase.
            $acts = $DB->get_records_sql(
                "SELECT cm.id AS cmid, m.name AS modname, cm.instance
                   FROM {course_modules} cm
                   JOIN {modules} m ON m.id = cm.module
                  WHERE cm.course = ?
                    AND m.name IN ('assign', 'quiz')
                    AND cm.deletioninprogress = 0",
                [$c->id]);
            $assignbyb = array_fill_keys(self::PHASES, []);
            $quizbyb   = array_fill_keys(self::PHASES, []);
            foreach ($acts as $a) {
                $name = $DB->get_field($a->modname, 'name', ['id' => $a->instance]);
                $b = self::classify((string) $name);
                if ($b === null) {
                    continue;
                }
                if ($a->modname === 'assign') {
                    $assignbyb[$b][] = $a->instance;
                } else {
                    $quizbyb[$b][] = $a->instance;
                }
            }

            // Determine, per phase, the set of students who submitted / drafted.
            $submitted = array_fill_keys(self::PHASES, []);
            $draft     = array_fill_keys(self::PHASES, []);
            $exists    = array_fill_keys(self::PHASES, false);
            foreach (self::PHASES as $b) {
                if (!empty($assignbyb[$b])) {
                    $exists[$b] = true;
                    list($isql, $ip) = $DB->get_in_or_equal($assignbyb[$b]);
                    foreach ($DB->get_records_sql(
                            "SELECT DISTINCT userid FROM {assign_submission}
                              WHERE assignment $isql AND status = 'submitted'", $ip) as $r) {
                        $submitted[$b][$r->userid] = 1;
                    }
                    foreach ($DB->get_records_sql(
                            "SELECT DISTINCT userid FROM {assign_submission}
                              WHERE assignment $isql AND status = 'draft'", $ip) as $r) {
                        $draft[$b][$r->userid] = 1;
                    }
                }
                if (!empty($quizbyb[$b])) {
                    $exists[$b] = true;
                    list($isql, $ip) = $DB->get_in_or_equal($quizbyb[$b]);
                    foreach ($DB->get_records_sql(
                            "SELECT DISTINCT userid FROM {quiz_attempts}
                              WHERE quiz $isql AND state = 'finished'", $ip) as $r) {
                        $submitted[$b][$r->userid] = 1;
                    }
                    foreach ($DB->get_records_sql(
                            "SELECT DISTINCT userid FROM {quiz_attempts}
                              WHERE quiz $isql AND state IN ('inprogress', 'overdue')", $ip) as $r) {
                        $draft[$b][$r->userid] = 1;
                    }
                }
            }

            // Enrolled students (role = student, roleid 5).
            $students = $DB->get_records_sql(
                "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email
                   FROM {user} u
                   JOIN {user_enrolments} ue ON ue.userid = u.id
                   JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = ?
                   JOIN {context} ctx ON ctx.instanceid = e.courseid AND ctx.contextlevel = 50
                   JOIN {role_assignments} ra ON ra.userid = u.id AND ra.contextid = ctx.id AND ra.roleid = 5
                  WHERE u.deleted = 0
               ORDER BY u.lastname, u.firstname",
                [$c->id]);
            if (empty($students)) {
                continue;
            }

            // Marks awarded per phase, and the course total they add up to.
            [$marks, $coursetotal, $coursemax] = self::load_marks($c->id, $assignbyb, $quizbyb);

            // Custom profile data for these students.
            list($usql, $up) = $DB->get_in_or_equal(array_keys($students));
            $prof = [];
            foreach ($DB->get_records_sql(
                    "SELECT id, userid, fieldid, " . $DB->sql_compare_text('data') . " AS data
                       FROM {user_info_data}
                      WHERE userid $usql AND fieldid $fsql", array_merge($up, $fparams)) as $r) {
                $prof[$r->userid][$fmap[$r->fieldid]] = trim((string) $r->data);
            }

            foreach ($students as $u) {
                $p = $prof[$u->id] ?? [];
                $program = (($p['prog'] ?? '') !== '') ? $p['prog'] : ($p['mprog'] ?? '');
                $row = [
                    $c->shortname,
                    trim($c->fullname),
                    trim($u->firstname . ' ' . $u->lastname),
                    $u->email,
                    $p['usn'] ?? '',
                    $p['enroll'] ?? '',
                    $program,
                    $p['elective'] ?? '',
                    $p['batch'] ?? '',
                ];
                foreach (self::PHASES as $b) {
                    if (!$exists[$b]) {
                        $val = 'N/A';
                    } else if (isset($submitted[$b][$u->id])) {
                        $val = 'Yes';
                    } else if (isset($draft[$b][$u->id])) {
                        $val = 'Draft';
                    } else {
                        $val = 'No';
                    }
                    $row[] = $val;
                    $summary['phases'][$b][$val]++;
                }
                foreach (self::PHASES as $b) {
                    $mark = $marks[$u->id][$b] ?? null;
                    $row[] = $mark === null ? '' : round($mark, 2);
                    if ($mark !== null) {
                        $summary['phases'][$b]['Marks']++;
                    }
                }
                $total = $coursetotal[$u->id] ?? null;
                $row[] = $total === null ? '' : round($total, 2);
                $row[] = $coursemax > 0 ? round($coursemax, 2) : '';
                if ($total !== null) {
                    $summary['totals']++;
                }

                $rows[] = $row;
                $summary['students']++;
            }
        }

        return ['header' => $header, 'rows' => $rows, 'summary' => $summary];
    }

    /**
     * The gradebook marks for one course's phases, and its course total.
     *
     * Grade items are tied to phases through the activity instances the caller already classified,
     * not by re-reading names, so the marks columns always describe the same activities as the
     * submission-status columns beside them.
     *
     * Where a course holds more than one activity for a phase - the older B*RP courses carry both a
     * "Synopsis Report" and a "Research Project Synopsis" - their marks are added together, so the
     * four phase columns still sum to the course total.
     *
     * @param int $courseid
     * @param array $assignbyb phase => assign instance ids
     * @param array $quizbyb phase => quiz instance ids
     * @return array{0:array,1:array,2:float} [userid => phase => mark, userid => total, total maximum]
     */
    protected static function load_marks(int $courseid, array $assignbyb, array $quizbyb): array {
        global $DB;

        // itemmodule + iteminstance => phase.
        $phaseof = [];
        foreach (self::PHASES as $b) {
            foreach ($assignbyb[$b] ?? [] as $instance) {
                $phaseof['assign:' . $instance] = $b;
            }
            foreach ($quizbyb[$b] ?? [] as $instance) {
                $phaseof['quiz:' . $instance] = $b;
            }
        }

        $marks = [];
        $itemphase = [];
        if ($phaseof) {
            // itemnumber 0 is the activity's own grade; anything else is an outcome or a second
            // grade an activity type can add, which is not the phase mark.
            $items = $DB->get_records_select('grade_items',
                "courseid = :courseid AND itemtype = 'mod' AND itemnumber = 0
                 AND itemmodule IN ('assign', 'quiz')",
                ['courseid' => $courseid], '', 'id, itemmodule, iteminstance, grademax');
            foreach ($items as $item) {
                $key = $item->itemmodule . ':' . $item->iteminstance;
                if (isset($phaseof[$key])) {
                    $itemphase[(int) $item->id] = $phaseof[$key];
                }
            }
        }

        if ($itemphase) {
            [$isql, $ip] = $DB->get_in_or_equal(array_keys($itemphase), SQL_PARAMS_NAMED, 'gi');
            $rs = $DB->get_recordset_select('grade_grades',
                "itemid {$isql} AND finalgrade IS NOT NULL", $ip, '', 'id, itemid, userid, finalgrade');
            foreach ($rs as $row) {
                $phase = $itemphase[(int) $row->itemid];
                $userid = (int) $row->userid;
                $marks[$userid][$phase] = ($marks[$userid][$phase] ?? 0) + (float) $row->finalgrade;
            }
            $rs->close();
        }

        // The course total, as Moodle computed it from the phases.
        $coursetotal = [];
        $coursemax = 0.0;
        $courseitem = $DB->get_record('grade_items',
            ['courseid' => $courseid, 'itemtype' => 'course'], 'id, grademax');
        if ($courseitem) {
            $coursemax = (float) $courseitem->grademax;
            $rs = $DB->get_recordset_select('grade_grades',
                'itemid = :itemid AND finalgrade IS NOT NULL', ['itemid' => $courseitem->id],
                '', 'id, userid, finalgrade');
            foreach ($rs as $row) {
                $coursetotal[(int) $row->userid] = (float) $row->finalgrade;
            }
            $rs->close();
        }

        return [$marks, $coursetotal, $coursemax];
    }

    /**
     * Build the styled .xlsx workbook and write it to $filepath.
     *
     * @param string $filepath absolute path to write the workbook to
     * @return array summary counts (courses, students, per-phase totals)
     */
    public static function build_xlsx(string $filepath): array {
        global $CFG;
        require_once($CFG->libdir . '/phpspreadsheet/vendor/autoload.php');

        $data = self::build_rows();
        $header = $data['header'];
        $rows = $data['rows'];
        $lastrow = count($rows) + 1;
        $lastcol = 'S'; // 19 columns: 9 identity, 4 phase statuses, 4 phase marks, total, maximum.

        $ss = new Spreadsheet();
        $ws = $ss->getActiveSheet();
        $ws->setTitle('Submission Status');

        // Header row.
        $ws->fromArray($header, null, 'A1');
        $ws->getStyle("A1:{$lastcol}1")->getFont()->setBold(true)
            ->getColor()->setRGB('FFFFFF');
        $ws->getStyle("A1:{$lastcol}1")->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1F4E78');
        $ws->getStyle("A1:{$lastcol}1")->getAlignment()->setHorizontal('center')->setVertical('center');

        // Data rows in one bulk write.
        if (!empty($rows)) {
            $ws->fromArray($rows, null, 'A2', true);
        }

        // Column widths.
        $widths = ['A' => 22, 'B' => 42, 'C' => 26, 'D' => 34, 'E' => 15, 'F' => 20,
                   'G' => 34, 'H' => 30, 'I' => 12, 'J' => 12, 'K' => 15, 'L' => 13, 'M' => 18,
                   'N' => 15, 'O' => 20, 'P' => 17, 'Q' => 22, 'R' => 10, 'S' => 12];
        foreach ($widths as $col => $w) {
            $ws->getColumnDimension($col)->setWidth($w);
        }

        $ws->freezePane('A2');
        $ws->setAutoFilter("A1:{$lastcol}1");

        // Colour the four phase columns (J..M) via conditional formatting so the
        // styling is cheap regardless of row count.
        if ($lastrow >= 2) {
            $palette = ['Yes' => 'C6EFCE', 'Draft' => 'FFEB9C', 'No' => 'FFC7CE', 'N/A' => 'D9D9D9'];
            foreach (['J', 'K', 'L', 'M'] as $col) {
                $conds = [];
                foreach ($palette as $text => $rgb) {
                    $c = new Conditional();
                    $c->setConditionType(Conditional::CONDITION_CELLIS);
                    $c->setOperatorType(Conditional::OPERATOR_EQUAL);
                    $c->addCondition('"' . $text . '"');
                    $c->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rgb);
                    $conds[] = $c;
                }
                $ws->getStyle("{$col}2:{$col}{$lastrow}")->setConditionalStyles($conds);
                $ws->getStyle("{$col}2:{$col}{$lastrow}")->getAlignment()->setHorizontal('center');
            }

            // Marks (N..Q), total (R) and its maximum (S) are numbers, so keep them right-aligned
            // and free of a fixed decimal format - a blank cell must stay blank, not become 0.00.
            $ws->getStyle("N2:S{$lastrow}")->getAlignment()->setHorizontal('right');
            $ws->getStyle("R2:R{$lastrow}")->getFont()->setBold(true);
        }

        $writer = new Xlsx($ss);
        $writer->save($filepath);

        // Free memory promptly (cron can be long-lived).
        $ss->disconnectWorksheets();
        unset($ss);

        return $data['summary'];
    }

    /**
     * Generate the workbook and email it to the configured recipients.
     *
     * @param bool $throwonempty when true, throw if there are no recipients
     * @return array{sent:int, failed:string[], recipients:string[], summary:array, filename:string}
     */
    public static function generate_and_send(bool $throwonempty = false): array {
        $recipients = self::get_recipients();
        if (empty($recipients)) {
            if ($throwonempty) {
                throw new \moodle_exception('norecipients', 'local_rpreport');
            }
            return ['sent' => 0, 'failed' => [], 'recipients' => [], 'summary' => [], 'filename' => ''];
        }

        $dir = make_temp_directory('local_rpreport');
        $filename = 'Research Project Submission Status ' . date('Y-m-d') . '.xlsx';
        $filepath = $dir . '/' . clean_filename($filename);

        $summary = self::build_xlsx($filepath);

        $from = \core_user::get_support_user();
        $subject = 'Research Project Submission Status — ' . userdate(time(), '%d %b %Y');
        list($text, $html) = self::build_message($summary);

        $sent = 0;
        $failed = [];
        foreach ($recipients as $email) {
            $to = self::make_recipient($email);
            if (email_to_user($to, $from, $subject, $text, $html, $filepath, $filename)) {
                $sent++;
            } else {
                $failed[] = $email;
            }
        }

        @unlink($filepath);

        return [
            'sent'       => $sent,
            'failed'     => $failed,
            'recipients' => $recipients,
            'summary'    => $summary,
            'filename'   => $filename,
        ];
    }

    /**
     * Construct a minimal user object suitable as an email_to_user() recipient,
     * for addresses that need not correspond to a Moodle account.
     *
     * @param string $email recipient email address
     * @return \stdClass
     */
    protected static function make_recipient(string $email): \stdClass {
        static $seq = 0;
        $seq--;
        $u = new \stdClass();
        $u->id            = $seq; // Negative so it is never mistaken for a real account.
        $u->email         = $email;
        $u->firstname     = $email;
        $u->lastname      = '';
        $u->username      = $email;
        $u->maildisplay   = 1;
        $u->mailformat    = 1; // HTML.
        $u->auth          = 'manual';
        $u->confirmed     = 1;
        $u->suspended     = 0;
        $u->deleted       = 0;
        $u->emailstop     = 0;
        $u->firstnamephonetic = '';
        $u->lastnamephonetic  = '';
        $u->middlename    = '';
        $u->alternatename = '';
        return $u;
    }

    /**
     * Build the plain-text and HTML email bodies from the run summary.
     *
     * @param array $summary summary counts from build_xlsx()
     * @return array [plaintext, html]
     */
    protected static function build_message(array $summary): array {
        $courses = $summary['courses'] ?? 0;
        $students = $summary['students'] ?? 0;
        $when = userdate(time());

        $lines = [];
        $lines[] = "Research Project submission-status report attached.";
        $lines[] = "";
        $lines[] = "Generated: {$when}";
        $lines[] = "Courses:   {$courses}";
        $lines[] = "Students:  {$students}";
        $lines[] = "";
        $lines[] = "Submissions by phase (Yes / Draft / No / N/A), and how many are marked:";
        foreach (self::PHASES as $b) {
            $p = self::phase_summary($summary, $b);
            $label = self::PHASE_LABELS[$b];
            $lines[] = sprintf('  %-18s %d / %d / %d / %d    marked: %d',
                $label, $p['Yes'], $p['Draft'], $p['No'], $p['N/A'], $p['Marks']);
        }
        $lines[] = "";
        $lines[] = 'Students with a course total: ' . ($summary['totals'] ?? 0);
        $text = implode("\n", $lines);
        $html = '<p>Research Project submission-status report attached.</p>'
            . '<p><strong>Generated:</strong> ' . s($when) . '<br>'
            . '<strong>Courses:</strong> ' . $courses . '<br>'
            . '<strong>Students:</strong> ' . $students . '</p>'
            . '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse">'
            . '<tr><th>Phase</th><th>Yes</th><th>Draft</th><th>No</th><th>N/A</th><th>Marked</th></tr>';
        foreach (self::PHASES as $b) {
            $p = self::phase_summary($summary, $b);
            $html .= '<tr><td>' . s(self::PHASE_LABELS[$b]) . '</td>'
                . '<td align="right">' . $p['Yes'] . '</td>'
                . '<td align="right">' . $p['Draft'] . '</td>'
                . '<td align="right">' . $p['No'] . '</td>'
                . '<td align="right">' . $p['N/A'] . '</td>'
                . '<td align="right">' . $p['Marks'] . '</td></tr>';
        }
        $html .= '</table>'
            . '<p><strong>Students with a course total:</strong> ' . ($summary['totals'] ?? 0) . '</p>';

        return [$text, $html];
    }

    /**
     * One phase's counters, with every key present.
     *
     * @param array $summary
     * @param string $phase
     * @return array
     */
    protected static function phase_summary(array $summary, string $phase): array {
        return ($summary['phases'][$phase] ?? [])
            + ['Yes' => 0, 'Draft' => 0, 'No' => 0, 'N/A' => 0, 'Marks' => 0];
    }
}
