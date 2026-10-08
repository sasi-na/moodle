<?php
// learning.iiitdwd.ac.in
//
// IIITD tenant config, derived from nitk's. Migration notes:
//
//   1. wwwroot is learning.iiitdwd.ac.in as of the 2026-09-14 cutover. The
//      staging hostname this site was built and validated on has since been
//      retired: it is off the HTTPRoute and no longer resolves.
//
//   2. dbname/dbuser/prefix are kept verbatim from the AWS box: `jaincfs` and
//      `cfs_`. Both are leftovers from an older migration and look wrong, but
//      renaming them during a migration buys nothing and risks a missed table.
//
//   3. dbpass comes from the environment (moodle-secrets), never from git.

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
// Every tenant on prod-gateway shares this one forwarding-rule address, so the value is
// the same here as elsewhere: learning.iiitdwd.ac.in resolves to 34.95.112.39, and did
// so under the staging hostname too, so the cutover changed nothing about this value.
// If IIITD is ever moved to its own IP, change it here or every logged IP becomes the LB's.
$CFG->reverseproxyignore = '34.95.112.39';

// Database configuration
$CFG->dbtype    = 'mysqli';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'mysql-db-pxc-db-haproxy';
$CFG->dbname    = 'jaincfs';
$CFG->dbuser    = 'jaincfs';
$CFG->dbpass    = getenv('MOODLE_DATABASE_PASSWORD');
$CFG->prefix    = 'cfs_';
$CFG->smsdisabled = true;

// Opt in to the branded email header/footer in email_to_user(). This is OFF by
// default for every tenant; without it, IIITD's letterhead would appear on mail
// from every other site sharing this image. Images are served from this site's
// own wwwroot -- see lib/moodlelib.php.
$CFG->emailbranding = true;

// IIITD-only features that ship in the shared image. Both default OFF for every
// other tenant: videotracker activates on any page embedding a Vimeo player,
// which is NOT a tenant boundary (jain and learn-usdc have ~69,000 such pages).
$CFG->videotracker   = true;
$CFG->assigngradelog = true;

// block_support hands users to a helpdesk whose endpoints used to be hardcoded
// to Jain's. On this site that meant an IIITD student clicking "Raise a Ticket"
// had their email POSTed to support.onlinejain.com/api/v1/sync-user/ and was
// then redirected into Jain's portal. Forced here rather than left to the admin
// UI so the destination cannot drift back to another tenant's helpdesk.
//
// IIITD's own helpdesk is support.iiitdwd.com (see the 4.4 build on the AWS
// box). The API key is NOT in this file: set BLOCK_SUPPORT_API_KEY in the
// deployment secret. With a base URL set and no key, support_api.php fails
// closed rather than falling back to Jain's key -- so an unset secret is a
// visible error, never a silent redirect to another tenant.
$CFG->forced_plugin_settings['block_support'] = [
    'apibaseurl'        => 'https://support.iiitdwd.com',
    'apikey'            => getenv('BLOCK_SUPPORT_API_KEY') ?: '',
    'portalticketurl'   => 'https://support.iiitdwd.com',
    // IIITD does not use the Learner Escalation SOP document.
    'showescalationsop' => '0',
];

// block_endsemesterexam shows one common "Start Now" button, but the exam portal
// behind it differs per institution -- and the portal was hardcoded. As shipped,
// this block posts the learner's EMAIL to Jain's ERP (dev.api-erp.onlinejain.com)
// and then hands the browser to exams.onlinejain.com. IIITD is protected from
// that today only by accident: the shipped audience gate is Jain's course
// category 174, which does not exist here, so the button never renders. Enabling
// it without the settings below would activate that cross-tenant data path.
//
// IIITD's own exam provider is Gianveshana (api.jgianveshana.com), reached with a
// two-step handshake: the learner's register number is exchanged for a bearer
// token under HTTP basic auth, then that token is exchanged for the exam URL.
// This is the flow the 4.4 build on the AWS box used.
//
// Entitlement matches production's: any learner whose register_no profile field
// is filled (431 of them here). The block derives that from the session -- it is
// deliberately NOT passed through the browser, as it was on AWS, where editing
// the URL would have obtained an exam session for another candidate.
//
// Credentials are NOT in this file: set EXAM_API_USER and EXAM_API_PASSWORD in
// the deployment secret. With a provider selected and credentials missing, the
// button fails closed with "not set up yet" -- it never falls back to another
// tenant's portal.
$CFG->forced_plugin_settings['block_endsemesterexam'] = [
    'gatemode'        => 'profilefield',
    'registerfield'   => 'register_no',
    'provider'        => 'gianveshana',
    'tokenendpoint'   => 'https://api.jgianveshana.com/v1/token/31',
    'examurlendpoint' => 'https://api.jgianveshana.com/v1/onlineExamUrl/31',
    'apiuser'         => getenv('EXAM_API_USER') ?: '',
    'apipassword'     => getenv('EXAM_API_PASSWORD') ?: '',
];

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

