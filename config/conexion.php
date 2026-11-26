<?php
$host = 'localhost';
$db = 'u906011863_madison';
$user = 'u906011863_madison';
$pass = 't>hv@Sm6';

try {
    // Conexión a la base de datos
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log('Error de conexión: ' . $e->getMessage());
    http_response_code(500);
    exit('No fue posible conectar con la base de datos.');
}