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

/**
 * Capture and replay the IIITD site configuration that lives only in this
 * database.
 *
 * The cutover re-imports the AWS production database. That box has never run
 * theme_baz -- its themes are apple, boost, classic and mb2aofk -- so the dump
 * contains no rows for it at all. Importing therefore discards the entire
 * bespoke configuration built for this migration: 400-odd theme_baz settings,
 * the site theme itself, four uploaded brand files, and a handful of smaller
 * data-level fixes. None of it is recoverable from the dump, and none of it is
 * recorded in config_log either -- set_config() from CLI does not write there,
 * which is worth knowing before trusting that table as a change record.
 *
 * Related tools, and why this is not either of them:
 *
 *   deploy/tools/iiitd-baz-settings.php seeds theme_baz for IIITD from about
 *   thirty hand-chosen values. It is how the theme was first configured, and it
 *   is the right starting point for a site that has none. It is not a restore:
 *   it carries no files, does not touch the site theme, and knows nothing of
 *   the several hundred settings adjusted since it was written.
 *
 *   deploy/tools/export-tenant-config.php emits a portable SQL seed for
 *   standing up a NEW tenant from a clean schema. Different job entirely.
 *
 * This captures what is actually in the database right now and puts it back.
 *
 * Usage:
 *   php replay-theme-config.php --export --dir=/tmp/iiitd-restore
 *       Run BEFORE the import. Writes settings.json plus the brand files.
 *
 *   php replay-theme-config.php --apply --dir=/tmp/iiitd-restore [--dry-run]
 *       Run AFTER the import and upgrade. Restores everything captured, then
 *       applies the fixes that are not settings at all.
 *
 * @package   local_iiitdcutover
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

// Run from anywhere -- this is a cutover tool, not a plugin, and it is copied
// into a pod rather than living in the tree.
$root = getenv('MOODLE_ROOT') ?: '/var/www/html';
if (!is_readable($root . '/config.php')) {
    fwrite(STDERR, "cannot find Moodle config.php under $root; set MOODLE_ROOT\n");
    exit(1);
}
require($root . '/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/lib/blocklib.php');

[$options, $unrecognised] = cli_get_params([
    'export'  => false,
    'apply'   => false,
    'dry-run' => false,
    'dir'     => '',
    'help'    => false,
], ['h' => 'help']);

if ($options['help'] || (!$options['export'] && !$options['apply'])) {
    cli_writeln("Capture and replay IIITD's database-only site configuration.

  --export --dir=PATH   capture current state (run BEFORE the re-import)
  --apply  --dir=PATH   restore it (run AFTER the import and upgrade)
  --dry-run             with --apply, report what would change and stop
");
    exit(0);
}

$dir = rtrim((string) $options['dir'], '/');
if ($dir === '') {
    cli_error('--dir is required');
}

// Settings whose values belong to the code or the cache, not to this site's
// configuration. Replaying a plugin version would lie to the upgrade system
// about what has run; themerev is a cache-buster Moodle regenerates.
const SKIP = ['version', 'themerev'];

/** @var string Plugins whose settings live in this database rather than config.php. */
const PLUGINS = ['theme_baz'];

/** @var string Brand files uploaded through the theme's own settings. */
const FILEAREAS = ['customsidebarlogo', 'customsidebardmlogo', 'customlogo', 'customdmlogo',
                   'loginlogo', 'loginbg', 'favicon', 'favicon16', 'favicon32',
                   'faviconsafaritab', 'footerbgimg', 'fontfiles'];

if ($options['export']) {
    export_state($dir);
} else {
    apply_state($dir, (bool) $options['dry-run']);
}

/**
 * Capture everything the re-import would discard.
 *
 * @param string $dir
 * @return void
 */
