<?php
$host = 'tokaido.proxy.rlwy.net';
$port = 48444;
$dbname = 'railway';
$username = 'root';
$password = 'ZMXEmORaWFLpPHSSmjMNKYEIMLHqwvnH';

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die("Database connection failed: " . $e->getMessage());
}
?>