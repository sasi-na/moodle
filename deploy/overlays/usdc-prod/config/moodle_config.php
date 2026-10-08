<?php
// learn.usdcglobal.com
//
// Adopted from the live ConfigMap in learn-usdc-prod and reconciled against the
// jain/nitk overlays. Behaviour-preserving except where called out below; the
// live file assigned several settings twice (sslproxy, and the session lock
// timeouts, once near the Redis block and again lower down), so the effective
// values were whichever came last. Those are stated once here.
//
// Four deliberate departures from the file this replaces:
//
//   1. dbpass is read from the environment, not hardcoded. The live ConfigMap
//      carries the production password in plaintext, readable by anyone who can
//      `get configmap` in the namespace, and the live CronJobs repeat it. The
//      Deployment and both CronJobs now inject MOODLE_DATABASE_PASSWORD from the
//      moodle-secrets Secret, whose value was verified identical before this
//      switch -- so the connection is unchanged, only the exposure is gone.
//
//   2. Redis prefixes are usdc-specific rather than the generic `moodle_*`.
//      Each tenant has its own redis-service in its own namespace so they cannot
//      collide today, but generic prefixes mean any future consolidation onto a
//      shared Redis silently mixes two sites' sessions. Renaming the session
//      prefix orphans the sessions currently in Redis, which signs out whoever
//      is logged in -- three accounts here, and the deploy restarts the pods
//      anyway.
//
//   3. lock_factory is set to the DB record factory, matching jain and nitk.
//      This cluster is Galera; GET_LOCK is not cluster-safe and PXC refuses it
//      outright in ENFORCING mode. usdc's node currently runs PERMISSIVE, which
//      is the only reason the default has worked -- it is a latent failure, not
//      a working configuration.
//
//   4. reverseproxyignore names usdc's own load balancer. Without it
//      getremoteaddr() trusts the last X-Forwarded-For hop, which Google's
//      balancer appends itself to, so every IP Moodle records is the balancer's
//      rather than the user's. Note this is 34.107.148.85 -- a DIFFERENT
//      forwarding rule from the one jain and nitk share.

unset($CFG);
global $CFG;
$CFG = new stdClass();
$CFG->getremoteaddrconf = 0;
$CFG->reverseproxyignore = '34.107.148.85';

// Database configuration
$CFG->dbtype    = 'mysqli';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'mysql-db-pxc-db-haproxy';
$CFG->dbname    = 'learn_usdc';
$CFG->dbuser    = 'learnusdc';
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
$CFG->wwwroot   = 'https://learn.usdcglobal.com';
$CFG->sslproxy  = true;
$CFG->dataroot  = '/var/moodledata';
$CFG->admin     = 'admin';
$CFG->directorypermissions = 02777;

// ===== REDIS SESSION CONFIGURATION =====
$CFG->session_handler_class = '\core\session\redis';
$CFG->session_redis_host = 'redis-service';
$CFG->session_redis_port = 6379;
$CFG->session_redis_database = 0;
$CFG->session_redis_prefix = 'usdc_sess_';
$CFG->session_redis_serializer_use_igbinary = true;
$CFG->sessiontimeout = 86400;

// Session lock tuning. Locking is disabled for the same reason as jain and nitk:
// with several pods behind one service, session locks serialise requests badly.
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
// Only these three exist. $CFG->cachetext, filecaching, cachemaxsize,
// cachetimeout and the four $CFG->cachedef_* entries that jain and nitk carry
// are read by nothing in core -- `cachedef_` there is a lang-string prefix for
// cache definition NAMES (cache/classes/definition.php:607), not a TTL setting.
// They are dropped here rather than copied forward, so nobody tunes them later
// and wonders why nothing changes.
$CFG->cachetemplates = true;
$CFG->cachejs = true;
$CFG->langstringcache = true;

// Redis Cache Store (MUC)
$CFG->cache_redis_host = 'redis-service';
$CFG->cache_redis_port = 6379;
$CFG->cache_redis_database = 1;
$CFG->cache_redis_prefix = 'usdc_cache_';
$CFG->cache_redis_serializer = 1;
$CFG->cache_redis_compressor = 0;
$CFG->cache_redis_ttl = 86400;

$CFG->forced_cache_configuration = array(
    'stores' => array(
        'redis_muc' => array(
            'class' => 'cachestore_redis',
            'configuration' => array(
                'server' => 'redis-service:6379',
                'prefix' => 'usdc_muc_',
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
// Sized for usdc's footprint (2 pods x 100m / 768Mi), matching nitk rather than
// jain. extramemorylimit must not exceed www.conf's php_admin_value[memory_limit],
// which config/www.conf sets to 2G -- php_admin_value is a hard ceiling, so a
// higher value here would silently do nothing.
$CFG->extramemorylimit = '2G';
$CFG->task_adhoc_max_runtime = 600;
$CFG->task_scheduled_max_runtime = 600;
$CFG->cron_keepalive = 900;
// Live carried 5 and 10, copied from a template rather than chosen. Five adhoc
// runners each free to raise to 2G is 10G of potential demand from a namespace
// whose pods request 768Mi. Two is still more parallelism than four courses can
// use, and unlike jain's 1 it cannot wedge the queue behind a single stuck task.
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
// mod_vpl is installed here, and its jail server is reached over plain HTTP with
// a self-signed certificate, so curl's default restrictions have to be relaxed.
// Carried over unchanged from the live config -- this is a real weakening of
// outbound request security (it also lets any Moodle feature that fetches a URL
// reach internal addresses), and it should be revisited once the jail host has a
// valid certificate. Not changed here: this commit is a platform merge, and
// altering security behaviour in the same step would make the diff impossible to
// reason about.
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
