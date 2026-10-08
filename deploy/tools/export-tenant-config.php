<?php
// Export a tenant's *configuration* (not its content) as a portable SQL seed.
//
//   php deploy/tools/export-tenant-config.php > /tmp/tenant-seed.sql
//
// Intended flow for standing up a new tenant:
//
//   1. Fresh Moodle install on the new database (admin/cli/install_database.php)
//   2. Apply this seed
//   3. Run admin/cli/upgrade.php
//   4. Set the new site's own identity (name, theme colours, logo) in the UI
//
// Why not clone the source database and delete the content?
//
// jain's database is 50.9 GB across 592 tables, holding 62,152 users, 1,766
// courses and 1,038,107 enrolments. The configuration actually worth carrying
// across is about 8,700 rows. Moodle declares no foreign keys, so deleting
// content leaves silent orphans, and a single missed table means one
// institution's student PII -- date of birth, parents' names, phone numbers,
// ABC/DEB IDs -- ends up inside another institution's LMS. Building the target
// from a clean schema makes that leak structurally impossible instead of
// dependent on enumerating 592 tables correctly.
//
// This script is READ-ONLY against the source database.

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');

global $DB;

// ---------------------------------------------------------------------------
// Deny rules. Anything matching is never exported.
// ---------------------------------------------------------------------------

// Setting names that carry credentials. Matched case-insensitively as a
// substring, so this catches apikey/api_key/secretkey/smtppass/... alike.
const DENY_NAME_SUBSTRINGS = [
    'pass', 'passwd', 'password',
    'secret', 'token', 'apikey', 'api_key',
    'privatekey', 'private_key', 'clientsecret', 'client_secret',
    'salt', 'credential', 'auth_key', 'accesskey', 'access_key',
    'sharedsecret', 'consumerkey', 'certificate',
];

// Core settings that identify or version THIS site. Copying them to another
// site is wrong at best and breaks caching or duplicate-site detection at worst.
const DENY_CONFIG_EXACT = [
    'siteidentifier',       // must be unique per site
    'wwwroot', 'dataroot',
    'themerev', 'jsrev', 'templaterev', 'yuipatchlevel',
    'localcachedirpurged', 'scheduledtaskreset',
    'cronremotepassword',
    'supportemail', 'supportname', 'supportpage',
    'noreplyaddress',
    'sitename', 'shortname', 'summary',
    'registered', 'registrationpending',
    'lastcron', 'lastcronstart',
    'allversionshash', 'version', 'release', 'branch',
    'digestmailtimelast', 'statslastdaily', 'statslastweekly',
    'statslastmonthly', 'statsrunning',
];

// Plugin config namespaces worth carrying: the bespoke plugins and the theme.
// Core plugin settings are deliberately NOT exported -- a fresh install already
// has sane defaults, and blanket-copying them drags along site-specific state.
const ALLOW_PLUGIN_PREFIXES = [
    'local_', 'theme_baz', 'mod_vpl', 'mod_pdfprotect',
    'mod_msteams', 'mod_zoom', 'mod_adaptivequiz', 'mod_dialogue',
    'mod_scheduler', 'mod_myactivity', 'block_',
];

// Plugin settings that carry the SOURCE tenant's identity rather than its
// behaviour. These are not secrets and not personal data, so nothing above
// catches them -- but applied unreviewed they make the new site wear the old
// one's branding and mail the old one's staff.
//
// Matched as "plugin/name", with a trailing * for a prefix match.
const DENY_PLUGIN_SETTINGS = [
    // Branding and identity.
    'theme_baz/customlogo', 'theme_baz/customlogomini', 'theme_baz/favicon',
    'theme_baz/loginbgimage', 'theme_baz/cwebsiteurl',
    'theme_baz/footerblock*', 'theme_baz/copyright*',
    'theme_baz/facebook', 'theme_baz/twitter', 'theme_baz/instagram',
    'theme_baz/linkedin', 'theme_baz/youtube', 'theme_baz/whatsapp',
    'theme_baz/dashboardbanner*', 'theme_baz/scb*',

    // Outbound mail identity and recipient lists -- the ones that would send a
    // new tenant's notifications to the previous tenant's staff.
    'local_welcome/sender_email', 'local_welcome/sender_firstname',
    'local_welcome/sender_lastname', 'local_welcome/moderator_email',
    'local_welcome/message_*',
    'local_rpreport/recipients',
    'local_ams/skipnotifyemails',

    // Endpoints belonging to the source tenant's other systems.
    'block_jan2025results/result_url',
    'block_renewals/payment_url',
    'block_supersetembed/apiurl', 'block_supersetembed/superseturl',

    // Shared infrastructure that must be an explicit decision, not inherited.
    'mod_vpl/jail_servers',
];

