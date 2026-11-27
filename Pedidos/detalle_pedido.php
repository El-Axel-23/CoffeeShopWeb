<?php

// Validar la sesión
require_once __DIR__ . '/../Login/sesion.php';

verificarSesion('../Login/login.php');

header('Cache-Control: no-store');

// Importar la conexión
require_once __DIR__ . '/../config/conexion.php';

$idUsuario = (int) $_SESSION['id_usuario'];

// Obtener el identificador del pedido
$idPedido = filter_input(
    INPUT_GET,
    'id_pedido',
    FILTER_VALIDATE_INT
);

// Validar el identificador
if (!$idPedido || $idPedido <= 0) {

    header('Location: mis_pedidos.php');
    exit;
}

// Consultar la información del pedido
$sql = "SELECT id_pedido,
               fecha,
               estado,
               tipo_entrega,
               metodo_pago,
               subtotal,
               impuestos,
               total
        FROM pedidos
        WHERE id_pedido = ?
        AND id_usuario = ?";

// Preparar la consulta
$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {

    error_log('Error al preparar pedido: ' . mysqli_error($conexion));

    http_response_code(500);
    exit('No fue posible consultar el pedido.');
}

// Asignar los parámetros
if (!mysqli_stmt_bind_param(
        $stmt,
        "ii",
        $idPedido,
        $idUsuario
    ) ||
    !mysqli_stmt_execute($stmt)) {

    error_log('Error al consultar pedido: ' . mysqli_stmt_error($stmt));

    mysqli_stmt_close($stmt);

    http_response_code(500);
    exit('No fue posible consultar el pedido.');
}

// Obtener el resultado
$resultado = mysqli_stmt_get_result($stmt);

if (!$resultado) {

    error_log('Error al obtener pedido: ' . mysqli_stmt_error($stmt));

    mysqli_stmt_close($stmt);

    http_response_code(500);
    exit('No fue posible obtener el pedido.');
}

$pedido = mysqli_fetch_assoc($resultado);

mysqli_free_result($resultado);

mysqli_stmt_close($stmt);

// Comprobar que el pedido pertenezca al usuario
if (!$pedido) {

    header('Location: mis_pedidos.php');
    exit;
}

// Consultar los productos comprados
$sql = "SELECT d.id_detalle,
               d.nombre_producto,
               d.precio_unitario,
               d.cantidad,
               d.subtotal,
               p.imagen_url
        FROM pedido_detalle d
        LEFT JOIN productos p
        ON d.id_producto = p.id_producto
        WHERE d.id_pedido = ?
        ORDER BY d.id_detalle";

$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {

    error_log('Error al preparar productos: ' . mysqli_error($conexion));

    http_response_code(500);
    exit('No fue posible consultar los productos.');
}

// Asignar el ID del pedido
if (!mysqli_stmt_bind_param($stmt, "i", $idPedido) ||
    !mysqli_stmt_execute($stmt)) {

    error_log('Error al consultar productos: ' . mysqli_stmt_error($stmt));

    mysqli_stmt_close($stmt);

    http_response_code(500);
    exit('No fue posible consultar los productos.');
}

$resultadoProductos = mysqli_stmt_get_result($stmt);

if (!$resultadoProductos) {

    error_log('Error al obtener productos: ' . mysqli_stmt_error($stmt));

    mysqli_stmt_close($stmt);

    http_response_code(500);
    exit('No fue posible obtener los productos.');
}

$productos = [];

// Guardar los productos comprados
while ($producto = mysqli_fetch_assoc($resultadoProductos)) {

    $productos[] = $producto;
}

mysqli_free_result($resultadoProductos);

mysqli_stmt_close($stmt);

// Consultar las personalizaciones de los productos
$sql = "SELECT d.id_detalle,
               o.nombre,
               po.precio_extra
        FROM pedido_detalle_opciones po
        INNER JOIN opciones o
        ON po.id_opcion = o.id_opcion
        INNER JOIN pedido_detalle d
        ON po.id_detalle = d.id_detalle
        WHERE d.id_pedido = ?";

