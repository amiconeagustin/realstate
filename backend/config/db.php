<?php
$host     = $_ENV['DB_HOST']     ?? getenv('DB_HOST')     ?? 'localhost';
$dbname   = $_ENV['DB_NAME']     ?? getenv('DB_NAME')     ?? 'realstate';
$username = $_ENV['DB_USER']     ?? getenv('DB_USER')     ?? 'root';
$password = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?? '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de conexión a la base de datos']);
    exit;
}
