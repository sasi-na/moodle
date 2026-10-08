<?php
// global.onlinejain.com
//
// Adopted from the live ConfigMap in global-jain-prod and reconciled against the
// jain / nitk / usdc overlays.
//
// Five deliberate departures from the file this replaces:
//
//   1. dbpass is read from the environment. The live ConfigMap carries the
//      production password in plaintext and all three CronJobs that mount it
//      repeat it. The Deployment and every CronJob now inject
//      MOODLE_DATABASE_PASSWORD from the moodle-secrets Secret, whose value was
//      verified identical beforehand -- so the connection is unchanged, only the
//      exposure is gone. Note that includes the bespoke weekly submission
//      report, which had NO database env var at all; see
//      cronjob-submission-report.yaml.
//
//   2. Redis prefixes are tenant-specific rather than the generic `moodle_*`.
//      Each tenant has its own redis-service in its own namespace so they cannot
//      collide today, but generic prefixes mean a future consolidation onto one
//      Redis silently mixes two sites' sessions -- and `moodle_sess_` is exactly
//      what jain uses. Renaming the session prefix orphans the sessions
//      currently in Redis, so everyone signed in is signed out. This tenant has
//      429 active accounts, so unlike usdc that is genuinely user-visible.
//
//   3. lock_factory is set to the DB record factory, matching every other
//      tenant. This cluster is Galera; GET_LOCK is not cluster-safe and PXC
//      refuses it outright in ENFORCING mode. This tenant's node runs
//      PERMISSIVE, which is the only reason the unset default has worked -- a
//      latent failure, not a working configuration. Setting it here is also the
//      prerequisite for ever aligning pxc_strict_mode with the rest of the
//      fleet; see deploy/global-jain-pxc-values.yaml.
//
//   4. reverseproxyignore names this tenant's own load balancer. It was EMPTY,
//      so getremoteaddr() trusts the last X-Forwarded-For hop, which Google's
//      balancer appends itself to -- every IP Moodle has recorded here is the
//      balancer's, not the user's. This tenant has its own Ingress and its own
//      forwarding rule at 34.8.26.232, distinct from jain/nitk (34.95.112.39)
//      and usdc (34.107.148.85).
//
//   5. Eight settings that core does not read are dropped rather than carried
//      forward -- see the cache block below.

unset($CFG);
global $CFG;
$CFG = new stdClass();
$CFG->getremoteaddrconf = 0;
$CFG->reverseproxyignore = '34.8.26.232';

// Database configuration
$CFG->dbtype    = 'mysqli';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'mysql-db-pxc-db-haproxy';
$CFG->dbname    = 'moodledb';
$CFG->dbuser    = 'moodleuser';
$CFG->dbpass    = getenv('MOODLE_DATABASE_PASSWORD');
$CFG->prefix    = 'acu_';
$CFG->smsdisabled = true;

$CFG->dboptions = array (
  'dbpersist' => 1,
  'dbport' => 3306,
  'dbsocket' => '',
  'dbcollation' => 'utf8mb4_unicode_ci',
  'dbhandlesoptions' => false,
  'clientcompress' => true,
  'read_timeout' => 300,
  'write_timeout' => 300,
  'connect_timeout' => 10,
  'fetch_buffer_size' => 1048576,
);

$CFG->dbpersist = true;
$CFG->dboptions['dbcollation'] = 'utf8mb4_unicode_ci';

// Site configuration
$CFG->wwwroot   = 'https://global.onlinejain.com';
$CFG->sslproxy  = true;
$CFG->dataroot  = '/var/moodledata';
$CFG->admin     = 'admin';
$CFG->directorypermissions = 02777;

// ===== REDIS SESSION CONFIGURATION =====
$CFG->session_handler_class = '\core\session\redis';
$CFG->session_redis_host = 'redis-service';
$CFG->session_redis_port = 6379;
$CFG->session_redis_database = 0;
$CFG->session_redis_prefix = 'gjain_sess_';
$CFG->session_redis_serializer_use_igbinary = true;
$CFG->sessiontimeout = 86400;

// Session lock tuning. Locking is disabled for the same reason as every other
// tenant: with several pods behind one service, session locks serialise requests
// badly.
$CFG->enable_read_only_sessions = false;
$CFG->session_redis_acquire_lock_timeout = 10;
$CFG->session_redis_lock_expire = 300;
$CFG->session_redis_lock_retries = 100;
$CFG->session_redis_lock_wait = 100;
$CFG->sessionlockenabled = false;

// ===== DIRECTORY CONFIGURATION =====
$CFG->tempdir = '/var/moodledata/tmp';
$CFG->filedir = $CFG->dataroot . '/filedir';
$CFG->localcachedir = '/tmp/moodle-cache';
$CFG->cachedir = '/tmp/moodle-cache';