$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {

    error_log('Error al preparar opciones: ' . mysqli_error($conexion));

    http_response_code(500);
    exit('No fue posible consultar las personalizaciones.');
}

// Asignar el ID del pedido
if (!mysqli_stmt_bind_param($stmt, "i", $idPedido) ||
    !mysqli_stmt_execute($stmt)) {

    error_log('Error al consultar opciones: ' . mysqli_stmt_error($stmt));

    mysqli_stmt_close($stmt);

    http_response_code(500);
    exit('No fue posible consultar las personalizaciones.');
}

$resultadoOpciones = mysqli_stmt_get_result($stmt);

if (!$resultadoOpciones) {

    error_log('Error al obtener opciones: ' . mysqli_stmt_error($stmt));

    mysqli_stmt_close($stmt);

    http_response_code(500);
    exit('No fue posible obtener las personalizaciones.');
}

$opciones = [];

// Guardar las personalizaciones
while ($opcion = mysqli_fetch_assoc($resultadoOpciones)) {

    $opciones[] = $opcion;
}

mysqli_free_result($resultadoOpciones);

mysqli_stmt_close($stmt);

// Agrupar las personalizaciones por producto
$opcionesProducto = [];

foreach ($opciones as $opcion) {

    $texto = $opcion['nombre'];

    if ($opcion['precio_extra'] > 0) {

        $texto .= ' (+$' .
            number_format($opcion['precio_extra'], 2) .
            ')';
    }

    $opcionesProducto[$opcion['id_detalle']][] = $texto;
}

// Consultar la cantidad de productos del carrito
$sql = "SELECT COALESCE(SUM(cantidad), 0)
        FROM carrito_items
        WHERE id_usuario = ?";

$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {

    error_log('Error al preparar carrito: ' . mysqli_error($conexion));

    http_response_code(500);
    exit('No fue posible consultar el carrito.');
}

// Asignar el ID del usuario
if (!mysqli_stmt_bind_param($stmt, "i", $idUsuario) ||
    !mysqli_stmt_execute($stmt)) {

    error_log('Error al consultar carrito: ' . mysqli_stmt_error($stmt));

    mysqli_stmt_close($stmt);

    http_response_code(500);
    exit('No fue posible consultar el carrito.');
}

// Obtener la cantidad del carrito
$cantidadCarrito = 0;

if (!mysqli_stmt_bind_result($stmt, $cantidadCarrito) ||
    mysqli_stmt_fetch($stmt) !== true) {

    error_log('Error al obtener la cantidad del carrito: ' .
        mysqli_stmt_error($stmt));

    mysqli_stmt_close($stmt);

    http_response_code(500);
    exit('No fue posible obtener la cantidad del carrito.');
}

$cantidadCarrito = (int) $cantidadCarrito;

mysqli_stmt_close($stmt);

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

// Obtener el estado del pedido
switch ($pedido['estado']) {

    case 'pendiente':

        $estado = 'Pendiente';
        $claseEstado = 'estado-pendiente';
        break;

    case 'en_preparacion':

        $estado = 'En preparación';
        $claseEstado = 'estado-en-preparacion';
        break;

    case 'listo':

        $estado = 'Listo';
        $claseEstado = 'estado-listo';
        break;

    case 'en_camino':

        $estado = 'En camino';
        $claseEstado = 'estado-en-camino';
        break;

    case 'entregado':

        $estado = 'Entregado';
        $claseEstado = 'estado-entregado';
        break;

    case 'cancelado':

        $estado = 'Cancelado';
        $claseEstado = 'estado-cancelado';
        break;

    default:

        $estado = 'Desconocido';
        $claseEstado = '';
        break;
}

