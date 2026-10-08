<?php
// dev.lms.thekaios.com (QA)
//
// Authored from production's config with QA values, rather than copied from
// QA's live ConfigMap. QA's live copy has fallen behind prod -- it is missing
// $CFG->cache_redis_prefix and $CFG->lock_factory, among others -- and the
// point of QA is to behave like production.
//
// dbpass comes from the environment; QA's Deployment already injects
// MOODLE_DATABASE_PASSWORD from the moodle-secrets Secret.

unset($CFG);
global $CFG;
$CFG = new stdClass();
$CFG->getremoteaddrconf = 0;

// Record the real client IP instead of the load balancer's. getremoteaddr() trusts the
// LAST X-Forwarded-For hop and Google's balancer appends itself there, so without this
// every stored IP is the balancer's -- 2,943 of the last 3,000 log rows here. Full
// reasoning and the verified cases are in the jain-prod overlay.
//
// dev.lms.thekaios.com resolves to the same forwarding-rule address as production.
$CFG->reverseproxyignore = '34.95.112.39';

// Database configuration.
// QA runs a plain `moodle-db` MySQL Deployment in-namespace, not PXC.
$CFG->dbtype    = 'mysqli';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'moodle-db';
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
$CFG->wwwroot   = 'https://dev.lms.thekaios.com';
$CFG->sslproxy = true;
$CFG->dataroot  = '/var/moodledata';
$CFG->admin     = 'admin';
$CFG->directorypermissions = 02777;

// ===== REDIS SESSION CONFIGURATION =====
$CFG->session_handler_class = '\core\session\redis';
$CFG->session_redis_host = 'redis-service';
$CFG->session_redis_port = 6379;
$CFG->session_redis_database = 0;
$CFG->session_redis_prefix = 'qa_sess_';
$CFG->session_redis_serializer_use_igbinary = true;
$CFG->sessiontimeout = 86400;

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
$CFG->cache_redis_prefix = 'qa_cache_';
$CFG->cache_redis_serializer = 1;
$CFG->cache_redis_compressor = 0;
$CFG->cache_redis_ttl = 86400;

$CFG->forced_cache_configuration = array(
    'stores' => array(
        'redis_muc' => array(
            'class' => 'cachestore_redis',
            'configuration' => array(
                'server' => 'redis-service:6379',
                'prefix' => 'qa_muc_',
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

// VPL jail server, as production.
$CFG->curlsecurityblockedhosts = '';
$CFG->curlsecurityallowedport = "80\n443";
$CFG->curlignoresslverification = true;

// ===== EMAIL =====
// QA must never mail real learners. Flip noemailever to false only for a
// deliberate, supervised email test.
$CFG->noemailever = true;
$CFG->smtpmaxbulk = 100;
$CFG->messageinbound_maxprocesstime = 600;
$CFG->emaildisplayno = 0;

// ===== DEBUG =====
// Developer debugging on: this is the environment where seeing the error beats
// hiding it. Production keeps all four at 0.
$CFG->debug = (E_ALL | E_STRICT);
$CFG->debugdisplay = 1;
$CFG->debugdeveloper = 1;
$CFG->perfdebug = 0;

$CFG->themedesignermode = false;

require_once(__DIR__ . '/lib/setup.php');