// ===== CACHE CONFIGURATION =====
// Only these three exist. The live file also set $CFG->cachetext, filecaching,
// cachemaxsize, cachetimeout and the four $CFG->cachedef_* entries -- all eight
// are read by NOTHING in core. `cachedef_` there is a lang-string prefix for
// cache definition NAMES (cache/classes/definition.php:607), not a TTL setting.
// They are dropped rather than copied forward so nobody tunes them later and
// wonders why nothing changes. jain and nitk still carry them; they are inert,
// and editing those tenants' config.php restarts their pods.
$CFG->cachetemplates = true;
$CFG->cachejs = true;
$CFG->langstringcache = true;

// Redis Cache Store (MUC)
$CFG->cache_redis_host = 'redis-service';
$CFG->cache_redis_port = 6379;
$CFG->cache_redis_database = 1;
$CFG->cache_redis_prefix = 'gjain_cache_';
$CFG->cache_redis_serializer = 1;
$CFG->cache_redis_compressor = 0;
$CFG->cache_redis_ttl = 86400;

$CFG->forced_cache_configuration = array(
    'stores' => array(
        'redis_muc' => array(
            'class' => 'cachestore_redis',
            'configuration' => array(
                'server' => 'redis-service:6379',
                'prefix' => 'gjain_muc_',
                'database' => 1,
                'serializer' => 1,
                'compressor' => 0,
                'ttl' => 86400,
            ),
        ),
    ),
    'modemappings' => array(
        array('mode' => 1, 'store' => 'redis_muc', 'sort' => -1), // Application
        array('mode' => 2, 'store' => 'redis_muc', 'sort' => -1), // Session
        array('mode' => 4, 'store' => 'redis_muc', 'sort' => -1), // Request
    ),
);

// ===== FILE SERVING (offload to nginx) =====
$CFG->xsendfile = 'X-Accel-Redirect';
$CFG->xsendfilealiases = array(
    '/dataroot/' => '/var/moodledata',
);
$CFG->filelifetime = 86400;
$CFG->maxbytes = 104857600;
$CFG->slasharguments = true;
$CFG->yuislasharguments = true;

// ===== PERFORMANCE =====
// Sized for this tenant (2 pods x 150m / 1Gi) rather than jain. extramemorylimit
// must not exceed www.conf's php_admin_value[memory_limit], which config/www.conf
// sets to 2G -- php_admin_value is a hard ceiling, so a higher value here would
// silently do nothing.
$CFG->extramemorylimit = '2G';
$CFG->task_adhoc_max_runtime = 600;
$CFG->task_scheduled_max_runtime = 600;
$CFG->cron_keepalive = 900;
// Live carried 5 and 10, copied from a template. Five adhoc runners each free to
// raise to 2G is 10G of potential demand from pods requesting 1Gi. Two is more
// parallelism than a site peaking at ~0.5 requests/second can use, and unlike
// jain's 1 it cannot wedge the queue behind a single stuck task.
$CFG->task_adhoc_concurrency_limit = 2;
$CFG->task_scheduled_concurrency_limit = 5;
$CFG->enable_availability_cache = true;

$CFG->tool_uploaduser_max_req_memory = '512M';
$CFG->tool_uploaduser_uncompressed = 1;
$CFG->tool_uploaduser_maxusers = 5000;

// ===== SECURITY =====
$CFG->cookiesecure = true;
$CFG->cookiehttponly = true;

// ===== VPL JAIL SERVER =====
// mod_vpl is installed here and its jail server is reached over plain HTTP with a
// self-signed certificate, so curl's default restrictions have to be relaxed.
// Carried over unchanged from the live config -- this IS a real weakening of
// outbound request security (it also lets any Moodle feature that fetches a URL
// reach internal addresses), and it should be revisited once the jail host has a
// valid certificate. Not changed here: altering security behaviour inside a
// platform merge would make the diff impossible to reason about.
$CFG->curlsecurityblockedhosts = '';
$CFG->curlsecurityallowedport = "80\n443";
$CFG->curlignoresslverification = true;

// ===== EMAIL =====
$CFG->smtpmaxbulk = 100;
$CFG->messageinbound_maxprocesstime = 600;
$CFG->emaildisplayno = 0;
$CFG->noemailever = false;

// ===== DEBUG (off) =====
$CFG->debug = 0;
$CFG->debugdisplay = 0;
$CFG->perfdebug = 0;
$CFG->debugdeveloper = 0;

$CFG->themedesignermode = false;

// PXC (Galera) blocks GET_LOCK() in ENFORCING mode - use DB record locks instead.
$CFG->lock_factory = '\core\lock\db_record_lock_factory';

require_once(__DIR__ . '/lib/setup.php');
