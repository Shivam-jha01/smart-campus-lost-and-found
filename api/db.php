<?php
require_once __DIR__ . '/core/Database.php';

try {
    $dbInstance = Database::getInstance();
    $pdo = $dbInstance->getConnection();
    $dbDriver = $dbInstance->getDriver();
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => false,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}