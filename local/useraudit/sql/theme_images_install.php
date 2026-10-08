<?php
// Install WebP-optimised theme_baz chrome images.  2.27 MB -> 272 KB (-88%).
// Applied to jain-prod 2026-08-11. See README-egress-optimisation-20260811.md.
//
// Replaces seven oversized theme images -- a 5000x2500 PNG body background and six
// banners up to 5375px wide -- with WebP at sane dimensions. Theme asset URLs are
// rev-versioned, so these are re-fetched fleet-wide on every themerev bump and the
// saving recurs.
//
// The images are read from assets/theme_baz_webp_20260811/ rather than generated
// here: prod GD has no WebP support (and there is no Imagick), so they were
// converted offline with `cwebp -q 82` after downscaling. Regenerate with:
//
//     sips --resampleWidth 1920 in.jpg -o tmp.jpg     # banners (were 5375px)
//     sips --resampleWidth 2560 in.png -o tmp.png     # bodybgimg (was 5000px)
//     cwebp -q 82 tmp.jpg -o out.webp
//
// Deliberately NOT changed: block5itemimg2-5 (3.7 MB of 1920x1080 PNGs). They are
// never requested -- no template renders them; they exist only in
// theme/baz/settings/block5.php and the pluginfile handler in theme/baz/lib.php.
//
// AFTER --commit you MUST restart the pods:
//     kubectl rollout restart deploy/moodle -n <namespace>
// theme_reset_all_caches() bumps themerev and wipes the theme localcache, which
// undoes the warm-theme-css pre-warm on running pods -- without a restart
// theme/styles.php falls back to serving 2.3 MB with `no-store`.
//
// Usage: php theme_images_install.php [--commit]

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/filelib.php');

$commit = in_array('--commit', $argv, true);
$assetdir = __DIR__ . '/assets/theme_baz_webp_20260811';

// setting name (also the filearea) => filename to install
$map = [
    'bodybgimg'           => 'dark.webp',
    'loginbg'             => 'JO-banner.webp',
    'dashboardbannerimg1' => 'LMS Banner 3.webp',
    'dashboardbannerimg2' => 'LMS Banner.webp',
    'dashboardbannerimg3' => 'Jan 25 Sem II.webp',
    'dashboardbannerimg4' => 'July 25 Sem I.webp',
    'dashboardbannerimg5' => 'Quiz Creative LMS Banner.webp',
];

$fs = get_file_storage();
$syscontext = context_system::instance();

// webp is not registered in a stock install here, so a .webp would be stored and
// served as document/unknown. theme_baz's own accepted_types already lists .webp --
// only the filetype registration was missing.
$types = core_filetypes::get_types();
if (!isset($types['webp'])) {
    if ($commit) {
        core_filetypes::add_type('webp', 'image/webp', 'image', ['web_image'], false, false);
        echo "registered filetype: webp => image/webp (group image, web_image)\n\n";
    } else {
        echo "WOULD register filetype: webp => image/webp\n\n";
    }
} else {
    echo "filetype webp: already registered\n\n";
}

$totalold = 0;
$totalnew = 0;
$missing  = 0;

foreach ($map as $area => $newname) {
    $path = $assetdir . '/' . $newname;
    if (!is_readable($path)) {
        printf("MISSING asset       %s\n", $path);
        $missing++;
        continue;
    }
    $data = file_get_contents($path);

    $old = $fs->get_area_files($syscontext->id, 'theme_baz', $area, 0, 'itemid', false);
    $oldsize = 0;
    $oldnames = [];
    foreach ($old as $f) {
        $oldsize += $f->get_filesize();
        $oldnames[] = $f->get_filename();
    }

    printf("%-21s %8d B -> %8d B  (%s)\n", $area, $oldsize, strlen($data), $newname);
    printf("%-21s   old: %s\n", '', $oldnames ? implode(', ', $oldnames) : 'none');
    printf("%-21s   config: %s -> %s\n", '',
        var_export(get_config('theme_baz', $area), true), '/' . $newname);

    $totalold += $oldsize;
    $totalnew += strlen($data);

    if (!$commit) {
        continue;
    }

    foreach ($old as $f) {
        $f->delete();
    }
    $fs->create_file_from_string([
        'contextid' => $syscontext->id,
        'component' => 'theme_baz',
        'filearea'  => $area,
        'itemid'    => 0,
        'filepath'  => '/',
        'filename'  => $newname,
    ], $data);
    // The setting stores the filename with a leading slash, e.g. '/dark.webp'.
    set_config($area, '/' . $newname, 'theme_baz');
}

printf("\nTOTAL %d B -> %d B (-%.0f%%)   missing assets=%d\n",
    $totalold, $totalnew,
    $totalold ? 100 * (1 - $totalnew / $totalold) : 0, $missing);

if ($commit) {
    theme_reset_all_caches();
    echo "\ntheme caches reset (themerev bumped).\n";
    echo "NOW RUN: kubectl rollout restart deploy/moodle -n <namespace>\n";
    echo "Otherwise theme/styles.php serves 2.3 MB with no-store until pods restart.\n";
} else {
    echo "\nDRY RUN -- nothing written. Re-run with --commit\n";
}
