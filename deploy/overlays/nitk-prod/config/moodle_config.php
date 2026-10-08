<?php
// learn.nitk.edu.in
//
// Derived from jain's config with the tenant values changed. Two deliberate
// differences beyond the obvious ones:
//
//   1. dbpass is read from the environment, not hardcoded. The Deployment and
//      both CronJobs inject MOODLE_DATABASE_PASSWORD from the moodle-secrets
//      Secret, so the production password never enters git. jain still uses a
//      literal; it switches when the credentials are rotated.
//
//   2. Redis prefixes are nitk-specific. Each tenant has its own redis-service
//      in its own namespace so they cannot collide today, but distinct prefixes
//      mean a future consolidation onto one Redis stays safe by default.

unset($CFG);
global $CFG;
$CFG = new stdClass();
$CFG->getremoteaddrconf = 0;

// Record the real client IP instead of the load balancer's. getremoteaddr() trusts the
// LAST X-Forwarded-For hop, and Google's balancer appends itself there, so without this
// every stored IP is the balancer's. Naming it here makes the client the last remaining
// hop; it stays spoof-resistant because Google appends the true client after anything the
// client supplied. Full reasoning and the verified cases are in the jain-prod overlay.
//
// learn.nitk.edu.in resolves to the SAME forwarding-rule address as learn.onlinejain.com,
// so this value is shared today. If nitk moves to its own IP, change it here.
$CFG->reverseproxyignore = '34.95.112.39';

// Database configuration
$CFG->dbtype    = 'mysqli';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'mysql-db-pxc-db-haproxy';
$CFG->dbname    = 'nitkdb';
$CFG->dbuser    = 'nitkuser';
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
//
// The real domain. NITK repointed learn.nitk.edu.in to the gateway
// (34.95.112.39) on 2026-08-10; the site was built and tested on the temporary
// nitk.lms.thekaios.com until then.
//
// AFTER DEPLOYING THIS, rewrite the URLs already stored in the database:
//
//   php admin/tool/replace/cli/replace.php --non-interactive \
//     --search='https://nitk.lms.thekaios.com' --replace='https://learn.nitk.edu.in'
//   php admin/cli/purge_caches.php
//
// The tool is admin/tool/replace/cli/replace.php. There is no
// admin/cli/replace_all_text.php in Moodle 5.0 -- an earlier version of this
// comment named that path and it simply does not exist.
//
// Changing wwwroot alone is not enough: Moodle stores absolute URLs inside course
// content, so anything created before the switch keeps pointing at the old host.
//
// The tool will NOT touch `config` or `config_plugins` -- db_should_replace() in
// lib/adminlib.php skips them deliberately, because settings are cached and
// validated. Any settings holding the old host must be changed through the
// supported API instead, e.g.:
//
//   set_config('noreplyaddress', 'noreply@learn.nitk.edu.in');
//
// Do NOT rewrite $CFG->siteidentifier even though it contains the old hostname:
// it is an opaque install-time identifier, not a URL, and changing it
// invalidates caches and duplicate-site detection.
//
// nitk.lms.thekaios.com deliberately stays on the HTTPRoute and in the
// certificate map, so anything already bookmarked keeps resolving.
$CFG->wwwroot   = 'https://learn.nitk.edu.in';
$CFG->sslproxy = true;
$CFG->dataroot  = '/var/moodledata';
$CFG->admin     = 'admin';
$CFG->directorypermissions = 02777;

// ===== REDIS SESSION CONFIGURATION =====
$CFG->session_handler_class = '\core\session\redis';
$CFG->session_redis_host = 'redis-service';
$CFG->session_redis_port = 6379;
$CFG->session_redis_database = 0;
$CFG->session_redis_prefix = 'nitk_sess_';
$CFG->session_redis_serializer_use_igbinary = true;
$CFG->sessiontimeout = 86400;

// Session lock tuning. Locking is disabled for the same reason as jain: with
// several pods behind one service, session locks serialise requests badly.
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
$CFG->cachetemplates = true;
$CFG->cachejs = true;
$CFG->cachetext = true;
$CFG->langstringcache = true;
$CFG->filecaching = true;
$CFG->cachemaxsize = 104857600;
$CFG->cachetimeout = 86400;
$CFG->cachedef_sql = 3600;
$CFG->cachedef_databasemeta = 3600;
$CFG->cachedef_string = 86400;
$CFG->cachedef_config = 86400;

// Redis Cache Store (MUC)
$CFG->cache_redis_host = 'redis-service';
$CFG->cache_redis_port = 6379;
$CFG->cache_redis_database = 1;
$CFG->cache_redis_prefix = 'nitk_cache_';
$CFG->cache_redis_serializer = 1;
$CFG->cache_redis_compressor = 0;
$CFG->cache_redis_ttl = 86400;

$CFG->forced_cache_configuration = array(
    'stores' => array(
        'redis_muc' => array(
            'class' => 'cachestore_redis',
            'configuration' => array(
                'server' => 'redis-service:6379',
                'prefix' => 'nitk_muc_',
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
// Sized for nitk's smaller footprint (2 pods x 2 CPU / 4Gi) rather than jain's.
$CFG->extramemorylimit = '2G';
$CFG->task_adhoc_max_runtime = 600;
$CFG->task_scheduled_max_runtime = 600;
$CFG->cron_keepalive = 900;
$CFG->task_adhoc_concurrency_limit = 1;
$CFG->task_scheduled_concurrency_limit = 5;
$CFG->enable_availability_cache = true;

$CFG->tool_uploaduser_max_req_memory = '512M';
$CFG->tool_uploaduser_uncompressed = 1;
$CFG->tool_uploaduser_maxusers = 5000;

// ===== SECURITY =====
$CFG->cookiesecure = true;
$CFG->cookiehttponly = true;

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
