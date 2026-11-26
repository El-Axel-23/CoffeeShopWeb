<?php
// GET api/pedidos.php?id_usuario=1  →  historial de pedidos
require __DIR__ . '/conexion.php';

$idUsuario = (int) ($_GET['id_usuario'] ?? 0);
if ($idUsuario <= 0) {
    responder(['ok' => false, 'error' => 'id_usuario es requerido'], 400);
}

$estados = [
    'pendiente'      => 'Pendiente',
    'en_preparacion' => 'En preparación',
    'listo'          => 'Listo para recoger',
    'en_camino'      => 'En camino',
    'entregado'      => 'Entregado',
    'cancelado'      => 'Cancelado',
];

try {
    $st = $pdo->prepare(
        "SELECT p.id_pedido, p.tipo_entrega, p.total, p.estado,
                DATE_FORMAT(p.fecha, '%d/%m/%Y') AS fecha
         FROM pedidos p
         WHERE p.id_usuario = ?
         ORDER BY p.fecha DESC, p.id_pedido DESC"
    );
    $st->execute([$idUsuario]);
    $pedidos = $st->fetchAll();

    $stDet = $pdo->prepare(
        'SELECT cantidad, nombre_producto
         FROM pedido_detalle
         WHERE id_pedido = ?
         ORDER BY id_detalle'
    );

    $lista = [];
    foreach ($pedidos as $p) {
        $stDet->execute([$p['id_pedido']]);
        $partes = [];
        foreach ($stDet->fetchAll() as $d) {
            $partes[] = $d['cantidad'] . ' ' . $d['nombre_producto'];
        }

        $lista[] = [
            'id_pedido'    => (int) $p['id_pedido'],
            'fecha'        => $p['fecha'],
            'productos'    => implode(', ', $partes),
            'total'        => (float) $p['total'],
            'tipo_entrega' => $p['tipo_entrega'],
            'estado'       => $p['estado'],
            'estado_texto' => $estados[$p['estado']] ?? $p['estado'],
        ];
    }

    responder(['ok' => true, 'pedidos' => $lista]);
} catch (PDOException $e) {
    responder(['ok' => false, 'error' => 'No se pudieron leer los pedidos'], 500);
}