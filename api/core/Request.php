<?php
/**
 * HTTP Request Handler
 * Encapsulates method, parameters, JSON payload, and headers.
 */
class Request {
    private string $method;
    private array $query;
    private array $post;
    private array $json;
    private array $headers;

    public function __construct() {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->query = $_GET;
        $this->post = $_POST;
        $this->headers = $this->extractHeaders();

        $rawBody = file_get_contents('php://input');
        $decoded = json_decode($rawBody, true);
        $this->json = is_array($decoded) ? $decoded : [];
    }

    public function getMethod(): string {
        return $this->method;
    }

    public function getAction(): string {
        return trim((string)($this->query['action'] ?? $this->input('action', '')));
    }

    public function get(string $key, $default = null) {
        return $this->query[$key] ?? $default;
    }

    public function post(string $key, $default = null) {
        return $this->post[$key] ?? $default;
    }

    public function input(string $key, $default = null) {
        return $this->json[$key] ?? $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function all(): array {
        return array_merge($this->query, $this->post, $this->json);
    }

    public function json(): array {
        return $this->json;
    }

    public function header(string $key, $default = null): ?string {
        $normalized = strtolower(str_replace('_', '-', $key));
        return $this->headers[$normalized] ?? $default;
    }

    public function getBearerToken(): ?string {
        $authHeader = $this->header('authorization');
        if ($authHeader && preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function extractHeaders(): array {
        $headers = [];
        if (function_exists('getallheaders')) {
            $all = getallheaders();
            if (is_array($all)) {
                foreach ($all as $k => $v) {
                    $headers[strtolower(str_replace('_', '-', $k))] = (string)$v;
                }
            }
        }
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($k, 5)));
                $headers[$name] = (string)$v;
            }
        }
        return $headers;
    }
}
