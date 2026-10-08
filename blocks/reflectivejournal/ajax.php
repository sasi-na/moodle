<?php
// Server-side SSO handshake for the Reflective Journal block.
// Runs the 2-step flex SSO flow and returns the one-time login URL as JSON.
// The master key is kept here on the server and is never exposed to the browser.

define('AJAX_SCRIPT', true);

require('../../config.php');

require_login();
require_sesskey();

global $USER;

$response = ['success' => false];

$email = trim((string)$USER->email);
if ($email === '') {
    echo json_encode($response);
    exit;
}

$apibase   = 'https://flex-api.iiitdwd.com';
$masterkey = 'JainUniversity2025SecretKey';

/**
 * Small JSON POST helper. Returns the decoded array on success, or null on any failure.
 */
function reflectivejournal_post(string $url, array $payload, array $extraheaders = []): ?array {
    $headers = array_merge(['Content-Type: application/json', 'Accept: application/json'], $extraheaders);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
    ]);

    $raw      = curl_exec($ch);
    $httpcode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $httpcode < 200 || $httpcode >= 300) {
        return null;
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

// Step 1 - get a short-lived access key using the SSO master key.
$keydata = reflectivejournal_post(
    $apibase . '/api/auth/sso/key/?Key=' . rawurlencode($masterkey),
    ['email' => $email]
);

if ($keydata === null || empty($keydata['key'])) {
    echo json_encode($response);
    exit;
}

$accesskey = trim($keydata['key']);

// Step 2 - exchange the access key for a one-time login URL.
$logindata = reflectivejournal_post(
    $apibase . '/api/auth/sso/sync-user/',
    ['email' => $email],
    ['Authorization: Bearer ' . $accesskey]
);

if ($logindata === null || empty($logindata['login_url'])) {
    echo json_encode($response);
    exit;
}

$response['success']   = true;
$response['login_url'] = trim($logindata['login_url']);

echo json_encode($response);
exit;
