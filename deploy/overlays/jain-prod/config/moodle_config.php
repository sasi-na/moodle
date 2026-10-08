<?php
unset($CFG);
global $CFG;
$CFG = new stdClass();
$CFG->getremoteaddrconf = 0;

// Record the real client IP instead of the load balancer's.
//
// Every IP Moodle stored was 34.95.112.39 -- 19,991 of the last 20,000 logstore rows and
// 42,862 users' lastip -- which is this site's own GCP forwarding-rule address, i.e. the
// "IP address" column in report/log/user.php was useless for identifying anyone.
//
// The header is present and Moodle does read it; the problem is which hop it trusts.
// getremoteaddr() (lib/moodlelib.php) takes end($forwardedaddresses) -- deliberately the
// LAST entry, because earlier ones can be forged by the client -- and Google's load
// balancer appends its own address last: "X-Forwarded-For: <client>, 34.95.112.39".
// So the last hop is always the balancer.
//
// getremoteaddr() filters the header through $CFG->reverseproxyignore BEFORE taking the
// last entry, so naming the balancer here makes the client the last remaining hop.
//
// This stays spoof-resistant. Google appends the true client address after anything the
// client supplied, so with "1.2.3.4, <client>, 34.95.112.39" the answer is still <client>,
// not the forged 1.2.3.4. Verified against the real function on production:
//
//   XFF                                    ignore         getremoteaddr()
//   49.204.89.54, 34.95.112.39             (empty)        34.95.112.39   <- today
//   49.204.89.54, 34.95.112.39             34.95.112.39   49.204.89.54
//   1.2.3.4, 49.204.89.54, 34.95.112.39    34.95.112.39   49.204.89.54
//   2405:201:abc::1, 34.95.112.39          34.95.112.39   2405:201:abc::1
//   49.204.89.54:52344, 34.95.112.39       34.95.112.39   49.204.89.54
//
// Health checks and anything else whose header holds only the balancer end up filtered to
// nothing and record 0.0.0.0 rather than a misleading address.
//
// Keep this in step with DNS: learn.onlinejain.com must resolve here. Already-stored IPs
// cannot be recovered -- this only fixes data from the restart onwards.
$CFG->reverseproxyignore = '34.95.112.39';

// Database configuration
$CFG->dbtype    = 'mysqli';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'mysql-db-pxc-db-haproxy';
$CFG->dbname    = 'moodledb';
$CFG->dbuser    = 'moodleuser';
// Read from the environment so the production password is not stored in git.
// The Deployment injects MOODLE_DATABASE_PASSWORD from the moodle-secrets
// Secret, and patch-cronjob-env.yaml injects it into both CronJobs, which mount
// this same file.
//
// ORDERING: the CronJob env and this line must be applied TOGETHER. `kubectl
// apply -k` does that. Applying the ConfigMap alone would leave cron
// bootstrapping Moodle with an empty password.
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
  'read_timeout' => 300,        // Prevent timeout on slow queries
  'write_timeout' => 300,       // Prevent timeout on slow writes
  'connect_timeout' => 10,      // Connection timeout
  'fetch_buffer_size' => 1048576, // 1MB fetch buffer
);

// Database connection pooling optimization
$CFG->dbpersist = true;
$CFG->dboptions['dbcollation'] = 'utf8mb4_unicode_ci';

// Site configuration
$CFG->wwwroot   = 'https://learn.onlinejain.com';
$CFG->sslproxy = true;
$CFG->dataroot  = '/var/moodledata';
$CFG->admin     = 'admin';
$CFG->directorypermissions = 02777;

// ===== REDIS SESSION CONFIGURATION (Critical for 5K concurrent) =====
$CFG->session_handler_class = '\core\session\redis';
$CFG->session_redis_host = 'redis-service';
$CFG->session_redis_port = 6379;
$CFG->session_redis_database = 0;
$CFG->session_redis_prefix = 'moodle_sess_';
// Lock tuning lives in the session-lock block further down; it is deliberately
// set in exactly one place so the effective value is readable at a glance.
$CFG->session_redis_serializer_use_igbinary = true;
# $CFG->session_redis_compressor = 'zstd';
$CFG->sessiontimeout = 86400; // 24 hours

// ===== DIRECTORY CONFIGURATION =====
$CFG->tempdir = '/var/moodledata/tmp';
$CFG->filedir = $CFG->dataroot . '/filedir';
$CFG->localcachedir = '/tmp/moodle-cache';
$CFG->cachedir = '/tmp/moodle-cache';

