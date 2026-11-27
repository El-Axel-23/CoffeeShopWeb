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

    // Comprobar que el producto exista y esté disponible
    $sql = "SELECT id_producto
            FROM productos
            WHERE id_producto = ?
            AND disponible = 1";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {

        throw new RuntimeException(mysqli_error($conexion));
    }

    mysqli_stmt_bind_param($stmt, "i", $idProducto);

    if (!mysqli_stmt_execute($stmt) ||
        !mysqli_stmt_store_result($stmt)) {

        $error = mysqli_stmt_error($stmt);

        mysqli_stmt_close($stmt);

        throw new RuntimeException($error);
    }

    $existeProducto = mysqli_stmt_num_rows($stmt) > 0;

    mysqli_stmt_close($stmt);

    if (!$existeProducto) {

        $_SESSION['estado_favoritos'] = 'error';

    } else {

        // Comprobar si ya está en favoritos
        $sql = "SELECT id_producto
                FROM favoritos
                WHERE id_usuario = ?
                AND id_producto = ?";

        $stmt = mysqli_prepare($conexion, $sql);

        if (!$stmt) {

            throw new RuntimeException(mysqli_error($conexion));
        }

        mysqli_stmt_bind_param(
            $stmt,
            "ii",
            $idUsuario,
            $idProducto
        );

        if (!mysqli_stmt_execute($stmt) ||
            !mysqli_stmt_store_result($stmt)) {

            $error = mysqli_stmt_error($stmt);

            mysqli_stmt_close($stmt);

            throw new RuntimeException($error);
        }

        $existeFavorito = mysqli_stmt_num_rows($stmt) > 0;

        mysqli_stmt_close($stmt);

        if (!$existeFavorito) {

            // Agregar producto a favoritos
            $sql = "INSERT INTO favoritos (
                        id_usuario,
                        id_producto
                    ) VALUES (?, ?)";

            $stmt = mysqli_prepare($conexion, $sql);

            if (!$stmt) {

                throw new RuntimeException(mysqli_error($conexion));
            }

            mysqli_stmt_bind_param(
                $stmt,
                "ii",
                $idUsuario,
                $idProducto
            );

            if (!mysqli_stmt_execute($stmt)) {

                $codigoError = mysqli_stmt_errno($stmt);
                $error = mysqli_stmt_error($stmt);

                mysqli_stmt_close($stmt);

                // Evitar errores por favoritos duplicados
                if ($codigoError != 1062) {

                    throw new RuntimeException($error);
                }

            } else {

                mysqli_stmt_close($stmt);
            }
        }

        $_SESSION['estado_favoritos'] = 'agregado';
    }

} catch (RuntimeException $e) {

    error_log(
        'Error al agregar favorito: ' . $e->getMessage()
    );

    $_SESSION['estado_favoritos'] = 'error';
}

// Regresar al catálogo
header('Location: ../index.php#menu', true, 303);

exit;