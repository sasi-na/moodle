<?php
// READ-ONLY safety checks before converting course cover images to JPEG.
//
// 1. Alpha: JPEG has no transparency. Any cover with a real alpha channel would
//    get a black background if blindly converted, so those must be detected.
// 2. References: converting PNG -> JPEG changes the filename. That is only safe
//    if nothing references the file by name. course/overviewfiles is the
//    dedicated "Course image" field, rendered by listing the filearea, but a
//    course summary could in principle embed it by pluginfile URL.

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');

$fs = get_file_storage();

echo "=== 1. alpha channel scan (PNG covers > 200KB) ===\n";
$rows = $DB->get_records_select('files',
    "component = ? AND filearea = ? AND filename <> ? AND filesize > ? AND mimetype = ?",
    ['course', 'overviewfiles', '.', 200 * 1024, 'image/png'],
    'filesize DESC', '*', 0, 60);

$withalpha = 0;
$checked = 0;
$examples = [];

foreach ($rows as $r) {
    $img = @imagecreatefromstring($fs->get_file_instance($r)->get_content());
    if (!$img) {
        continue;
    }
    $checked++;

    // Sample the image for any pixel with real transparency.
    $w = imagesx($img); $h = imagesy($img);
    $hasalpha = false;
    for ($x = 0; $x < $w && !$hasalpha; $x += max(1, (int)($w / 60))) {
        for ($y = 0; $y < $h; $y += max(1, (int)($h / 60))) {
            $a = (imagecolorat($img, $x, $y) >> 24) & 0x7F;
            if ($a > 0) { $hasalpha = true; break; }
        }
    }
    if ($hasalpha) {
        $withalpha++;
        if (count($examples) < 5) { $examples[] = $r->filename; }
    }
    imagedestroy($img);
}

printf("checked %d PNG covers: %d have transparent pixels\n", $checked, $withalpha);
foreach ($examples as $e) { echo "  alpha: $e\n"; }

echo "\n=== 2. do any course summaries reference overviewfiles by URL? ===\n";
$n = $DB->count_records_select('course', $DB->sql_like('summary', '?'), ['%overviewfiles%']);
printf("courses with 'overviewfiles' in summary HTML: %d\n", $n);

echo "\n=== 3. how many distinct contenthashes (dedup factor) ===\n";
$r = $DB->get_record_sql(
    'SELECT COUNT(*) rows_, COUNT(DISTINCT contenthash) uniq, SUM(filesize) b
       FROM {files}
      WHERE component = ? AND filearea = ? AND filename <> ? AND filesize > ?',
    ['course', 'overviewfiles', '.', 200 * 1024]);
printf("file rows=%d  distinct contenthash=%d  summed filesize=%.1f MB\n",
    $r->rows_, $r->uniq, $r->b / 1048576);
printf("(rows share content: %d duplicate rows point at an already-stored blob)\n",
    $r->rows_ - $r->uniq);

echo "\n(read-only: nothing was written)\n";
