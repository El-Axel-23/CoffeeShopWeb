<?php

// Validar la sesión
require_once __DIR__ . '/../Login/sesion.php';

verificarSesion('../Login/login.php');

header('Cache-Control: no-store');

require_once __DIR__ . '/../config/conexion.php';

$idUsuario = $_SESSION['id_usuario'];

// Generar token de seguridad
if (!isset($_SESSION['csrf_favoritos'])) {

    $_SESSION['csrf_favoritos'] = bin2hex(random_bytes(32));
}

// Consultar los productos favoritos
$sql = "SELECT p.id_producto,
               p.nombre,
               p.descripcion,
               p.precio,
               p.imagen_url,
               p.disponible
        FROM favoritos f
        INNER JOIN productos p
        ON f.id_producto = p.id_producto
        WHERE f.id_usuario = ?
        ORDER BY f.fecha DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$idUsuario]);

$favoritos = $stmt->fetchAll();

// Consultar cantidad de productos en el carrito
$sql = "SELECT COALESCE(SUM(cantidad), 0)
        FROM carrito_items
        WHERE id_usuario = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$idUsuario]);

$cantidadCarrito = (int) $stmt->fetchColumn();

// Obtener las iniciales del usuario
$palabras = explode(' ', trim($_SESSION['nombre_completo']));

$iniciales = mb_strtoupper(mb_substr($palabras[0], 0, 1));

if (count($palabras) > 1) {

    $iniciales .= mb_strtoupper(mb_substr($palabras[1], 0, 1));
}

// Mensajes de confirmación o error
$mensaje = '';
$tipoMensaje = 'alert-success';

$estado = $_SESSION['estado_favoritos'] ?? '';

if ($estado === 'eliminado') {

    $mensaje = 'Producto eliminado de tus favoritos.';

} elseif ($estado === 'agregado') {

    $mensaje = 'Producto agregado a tus favoritos.';

} elseif ($estado === 'error') {

    $mensaje = 'No fue posible realizar la operación.';
    $tipoMensaje = 'alert-danger';
}

unset($_SESSION['estado_favoritos']);

// Mostrar la pantalla vacía o los productos
$claseVacio = count($favoritos) === 0 ? '' : 'd-none';
$claseLista = count($favoritos) === 0 ? 'd-none' : '';
$claseMensaje = $mensaje === '' ? 'd-none' : '';

// Generar las tarjetas de productos
ob_start();

?>

<?php foreach ($favoritos as $p): ?>

    <div class="col-12 col-sm-6 col-lg-4 col-xl-3">

        <article class="tarjeta-favorito">

            <?php if (!empty($p['imagen_url']) &&
                is_file(__DIR__ . '/../' . $p['imagen_url'])): ?>

                <img src="../<?= htmlspecialchars($p['imagen_url']) ?>"
                     alt="<?= htmlspecialchars($p['nombre']) ?>"
                     class="imagen-producto">

            <?php else: ?>

                <div class="imagen-producto d-flex align-items-center justify-content-center bg-light">

                    <i class="bi bi-cup-hot fs-1"></i>

                </div>

            <?php endif; ?>

            <!-- Eliminar producto de favoritos -->
            <form method="POST"
                  action="eliminar_favorito.php"
                  class="form-eliminar-favorito">

                <input type="hidden"
                       name="id_producto"
                       value="<?= (int) $p['id_producto'] ?>">

                <input type="hidden"
                       name="csrf_token"
                       value="<?= htmlspecialchars($_SESSION['csrf_favoritos']) ?>">

                <button type="submit"
                        class="boton-eliminar"
                        aria-label="Quitar de favoritos">

                    <i class="bi bi-heart-fill"></i>

                </button>

            </form>

            <div class="informacion-producto">

                <h2 class="nombre-producto">

                    <?= htmlspecialchars($p['nombre']) ?>

                </h2>

                <p class="descripcion-producto">

                    <?= htmlspecialchars($p['descripcion'] ?? '') ?>

                </p>

                <?php if ((int) $p['disponible'] !== 1): ?>

                    <p class="text-danger small">
                        Producto no disponible
                    </p>

                <?php endif; ?>

                <span class="precio-producto">

                    $<?= number_format($p['precio'], 2) ?>

                </span>

            </div>

        </article>

    </div>

<?php endforeach; ?>

<?php

$listaFavoritos = ob_get_clean();

// Cargar la vista HTML
$html = file_get_contents(__DIR__ . '/favoritos.html');

if ($html === false || trim($html) === '') {

    http_response_code(500);
    exit('No fue posible cargar la vista de favoritos.');
}

// Enviar los datos a la vista
$html = strtr($html, [

    '{{iniciales}}' => htmlspecialchars($iniciales),

    '{{cantidad_carrito}}' => (string) $cantidadCarrito,

    '{{mensaje}}' => htmlspecialchars($mensaje),

    '{{tipo_mensaje}}' => $tipoMensaje,

    '{{clase_mensaje}}' => $claseMensaje,

    '{{clase_vacio}}' => $claseVacio,

    '{{clase_lista}}' => $claseLista,

    '{{lista_favoritos}}' => $listaFavoritos

]);

// Mostrar la vista
echo $html;