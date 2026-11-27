<?php

// Validar la sesión
require_once __DIR__ . '/../Login/sesion.php';

verificarSesion('../Login/login.php');

// Importar la conexión
require_once __DIR__ . '/../config/conexion.php';

// Comprobar el método de envío
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);
    exit('Solo se permite POST.');
}

// Validar token de seguridad
$token = $_POST['csrf_token'] ?? '';

if (!isset($_SESSION['csrf_favoritos']) ||
    !is_string($token) ||
    !hash_equals($_SESSION['csrf_favoritos'], $token)) {

    http_response_code(403);
    exit('Solicitud no válida.');
}

// Obtener los datos
$idUsuario = $_SESSION['id_usuario'];

$idProducto = filter_input(
    INPUT_POST,
    'id_producto',
    FILTER_VALIDATE_INT
);

// Validar el producto
if (!$idProducto || $idProducto <= 0) {

    http_response_code(400);
    exit('Producto no válido.');
}

try {

    // Eliminar el producto de favoritos
    $sql = "DELETE FROM favoritos
            WHERE id_usuario = ?
            AND id_producto = ?";

    // Preparar la consulta
    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {

        throw new RuntimeException(mysqli_error($conexion));
    }

    // Asignar los parámetros
    mysqli_stmt_bind_param(
        $stmt,
        "ii",
        $idUsuario,
        $idProducto
    );

    // Ejecutar la consulta
    if (!mysqli_stmt_execute($stmt)) {

        $error = mysqli_stmt_error($stmt);

        mysqli_stmt_close($stmt);

        throw new RuntimeException($error);
    }

    // Cerrar la consulta
    mysqli_stmt_close($stmt);

    $_SESSION['estado_favoritos'] = 'eliminado';

} catch (RuntimeException $e) {

    error_log(
        'Error al eliminar favorito: ' . $e->getMessage()
    );

    $_SESSION['estado_favoritos'] = 'error';
}

// Regresar a favoritos
header('Location: favoritos.php', true, 303);

exit;