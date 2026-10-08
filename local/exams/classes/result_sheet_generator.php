<?php
namespace local_exams;

defined('MOODLE_INTERNAL') || die();

use local_exams\analytics\scorecard;

/**
 * Generates and caches a PDF result sheet for a student using Moodle's bundled
 * TCPDF wrapper. Mirrors the storage approach of local_studentid's ID-card flow.
 *
 * @package   local_exams
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class result_sheet_generator {

    /**
     * Get the cached result sheet for a user, generating it if missing.
     *
     * @param int $userid
     * @param bool $forceregenerate
     * @return \stored_file
     */
    public static function get_or_generate(int $userid, bool $forceregenerate = false): \stored_file {
        global $DB;

        $syscontext = \context_system::instance();
        $fs = get_file_storage();
        $record = $DB->get_record('local_exams_resultsheets', ['userid' => $userid]);

        if ($record && !$forceregenerate) {
            $file = $fs->get_file($syscontext->id, 'local_exams', 'resultsheet', $record->id, '/', $record->filename);
            if ($file) {
                return $file;
            }
        }

        return self::generate($userid);
    }

    /**
     * Build the PDF and store it, returning the stored file.
     *
     * @param int $userid
     * @return \stored_file
     */
    public static function generate(int $userid): \stored_file {
        global $DB, $CFG, $SITE;

        require_once($CFG->libdir . '/pdflib.php');

        $student = manager::get_student_data($userid);
        $card = scorecard::get_for_user($userid);

        $pdf = new \pdf();
        $pdf->SetCreator('Moodle');
        $pdf->SetTitle('Result sheet');
        $pdf->SetMargins(15, 18, 15);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, format_string($SITE->fullname), 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 8, 'Statement of results', 0, 1, 'C');
        $pdf->Ln(4);

        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 7, 'Name: ' . $student->fullname, 0, 1);
        if ($student->idnumber) {
            $pdf->Cell(0, 7, 'ID: ' . $student->idnumber, 0, 1);
        }
        if ($student->program) {
            $pdf->Cell(0, 7, 'Programme: ' . $student->program, 0, 1);
        }
        if ($card['overall']['percentage'] !== null) {
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(0, 7, 'Overall: ' . $card['overall']['percentage'] . '%', 0, 1);
        }
        $pdf->Ln(2);

        foreach ($card['semesters'] as $sem) {
            $pdf->SetFont('helvetica', 'B', 12);
            $title = $sem['name'];
            if ($sem['percentage'] !== null) {
                $title .= '  (' . $sem['percentage'] . '%)';
            }
            $pdf->Cell(0, 8, $title, 0, 1);

            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(95, 7, 'Exam', 1, 0);
            $pdf->Cell(30, 7, 'Marks', 1, 0, 'R');
            $pdf->Cell(25, 7, 'Max', 1, 0, 'R');
            $pdf->Cell(30, 7, 'Status', 1, 1, 'C');

            $pdf->SetFont('helvetica', '', 9);
            foreach ($sem['quizzes'] as $q) {
                $pdf->Cell(95, 7, $q['quizname'], 1, 0);
                $pdf->Cell(30, 7, $q['present'] ? (string) $q['finalgrade'] : '-', 1, 0, 'R');
                $pdf->Cell(25, 7, (string) $q['grademax'], 1, 0, 'R');
                $pdf->Cell(30, 7, $q['present'] ? 'Appeared' : 'Absent', 1, 1, 'C');
            }
            $pdf->Ln(3);
        }

        $content = $pdf->Output('result.pdf', 'S');

        // Upsert the metadata row first so we have an itemid.
        $now = time();
        $filename = 'result-' . $userid . '.pdf';
        $record = $DB->get_record('local_exams_resultsheets', ['userid' => $userid]);
        if ($record) {
            $record->filename = $filename;
            $record->timegenerated = $now;
            $DB->update_record('local_exams_resultsheets', $record);
            $itemid = $record->id;
        } else {
            $itemid = $DB->insert_record('local_exams_resultsheets', (object) [
                'userid'        => $userid,
                'filename'      => $filename,
                'downloadcount' => 0,
                'lastdownloaded'=> 0,
                'timegenerated' => $now,
            ]);
        }

        $syscontext = \context_system::instance();
        $fs = get_file_storage();
        $fileinfo = [
            'contextid' => $syscontext->id,
            'component' => 'local_exams',
            'filearea'  => 'resultsheet',
            'itemid'    => $itemid,
            'filepath'  => '/',
            'filename'  => $filename,
        ];
        // Replace any stale file.
        $old = $fs->get_file($syscontext->id, 'local_exams', 'resultsheet', $itemid, '/', $filename);
        if ($old) {
            $old->delete();
        }
        return $fs->create_file_from_string($fileinfo, $content);
    }
}
