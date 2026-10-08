<?php
// Ensure this pod's local theme CSS cache holds the sheet for the CURRENT theme
// revision. Idempotent and silent when there is nothing to do.
//
// WHY THIS EXISTS
//
// $CFG->localcachedir is /tmp/moodle-cache, a per-pod emptyDir. If it has no
// all_<subrev>.css for the current revision, theme/styles.php falls back to
// $CFG->tempdir/theme/<name>/all.css and serves it via css_send_temporary_css()
// -- which sets `Cache-Control: no-cache, no-store, must-revalidate`. It then
// tries to regenerate while holding a 1-SECOND lock, loses that race under
// production load, and never populates the cache. The result is ~2.3MB of CSS
// re-downloaded on every page view for the life of the pod. On jain-prod that was
// ~31% of all egress.
//
// The warm-theme-css initContainer covers pod startup. This script exists for the
// other case: anything that bumps themerev mid-life -- an admin saving a theme
// setting, purge_caches -- calls theme_reset_all_caches(), which bumps the revision
// AND deletes the theme localcache. Running pods then serve `no-store` until
// someone restarts them, and nobody clicking Save knows to run kubectl.
//
// THIS CANNOT BE A MOODLE SCHEDULED TASK. Cron runs in a separate pod with its own
// `tmp-cache` emptyDir, so a task would warm the cron pod's cache -- which no user
// ever reads. It has to run inside each web pod, which is why the deployment runs
// it from a sidecar.
//
// Deliberately NOT admin/cli/build_theme_css.php: that bumps the sub-revision
// globally, so on a multi-replica deployment only the pod that bumped last would
// own a matching file. Worse, a request whose URL sub-revision disagrees with the
// serving pod takes the generate path and blocks rand(90,120) SECONDS on
// core_theme_get_css_content, pinning a php-fpm worker. This writes into the
// CURRENT sub-revision and mutates no global state.
//
// Usage:
//   php warm_theme_css.php            # warm if needed, silent if already warm
//   php warm_theme_css.php --verbose  # also report when already warm
//
// Exit codes: 0 = warm (already, or now). 1 = failed to warm.

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/csslib.php');

$verbose = in_array('--verbose', $argv, true);

try {
    $themename = $CFG->theme;
    $rev       = theme_get_revision();
    $subrev    = theme_get_sub_revision_for_theme($themename);

    // Only 'ltr' is warmed. An RTL request falls back to the pre-existing path,
    // which is a no-op for them rather than a regression.
    $file = theme_get_css_filename($themename, $rev, $subrev, 'ltr');

    // Cheap path first: this runs every interval, so do not load theme_config
    // or touch SCSS unless the sheet is actually missing.
    if (file_exists($file)) {
        if ($verbose) {
            printf("already warm: %s (rev %s, subrev %s)\n", $file, $rev, $subrev);
        }
        exit(0);
    }

    printf("theme CSS missing for rev %s subrev %s -- compiling %s\n",
        $rev, $subrev, basename($file));

    $theme = theme_config::load($themename);
    $theme->force_svg_use(true);
    $theme->set_rtl_mode(false);

    make_localcache_directory("theme/{$rev}/{$themename}/css", false);

    $css = $theme->get_css_content();
    $theme->set_css_content_cache($css);
    css_store_css($theme, $file, $css);

    printf("wrote %s (%d bytes)\n", $file, strlen($css));
    exit(0);

} catch (Throwable $e) {
    // Never fatal for the caller: failing here just leaves the pre-existing
    // uncached fallback in place, which is the old behaviour, not an outage.
    fprintf(STDERR, "WARN: theme CSS warm failed: %s\n", $e->getMessage());
    exit(1);
}
