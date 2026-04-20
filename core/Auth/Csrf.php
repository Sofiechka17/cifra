<?php

/**
 * CSRF-токен, хранится в сессии, живёт всю сессию.
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf';
    private const HEADER_NAME = 'X-CSRF-Token';
    private const FORM_FIELD = '_csrf';

    /** Возвращает токен (создаёт при первом обращении). */
    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    /** HTML-инпут для форм. */
    public static function input(): string
    {
        return '<input type="hidden" name="' . self::FORM_FIELD . '" value="' . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }

    /**
     * Проверяет токен из POST или заголовка X-CSRF-Token.
     * При несовпадении — HTTP 403 и завершает выполнение.
     */
    public static function verifyOrFail(): void
    {
        $expected = self::token();
        $fromPost = $_POST[self::FORM_FIELD] ?? '';
        $fromHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $actual = $fromPost !== '' ? $fromPost : $fromHeader;

        if (!is_string($actual) || !hash_equals($expected, $actual)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Недействительный CSRF-токен.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}
