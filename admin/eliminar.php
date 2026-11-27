<?php

// Importar el control de sesiones
require_once __DIR__ . '/../Login/sesion.php';

// Candado de seguridad
verificarSesion('../Login/login.php', 'admin');

// Importar la conexión
require_once __DIR__ . '/../config/conexion.php';

// Validar método de envío
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    exit('Método no permitido.');
}

// Validar token de seguridad
$token = $_POST['csrf_token'] ?? '';

if (!isset($_SESSION['csrf_admin']) ||
    !is_string($token) ||
    !hash_equals($_SESSION['csrf_admin'], $token)) {

    http_response_code(403);

    exit('Solicitud no válida.');
}

$resultado = 'no_encontrado';

$transaccionActiva = false;

// Recibir el ID del producto por POST
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if ($id && $id > 0) {

    try {

        // Iniciar transacción
        if (!mysqli_begin_transaction($conexion)) {

            throw new RuntimeException(mysqli_error($conexion));
        }

        $transaccionActiva = true;

        // 1. Buscar la imagen del producto
        $sqlBusq = "SELECT imagen_url
                    FROM productos
                    WHERE id_producto = ?
                    FOR UPDATE";

        $stmt = mysqli_prepare($conexion, $sqlBusq);

        if (!$stmt) {

            throw new RuntimeException(mysqli_error($conexion));
        }

        mysqli_stmt_bind_param($stmt, "i", $id);

        if (!mysqli_stmt_execute($stmt)) {

            $error = mysqli_stmt_error($stmt);

            mysqli_stmt_close($stmt);

            throw new RuntimeException($error);
        }

        $resBusq = mysqli_stmt_get_result($stmt);

        if (!$resBusq) {

            $error = mysqli_stmt_error($stmt);

            mysqli_stmt_close($stmt);

            throw new RuntimeException($error);
        }

        $dato = mysqli_fetch_assoc($resBusq);

        mysqli_free_result($resBusq);

        mysqli_stmt_close($stmt);

        if ($dato) {

            // 2. Comprobar si el producto está en algún pedido
            $sqlPedidos = "SELECT id_detalle
                           FROM pedido_detalle
                           WHERE id_producto = ?
                           LIMIT 1";

            $stmtPedidos = mysqli_prepare(
                $conexion,
                $sqlPedidos
            );

            if (!$stmtPedidos) {

                throw new RuntimeException(mysqli_error($conexion));
            }

            mysqli_stmt_bind_param($stmtPedidos, "i", $id);

            if (!mysqli_stmt_execute($stmtPedidos)) {

                $error = mysqli_stmt_error($stmtPedidos);

                mysqli_stmt_close($stmtPedidos);

                throw new RuntimeException($error);
            }

            $resPedidos = mysqli_stmt_get_result($stmtPedidos);

            if (!$resPedidos) {

                $error = mysqli_stmt_error($stmtPedidos);

                mysqli_stmt_close($stmtPedidos);

                throw new RuntimeException($error);
            }

            $enPedidos = mysqli_fetch_assoc($resPedidos);

            mysqli_free_result($resPedidos);

            mysqli_stmt_close($stmtPedidos);

            // 3. Comprobar si el producto está en algún carrito
            $sqlCarrito = "SELECT id_item
                           FROM carrito_items
                           WHERE id_producto = ?
                           LIMIT 1";

            $stmtCarrito = mysqli_prepare(
                $conexion,
                $sqlCarrito
            );

            if (!$stmtCarrito) {

                throw new RuntimeException(mysqli_error($conexion));
            }

            mysqli_stmt_bind_param($stmtCarrito, "i", $id);

            if (!mysqli_stmt_execute($stmtCarrito)) {

                $error = mysqli_stmt_error($stmtCarrito);

                mysqli_stmt_close($stmtCarrito);

                throw new RuntimeException($error);
            }

            $resCarrito = mysqli_stmt_get_result($stmtCarrito);

            if (!$resCarrito) {

                $error = mysqli_stmt_error($stmtCarrito);

                mysqli_stmt_close($stmtCarrito);

                throw new RuntimeException($error);
            }

            $enCarrito = mysqli_fetch_assoc($resCarrito);

            mysqli_free_result($resCarrito);

            mysqli_stmt_close($stmtCarrito);

            // No eliminar si el producto está en uso
            if ($enPedidos || $enCarrito) {

                if (!mysqli_rollback($conexion)) {

                    throw new RuntimeException(mysqli_error($conexion));
                }

                $transaccionActiva = false;

                $resultado = 'en_uso';

            } else {

                // 4. Eliminar el producto
                $sqlDel = "DELETE FROM productos
                           WHERE id_producto = ?";

                $stmtDel = mysqli_prepare($conexion, $sqlDel);

                if (!$stmtDel) {

                    throw new RuntimeException(mysqli_error($conexion));
                }

                mysqli_stmt_bind_param($stmtDel, "i", $id);

                if (!mysqli_stmt_execute($stmtDel)) {

                    $error = mysqli_stmt_error($stmtDel);

                    mysqli_stmt_close($stmtDel);

                    throw new RuntimeException($error);
                }

                mysqli_stmt_close($stmtDel);

                // Confirmar la eliminación
                if (!mysqli_commit($conexion)) {

                    throw new RuntimeException(mysqli_error($conexion));
                }

                $transaccionActiva = false;

                $resultado = 'eliminado';

                // 5. Eliminar la imagen del servidor
                $imagen = $dato['imagen_url'] ?? '';

                $rutaArchivo = __DIR__ . '/../' . $imagen;

                // Solo eliminar imágenes subidas desde el panel
                if (
                    strpos($imagen, 'img/productos/prod_') === 0 &&
                    basename($imagen) === substr(
                        $imagen,
                        strlen('img/productos/')
                    ) &&
                    is_file($rutaArchivo)
                ) {

                    if (!unlink($rutaArchivo)) {

                        error_log(
                            'No se pudo eliminar la imagen: ' . $rutaArchivo
                        );
                    }
                }
            }

        } else {

            // El producto no existe
            if (!mysqli_rollback($conexion)) {

                throw new RuntimeException(mysqli_error($conexion));
            }

            $transaccionActiva = false;

            $resultado = 'no_encontrado';
        }

    } catch (RuntimeException $e) {

        // Cancelar la transacción si ocurrió un error
        if ($transaccionActiva) {

            mysqli_rollback($conexion);
        }

        error_log(
            'Error al eliminar producto: ' . $e->getMessage()
        );

        $resultado = 'error';
    }
}

// Regresar al listado de productos
header('Location: index.php?msg=' . $resultado, true, 303);

exit;