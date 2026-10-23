<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Superset Token Proxy - Uses API Gateway to get guest token
 *
 * @package    block_supersetembed
 * @copyright  2025 JO LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_login();

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Get configuration
$apiurl = get_config('block_supersetembed', 'apiurl');
$dashboardid = get_config('block_supersetembed', 'dashboardid');

if (empty($apiurl)) {
    $apiurl = 'https://qa.ams.dashboard.thekaios.com/api/v1/superset/guest-token';
}
if (empty($dashboardid)) {
    $dashboardid = '4';  // Numeric dashboard ID
}

error_log('Superset Token Proxy: Using API Gateway at ' . $apiurl);
error_log('Superset Token Proxy: Dashboard ID: ' . $dashboardid);
error_log('Superset Token Proxy: Dashboard ID type: ' . gettype($dashboardid));

// Prepare the request data with user info and dashboard ID
$requestData = [
    'user' => [
        'username' => 'public',
        'first_name' => 'public',
        'last_name' => 'user'
    ],
    'resources' => [
        [
            'type' => 'dashboard',
            'id' => $dashboardid
        ]
    ],
    'rls' => []
];

error_log('Superset Token Proxy: Sending request to API Gateway');
error_log('Superset Token Proxy: Request body: ' . json_encode($requestData));

// Make the API call to Ganesh's API Gateway
$ch = curl_init($apiurl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Log the response
error_log('Superset Token Proxy: API Gateway response code: ' . $httpCode);

if ($curlError) {
    error_log('Superset Token Proxy: cURL Error: ' . $curlError);
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to connect to API Gateway',
        'details' => $curlError
    ]);
    exit;
}

if ($httpCode !== 200) {
    error_log('Superset Token Proxy: API Gateway error (Status ' . $httpCode . '): ' . $response);
    http_response_code($httpCode);
    
    // Try to parse the response as JSON for better error messages
    $responseData = json_decode($response, true);
    if ($responseData) {
        echo json_encode([
            'error' => 'API Gateway returned error',
            'status' => $httpCode,
            'response' => $responseData
        ]);
    } else {
        echo json_encode([
            'error' => 'API Gateway returned error',
            'status' => $httpCode,
            'response' => $response
        ]);
    }
    exit;
}

// Success - return the token response from API Gateway
error_log('Superset Token Proxy: Successfully got token from API Gateway');
echo $response;
