<?php
/**
 * Application Router
 * Maps request action parameters to corresponding MVC Controller actions.
 */
class Router {
    private array $routes = [];
    private Request $request;
    private PDO $pdo;

    public function __construct(Request $request, PDO $pdo) {
        $this->request = $request;
        $this->pdo = $pdo;
    }

    public function register(string $action, string $controllerClass, string $method): void {
        $this->routes[$action] = [$controllerClass, $method];
    }

    public function dispatch(): void {
        $action = $this->request->getAction();
        if (empty($action) || !isset($this->routes[$action])) {
            Response::fail('Unknown action', 404);
        }

        [$controllerClass, $method] = $this->routes[$action];

        if (!class_exists($controllerClass)) {
            Response::fail("Controller '{$controllerClass}' not found", 500);
        }

        $controller = new $controllerClass($this->request, $this->pdo);

        if (!method_exists($controller, $method)) {
            Response::fail("Action '{$method}' not found in '{$controllerClass}'", 500);
        }

        try {
            $controller->$method();
        } catch (PDOException $e) {
            if ($e->getCode() === '23000' || $e->getCode() === '23505') {
                Response::fail('Duplicate entry. This email or ID may already be registered.', 409);
            }
            Response::fail('Database error: ' . $e->getMessage(), 500);
        } catch (Throwable $e) {
            Response::fail($e->getMessage(), 500);
        }
    }
}