// ===== CACHE CONFIGURATION (OPTIMIZED) =====
# $CFG->alternative_cache_factory_class = 'cache_factory';
$CFG->cachetemplates = true;
$CFG->cachejs = true;
$CFG->cachetext = true;
$CFG->langstringcache = true;
$CFG->filecaching = true;
$CFG->cachemaxsize = 104857600; // 200MB (increased)
$CFG->cachetimeout = 86400; // 24 hours
$CFG->cachedef_sql = 3600; // SQL query cache
$CFG->cachedef_databasemeta = 3600; // Database metadata cache
$CFG->cachedef_string = 86400; // String cache
$CFG->cachedef_config = 86400; // Config cache

// Redis Cache Store (MUC) configuration
$CFG->cache_redis_host = 'redis-service';
$CFG->cache_redis_port = 6379;
$CFG->cache_redis_database = 1; // Separate DB from sessions
$CFG->cache_redis_prefix = 'moodle_cache_';
$CFG->cache_redis_serializer = 1; // PHP serializer
$CFG->cache_redis_compressor = 0; // No compression for better performance
$CFG->cache_redis_ttl = 86400; // 24 hours default TTL

// WARNING: $CFG->forced_cache_configuration IS NOT A MOODLE SETTING. It appears
// nowhere in core (cache/, lib/, config-dist.php) and has never had any effect, so
// despite this block MUC is running entirely on core defaults — verified live:
// APPLICATION => cachestore_file (in $CFG->cachedir, a per-pod emptyDir, therefore
// cold on every pod start and shared with nothing), SESSION => cachestore_session,
// REQUEST => cachestore_static. dataroot muc/config.php mentions redis zero times.
// To actually move MUC to Redis, add a cachestore_redis instance and set the mode
// mappings in Site administration > Plugins > Caching > Configuration (or in
// muc/config.php). Leaving the dead array below only so its intent is not lost.
$CFG->forced_cache_configuration = array(
    'stores' => array(
        'redis_muc' => array(
            'class' => 'cachestore_redis',
            'configuration' => array(
                'server' => 'redis-service:6379',
                'prefix' => 'moodle_muc_',
                'database' => 1,
                'serializer' => 1, // PHP
                'compressor' => 0, // No compression
                'ttl' => 86400, // 24 hours
            ),
        ),
    ),
    'modemappings' => array(
        array('mode' => 1, 'store' => 'redis_muc', 'sort' => -1), // Application
        array('mode' => 2, 'store' => 'redis_muc', 'sort' => -1), // Session
        array('mode' => 4, 'store' => 'redis_muc', 'sort' => -1), // Request
    ),
);

// ===== SESSION LOCK TUNING =====
// Every request from a user serialises on that user's session lock, so any page
// that blocks (external HTTP, heavy report) stalls all of their other tabs and
// AJAX polls. Requests that declare READ_ONLY_SESSION would skip the exclusive
// lock entirely if this were on.
//
// KEEP THIS false FOR NOW. Core (\core\session\manager::start) throws a fatal
// moodle_exception when read-only sessions are enabled while any session-mode MUC
// cache still lives in the default session store, and on this site it does: the
// live mode mappings are all core defaults, so mode SESSION resolves to
// cachestore_session. The forced_cache_configuration block above does NOT change
// that — it is not a Moodle setting and has never had any effect. Map session-mode
// caches to a real shared store first, then flip this.
$CFG->enable_read_only_sessions = false;
// Wait this long for a lock before erroring; the ceiling users actually feel.
// NOTE: this is the ONLY place the lock settings are set — an earlier duplicate
// pair in the Redis session block was silently overridden by these values.
$CFG->session_redis_acquire_lock_timeout = 10; // Only wait 10s for lock
$CFG->session_redis_lock_expire = 300; // 5 min
$CFG->session_redis_lock_retries = 100;
$CFG->session_redis_lock_wait = 100; // ms
// NOTE: do not re-add $CFG->sessionlockenabled. It is not a Moodle setting (it
// appears nowhere in core), so setting it to false did nothing for as long as it
// was here while reading as though session locking had been turned off.

// ===== FILE SERVING OPTIMIZATION (Offload to Nginx) =====
$CFG->xsendfile = 'X-Accel-Redirect';
$CFG->xsendfilealiases = array(
    '/dataroot/' => '/var/moodledata',
);

