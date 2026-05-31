<?php
header('Access-Control-Allow-Origin: https://app.amicone.com.ar');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

// Cookie de sesión cross-origin: debe enviarse con SameSite=None y Secure
ini_set('session.cookie_samesite', 'None');
ini_set('session.cookie_secure',   '1');
ini_set('session.cookie_httponly', '1');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
