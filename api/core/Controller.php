<?php
/**
 * Base Controller Class
 * Abstract class providing request context, response helpers, and auth guards.
 */
abstract class Controller {
    protected Request $request;
    protected PDO $pdo;

    public function __construct(Request $request, PDO $pdo) {
        $this->request = $request;
        $this->pdo = $pdo;
    }

    protected function ok(array $data = [], int $status = 200): void {
        Response::ok($data, $status);
    }

    protected function fail(string $message, int $status = 400): void {
        Response::fail($message, $status);
    }

    protected function getUser(): ?array {
        return Auth::getUser($this->request);
    }

    protected function requireUser(): array {
        return Auth::requireUser($this->request);
    }

    protected function requireAdmin(): array {
        return Auth::requireAdmin($this->request);
    }

    protected function input(string $key, $default = null) {
        $val = $this->request->input($key, $default);
        return is_string($val) ? trim($val) : $val;
    }
}
