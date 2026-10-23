<?php
require('../../config.php');

require_login();

global $USER;

$email = $USER->email;

$secretkey = 'a282a104dd76f4d25eaa0d625326cddb';

$payload = [
    'email' => $email,
    'iat' => 0,
    'exp' => 1785522600 //01-Aug-2026 00:00 IST
];

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

$header = base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
$body   = base64url_encode(json_encode($payload));

$signature = base64url_encode(hash_hmac('sha256', "$header.$body", $secretkey, true));

$jwt = "$header.$body.$signature";

$url = 'https://joerpexam.enovasolutions.com/api/auth/sso?credentials=' . $jwt;

redirect($url);
