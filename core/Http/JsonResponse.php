<?php

/**
 * Единый формат JSON-ответа для API-эндпоинтов.
 */
final class JsonResponse
{
    /** @param array<string, mixed> $data */
    public static function success(string $message = '', array $data = []): void
    {
        self::send(200, ['success' => true, 'message' => $message] + ($data ? ['data' => $data] : []));
    }

    public static function error(int $statusCode, string $message): void
    {
        self::send($statusCode, ['success' => false, 'message' => $message]);
    }

    /** @param array<string, mixed> $payload */
    private static function send(int $statusCode, array $payload): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}
