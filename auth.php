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
 * При отсутствии авторизации перенаправляет на login.php
 */
function require_auth(): void
{
    ensure_session_started();
    if (empty($_SESSION['user_id'])) {
        header("Location: login.php");
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
