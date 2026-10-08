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
 * Strings for block_endsemesterexam.
 *
 * @package   block_endsemesterexam
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Semester Examination';
$string['endsemesterexam'] = 'Start Now';

// Audience.
$string['audienceheading'] = 'Who sits the exam';
$string['audienceheading_desc'] = 'Which learners are shown the exam button, and how they are identified to the exam portal.';
$string['gatemode'] = 'Entitlement';
$string['gatemode_desc'] = 'How to decide whether a learner may sit the exam.';
$string['gatemode_category'] = 'Enrolled in a course category';
$string['gatemode_profilefield'] = 'Has a register number';
$string['examcategory'] = 'Exam course category';
$string['examcategory_desc'] = 'Category ID whose learners sit the exam. Sub-categories count. Used only by the "Enrolled in a course category" entitlement.';
$string['registerfield'] = 'Register number field';
$string['registerfield_desc'] = 'Short name of the custom profile field holding the register number, for example <code>register_no</code>. Used by the "Has a register number" entitlement, and as the candidate identifier by portals that expect a student code.';

// Provider.
$string['providerheading'] = 'Exam portal';
$string['providerheading_desc'] = 'The exam button is the same on every site; the portal behind it is not. Choose this site\'s portal and give its endpoints. Endpoints and credentials are never shared between sites: if this site names its own endpoint it must give its own credentials, and the button fails with a clear message rather than reaching another institution\'s portal.';
$string['provider'] = 'Exam portal';
$string['provider_desc'] = 'Which exam portal candidates are handed to.';
$string['provider_examstack'] = 'Examstack (email sign-in)';
$string['provider_gianveshana'] = 'Gianveshana (student code)';

// Examstack settings.
$string['ssoendpoint'] = 'Examstack: SSO endpoint';
$string['ssoendpoint_desc'] = 'ERP sign-in API. The candidate\'s email address is posted here, server-side, in exchange for an access token. Leave empty to keep this site on the values the plugin shipped with.';
$string['ssoapikey'] = 'Examstack: API key';
$string['ssoapikey_desc'] = 'Sent as the <code>X-API-KEY</code> header. Required once an SSO endpoint is set.';
$string['ssoorigin'] = 'Examstack: Origin';
$string['ssoorigin_desc'] = 'Sent as the <code>Origin</code> header, which the API uses as a source gate. Optional.';
$string['examportalurl'] = 'Examstack: portal URL';
$string['examportalurl_desc'] = 'Exam app sign-in URL. The access token is appended to it, so this normally ends with a query parameter, for example <code>https://exams.example.com/sso-login/?token=</code>. Required once an SSO endpoint is set.';

// Gianveshana settings.
$string['tokenendpoint'] = 'Gianveshana: token endpoint';
$string['tokenendpoint_desc'] = 'The candidate\'s student code is posted here, under HTTP basic authentication, in exchange for a bearer token.';
$string['examurlendpoint'] = 'Gianveshana: exam URL endpoint';
$string['examurlendpoint_desc'] = 'Asked, with the bearer token, for the exam URL the candidate is sent to.';
$string['apiuser'] = 'Gianveshana: API username';
$string['apiuser_desc'] = 'HTTP basic authentication user for the token endpoint.';
$string['apipassword'] = 'Gianveshana: API password';
$string['apipassword_desc'] = 'HTTP basic authentication password for the token endpoint.';

// Runtime messages.
$string['ssofailed'] = 'Could not start the exam session right now. Please try again shortly, or contact support if this continues.';
$string['examnotconfigured'] = 'The exam portal is not set up on this site yet. Please contact support.';
$string['notentitled'] = 'You are not registered for this examination. Please contact support if you believe this is wrong.';
$string['unknowncandidate'] = 'The examination system does not recognise your register number. Please contact support.';
$string['privacy:metadata'] = 'The Semester Examination block stores no personal data itself. To sign a candidate in, it sends identifying details — the email address or register number, depending on the portal configured — to the external examination portal this site is configured to use.';
