<?php
require_once __DIR__ . '/includes/helpers.php';

start_session();
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $parameters = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $parameters['path'],
        'domain' => $parameters['domain'],
        'secure' => $parameters['secure'],
        'httponly' => $parameters['httponly'],
        'samesite' => 'Lax',
    ]);
}
setcookie('no_track', '', [
    'expires' => time() - 42000,
    'path' => '/',
    'secure' => request_is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_destroy();
redirect('index.php');
