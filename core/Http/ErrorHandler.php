<?php

/**
 * Глобальный обработчик Throwable.
 * Пишет полный текст в error_log, клиенту отдаёт generic сообщение.
 * В dev-режиме (ENV APP_DEBUG=1) отдаёт детали.
 */
final class ErrorHandler
{
    public static function register(): void
    {
        set_exception_handler([self::class, 'handleException']);
        set_error_handler([self::class, 'handleError']);
    }

    public static function handleException(Throwable $e): void
    {
        error_log(sprintf(
            "[%s] %s in %s:%d\n%s",
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));

        $isDebug = getenv('APP_DEBUG') === '1';
        $message = $isDebug
            ? sprintf('%s: %s', get_class($e), $e->getMessage())
            : 'Внутренняя ошибка сервера';

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    }

    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
}
