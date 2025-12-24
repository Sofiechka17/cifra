<?php
/**
 * Вспомогательные функции для аутентификации и авторизации
 */

/**
 * Гарантирует, что сессия запущена
 * @return void
 */
function ensure_session_started(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Проверяет, что пользователь авторизован
 * При отсутствии авторизации выводит сообщение и завершает выполнение (exit).
 * @return void
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
 * При отсутствии прав завершает выполнение (die) с HTTP 403.
 * @return void
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
 * @return int|null ID пользователя или null, если не авторизован.
 */
function current_user_id(): ?int
{
    ensure_session_started();
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/**
 * Возвращает имя пользователя (ФИО) или null
 * @return string|null ФИО или null, если не задано.
 */
function current_user_name(): ?string
{
    ensure_session_started();
    return $_SESSION['user_full_name'] ?? null;
}

/**
 * Возвращает название муниципального образования из сессии или null
 * @return string|null Название МО или null, если не задано.
 */
function current_municipality_name(): ?string
{
    ensure_session_started();
    return $_SESSION['municipality_name'] ?? null;
}

/**
 * Проверяет, является ли пользователь администратором.
 * @return bool true если пользователь администратор.
 */
function is_admin(): bool
{
    ensure_session_started();
    return !empty($_SESSION['is_admin']);
}