function export_state(string $dir): void {
    global $DB, $CFG;

    if (!is_dir($dir) && !mkdir($dir, 0777, true)) {
        cli_error("cannot create $dir");
    }

    $state = [
        'captured'  => date('c'),
        'wwwroot'   => $CFG->wwwroot,
        'coretheme' => get_config('core', 'theme'),
        'plugins'   => [],
        'files'     => [],
    ];

    // --- Plugin settings. Forced values are skipped: config.php already
    // asserts them on every request, so replaying them into the database would
    // create a second, silently ignored copy that drifts. ---
    $forcedall = $CFG->forced_plugin_settings ?? [];
    foreach (PLUGINS as $plugin) {
        $forced = $forcedall[$plugin] ?? [];
        $rows = $DB->get_records('config_plugins', ['plugin' => $plugin], 'name');
        $kept = [];
        foreach ($rows as $row) {
            if (in_array($row->name, SKIP, true) || array_key_exists($row->name, $forced)) {
                continue;
            }
            $kept[$row->name] = $row->value;
        }
        $state['plugins'][$plugin] = $kept;
        cli_writeln(sprintf('  %-22s %4d stored, %d forced, %d captured',
            $plugin, count($rows), count($forced), count($kept)));
    }

    // --- Brand files. The moodledata rsync overwrites these, so their content
    // is copied out rather than merely referenced. ---
    $fs = get_file_storage();
    $ctx = context_system::instance();
    foreach (PLUGINS as $plugin) {
        foreach (FILEAREAS as $area) {
            foreach ($fs->get_area_files($ctx->id, $plugin, $area, false, 'itemid', false) as $file) {
                $name = $file->get_filename();
                $blob = $dir . '/files/' . $plugin . '/' . $area;
                if (!is_dir($blob) && !mkdir($blob, 0777, true)) {
                    cli_error("cannot create $blob");
                }
                file_put_contents($blob . '/' . $name, $file->get_content());
                $state['files'][] = [
                    'plugin'   => $plugin,
                    'filearea' => $area,
                    'itemid'   => (int) $file->get_itemid(),
                    'filepath' => $file->get_filepath(),
                    'filename' => $name,
                    'size'     => (int) $file->get_filesize(),
                ];
                cli_writeln(sprintf('  file %-22s %-30s %d bytes', $area, $name, $file->get_filesize()));
            }
        }
    }

    file_put_contents($dir . '/settings.json', json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    cli_writeln("\ncaptured to $dir/settings.json");
    cli_writeln('  core theme : ' . var_export($state['coretheme'], true));
    cli_writeln('  files      : ' . count($state['files']));
}

/**
 * Restore what was captured, then apply the fixes that are not settings.
 *
 * @param string $dir
 * @param bool $dryrun
 * @return void
 */
function apply_state(string $dir, bool $dryrun): void {
    global $DB;

    $path = $dir . '/settings.json';
    if (!is_readable($path)) {
        cli_error("no capture at $path -- was --export run before the import?");
    }
    $state = json_decode(file_get_contents($path), true);
    if (!is_array($state) || empty($state['plugins'])) {
        cli_error("capture at $path is unreadable or empty");
    }

    cli_writeln('capture from ' . ($state['captured'] ?? '?') . ($dryrun ? '  [DRY RUN]' : ''));

    // --- Settings. ---
    foreach ($state['plugins'] as $plugin => $settings) {
        $set = 0; $same = 0;
        foreach ($settings as $name => $value) {
            if ((string) get_config($plugin, $name) === (string) $value) {
                $same++;
                continue;
            }
            if (!$dryrun) {
                set_config($name, $value, $plugin);
            }
            $set++;
        }
        cli_writeln(sprintf('  %-22s %d restored, %d already correct', $plugin, $set, $same));
    }

    // --- Site theme. The import restores prod's theme, which this image does
    // not contain, so the site would fall back to Boost. ---
    $theme = $state['coretheme'] ?? 'baz';
    if (get_config('core', 'theme') !== $theme) {
        cli_writeln('  site theme               ' . var_export(get_config('core', 'theme'), true) . " -> '$theme'");
        if (!$dryrun) {
            set_config('theme', $theme);
        }
    } else {
        cli_writeln("  site theme               already '$theme'");
    }

    // --- Brand files. ---
    $fs = get_file_storage();
    $ctx = context_system::instance();
    $restored = 0;
    foreach (($state['files'] ?? []) as $f) {
        $blob = $dir . '/files/' . $f['plugin'] . '/' . $f['filearea'] . '/' . $f['filename'];
        if (!is_readable($blob)) {
            cli_writeln('  MISSING BLOB ' . $blob);
            continue;
        }
        $exists = $fs->get_file($ctx->id, $f['plugin'], $f['filearea'], $f['itemid'],
            $f['filepath'], $f['filename']);
        if ($exists) {
            if ($exists->get_contenthash() === sha1_file($blob)) {
                continue;
            }
            if (!$dryrun) {
                $exists->delete();
            }
        }
        if (!$dryrun) {
            $fs->create_file_from_pathname([
                'contextid' => $ctx->id,
                'component' => $f['plugin'],
                'filearea'  => $f['filearea'],
                'itemid'    => $f['itemid'],
                'filepath'  => $f['filepath'],
                'filename'  => $f['filename'],
            ], $blob);
        }
        $restored++;
    }
    cli_writeln("  brand files              $restored restored");

    // --- Fixes that are not settings. ---

    // The calendar block pinned to the admin search page. It is IIITD data, so
    // the re-import brings it back.
    $stray = $DB->get_records('block_instances',
        ['blockname' => 'calendar_month', 'pagetypepattern' => 'admin-search']);
    foreach ($stray as $block) {
        cli_writeln('  removing calendar_month on admin-search (id ' . $block->id . ')');
        if (!$dryrun) {
            blocks_delete_instance($block);
        }
    }
    if (!$stray) {
        cli_writeln('  calendar on admin-search already absent');
    }

    // Personal theme overrides pointing at themes this image does not ship.
    // Left alone, those users get a broken theme rather than the site's.
    $installed = array_keys(core_component::get_plugin_list('theme'));
    [$insql, $params] = $DB->get_in_or_equal($installed, SQL_PARAMS_NAMED, 'th', false);
    $broken = $DB->get_records_select_menu('user',
        "deleted = 0 AND theme <> '' AND theme $insql", $params, '', 'id, theme');
    if ($broken) {
        $counts = array_count_values($broken);
        foreach ($counts as $t => $c) {
            cli_writeln("  clearing personal theme '$t' from $c user(s)");
        }
        if (!$dryrun) {
            $DB->set_field_select('user', 'theme', '',
                "deleted = 0 AND theme <> '' AND theme $insql", $params);
        }
    } else {
        cli_writeln('  no broken personal theme overrides');
    }

    if ($dryrun) {
        cli_writeln("\nDRY RUN -- nothing written. Re-run without --dry-run to apply.");
        return;
    }

    purge_all_caches();
    cli_writeln("\ncaches purged on THIS pod. Run admin/cli/purge_caches.php on every OTHER");
    cli_writeln('replica too -- a purge from one pod does not clear its siblings.');
}
