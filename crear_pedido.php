<?php
require __DIR__ . '/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['ok' => false, 'error' => 'Usa el método POST'], 405);
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) {
    responder(['ok' => false, 'error' => 'JSON inválido'], 400);
}

$idUsuario   = (int) ($in['id_usuario'] ?? 0);
$tipo        = $in['tipo_entrega'] ?? '';
$direccion   = trim((string) ($in['direccion'] ?? ''));
$referencias = trim((string) ($in['referencias'] ?? ''));

if ($idUsuario <= 0) {
    responder(['ok' => false, 'error' => 'id_usuario es requerido'], 400);
}
if (!in_array($tipo, ['en_cafeteria', 'domicilio'], true)) {
    responder(['ok' => false, 'error' => 'Elige cómo quieres pagar'], 400);
}
if ($tipo === 'domicilio' && $direccion === '') {
    responder(['ok' => false, 'error' => 'Escribe la dirección de entrega'], 400);
}
if (mb_strlen($direccion) > 200 || mb_strlen($referencias) > 200) {
    responder(['ok' => false, 'error' => 'La dirección o las referencias son demasiado largas (máx. 200)'], 400);
}

$metodoPago = $tipo === 'domicilio' ? 'efectivo_domicilio' : 'efectivo_cafeteria';

try {
    $pdo->beginTransaction();

    $carrito = obtenerCarrito($pdo, $idUsuario);
    if (!$carrito['items']) {
        $pdo->rollBack();
        responder(['ok' => false, 'error' => 'Tu carrito está vacío'], 400);
    }

    // Dirección (solo a domicilio)
    $idDireccion = null;
    if ($tipo === 'domicilio') {
        $st = $pdo->prepare(
            "INSERT INTO direcciones (id_usuario, alias, direccion, referencias, es_principal)
             VALUES (?, 'Casa', ?, ?, 0)"
        );
        $st->execute([$idUsuario, $direccion, $referencias !== '' ? $referencias : null]);
        $idDireccion = (int) $pdo->lastInsertId();
    }

    // Pedido
    $st = $pdo->prepare(
        'INSERT INTO pedidos (id_usuario, tipo_entrega, metodo_pago, id_direccion, subtotal, impuestos, total)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $st->execute([
        $idUsuario, $tipo, $metodoPago, $idDireccion,
        $carrito['subtotal'], $carrito['impuestos'], $carrito['total'],
    ]);
    $idPedido = (int) $pdo->lastInsertId();

    // Detalle y opciones (se guarda nombre y precio del momento)
    $stDet = $pdo->prepare(
        'INSERT INTO pedido_detalle (id_pedido, id_producto, nombre_producto, precio_unitario, cantidad, subtotal)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stOpc = $pdo->prepare(
        'INSERT INTO pedido_detalle_opciones (id_detalle, id_opcion, precio_extra) VALUES (?, ?, ?)'
    );

    $resumen = [];
    foreach ($carrito['items'] as $it) {
        $stDet->execute([
            $idPedido, $it['id_producto'], $it['nombre'],
            $it['precio_unitario'], $it['cantidad'], $it['subtotal'],
        ]);
        $idDetalle = (int) $pdo->lastInsertId();

        foreach ($it['opciones'] as $o) {
            $stOpc->execute([$idDetalle, $o['id_opcion'], $o['precio_extra']]);
        }
        $resumen[] = ['cantidad' => $it['cantidad'], 'nombre' => $it['nombre']];
    }

    // Notificación para la campanita
    $st = $pdo->prepare('INSERT INTO notificaciones (id_usuario, id_pedido, titulo) VALUES (?, ?, ?)');
    $st->execute([$idUsuario, $idPedido, "Recibimos tu pedido #$idPedido"]);

    // Vaciar carrito (carrito_item_opciones se borra en cascada)
    $st = $pdo->prepare('DELETE FROM carrito_items WHERE id_usuario = ?');
    $st->execute([$idUsuario]);

    $pdo->commit();

    responder([
        'ok'           => true,
        'id_pedido'    => $idPedido,
        'tipo_entrega' => $tipo,
        'subtotal'     => $carrito['subtotal'],
        'impuestos'    => $carrito['impuestos'],
        'total'        => $carrito['total'],
        'items'        => $resumen,
    ], 201);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    responder(['ok' => false, 'error' => 'No se pudo crear el pedido'], 500);
}