$skipped = [
    'secretish' => 0, 'siteidentity' => 0,
    'notallowed' => 0, 'tenantidentity' => 0,
];

// Names of the tenant-identity settings that were dropped, listed in the footer
// so the operator knows exactly what the new site still has to be given.
$identityskips = [];

// True when the setting names the source tenant rather than describing
// behaviour: an explicit deny entry, an email address, or a hostname belonging
// to the source site.
function is_tenant_identity(string $plugin, string $name, ?string $value): bool {
    $key = $plugin . '/' . $name;
    foreach (DENY_PLUGIN_SETTINGS as $pattern) {
        if (substr($pattern, -1) === '*') {
            if (strpos($key, rtrim($pattern, '*')) === 0) {
                return true;
            }
        } else if ($key === $pattern) {
            return true;
        }
    }

    // Value-based backstop, so a setting added later is caught without anyone
    // remembering to extend the list above.
    if ($value !== null && $value !== '') {
        if (preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', $value)) {
            return true;   // any email address
        }
        if (preg_match('/\b(onlinejain|jainonline)\.(com|info|in)\b/i', $value)) {
            return true;   // the source tenant's own domains
        }
    }
    return false;
}

function is_secretish(string $name): bool {
    $n = strtolower($name);
    foreach (DENY_NAME_SUBSTRINGS as $bad) {
        if (strpos($n, $bad) !== false) {
            return true;
        }
    }
    return false;
}

function plugin_allowed(string $plugin): bool {
    foreach (ALLOW_PLUGIN_PREFIXES as $p) {
        if (strpos($plugin, $p) === 0) {
            return true;
        }
    }
    return false;
}

function q($v): string {
    global $DB;
    return is_null($v) ? 'NULL' : "'" . str_replace("'", "''", (string)$v) . "'";
}

$out = [];
$out[] = "-- Tenant configuration seed";
$out[] = "-- Source: " . $CFG->wwwroot;
$out[] = "-- Contains configuration only. No users, courses, enrolments, grades,";
$out[] = "-- files, logs or personal data. Credentials are stripped -- every";
$out[] = "-- password, token and API key must be re-entered on the target site.";
$out[] = "--";
$out[] = "-- Apply to a FRESHLY INSTALLED Moodle, then run admin/cli/upgrade.php.";
$out[] = "";
$out[] = "START TRANSACTION;";
$out[] = "";

// ---------------------------------------------------------------------------
// 1. Plugin settings
// ---------------------------------------------------------------------------
$out[] = "-- ---------- config_plugins ----------";
$n = 0;
foreach ($DB->get_records('config_plugins', null, 'plugin, name') as $r) {
    if (!plugin_allowed($r->plugin)) { $skipped['notallowed']++; continue; }
    if (is_secretish($r->name))      { $skipped['secretish']++;  continue; }
    if (is_tenant_identity($r->plugin, $r->name, $r->value)) {
        $skipped['tenantidentity']++;
        // Listed rather than silently dropped: these are the settings the new
        // tenant has to supply for itself, so the operator needs to see them.
        $identityskips[] = $r->plugin . '/' . $r->name;
        continue;
    }

    $out[] = sprintf(
        "INSERT INTO {prefix}config_plugins (plugin, name, value) VALUES (%s, %s, %s)\n" .
        "  ON DUPLICATE KEY UPDATE value = VALUES(value);",
        q($r->plugin), q($r->name), q($r->value)
    );
    $n++;
}
$out[] = "-- {$n} plugin settings";
$out[] = "";

// ---------------------------------------------------------------------------
// 2. Custom profile fields (DEFINITIONS ONLY -- never user_info_data)
// ---------------------------------------------------------------------------
$out[] = "-- ---------- profile field definitions ----------";
$out[] = "-- Definitions only. user_info_data holds the actual personal values";
$out[] = "-- and is deliberately never exported.";
foreach ($DB->get_records('user_info_category', null, 'sortorder') as $c) {
    $out[] = sprintf(
        "INSERT INTO {prefix}user_info_category (id, name, sortorder) VALUES (%d, %s, %d)\n" .
        "  ON DUPLICATE KEY UPDATE name = VALUES(name), sortorder = VALUES(sortorder);",
        $c->id, q($c->name), $c->sortorder
    );
}
$fields = $DB->get_records('user_info_field', null, 'categoryid, sortorder');
foreach ($fields as $f) {
    $cols = ['shortname','name','datatype','description','descriptionformat','categoryid',
             'sortorder','required','locked','visible','forceunique','signup',
             'defaultdata','defaultdataformat','param1','param2','param3','param4','param5'];
    $vals = [];
    foreach ($cols as $c) {
        $vals[] = is_numeric($f->$c ?? null) ? (string)($f->$c ?? 0) : q($f->$c ?? null);
    }
    $out[] = sprintf(
        "INSERT INTO {prefix}user_info_field (id, %s) VALUES (%d, %s)\n" .
        "  ON DUPLICATE KEY UPDATE shortname = VALUES(shortname), name = VALUES(name);",
        implode(', ', $cols), $f->id, implode(', ', $vals)
    );
}
$out[] = "-- " . count($fields) . " profile fields";
$out[] = "";

