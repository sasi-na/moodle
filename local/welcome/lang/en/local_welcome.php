<?php
// This file is part of the Local welcome plugin
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
 * This plugin sends users a welcome message after logging in
 * and notify a moderator a new user has been added
 * it has a settings page that allow you to configure the messages
 * send.
 *
 * @package    local
 * @subpackage welcome
 * @copyright  2017 Bas Brands, basbrands.nl, bas@sonsbeekmedia.nl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Moodle welcome';
$string['fieldname'] = 'Fieldname';
$string['type'] = 'Type';
$string['yourvalue'] = 'Your Value';
$string['customprofilefields'] = 'Custom profile fields';
$string['defaultprofilefields'] = 'Default profile fields';
$string['welcomefields'] = 'Additional template fields';
$string['globalhelp'] = 'This plugin for Moodle sends a configurable welcome message to new users.
<br><br>
The plugin uses the event system in Moodle and will be triggerd when a new
user is created, no matter if this was a manually created account or an
account created using self registration.<br>
<br>
The tables on this page show the available profile fields that can be used in the message template on this plugin\'s configuration page.
The values shown in this table are YOUR profile field values, they will be replaced by the recipients values when the welcome email is send.';
$string['configure'] = 'Configure this plugin';
$string['auth_plugins'] = 'Auth plugins';
$string['auth_plugins_desc'] = 'Choose the auth plugins for which a welcome message should be send';
$string['message_user_enabled'] = 'Enable user messages';
$string['message_user_enabled_desc'] = 'This tickbox enables the sending of welcome messages to new users<br><br>Visit <a href="{$a}">this page to see the list of available fields</a>';
$string['message_user_subject'] = 'User subject';
$string['message_user_subject_desc'] = 'This will be the subject of the email send to the user. Use [[fullname]] as a tag, this will be replaced with the users Firstname Lastname.';
$string['message_user'] = 'User message';
$string['message_user_desc'] = 'Message send to new users';

$string['message_moderator_enabled'] = 'Enable moderator messages';
$string['message_moderator_enabled_desc'] = 'This tickbox enables the sending of notification messages to moderators';
$string['message_moderator'] = 'Moderator message';
$string['message_moderator_subject'] = 'Moderator subject';
$string['message_moderator_subject_desc'] = 'This will be the subject of the email send to the moderator. Use [[fullname]] as a tag, this wil be replaced with the users Firstname Lastname.';
$string['message_moderator_desc'] = 'Message send to moderators';
$string['moderator_email'] = 'Moderator email';
$string['moderator_email_desc'] = 'New user notifications are send to this email address';

$string['sender_email'] = 'Sender email address';
$string['sender_email_desc'] = 'When new users log in this email address is used to send a notification message, users will be able to see this email address';

$string['sender_firstname'] = 'Welcome message sender firstname';
$string['sender_firstname_desc'] = 'First name used when sending mail to users.';

$string['sender_lastname'] = 'Moderator lastname';
$string['sender_lastname_desc'] = 'Last name used when sending mail to users.';

$string['default_user_email_subject'] = 'Welcome to [[sitename]] - Your Login Details';
$string['default_user_email'] = '

<html>
<body>
<table cellspacing="0" cellpadding="8">
<tr><td colspan="2"><h3>Welcome [[fullname]]</h3>
    Your account has been created on [[sitelink]]. Use the login details below to
    sign in:</td></tr>
<tr><td>Name:</td><td>[[fullname]]</td></tr>
<tr><td>Username: </td><td>[[username]]</td></tr>
<tr><td>Password: </td><td><strong>[[password]]</strong></td></tr>
<tr><td colspan="2">Login here: [[loginurl]]</td></tr>
<tr><td colspan="2">For your security, please change your password after you first sign in.<br>
    If you ever forget it, resetting is easy: [[resetpasswordlink]]</td></tr>
</table>
</body>
</html>
';

$string['fixedpassword'] = 'Fixed password for new accounts';
$string['fixedpassword_desc'] = 'Leave this empty to give every new account a randomly generated password, which is the recommended setting. If a value is entered, that single password is issued instead to every new account and to every password reset, and is what the learner receives by email. It must satisfy the site password policy. Because the same password is then held by every account that has not yet logged in, only use this where "Force password change on first login" above is also enabled.';

$string['resetexistingonupload'] = 'Regenerate passwords for existing users on upload';
$string['resetexistingonupload_desc'] = 'When a CSV upload is run with "New user password" set to create and email passwords, also generate a new password for users that already exist, and email it to them using the password reset message below. Their current password stops working immediately, so leave this off unless a run is specifically intended to reissue credentials.';

$string['forcepasswordchange'] = 'Force password change on first login';
$string['forcepasswordchange_desc'] = 'When a password is auto-generated (new account) or reset and emailed to the learner, require them to set a new password the first time they log in. Recommended: the emailed password becomes one-time.';

$string['message_reset_subject'] = 'Password reset subject';
$string['message_reset_subject_desc'] = 'Subject of the email sent to a learner when their password is reset and emailed. Use [[fullname]] as a tag.';
$string['message_reset'] = 'Password reset message';
$string['message_reset_desc'] = 'Message sent to a learner when an administrator (or the AMS/ERP) resets their password. Use [[password]] to include the new password and [[loginurl]] for the login link.';

$string['default_reset_email_subject'] = 'Your [[sitename]] password has been reset';
$string['default_reset_email'] = '

<html>
<body>
<table cellspacing="0" cellpadding="8">
<tr><td colspan="2"><h3>Hello [[fullname]],</h3>
    Your password on [[sitelink]] has been reset. Your updated login details are:</td></tr>
<tr><td>Username: </td><td>[[username]]</td></tr>
<tr><td>New password: </td><td><strong>[[password]]</strong></td></tr>
<tr><td colspan="2">Login here: [[loginurl]]</td></tr>
<tr><td colspan="2">For your security, please change this password after you sign in.<br>
    If you did not expect this change, please contact support immediately.</td></tr>
</table>
</body>
</html>
';

$string['resetcredentials'] = 'Reset password &amp; email new credentials';
$string['resetpassword_confirm'] = 'Generate a new random password for <strong>{$a}</strong> and email the updated login details to their registered email address? Their existing password will stop working immediately.';
$string['resetpassword_done'] = 'A new password was generated and emailed to {$a}.';
$string['resetpassword_mailfailed'] = 'The password for {$a} was reset, but the notification email could not be sent.';
$string['passwordfield'] = 'password (only shown in the welcome/reset email at send time)';
$string['loginurlfield'] = 'loginurl';
$string['cannotresetexternalauth'] = 'Cannot reset the password for an externally-authenticated account (auth: {$a}).';
$string['default_moderator_email_subject'] = 'A new user signed up on [[sitename]] : [[fullname]]';
$string['default_moderator_email'] = '
<html>
<body>
<table cellspacing="0" cellpadding="8">
<tr><td colspan="2"><h3>New site user [[fullname]]</h3>
    A new account has been created with the following details:</td></tr>
<tr><td>Name:</td><td>[[fullname]]</td></tr>
<tr><td>Username: </td><td>[[username]]</td></tr>
<tr><td>Email: </td><td>[[email]]</td></tr>
</table>
</body>
</html>';

$string['resetpass'] = 'Reset your password here';
$string['privacy:metadata'] = 'This plugin only sends a welcome email. It does not store any personal data.';
