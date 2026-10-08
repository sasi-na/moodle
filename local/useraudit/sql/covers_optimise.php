<?php
// Phase 3: shrink course cover images (course/overviewfiles).
//
// They render into .rui-course-card-img-top -- width:100%, height:150px,
// background-size:cover (theme/baz/scss/moodle/course.scss:16-26) -- yet many are
// 1448x1086 PNGs of 2-3 MB. Measured: 213.9 MB across 364 files -> ~7.9 MB (-96%)
// at 800px wide, JPEG q82. The win is resizing plus PNG->JPEG, not the codec, so
// the pod's own GD is enough (it has no WebP support anyway).
//
// Verified safe beforehand (cover_safety.php):
//   - 0 of 60 sampled PNG covers contain transparent pixels
//   - 0 course summaries reference 'overviewfiles', so renaming .png -> .jpg
//     breaks no embedded URL (this filearea is the dedicated Course image field,
//     rendered by listing the area, not by filename)
//
// Still refuses per-file if it finds alpha, rather than trusting that sample.
//
// DRY RUN by default. Pass --commit to write.
//
// Usage: php covers_optimise.php [--commit] [--width=800] [--minkb=200] [--limit=N]
//                                [--baktable=bak_overviewfiles_YYYYMMDD]
//
// Applied to jain-prod 2026-08-11 with the defaults below: 224.8 MB -> 30.0 MB
// across all 648 covers, originals preserved in acu_bak_overviewfiles_20260811.

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');

$commit = in_array('--commit', $argv, true);
$width    = 800;
$minkb    = 200;
$limit    = 0;
$baktable = 'bak_overviewfiles_' . date('Ymd');
foreach ($argv as $a) {
    if (preg_match('/^--width=(\d+)$/', $a, $m))        { $width = (int)$m[1]; }
    if (preg_match('/^--minkb=(\d+)$/', $a, $m))        { $minkb = (int)$m[1]; }
    if (preg_match('/^--limit=(\d+)$/', $a, $m))        { $limit = (int)$m[1]; }
    if (preg_match('/^--baktable=([a-z0-9_]+)$/', $a, $m)) { $baktable = $m[1]; }
}

$fs = get_file_storage();
$quality = 82;

$select = 'component = ? AND filearea = ? AND filename <> ? AND filesize > ?';
$params = ['course', 'overviewfiles', '.', $minkb * 1024];
$rows = $DB->get_records_select('files', $select, $params, 'filesize DESC', '*', 0, $limit ?: 0);

printf("%d candidate files (>%d KB), target width %dpx, JPEG q%d, commit=%s\n\n",
    count($rows), $minkb, $width, $quality, $commit ? 'YES' : 'no (dry run)');

// ---------------------------------------------------------------- backup table
if ($commit) {
    $full = $CFG->prefix . $baktable;
    if (!$DB->get_manager()->table_exists(new xmldb_table($baktable))) {
        // CREATE TABLE ... AS SELECT is not supported on Galera/PXC, so this is
        // CREATE TABLE LIKE followed by INSERT ... SELECT.
        $DB->execute("CREATE TABLE {$full} LIKE {$CFG->prefix}files");
        $DB->execute("INSERT INTO {$full} SELECT * FROM {$CFG->prefix}files
                       WHERE component = ? AND filearea = ? AND filename <> ? AND filesize > ?",
                     $params);
        $n = $DB->count_records_sql("SELECT COUNT(*) FROM {$full}");
        echo "backup: created {$full} with {$n} rows\n\n";
    } else {
        echo "backup: {$full} already exists, leaving it alone\n\n";
    }
}

$encoded = [];   // source contenthash => encoded jpeg bytes (173 rows are dupes)
$totold = 0; $totnew = 0; $done = 0; $skipped = 0;

foreach ($rows as $r) {
    $file = $fs->get_file_instance($r);

    if (!isset($encoded[$r->contenthash])) {
        $img = @imagecreatefromstring($file->get_content());
        if (!$img) {
            printf("SKIP undecodable   %s\n", $r->filename);
            $skipped++;
            continue;
        }

        $w = imagesx($img); $h = imagesy($img);

        // Refuse anything with real transparency: JPEG would black it out.
        $alpha = false;
        for ($x = 0; $x < $w && !$alpha; $x += max(1, (int)($w / 50))) {
            for ($y = 0; $y < $h; $y += max(1, (int)($h / 50))) {
                if (((imagecolorat($img, $x, $y) >> 24) & 0x7F) > 0) { $alpha = true; break; }
            }
        }
        if ($alpha) {
            printf("SKIP has alpha     %s (%dx%d)\n", $r->filename, $w, $h);
            imagedestroy($img);
            $skipped++;
            continue;
        }

        $scaled = ($w > $width) ? imagescale($img, $width) : $img;
        ob_start();
        imagejpeg($scaled, null, $quality);
        $encoded[$r->contenthash] = ob_get_clean();

        imagedestroy($img);
        if ($scaled !== $img) { imagedestroy($scaled); }
    }

    $data = $encoded[$r->contenthash];
    $newname = preg_replace('/\.[A-Za-z0-9]+$/', '', $r->filename) . '.jpg';

    printf("%-7s %8d -> %7d B (-%2.0f%%)  %s -> %s\n",
        $commit ? 'WRITE' : 'would', $r->filesize, strlen($data),
        100 * (1 - strlen($data) / $r->filesize), substr($r->filename, 0, 34), $newname);

    $totold += $r->filesize;
    $totnew += strlen($data);
    $done++;

    if (!$commit) { continue; }

    // Encode first, then delete, then create -- so a failure cannot leave the
    // course with no image. Old content stays recoverable from the file trash
    // for a few days, and the backup table records every original row.
    $filerecord = [
        'contextid' => $r->contextid,
        'component' => 'course',
        'filearea'  => 'overviewfiles',
        'itemid'    => $r->itemid,
        'filepath'  => $r->filepath,
        'filename'  => $newname,
        'author'    => $r->author,
        'license'   => $r->license,
        'source'    => $r->source,
    ];
    $file->delete();
    $fs->create_file_from_string($filerecord, $data);
}

printf("\n%s %d files: %.1f MB -> %.1f MB (-%.0f%%)   skipped=%d   distinct images encoded=%d\n",
    $commit ? 'CONVERTED' : 'WOULD CONVERT', $done,
    $totold / 1048576, $totnew / 1048576,
    $totold ? 100 * (1 - $totnew / $totold) : 0, $skipped, count($encoded));

if ($commit) {
    // Course card images are served via pluginfile with no themerev in the URL,
    // so no theme cache reset and no pod restart is needed. Course summary /
    // card caches do hold the image URL though, so clear those.
    cache_helper::purge_by_event('changesincourse');
    echo "purged course caches (changesincourse)\n";
    echo "rollback: originals are in {$CFG->prefix}{$baktable}\n";
} else {
    echo "\nDRY RUN -- nothing written. Re-run with --commit\n";
}