// Obtener el tipo de entrega
switch ($pedido['tipo_entrega']) {

    case 'en_cafeteria':

        $tipoEntrega = 'En cafetería';
        break;

    case 'domicilio':

        $tipoEntrega = 'Entrega a domicilio';
        break;

    default:

        $tipoEntrega = 'No especificado';
        break;
}

// Obtener el método de pago
switch ($pedido['metodo_pago']) {

    case 'efectivo_cafeteria':

        $metodoPago = 'Efectivo en cafetería';
        break;

    case 'efectivo_domicilio':

        $metodoPago = 'Efectivo a domicilio';
        break;

    default:

        $metodoPago = 'No especificado';
        break;
}

// Formatear la fecha y hora
$fechaPedido = date(
    'd/m/Y · h:i A',
    strtotime($pedido['fecha'])
);

// Generar la lista de productos
ob_start();

?>

<?php if (count($productos) === 0): ?>

    <p class="text-muted small">
        No hay productos disponibles para mostrar.
    </p>

<?php endif; ?>

<?php foreach ($productos as $producto): ?>

    <?php

    $idDetalle = $producto['id_detalle'];

    $personalizaciones =
        $opcionesProducto[$idDetalle] ?? [];

    $textoOpciones = implode(
        ', ',
        $personalizaciones
    );

    ?>

    <div class="producto-detalle">

        <!-- Imagen del producto -->
        <?php if (!empty($producto['imagen_url']) &&
            is_file(__DIR__ . '/../' . $producto['imagen_url'])): ?>

            <img src="../<?= htmlspecialchars($producto['imagen_url']) ?>"
                 alt="<?= htmlspecialchars($producto['nombre_producto']) ?>"
                 class="imagen-producto">

        <?php else: ?>

            <div class="imagen-producto imagen-sin-producto">

                <i class="bi bi-cup-hot"></i>

            </div>

        <?php endif; ?>

        <!-- Información del producto -->
        <div class="informacion-producto">

            <h4 class="nombre-producto">

                <?= htmlspecialchars($producto['nombre_producto']) ?>

            </h4>

            <?php if ($textoOpciones !== ''): ?>

                <p class="opciones-producto">

                    <?= htmlspecialchars($textoOpciones) ?>

                </p>

            <?php endif; ?>

            <p class="cantidad-producto">

                Cantidad: <?= (int) $producto['cantidad'] ?>

            </p>

            <p class="precio-unitario">

                Precio unitario:
                $<?= number_format($producto['precio_unitario'], 2) ?>

            </p>

        </div>

        <!-- Subtotal del producto -->
        <span class="precio-producto">

            $<?= number_format($producto['subtotal'], 2) ?>

        </span>

    </div>

<?php endforeach; ?>

<?php

$listaProductos = ob_get_clean();

// Cargar la vista HTML
$html = file_get_contents(
    __DIR__ . '/detalle_pedido.html'
);

if ($html === false || trim($html) === '') {

    http_response_code(500);

    exit('No fue posible cargar la vista del pedido.');
}

// Colocar los datos en la vista
$html = strtr($html, [

    '{{iniciales}}' => htmlspecialchars($iniciales),

    '{{cantidad_carrito}}' => (string) $cantidadCarrito,

    '{{id_pedido}}' => (string) $idPedido,

    '{{fecha_pedido}}' => htmlspecialchars($fechaPedido),

    '{{estado_pedido}}' => htmlspecialchars($estado),

    '{{clase_estado}}' => $claseEstado,

    '{{lista_productos}}' => $listaProductos,

    '{{tipo_entrega}}' => htmlspecialchars($tipoEntrega),

    '{{metodo_pago}}' => htmlspecialchars($metodoPago),

    '{{subtotal}}' => number_format($pedido['subtotal'], 2),

    '{{impuestos}}' => number_format($pedido['impuestos'], 2),

    '{{total}}' => number_format($pedido['total'], 2)

]);

// Mostrar la vista
echo $html;