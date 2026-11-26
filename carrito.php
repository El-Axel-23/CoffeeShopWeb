<?php
require __DIR__ . '/conexion.php';

$idUsuario = (int) ($_GET['id_usuario'] ?? 0);
if ($idUsuario <= 0) {
    responder(['ok' => false, 'error' => 'id_usuario es requerido'], 400);
}

try {
    responder(['ok' => true] + obtenerCarrito($pdo, $idUsuario));
} catch (PDOException $e) {
    responder(['ok' => false, 'error' => 'No se pudo leer el carrito'], 500);
}