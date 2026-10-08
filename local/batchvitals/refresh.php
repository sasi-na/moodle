<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Recompute one batch on demand.
 *
 * @package   local_batchvitals
 * @copyright 2026 JAIN Online
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_batchvitals\access;
use local_batchvitals\vitals;

require_login();
require_sesskey();

$batchid = required_param('batch', PARAM_INT);
$return = new moodle_url('/local/batchvitals/index.php', ['batch' => $batchid]);

// A site-wide viewer may refresh anything configured; a coordinator only the
// programme categories they hold the capability on.
if (!access::is_sitewide()) {
    $ctx = context_coursecat::instance($batchid, IGNORE_MISSING);
    if (!$ctx || !has_capability(access::CAP_PROGRAMME, $ctx)) {
        throw new required_capability_exception(context_system::instance(),
            access::CAP_PROGRAMME, 'nopermissions', '');
    }
}

// Up to a few seconds on the largest batch, so raise the limit rather than
// risk a half-written cache entry.
\core_php_time_limit::raise(120);

$row = vitals::refresh_one($batchid);
if ($row === null) {
    redirect($return, get_string('refreshfailed', 'local_batchvitals'), null,
        \core\output\notification::NOTIFY_ERROR);
}

redirect($return, get_string('refreshed', 'local_batchvitals',
    (object) ['name' => $row['name'], 'secs' => format_float($row['secs'], 2)]));