// ===== FILE SERVING OPTIMIZATION =====
$CFG->filelifetime = 86400; // 24 hours cache for static files
$CFG->maxbytes = 104857600; // 100MB max file size
$CFG->slasharguments = true;
$CFG->yuislasharguments = true;

// ===== PERFORMANCE TUNING FOR HIGH TRAFFIC =====
$CFG->extramemorylimit = '5G';
$CFG->task_adhoc_max_runtime = 600;
$CFG->task_scheduled_max_runtime = 600;
# $CFG->task_adhoc_concurrency_limit = 2; // Reduced from 5 to prevent contention
# $CFG->task_scheduled_concurrency_limit = 1; // Reduced from 3

$CFG->tool_uploaduser_max_req_memory = '512M';  // Memory for CSV upload parsing
$CFG->tool_uploaduser_uncompressed = 1;         // Don't compress during upload
$CFG->tool_uploaduser_maxusers = 5000;          // Allow bulk import of 5000 at once

// ===== SECURITY SETTINGS =====
$CFG->cookiesecure = true;
$CFG->cookiehttponly = true;
$CFG->sslproxy = true;

// ===== VPL JAIL SERVER CONFIGURATION =====
// Allow connection to HTTP VPL jail server
// WARNING: This is a temporary solution. Use HTTPS in production.
$CFG->curlsecurityblockedhosts = ''; // Allow all hosts
$CFG->curlsecurityallowedport = "80\n443"; // Allow port 80 for VPL jail server

// Alternative: If you want to be more specific, use:
// $CFG->curlsecurityblockedhosts = ''; // Empty to allow all
// or specify allowed IPs/domains

// If using self-signed certificate on VPL server, add:
$CFG->curlignoresslverification = true; // Use only for self-signed certs in testing

// ===== CRON JOB OPTIMIZATION FOR ADMIN OPERATIONS =====
$CFG->cron_keepalive = 900; // 15 minutes for bulk operations
$CFG->task_adhoc_concurrency_limit = 1; // Increased to 3 to allow parallel tasks
$CFG->task_scheduled_concurrency_limit = 10; // Increased to 10

// ===== EMAIL CONFIGURATION (REAL SETTINGS) =====
$CFG->smtpmaxbulk = 100; // Send max 100 emails per SMTP session before reconnecting
$CFG->messageinbound_maxprocesstime = 600; // Max 10 minutes for message processing

// ===== QUICKMAIL ASYNC CONFIGURATION =====
// DO NOT force QuickMail settings - let the admin UI database settings take precedence
// The setting "block_quickmail_send_as_tasks" should be configured in:
// Site administration → Plugins → Blocks → QuickMail → "Send all messages as background tasks"
// 
// REMOVED these lines as they may be preventing async from working:
// $CFG->forced_plugin_settings['block_quickmail']['send_as_tasks'] = 1;
// $CFG->forced_plugin_settings['block_quickmail']['allow_async'] = 1;
//
// If you need to force these settings, ensure they match the exact plugin setting names

// ===== MOODLE EMAIL QUEUE CONFIGURATION =====
// Enable Moodle's native email task queuing system
$CFG->emaildisplayno = 0;  // Allow email sending
$CFG->noemailever = false;  // Don't disable email completely

// ===== PERFORMANCE OPTIMIZATION =====
# $CFG->sessiontimeout = 7200; // 2 hour session timeout
$CFG->enable_availability_cache = true; // Cache availability checks


# $CFG->debug = (E_ALL | E_STRICT);
# $CFG->debugdisplay = 1; // show debug messages in HTML output
# $CFG->perfdebug = 1;

# $CFG->debugdeveloper = true;

$CFG->debug = 0;
$CFG->debugdisplay = 0;
$CFG->perfdebug = 0;
$CFG->debugdeveloper = 0;

// Specific QuickMails logging (if this is a different plugin)
$CFG->forced_plugin_settings['local_quickmails']['debugmode'] = 1;
$CFG->forced_plugin_settings['local_quickmails']['loglevel'] = 'DEBUG';

// ===== THEME OPTIMIZATION =====
$CFG->themedesignermode = false;


// PXC (Galera) blocks GET_LOCK() in ENFORCING mode — use DB record locks instead
$CFG->lock_factory = '\core\lock\db_record_lock_factory';

require_once(__DIR__ . '/lib/setup.php');
