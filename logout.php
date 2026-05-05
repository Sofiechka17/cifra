<?php

/**
 * Контроллер выхода. Принимает только POST с CSRF-токеном.
 */
require_once __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Метод не поддерживается.');
}

Csrf::verifyOrFail();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}
session_destroy();

header('Location: index.php');
exit;
