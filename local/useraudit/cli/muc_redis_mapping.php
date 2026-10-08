<?php
// Map the expensive-to-rebuild MUC definitions onto Redis, so they survive pod
// restarts and are shared by every pod.
//
// WHY THIS EXISTS
//
// MUC on this site runs entirely on core defaults: APPLICATION => cachestore_file
// (inside $CFG->cachedir, a per-pod emptyDir), SESSION => cachestore_session,
// REQUEST => cachestore_static. The $CFG->forced_cache_configuration array in
// config.php looks like it moved all three to Redis, but that is NOT a Moodle
// setting and has never had any effect -- confirm with
// cache_config::instance()->get_mode_mappings(), never by reading config.php.
//
// The cost of that is paid after every rollout. The file store held 128MB across
// 19,438 files eighteen minutes into a pod's life, and it is rebuilt from scratch
// on each new pod: get_fast_modinfo() -> build_course_cache() -> a
// *_get_coursemodule_info() call per module (vpl, resource -> get_area_files()),
// plus core_course_category::get() querying per category. Users pay for that
// rebuild in page time for several minutes after each deploy.
//
// WHY ONLY SOME DEFINITIONS
//
// Once a pod is warm, cachestore_file on the node's local SSD is FASTER than a
// Redis round-trip. Moving the cheap, hot, tiny definitions (core/string,
// core/config, core/htmlpurifier) to Redis would add network latency for no gain,
// because they are cheap to rebuild. Only definitions that are expensive to BUILD
// are worth centralising. So this sets definition-level mappings, not mode ones.
//
// WHY NOT THE WHOLE APPLICATION MODE, AND WHY SESSIONS ARE LEFT ALONE
//
// cachestore_redis has no database-index option in Moodle 5.0 -- there is no
// ->select() call in cache/stores/redis/lib.php -- so cache keys land in db0
// alongside the ~46,000 session keys, separated only by the key prefix.
// redis.conf sets `maxmemory 5gb` with `allkeys-lru`, and LRU eviction is
// instance-wide: enough cache growth would evict SESSION keys and log users out.
// The definitions below add roughly 100-150MB to an instance already holding
// ~385MB, so utilisation stays near 10% and eviction should never trigger.
// Before widening this, watch `redis-cli info stats | grep evicted_keys` -- it
// must stay at 0 -- and note that the redis pod requests 1Gi with no memory
// limit while its own config permits 5gb.
//
// Usage:
//   php muc_redis_mapping.php --status     # show current stores and mappings
//   php muc_redis_mapping.php --dry-run    # report what would change
//   php muc_redis_mapping.php --apply      # add the store and set the mappings
//   php muc_redis_mapping.php --revert     # unmap the definitions, keep the store
//
// Idempotent: re-running --apply reports "already mapped" and changes nothing.
// The config lives in $CFG->dataroot/muc/config.php, which is shared dataroot, so
// this runs ONCE per site and not per pod. Running pods pick it up within
// opcache.revalidate_freq (60s here); no restart required.

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');

// Cache store classes are not autoloaded -- the cache API pulls them in on demand --
// so the suitability checks below need this explicitly.
require_once($CFG->dirroot . '/cache/stores/redis/lib.php');

use core_cache\config;
use core_cache\config_writer;
use core_cache\definition;

/** Store instance name created by this script. */
const STORE_NAME = 'redis_muc';

/**
 * Definitions worth centralising, i.e. expensive to rebuild rather than merely hot.
 * Sizes are what one pod had accumulated 18 minutes after startup.
 */
const TARGETS = [
    // Not a size problem like the others below -- a CORRECTNESS problem. set_config()
    // invalidates only the pod that handled the request, so a setting saved in the admin UI
    // was served stale by every other pod until they recycled: on a four-pod fleet roughly
    // three requests in four kept the old value, silently, which makes settings changes look
    // like they intermittently do not work. Proven by setting one value and reading it back
    // from all four pods: 1 / 0 / 0 / 0.
    //
    // Cost of centralising it: +0.62ms per config key per request (file 0.050ms vs redis
    // 0.670ms, measured on this site), and staticacceleration means once per key per request
    // rather than per get_config() call. 132 keys exist; a page reads a handful.
    'core/config',
    'core/coursemodinfo',     // 27MB -- the modinfo rebuild storm itself.
    'core/coursecat',         // queried per category from theme_baz's sidebar.
    'core/coursecattree',
    'core/coursecompletion',  // 15MB
    'core/questiondata',      // 10MB
    'mod_quiz/overrides',     // 18MB
    'mod_lesson/overrides',   // 10MB
    'mod_assign/overrides',   // 3MB
    'core/groupdata',         // 9MB
    'core/coursecontacts',    // 4MB
];

$apply   = in_array('--apply', $argv, true);
$revert  = in_array('--revert', $argv, true);
$status  = in_array('--status', $argv, true);
$dryrun  = in_array('--dry-run', $argv, true) || (!$apply && !$revert && !$status);

/**
 * Print the current store instances and definition-level mappings.
 */
