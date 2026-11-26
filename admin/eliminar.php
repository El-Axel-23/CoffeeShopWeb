<?php

// Importar el control de sesiones
require_once __DIR__ . '/../Login/sesion.php';

// Candado de seguridad
verificarSesion('../Login/login.php', 'admin');

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

// Llega por POST desde el botón Eliminar

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if ($id && $id > 0) {

    try {

        $pdo->beginTransaction();

        // 1. Buscar el nombre de la imagen antes de borrar el registro

        $sqlBusq = "SELECT imagen_url
                    FROM productos
                    WHERE id_producto = ?
                    FOR UPDATE";

        $stmt = $pdo->prepare($sqlBusq);

        $stmt->execute([$id]);

        $dato = $stmt->fetch();

        $stmt->closeCursor();

        if ($dato) {

            // Comprobar si el producto está en algún pedido

            $sqlPedidos = "SELECT id_detalle
                           FROM pedido_detalle
                           WHERE id_producto = ?
                           LIMIT 1";

            $stmtPedidos = $pdo->prepare($sqlPedidos);

            $stmtPedidos->execute([$id]);

            $enPedidos = $stmtPedidos->fetch();

            $stmtPedidos->closeCursor();

            // Comprobar si el producto está en algún carrito

            $sqlCarrito = "SELECT id_item
                           FROM carrito_items
                           WHERE id_producto = ?
                           LIMIT 1";

            $stmtCarrito = $pdo->prepare($sqlCarrito);

            $stmtCarrito->execute([$id]);

            $enCarrito = $stmtCarrito->fetch();

            $stmtCarrito->closeCursor();

            if ($enPedidos || $enCarrito) {

                $pdo->rollBack();

                $resultado = 'en_uso';

            } else {

                // 2. Borrar el registro

                $sqlDel = "DELETE FROM productos
                           WHERE id_producto = ?";

                $stmtDel = $pdo->prepare($sqlDel);

                $stmtDel->execute([$id]);

                $pdo->commit();

                $resultado = 'eliminado';

                // 3. Ya se borró: ahora sí quitamos el archivo físico
                // Solo si lo subió el panel, así no borramos tus imágenes originales

                $rutaArchivo = __DIR__ . '/../' . $dato['imagen_url'];

                if (strpos($dato['imagen_url'], 'img/productos/prod_') === 0 &&
                    basename($dato['imagen_url']) === substr($dato['imagen_url'], strlen('img/productos/')) &&
                    is_file($rutaArchivo)) {

                    unlink($rutaArchivo);
                }
            }

        } else {

            $pdo->rollBack();
        }

    } catch (PDOException $e) {

        if ($pdo->inTransaction()) {

            $pdo->rollBack();
        }

        error_log('Error al eliminar producto: ' . $e->getMessage());

        $resultado = 'error';
    }
}

header('Location: index.php?msg=' . $resultado, true, 303);

exit;