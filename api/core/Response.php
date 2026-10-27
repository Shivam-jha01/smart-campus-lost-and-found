<?php
/**
 * HTTP Response Handler
 * Sends standardized JSON responses with security headers and CORS protection.
 */
class Response {
    public static function setupCors(): void {
        // Enforce essential security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), camera=(), microphone=()');

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';

        if (!empty($origin)) {
            $parsed = parse_url($origin);
            $originHost = $parsed['host'] ?? '';
            // Allow same host, Vercel deployments, or local development
            if ($originHost === $host || str_ends_with($originHost, '.vercel.app') || in_array($originHost, ['localhost', '127.0.0.1'], true)) {
                header("Access-Control-Allow-Origin: {$origin}");
                header('Access-Control-Allow-Credentials: true');
            } else {
                header('Access-Control-Allow-Origin: *');
            }
        } else {
            header('Access-Control-Allow-Origin: *');
        }

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