function muc_report(): void {
    $conf = config::instance();

    echo "STORES:\n";
    foreach ($conf->get_all_stores() as $name => $store) {
        printf("  %-22s %-24s default=%s\n", $name, $store['class'], !empty($store['default']) ? 'y' : 'n');
    }

    $modes = [1 => 'APPLICATION', 2 => 'SESSION', 4 => 'REQUEST'];
    echo "MODE MAPPINGS:\n";
    foreach ($conf->get_mode_mappings() as $m) {
        printf("  %-12s -> %s\n", $modes[$m['mode']] ?? $m['mode'], $m['store']);
    }

    $defmappings = $conf->get_definition_mappings();
    echo "DEFINITION MAPPINGS: " . (count($defmappings) ? "\n" : "none\n");
    foreach ($defmappings as $m) {
        printf("  %-26s -> %s\n", $m['definition'], $m['store']);
    }
}

/**
 * Is this definition safe to serve from the Redis store?
 *
 * Guards the one requirement cachestore_redis cannot meet. It is key-aware,
 * lockable and searchable, so locking-before-write and multiple identifiers are
 * fine; a definition demanding a data guarantee is not, because a cache Redis
 * under allkeys-lru may evict.
 *
 * @param string $name
 * @param array $definitionconfig
 * @return string '' when suitable, else the reason it is not
 */
function muc_unsuitable_reason(string $name, array $definitionconfig): string {
    $def = definition::load($name, $definitionconfig);

    if (!($def->get_mode() & cachestore_redis::get_supported_modes([]))) {
        return 'store does not support this cache mode';
    }
    if ($def->require_data_guarantee()) {
        return 'definition requires a data guarantee; an LRU cache can evict';
    }

    return '';
}

if ($status) {
    muc_report();
    exit(0);
}

// A store whose requirements are not met would be accepted into the config and then
// fail at runtime for every read, so refuse before writing anything.
if (!cachestore_redis::are_requirements_met()) {
    cli_error('cachestore_redis requirements are not met on this host (is the phpredis extension installed?).');
}

if ($revert) {
    $writer = config_writer::instance();
    $conf = config::instance();
    $mapped = [];
    foreach ($conf->get_definition_mappings() as $m) {
        if ($m['store'] === STORE_NAME) {
            $mapped[] = $m['definition'];
        }
    }

    if (!$mapped) {
        echo "Nothing mapped to " . STORE_NAME . "; nothing to revert.\n";
        exit(0);
    }

    foreach ($mapped as $definition) {
        // An empty mapping list returns the definition to its mode's default store.
        $writer->set_definition_mappings($definition, []);
        echo "  unmapped $definition\n";
    }

    echo "\nReverted " . count($mapped) . " definition(s) to the default store.\n";
    echo "The " . STORE_NAME . " store instance was left in place; data in it is now orphaned.\n";
    exit(0);
}

// --- dry-run / apply -------------------------------------------------------

$conf = config::instance();
$stores = $conf->get_all_stores();
$definitions = $conf->get_definitions();

$existingmappings = [];
foreach ($conf->get_definition_mappings() as $m) {
    $existingmappings[$m['definition']] = $m['store'];
}

// Decide the plan before touching anything, so --dry-run and --apply agree.
$needstore = !array_key_exists(STORE_NAME, $stores);
$tomap = [];
$skipped = [];

foreach (TARGETS as $name) {
    if (!array_key_exists($name, $definitions)) {
        $skipped[$name] = 'not present in this site\'s cache config';
        continue;
    }
    if (($existingmappings[$name] ?? null) === STORE_NAME) {
        $skipped[$name] = 'already mapped';
        continue;
    }
    if ($reason = muc_unsuitable_reason($name, $definitions[$name])) {
        $skipped[$name] = $reason;
        continue;
    }
    $tomap[] = $name;
}

echo "PLAN\n";
echo "  store '" . STORE_NAME . "': " . ($needstore ? 'CREATE' : 'already exists') . "\n";
echo "  definitions to map: " . count($tomap) . "\n";
foreach ($tomap as $name) {
    echo "     + $name\n";
}
foreach ($skipped as $name => $reason) {
    printf("     - %-26s (%s)\n", $name, $reason);
}

if ($dryrun) {
    echo "\nDry run only. Re-run with --apply to make these changes.\n";
    exit(0);
}

if (!$needstore && !$tomap) {
    echo "\nNothing to do.\n";
    exit(0);
}

$writer = config_writer::instance();

if ($needstore) {
    // Keys land in db0 next to the sessions; the prefix is what keeps them apart.
    // Serializer 1 = PHP, matching what the (inert) config.php block intended.
    // No 'database' key: cachestore_redis does not support choosing one.
    $writer->add_store_instance(STORE_NAME, 'redis', [
        'server'     => 'redis-service:6379',
        'prefix'     => 'moodle_muc_',
        'password'   => '',
        'serializer' => 1,
        'compressor' => 0,
    ]);
    echo "\nCreated store '" . STORE_NAME . "' -> redis-service:6379 (prefix moodle_muc_)\n";
}

foreach ($tomap as $name) {
    $writer->set_definition_mappings($name, [STORE_NAME]);
    echo "  mapped $name\n";
}

echo "\nDone. Verify with --status, then watch:\n";
echo "  redis-cli info stats  | grep evicted_keys   # must stay 0\n";
echo "  redis-cli info memory | grep used_memory_human\n";
