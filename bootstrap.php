<?php

/**
 * Единая точка инициализации для всех entry-points.
 * Подключает autoload, настраивает cookie params, стартует сессию,
 * регистрирует обработчик ошибок, устанавливает $conn.
 */

require __DIR__ . '/vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => false, // на проде: true (HTTPS only)
    ]);
}

require __DIR__ . '/auth.php';
ensure_session_started();

require __DIR__ . '/db.php';

