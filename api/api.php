<?php
/**
 * Smart Campus Lost & Found API - MVC Front Controller
 * Bootstraps autoloader, initializes database, registers routes, and dispatches HTTP requests.
 */

// 1. Register Autoloader
require_once __DIR__ . '/core/Autoloader.php';
Autoloader::register();

// 2. Setup Security headers and CORS
Response::setupCors();

// 3. Connect to Database & verify schema
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    if (!Schema::isInitialized($pdo)) {
        Schema::initialize($pdo);
    } else {
        Schema::ensureMigrations($pdo);
    }
} catch (Throwable $e) {
    Response::fail('Database connection failed: ' . $e->getMessage(), 500);
}

// 4. Instantiate Request and Router
$request = new Request();
$router = new Router($request, $pdo);

// 5. Register Authentication routes
$router->register('session', AuthController::class, 'session');
$router->register('login', AuthController::class, 'login');
$router->register('register', AuthController::class, 'register');
$router->register('logout', AuthController::class, 'logout');

// 6. Register Dashboard routes
$router->register('dashboard', DashboardController::class, 'dashboard');

// 7. Register Item routes
$router->register('browse', ItemController::class, 'browse');
$router->register('submit_lost', ItemController::class, 'submitLost');
$router->register('submit_found', ItemController::class, 'submitFound');
$router->register('my_reports', ItemController::class, 'myReports');
$router->register('delete_lost', ItemController::class, 'deleteLost');

// 8. Register Claim routes
$router->register('claim_info', ClaimController::class, 'claimInfo');
$router->register('submit_claim', ClaimController::class, 'submitClaim');

// 9. Register Admin routes
$router->register('admin_dashboard', AdminController::class, 'dashboard');
$router->register('admin_delete_item', AdminController::class, 'deleteItem');
$router->register('admin_claims', AdminController::class, 'claims');
$router->register('approve_claim', AdminController::class, 'approveClaim');
$router->register('reject_claim', AdminController::class, 'rejectClaim');
$router->register('admin_users', AdminController::class, 'users');
$router->register('toggle_user_status', AdminController::class, 'toggleUserStatus');
$router->register('admin_logs', AdminController::class, 'logs');

// 10. Dispatch request
$router->dispatch();