// Theme settings forced for this site.
//
// supportticketurl: the floating "Need help?" panel points at IIITD's own
// helpdesk (I-dost) through the SSO endpoint, so the student arrives signed in
// rather than at a second login screen. The Support block is not the entry
// point here -- the bespoke panel is, and the block itself is hidden.
//
// student_course_blocks: the bespoke course view does not render the side-pre
// region, so Live Session Stats -- which students had on the old theme, with
// its attendance modal -- is invisible to them. Named here so it comes back as
// a card in the student rail.
$CFG->forced_plugin_settings['theme_baz'] = [
    'supportticketurl'     => '/blocks/support/support_api.php',
    // Reflective Journal is placed for students in Project-III (course 83, 78
    // students) but was not named here, so the bespoke course page never drew
    // it -- staff saw it on the classic layout, students did not.
    'student_course_blocks' => 'livesessionstats,reflectivejournal',
    // Live Session Stats already shows live-session progress, so the built-in
    // "Your progress" card directly above it repeated the same figures.
    'student_rail_progress' => '0',
    // The bespoke student dashboard renders no generic blocks, so the exam
    // button reaches students only through the dashboard's own CTA. This is the
    // exam-cycle switch for it; entitlement is block_endsemesterexam's.
    'show_endsem_exam'      => '1',
    // Support contact, shown in the floating "Need help?" panel under the
    // helpdesk button. Asked for so students have a route to a person that does
    // not depend on the ticketing system being reachable.
    'supportcontactemail'   => 'support.dsai@iiitdwd.ac.in',
    'supportcontactphone'   => '87929 97407',
];

// Core's own support contact. Used by the "Contact site support" page and the
// footer, and as the reply-to on some system mail -- all of which pointed at the
// shared placeholder supportemail@noemailusdc.com, which is not IIITD's and not
// monitored. supportpage is left unset so core keeps linking its own contact
// form rather than sending students off-site.
$CFG->supportemail = 'support.dsai@iiitdwd.ac.in';
$CFG->supportname  = 'IIITD DSAI Support';

// Site configuration
//
// LIVE VALUE, set at cutover on 2026-09-14. learning.iiitdwd.ac.in now resolves
// to this platform (A record 34.95.112.39, changed by IIITD IT); AWS no longer
// serves it.
//
// This had to change with the DNS, not after it: Moodle sends every visitor to
// wwwroot, so while this named the staging hostname a user arriving on the real
// domain was bounced straight back to staging and the cutover did nothing.
//
// No URL-rewrite step accompanies this, deliberately. The data in this database
// came from the AWS box, where learning.iiitdwd.ac.in has always been the live
// domain, so stored URLs already say it -- the hostname does not change from the
// data's point of view. admin/tool/replace would be a no-op at best.
//
// The exception, and the thing to watch: anything authored or touched WHILE this
// site ran as staging carries the old staging hostname in its stored URLs, which
// no longer resolves. That is a real rewrite candidate, scoped to content created
// between the data import and cutover -- not the whole database.
$CFG->wwwroot   = 'https://learning.iiitdwd.ac.in';
$CFG->sslproxy = true;
$CFG->dataroot  = '/var/moodledata';
$CFG->admin     = 'admin';
$CFG->directorypermissions = 02777;

// ===== REDIS SESSION CONFIGURATION =====
$CFG->session_handler_class = '\core\session\redis';
$CFG->session_redis_host = 'redis-service';
$CFG->session_redis_port = 6379;
$CFG->session_redis_database = 0;
$CFG->session_redis_prefix = 'iiitd_sess_';
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
$CFG->cache_redis_prefix = 'iiitd_cache_';
$CFG->cache_redis_serializer = 1;
$CFG->cache_redis_compressor = 0;
$CFG->cache_redis_ttl = 86400;

$CFG->forced_cache_configuration = array(
    'stores' => array(
        'redis_muc' => array(
            'class' => 'cachestore_redis',
            'configuration' => array(
                'server' => 'redis-service:6379',
                'prefix' => 'iiitd_muc_',
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
// Sized for IIITD's footprint (2 pods x 2 CPU / 4Gi). 603 users, 94 courses.
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
// Mail is LIVE as of the 2026-09-14 cutover.
//
// This was true for the whole staging period, for good reason. On 2026-09-08,
// with it false and cron enabled, the site sent 7,991 notifications to 456 real
// students in under three hours -- 2,856 of them "new device sign-in" security
// alerts dated months earlier. Every one was a stale adhoc task from the
// imported database, replayed by a test system.
//
// The condition for flipping it was that the backlog be purged FIRST. That was
// done at cutover: 37,483 tasks deleted, of which 21,281 were exactly those
// sign-in alerts. Before flipping, every queue that can generate mail was
// checked and found empty -- the outgoing message table, QuickMail (48 of 48
// already sent), generated-password mails, enrolment-expiry notices, badge
// notifications, and the failed-login digest, which has no recipients
// configured. 223 adhoc tasks remain and none of them send anything.
//
// If mail ever needs stopping again in a hurry, divertallemailsto is the
// reliable brake and does not need a deploy to take effect the way this does.
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
