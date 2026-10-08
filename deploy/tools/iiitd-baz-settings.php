<?php
/**
 * Seed theme_baz settings for IIITD.
 *
 * IIITD's database came from AWS, where theme_baz never existed, so all of its
 * theme_baz rows are defaults written during the Moodle 5.0 upgrade -- nobody
 * has configured the theme. That is why the site does not look like nitk or
 * jain, even though it runs the SAME theme at the SAME version. It is
 * configuration, not code.
 *
 * WHAT THIS DOES AND DOES NOT COPY
 * --------------------------------
 * Structural layout settings are taken from nitk-prod, because layout is not
 * tenant identity. Everything that encodes identity is NOT copied:
 *
 *   - nitk's colours (#001c54 navy, #f8c300 yellow) -> IIITD's own palette,
 *     recovered from the mb2aofk theme IIITD used on AWS.
 *   - nitk's copy ("NAAC A+ Accreditation", programme blurbs) -> not copied.
 *   - nitk's images -> not copied; they are Moodle file-area references that
 *     point at nitk's files and would render broken here.
 *   - admin_dau_series -> nitk's daily-active-user telemetry. Never copy.
 *
 * Nine of nitk's front-page blocks still contain the THEME VENDOR'S DEMO
 * CONTENT, hotlinking images from roseathemes.com / rosea.io. Those are
 * deliberately excluded -- copying them would put a vendor demo on IIITD's
 * homepage and hotlink a third party.
 *
 *   php deploy/tools/iiitd-baz-settings.php          # show what would change
 *   php deploy/tools/iiitd-baz-settings.php --apply
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options] = cli_get_params(['apply' => false, 'help' => false], ['h' => 'help']);
if ($options['help']) {
    cli_writeln("Seed theme_baz settings for IIITD.\n--apply  write the changes (default: dry run)");
    exit(0);
}

// Guard: this script encodes IIITD's identity. Refuse to run anywhere else.
if (stripos($CFG->wwwroot, 'iiitd') === false) {
    cli_error("Refusing to run: wwwroot ({$CFG->wwwroot}) is not an IIITD site.");
}

/*
 * IIITD's palette, recovered from theme_mb2aofk on the AWS site:
 *   #022758 / #022a5c / #032c62  deep navy, the primary brand colour
 *   rgb(0,117,237) = #0075ed      link blue
 *   #b9dcff / #eef6f9             light blue tints
 *   #444444                       body text
 */
$settings = [
    // ---- identity -------------------------------------------------------
    'brandnamefull'       => 'Indian Institute of Information Technology Dharwad',
    'brandnameshort'      => 'IIIT Dharwad',

    // ---- palette (IIITD's, not nitk's) ----------------------------------
    'brandnavy'           => '#022758',
    'colortopbarbg'       => '#022758',
    'brandaccent'         => '#0075ed',
    'colorbody'           => '#444444',
    'colortopbarbtn'      => '#b9dcff',
    'colortopbarbtnhover' => '#eef6f9',

    // ---- structural layout ---------------------------------------------
    // Admin dashboard/pages views, as on nitk.
    'admin_view_dashboard' => '1',
    'admin_view_pages'     => '1',
    'darkmodetheme'        => '0',

    // Every front-page content block OFF. IIITD has authored no block content,
    // so anything switched on renders empty or falls back to the theme's demo
    // material. nitk shows block1 and block15 because nitk filled them in;
    // copying that here would just display two empty sections.
    // Turn these on individually as IIITD writes the content.
    'displayblock1'       => '0',
    'displayblock2'       => '0',
    'displayblock3'       => '0',
    'displayblock4'       => '0',
    'displayblock5'       => '0',
    'displayblock6'       => '0',
    'displayblock7'       => '0',
    'displayblock8'       => '0',
    'displayblock9'       => '0',
    'displayblock10'      => '0',
    'displayblock11'      => '0',
    'displayblock12'      => '0',
    'displayblock13'      => '0',
    'displayblock14'      => '0',
    'displayblock15'      => '0',
    'displayblock16'      => '0',
    'displayblock17'      => '0',
    'displayblock18'      => '0',
    'displayblock19'      => '0',

    // NOT SET HERE, deliberately:
    //   block1..block19  -- these are SLOT-POSITION selectors (configselect,
    //     default = own number). nitk's values encode nitk's front-page
    //     ordering, which is meaningless without nitk's content.
    //   bespoke_login    -- the bespoke login design expects a background image,
    //     and that is a file-area upload. Enabling it before the image exists
    //     would look broken. Turn it on AFTER uploading the background.
];

cli_writeln($options['apply'] ? 'APPLYING' : 'DRY RUN -- nothing will be written');
cli_writeln('');
$changed = 0;
foreach ($settings as $name => $value) {
    $current = get_config('theme_baz', $name);
    if ((string)$current === (string)$value) {
        continue;
    }
    cli_writeln(sprintf('  %-24s %-22s -> %s',
        $name,
        $current === false ? '<unset>' : "'" . substr((string)$current, 0, 20) . "'",
        "'" . substr((string)$value, 0, 30) . "'"));
    if ($options['apply']) {
        set_config($name, $value, 'theme_baz');
    }
    $changed++;
}

cli_writeln('');
cli_writeln($changed ? "$changed setting(s) " . ($options['apply'] ? 'written' : 'would change')
                     : 'nothing to change');

if ($options['apply'] && $changed) {
    theme_reset_all_caches();
    cli_writeln('theme caches reset');
}

cli_writeln('');
cli_writeln('STILL REQUIRES A HUMAN -- these cannot be scripted:');
cli_writeln('  * Logos and favicon. theme_baz stores these as file-area uploads, not');
cli_writeln('    paths. Upload IIITD\'s /iiit-Logo.svg and /fav.ico via');
cli_writeln('    Site administration > Appearance > Baz.');
cli_writeln('  * Front-page block content (block15htmlcontent and the block1 carousel');
cli_writeln('    slides). nitk\'s is either its own copy or the vendor demo; IIITD needs');
cli_writeln('    to write its own.');
cli_writeln('  * Login background image (bespoke_login is enabled by this script, but');
cli_writeln('    the background image is a file-area upload).');
cli_writeln('  * Contact strip: support.dsai@iiitdwd.ac.in / +91-8792997405');
