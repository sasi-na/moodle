<?php
namespace local_studentid;

defined('MOODLE_INTERNAL') || die();

class idcard_generator {

    /**
     * Fill the JAIN Student ID card PDF template with student data and photo.
     *
     * Calls cli/idcard_fill.py (PyMuPDF) which overlays text and the passport
     * photo onto the official two-page PDF template stored in pix/idcard_template.pdf.
     *
     * @param array  $studentdata  Keys: fullname, program, batch, usn, dob,
     *                             blood_group, contact, city, state, country
     * @param string $photodata    Raw JPEG/PNG bytes, or empty string
     * @return string              Binary PDF content ready to store in Moodle files
     * @throws \moodle_exception   On script failure or missing dependencies
     */
    public static function generate(array $studentdata, string $photodata): string {
        global $CFG;

        $tmpdir    = make_temp_directory('local_studentid');
        $uniq      = uniqid('sid_', true);
        $json_file = $tmpdir . '/' . $uniq . '_data.json';
        $out_file  = $tmpdir . '/' . $uniq . '_card.pdf';
        $photo_file = '';

        // Write photo bytes to a temp file so the script can read it.
        if (!empty($photodata)) {
            $photo_file = $tmpdir . '/' . $uniq . '_photo';
            file_put_contents($photo_file, $photodata);
        }

        $payload = [
            'student' => [
                'fullname'    => $studentdata['fullname']    ?? '',
                'program'     => $studentdata['program']     ?? '',
                'batch'       => $studentdata['batch']       ?? '',
                'usn'         => $studentdata['usn']         ?? '',
                'dob'         => $studentdata['dob']         ?? '',
                'blood_group' => $studentdata['blood_group'] ?? '',
                'contact'     => $studentdata['contact']     ?? '',
                'city'        => $studentdata['city']        ?? '',
                'state'       => $studentdata['state']       ?? '',
                'country'     => $studentdata['country']     ?? '',
            ],
            'photo_path' => $photo_file,
        ];
        file_put_contents($json_file, json_encode($payload, JSON_UNESCAPED_UNICODE));

        $script = realpath($CFG->dirroot . '/local/studentid/cli/idcard_fill.py');
        if (!$script || !is_file($script)) {
            self::cleanup([$json_file, $photo_file]);
            throw new \moodle_exception('erroridcardgen', 'local_studentid', '', 'Script not found');
        }

        $cmd     = 'python3 ' . escapeshellarg($script) . ' '
                 . escapeshellarg($json_file) . ' '
                 . escapeshellarg($out_file) . ' 2>&1';
        $output  = shell_exec($cmd);
        $exists  = file_exists($out_file) && filesize($out_file) > 0;

        self::cleanup([$json_file, $photo_file]);

        if (!$exists) {
            throw new \moodle_exception('erroridcardgen', 'local_studentid', '',
                trim((string)$output));
        }

        $pdf = file_get_contents($out_file);
        @unlink($out_file);

        return $pdf;
    }

    private static function cleanup(array $files): void {
        foreach ($files as $f) {
            if ($f && file_exists($f)) {
                @unlink($f);
            }
        }
    }
}