// ---------------------------------------------------------------------------
// 3. Custom roles + capability overrides
// ---------------------------------------------------------------------------
$out[] = "-- ---------- custom roles ----------";
$coreshortnames = ['manager','coursecreator','editingteacher','teacher','student','guest','user','frontpage'];
$roles = $DB->get_records('role', null, 'sortorder');
$customroles = [];
foreach ($roles as $r) {
    if (in_array($r->shortname, $coreshortnames, true)) {
        continue;
    }
    $customroles[$r->id] = $r;
    $out[] = sprintf(
        "INSERT INTO {prefix}role (id, name, shortname, description, sortorder, archetype)\n" .
        "  VALUES (%d, %s, %s, %s, %d, %s)\n" .
        "  ON DUPLICATE KEY UPDATE name = VALUES(name), archetype = VALUES(archetype);",
        $r->id, q($r->name), q($r->shortname), q($r->description), $r->sortorder, q($r->archetype)
    );
}
foreach ($customroles as $rid => $r) {
    foreach ($DB->get_records('role_capabilities', ['roleid' => $rid]) as $rc) {
        $out[] = sprintf(
            "INSERT INTO {prefix}role_capabilities (contextid, roleid, capability, permission)\n" .
            "  VALUES (%d, %d, %s, %d)\n" .
            "  ON DUPLICATE KEY UPDATE permission = VALUES(permission);",
            $rc->contextid, $rc->roleid, q($rc->capability), $rc->permission
        );
    }
    foreach ($DB->get_records('role_context_levels', ['roleid' => $rid]) as $rl) {
        $out[] = sprintf(
            "INSERT IGNORE INTO {prefix}role_context_levels (roleid, contextlevel) VALUES (%d, %d);",
            $rl->roleid, $rl->contextlevel
        );
    }
}
$out[] = "-- " . count($customroles) . " custom roles";
$out[] = "";

// ---------------------------------------------------------------------------
// 4. Core settings -- reported, NOT auto-applied
// ---------------------------------------------------------------------------
$out[] = "-- ---------- core config (COMMENTED OUT -- review before use) ----------";
$out[] = "-- Core settings are emitted commented out on purpose. Many encode site";
$out[] = "-- identity or tuning specific to jain's scale, and a fresh install";
$out[] = "-- already ships sane defaults. Uncomment only what the new site needs.";
foreach ($DB->get_records('config', null, 'name') as $r) {
    if (in_array($r->name, DENY_CONFIG_EXACT, true)) { $skipped['siteidentity']++; continue; }
    if (is_secretish($r->name))                      { $skipped['secretish']++;    continue; }
    $stmt = sprintf(
        "INSERT INTO {prefix}config (name, value) VALUES (%s, %s)\n" .
        "  ON DUPLICATE KEY UPDATE value = VALUES(value);",
        q($r->name), q($r->value)
    );
    // Comment EVERY line, not just the first. A setting value can contain
    // literal newlines -- `agedigitalconsentmap` is one country per line -- and
    // prefixing only the opening line leaves the rest as live SQL. Applying such
    // a seed dies mid-file with a syntax error pointing at what looks like
    // country codes, and because the whole seed is one transaction, nothing at
    // all is applied.
    $out[] = preg_replace('/^/m', '-- ', $stmt);
}
$out[] = "";
$out[] = "COMMIT;";
$out[] = "";
$out[] = sprintf(
    "-- Skipped: %d credential-bearing, %d site-identity, %d tenant-identity,\n" .
    "--          %d non-allowlisted plugin settings.",
    $skipped['secretish'], $skipped['siteidentity'],
    $skipped['tenantidentity'], $skipped['notallowed']
);
$out[] = "-- Every skipped credential must be re-entered on the target site.";

if ($identityskips) {
    $out[] = "--";
    $out[] = "-- Tenant-identity settings dropped. These carried the SOURCE site's";
    $out[] = "-- branding, mail identity or endpoints; the new site must supply its own:";
    sort($identityskips);
    foreach ($identityskips as $k) {
        $out[] = "--   " . $k;
    }
}

// {prefix} is left as a literal so the target site can substitute its own table
// prefix. Both sites currently use acu_.
echo str_replace('{prefix}', $CFG->prefix, implode("\n", $out)) . "\n";
