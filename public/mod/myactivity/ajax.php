<?php
require_once(__DIR__ . '/../../config.php');
require_login();
global $USER;

header('Content-Type: application/json; charset=utf-8');

$apiKey    = 'jain1020';
$apiSecret = 'qxpnv9f6ubd4';

// 1️⃣ Request token
$tokenUrl = "https://partner.isdcpro.com/api/rogo/v1/token/?apikey=" . urlencode($apiKey) . "&apisecret=" . urlencode($apiSecret);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $tokenUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 1,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
]);

$tokenResponse = curl_exec($ch);
$curlErrNo = curl_errno($ch);
$curlErr = curl_error($ch);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($curlErrNo !== 0 || $httpStatus >= 400) {
    echo json_encode([
        'success' => false,
        'stage' => 'token_request_failed',
        'http_status' => $httpStatus,
        'curl_errno' => $curlErrNo,
        'curl_error' => $curlErr,
        'raw_response' => $tokenResponse
    ]);
    exit;
}

// 🔧 FIX: handle both JSON and raw quoted string responses
$token = null;
$trimmed = trim($tokenResponse);

// If it’s JSON with "token" key
if (strpos($trimmed, '{') === 0) {
    $tokenData = json_decode($trimmed, true);
    $token = $tokenData['token'] ?? null;
} else {
    // It’s probably a raw string like "eyJhbGc..."
    $token = trim($trimmed, "\" \n\r\t");
}

if (empty($token)) {
    echo json_encode([
        'success' => false,
        'stage' => 'token_missing',
        'raw_response' => $tokenResponse
    ]);
    exit;
}

// 2️⃣ Login call using Moodle user email
$email = $USER->email;
//$email ="sk2018isdc@gmail.com";
$lms_partner_id = '1020';

$loginUrl = 'https://partner.isdcpro.com/api/rogo/v1/login?lms_user_email=' . urlencode($email) .'&lms_partner_id=' . urlencode($lms_partner_id);

$ch2 = curl_init();
curl_setopt_array($ch2, [
    CURLOPT_URL => $loginUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token
    ],
]);

$loginResponse = curl_exec($ch2);
$curlErrNo2 = curl_errno($ch2);
$curlErr2 = curl_error($ch2);
$httpStatus2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

$loginData = json_decode($loginResponse, true);

$response = [
    'success' => ($curlErrNo2 === 0 && $httpStatus2 < 400),
    'stage' => 'login_call',
    'http_status' => $httpStatus2,
    'curl_errno' => $curlErrNo2,
    'curl_error' => $curlErr2,
    'email_sent' => $email,
    'raw_response' => $loginResponse,
    'parsed_response' => $loginData,
];

// Extract possible redirect/login URL
if (is_array($loginData)) {
    if (!empty($loginData['login_url'])) {
        $response['redirect_url'] = $loginData['login_url'];
    } elseif (!empty($loginData['redirect_url'])) {
        $response['redirect_url'] = $loginData['redirect_url'];
    } elseif (!empty($loginData['data']['login_url'])) {
        $response['redirect_url'] = $loginData['data']['login_url'];
    }
}

echo json_encode($response);
exit;

