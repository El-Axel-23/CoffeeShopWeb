<?php

// Validar la sesión
require_once __DIR__ . '/../Login/sesion.php';

verificarSesion('../Login/login.php');

header('Cache-Control: no-store');

require_once __DIR__ . '/../config/conexion.php';

$idUsuario = $_SESSION['id_usuario'];

// Consultar los pedidos del usuario
$sql = "SELECT id_pedido,
               fecha,
               total,
               estado
        FROM pedidos
        WHERE id_usuario = ?
        ORDER BY fecha DESC, id_pedido DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$idUsuario]);

$pedidos = $stmt->fetchAll();

// Consultar los productos de los pedidos
$sql = "SELECT d.id_pedido,
               d.nombre_producto,
               d.cantidad
        FROM pedido_detalle d
        INNER JOIN pedidos p
        ON d.id_pedido = p.id_pedido
        WHERE p.id_usuario = ?
        ORDER BY d.id_detalle";

$stmt = $pdo->prepare($sql);
$stmt->execute([$idUsuario]);

$detalles = $stmt->fetchAll();

// Agrupar los productos por pedido
$productosPedido = [];

foreach ($detalles as $detalle) {

    $idPedido = $detalle['id_pedido'];

    $productosPedido[$idPedido][] =
        $detalle['cantidad'] . ' ' .
        $detalle['nombre_producto'];
}

// Consultar la cantidad de productos del carrito
$sql = "SELECT COALESCE(SUM(cantidad), 0)
        FROM carrito_items
        WHERE id_usuario = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$idUsuario]);

$cantidadCarrito = (int) $stmt->fetchColumn();

// Obtener las iniciales del usuario
$palabras = explode(
    ' ',
    trim($_SESSION['nombre_completo'])
);

$iniciales = mb_strtoupper(
    mb_substr($palabras[0], 0, 1)
);

if (count($palabras) > 1) {

    $iniciales .= mb_strtoupper(
        mb_substr($palabras[1], 0, 1)
    );
}

// Obtener el nombre del estado
function obtenerEstado($estado) {

    switch ($estado) {

        case 'pendiente':
            return 'Pendiente';

        case 'en_preparacion':
            return 'En preparación';

        case 'listo':
            return 'Listo';

        case 'en_camino':
            return 'En camino';

        case 'entregado':
            return 'Entregado';

        case 'cancelado':
            return 'Cancelado';

        default:
            return 'Desconocido';
    }
}

// Obtener la clase CSS del estado
function obtenerClaseEstado($estado) {

    switch ($estado) {

        case 'pendiente':
            return 'estado-pendiente';

        case 'en_preparacion':
            return 'estado-en-preparacion';

        case 'listo':
            return 'estado-listo';

        case 'en_camino':
            return 'estado-en-camino';

        case 'entregado':
            return 'estado-entregado';

        case 'cancelado':
            return 'estado-cancelado';

        default:
            return '';
    }
}

// Mostrar la lista o la pantalla vacía
$claseVacio = count($pedidos) === 0 ? '' : 'd-none';

$claseLista = count($pedidos) === 0 ? 'd-none' : '';

// Generar las tarjetas de pedidos
ob_start();

?>

<?php foreach ($pedidos as $pedido): ?>

    <?php

    $idPedido = (int) $pedido['id_pedido'];

    $fecha = date(
        'd/m/Y',
        strtotime($pedido['fecha'])
    );

    $estado = obtenerEstado($pedido['estado']);

    $claseEstado = obtenerClaseEstado(
        $pedido['estado']
    );

    $productos = $productosPedido[$idPedido] ?? [];

    $resumenProductos = implode(', ', $productos);

    if ($resumenProductos === '') {

        $resumenProductos = 'Sin detalle de productos disponible.';
    }

    ?>

    <div class="col-12 col-md-6">

        <a href="detalle_pedido.php?id_pedido=<?= $idPedido ?>"
           class="tarjeta-pedido"
           aria-label="Ver detalle del pedido <?= $idPedido ?>">

            <!-- Número y fecha -->
            <p class="encabezado-pedido">

                Pedido #<?= $idPedido ?>

                · <?= htmlspecialchars($fecha) ?>

            </p>

            <!-- Productos comprados -->
            <p class="productos-pedido">

                <?= htmlspecialchars($resumenProductos) ?>

            </p>

            <!-- Total de la compra -->
            <p class="total-pedido">

                Total: $<?= number_format($pedido['total'], 2) ?>

            </p>

            <!-- Estado del pedido -->
            <span class="estado-pedido <?= $claseEstado ?>">

                <?= htmlspecialchars($estado) ?>

            </span>

        </a>

    </div>

<?php endforeach; ?>

<?php

$listaPedidos = ob_get_clean();

// Cargar la vista HTML
$html = file_get_contents(
    __DIR__ . '/mis_pedidos.html'
);

if ($html === false || trim($html) === '') {

    http_response_code(500);

    exit('No fue posible cargar la vista de pedidos.');
}

// Colocar los datos en la vista
$html = strtr($html, [

    '{{iniciales}}' => htmlspecialchars($iniciales),

    '{{cantidad_carrito}}' => (string) $cantidadCarrito,

    '{{clase_vacio}}' => $claseVacio,

    '{{clase_lista}}' => $claseLista,

    '{{lista_pedidos}}' => $listaPedidos

]);

// Mostrar la vista
echo $html;