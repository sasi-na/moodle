<?php
// Local read-only helper: active accounts and the fields the user browser filters on. CLI only.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
foreach ($DB->get_records_select('user', 'deleted = 0', null, 'id', 'id, username, firstname, lastname, email, auth, confirmed, suspended, mnethostid') as $u) {
    echo implode(' | ', [(int) $u->id, $u->username, "$u->firstname $u->lastname", $u->email, $u->auth, "confirmed=$u->confirmed", "suspended=$u->suspended", "mnethost=$u->mnethostid"]) . "\n";
}
echo "local mnethost id: {$CFG->mnet_localhost_id}\n";
// Saved filters on the admin "Browse list of users" report.
$rep = $DB->get_records_select('reportbuilder_report', "source LIKE '%users%' AND type = 1", null, '', 'id, source, component, area');
foreach ($rep as $r) {
    $prefs = $DB->get_records_select('user_preferences', "userid = 2 AND name LIKE ?", ["reportbuilder-report-{$r->id}%"]);
    foreach ($prefs as $p) { echo "admin filter pref {$p->name}: " . substr($p->value, 0, 300) . "\n"; }
}
