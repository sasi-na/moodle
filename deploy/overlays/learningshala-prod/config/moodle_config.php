<?php
// learn.learningshala.co.in
//
// A fresh install, not a migration, so this starts from the nitk config -- the
// other fresh-install tenant -- rather than from jain's. Two things carried over
// deliberately:
//
//   1. dbpass is read from the environment, never hardcoded. The Deployment and
//      both CronJobs inject MOODLE_DATABASE_PASSWORD from the moodle-secrets
//      Secret, so the production password never enters git.
//
//   2. Redis prefixes are tenant-specific. Each tenant has its own redis-service
//      in its own namespace so they cannot collide today, but distinct prefixes
//      mean a future consolidation onto one Redis stays safe by default.
//
// The dead `cachedef_*`, `cachetext`, `filecaching`, `cachemaxsize` and
// `cachetimeout` settings the older tenants carry are NOT here: `cachedef_` in
// core is a lang-string prefix for cache definition NAMES
// (cache/classes/definition.php), not a setting namespace, and nothing reads any
// of them. `cachetemplates`, `cachejs` and `langstringcache` are real and kept.

unset($CFG);
global $CFG;
$CFG = new stdClass();
$CFG->getremoteaddrconf = 0;

// Record the real client IP instead of the load balancer's. getremoteaddr() trusts the
// LAST X-Forwarded-For hop, and Google's balancer appends itself there, so without this
// every stored IP is the balancer's. Naming it here makes the client the last remaining
// hop; it stays spoof-resistant because Google appends the true client after anything the
// client supplied.
//
// learn.learningshala.co.in resolves to the shared prod-gateway forwarding rule,
// the same address as learn.onlinejain.com and learn.nitk.edu.in. If this tenant
// ever moves to its own IP, change it here.
$CFG->reverseproxyignore = '34.95.112.39';

// Database configuration
$CFG->dbtype    = 'mysqli';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'mysql-db-pxc-db-haproxy';
$CFG->dbname    = 'learningshala';
$CFG->dbuser    = 'learningshala';
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
// Installed at the real hostname from the start. Installing under a temporary
// host and moving wwwroot later means rewriting every absolute URL already
// stored in course content with admin/tool/replace/cli/replace.php, plus every
// setting holding the old host through set_config() -- the replace tool skips
// `config` and `config_plugins` on purpose. Not worth it when the DNS record can
// simply be created first.
$CFG->wwwroot   = 'https://learn.learningshala.co.in';
$CFG->sslproxy = true;
$CFG->dataroot  = '/var/moodledata';
$CFG->admin     = 'admin';
$CFG->directorypermissions = 02777;

// ===== REDIS SESSION CONFIGURATION =====
$CFG->session_handler_class = '\core\session\redis';
$CFG->session_redis_host = 'redis-service';
$CFG->session_redis_port = 6379;
$CFG->session_redis_database = 0;
$CFG->session_redis_prefix = 'lshala_sess_';
$CFG->session_redis_serializer_use_igbinary = true;
$CFG->sessiontimeout = 86400;

// Session lock tuning. Locking is disabled for the same reason as every other
// tenant: with several pods behind one service, session locks serialise requests
// badly -- block_rss_client once held one for ~12 seconds on jain.
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
$CFG->langstringcache = true;

// Redis Cache Store (MUC)
$CFG->cache_redis_host = 'redis-service';
$CFG->cache_redis_port = 6379;
$CFG->cache_redis_database = 1;
$CFG->cache_redis_prefix = 'lshala_cache_';
$CFG->cache_redis_serializer = 1;
$CFG->cache_redis_compressor = 0;
$CFG->cache_redis_ttl = 86400;

$CFG->forced_cache_configuration = array(
    'stores' => array(
        'redis_muc' => array(
            'class' => 'cachestore_redis',
            'configuration' => array(
                'server' => 'redis-service:6379',
                'prefix' => 'lshala_muc_',
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
// Sized for 2 pods x 100m / 512Mi. extramemorylimit must stay at or below
// php_admin_value[memory_limit] in config/www.conf -- that is a hard ceiling, so
// raise_memory_limit() cannot exceed it and setting this higher does nothing.
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
