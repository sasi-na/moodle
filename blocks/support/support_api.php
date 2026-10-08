<?php
require_once('../../config.php');
global $USER;

require_login();

// Destination helpdesk. Previously hardcoded to Jain's, which meant every site
// on this shared image POSTed its own users' email addresses to Jain and then
// redirected them into Jain's portal.
//
// The base URL and the key are read together, never mixed: a site that has
// configured its own helpdesk must not fall back to Jain's API key just
// because its own key is missing, because that would send this site's users
// straight back to Jain -- the exact bug this replaces. So the legacy defaults
// apply only when the site has configured no base URL at all, which keeps
// Jain's own site working unchanged.
const BLOCK_SUPPORT_LEGACY_BASE = 'https://support.onlinejain.com';
const BLOCK_SUPPORT_LEGACY_KEY = '426f90b088970132136cbc96dfa42d55dd5606d1';

$base = rtrim((string) get_config('block_support', 'apibaseurl'), '/');
if ($base === '') {
    $base = BLOCK_SUPPORT_LEGACY_BASE;
    $apikey = BLOCK_SUPPORT_LEGACY_KEY;
} else {
    $apikey = (string) get_config('block_support', 'apikey');
}

if ($apikey === '') {
    // Configured helpdesk with no key: fail closed rather than hand the user to
    // somebody else's helpdesk.
    throw new moodle_exception('supportnotconfigured', 'block_support');
}

$email = $USER->email;
$payload = [
    "email" => $email
];

$url = $base . "/api/v1/auth/key/?Key=" . rawurlencode($apikey);


$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
curl_close($ch);
$responseData = json_decode($response, true);
$tokenkey = $responseData['key'] ?? '';

if (!$tokenkey) {
    $loginurl = $base;
    redirect($loginurl);
}

$url = $base . "/api/v1/sync-user/";

$payload = [
    "email" => $email
];

$ch2 = curl_init($url);

curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_POST, true);
curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($payload));

curl_setopt($ch2, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $tokenkey",
    "Content-Type: application/json"
]);

$response2 = curl_exec($ch2);
curl_close($ch2);

// Decode second response
$responseData2 = json_decode($response2, true);
$loginurl = $responseData2['login_url'] ?? '';


if (!empty($loginurl)) {
    redirect($loginurl);
} else {
    $loginurl = $base;
    redirect($loginurl);
}