<?php
/**
 * HTTP Response Handler
 * Sends JSON responses with appropriate headers and status codes.
 */
class Response {
    public static function setupCors(): void {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Content-Type: application/json; charset=utf-8');

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    public static function json(array $data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function ok(array $data = [], int $status = 200): void {
        self::json(array_merge(['ok' => true], $data), $status);
    }

    public static function fail(string $message, int $status = 400): void {
        self::json(['ok' => false, 'message' => $message], $status);
    }
}
