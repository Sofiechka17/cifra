<?php
/**
 * Вспомогательные функции для аутентификации и авторизации
 */

/**
 * Гарантирует, что сессия запущена
 */
function ensure_session_started(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Проверяет, что пользователь авторизован
 */
function require_auth(): void
{
    ensure_session_started();
    if (empty($_SESSION['user_id'])) {
        ?>
        <!DOCTYPE html>
        <html lang="ru">
        <head>
            <meta charset="UTF-8">
            <title>Требуется авторизация</title>
        </head>
        <body style="background:#201D1D; color:#fff; font-family:Helvetica, sans-serif;">
        <script>
            alert("Чтобы заполнить форму, необходимо авторизоваться.");
            window.location.href = "index.php";
        </script>
        <noscript>
            Чтобы заполнить форму, необходимо авторизоваться.
            <br><a href="index.php">Перейти на главную</a>
        </noscript>
        </body>
        </html>
        <?php
        exit;
    }
}

/**
 * Проверяет, что текущий пользователь — администратор
 * При отсутствии прав завершает выполнение
 */
function require_admin(): void
{
    ensure_session_started();
    if (empty($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
        http_response_code(403);
        die("Доступ запрещён");
    }
}

/**
 * Возвращает ID текущего пользователя или null
 */
function current_user_id(): ?int
{
    ensure_session_started();
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/**
 * Возвращает имя пользователя (ФИО) или null
 */
function current_user_name(): ?string
{
    ensure_session_started();
    return $_SESSION['user_full_name'] ?? null;
}

/**
 * Возвращает название муниципального образования из сессии или null
 */
function current_municipality_name(): ?string
{
    ensure_session_started();
    return $_SESSION['municipality_name'] ?? null;
}

/**
 * Возвращает флаг, является ли пользователь администратором
 */
function is_admin(): bool
{
    ensure_session_started();
    return !empty($_SESSION['is_admin']);
}
