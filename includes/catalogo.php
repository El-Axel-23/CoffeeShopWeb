<?php

// Importar la conexión
require_once __DIR__ . '/../config/conexion.php';

// Comprobar si el usuario inició sesión
$sesionActiva = session_status() === PHP_SESSION_ACTIVE
    && isset($_SESSION['id_usuario']);

$favoritosUsuario = [];

// Consultar los favoritos del usuario
if ($sesionActiva) {

    if (!isset($_SESSION['csrf_favoritos'])) {

        $_SESSION['csrf_favoritos'] = bin2hex(random_bytes(32));
    }

    $sqlFavoritos = "SELECT id_producto
                     FROM favoritos
                     WHERE id_usuario = ?";

    $idUsuario = (int) $_SESSION['id_usuario'];

    $stmtFavoritos = mysqli_prepare($conexion, $sqlFavoritos);

    if (!$stmtFavoritos) {

        error_log('Error al preparar favoritos: ' . mysqli_error($conexion));

        http_response_code(500);
        exit('No fue posible consultar los favoritos.');
    }

    // Asignar el ID del usuario y ejecutar la consulta
    if (!mysqli_stmt_bind_param($stmtFavoritos, "i", $idUsuario) ||
        !mysqli_stmt_execute($stmtFavoritos) ||
        !mysqli_stmt_bind_result($stmtFavoritos, $idFavorito)) {

        error_log('Error al consultar favoritos: ' . mysqli_stmt_error($stmtFavoritos));

        mysqli_stmt_close($stmtFavoritos);

        http_response_code(500);
        exit('No fue posible consultar los favoritos.');
    }

    // Guardar los favoritos del usuario
    while (($lectura = mysqli_stmt_fetch($stmtFavoritos)) === true) {

        $favoritosUsuario[(int) $idFavorito] = true;
    }

    if ($lectura === false) {

        error_log('Error al leer favoritos: ' . mysqli_stmt_error($stmtFavoritos));

        mysqli_stmt_close($stmtFavoritos);

        http_response_code(500);
        exit('No fue posible obtener los favoritos.');
    }

    mysqli_stmt_close($stmtFavoritos);
}

// Mostrar el corazón del producto
function mostrarCorazonCatalogo($idProducto, $esFavorito, $sesionActiva) {

    $accion = $esFavorito
        ? 'Favoritos/eliminar_favorito.php'
        : 'Favoritos/agregar_favorito.php';

    $icono = $esFavorito
        ? 'bi-heart-fill text-cafe'
        : 'bi-heart text-moka';

    ?>

    <?php if ($sesionActiva): ?>

        <form method="POST"
              action="<?= $accion ?>"
              class="absolute top-2 right-2">

            <input type="hidden"
                   name="id_producto"
                   value="<?= (int) $idProducto ?>">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['csrf_favoritos']) ?>">

            <input type="hidden"
                   name="origen"
                   value="catalogo">

            <button type="submit"
                    class="w-8 h-8 rounded-full bg-white
                           flex items-center justify-center
                           shadow-sm hover:bg-crema
                           transition-colors"
                    aria-label="<?= $esFavorito
                        ? 'Quitar favorito'
                        : 'Agregar favorito' ?>"
                    aria-pressed="<?= $esFavorito ? 'true' : 'false' ?>">

                <i class="bi <?= $icono ?> text-sm"></i>

            </button>

        </form>

    <?php else: ?>

        <a href="Login/login.php"
           class="absolute top-2 right-2 w-8 h-8
                  rounded-full bg-white flex items-center
                  justify-center shadow-sm hover:bg-crema"
           aria-label="Iniciar sesión para guardar favoritos">

            <i class="bi bi-heart text-sm"></i>

        </a>

    <?php endif; ?>

    <?php
}

// Obtener los filtros
$q = $q ?? '';
$cat = (int) ($cat ?? 0);

$filtrando = ($q !== '' || $cat > 0);

$like = '%' . $q . '%';

$filtroCategoria = '';

if ($cat > 0) {

    $filtroCategoria = ' AND id_categoria = ?';
}

$resDestacados = [];
$soloNormales = '';

// Consultar productos destacados
if (!$filtrando) {

    $sqlDestacados = "SELECT * FROM productos
                      WHERE disponible = 1
                      AND destacado = 1
                      ORDER BY id_producto";

    $resultadoDestacados = mysqli_query(
        $conexion,
        $sqlDestacados
    );

    if (!$resultadoDestacados) {

        error_log('Error al consultar destacados: ' . mysqli_error($conexion));

        http_response_code(500);
        exit('No fue posible consultar los productos destacados.');
    }

    // Guardar los productos destacados
    while ($producto = mysqli_fetch_assoc($resultadoDestacados)) {

        $resDestacados[] = $producto;
    }

    mysqli_free_result($resultadoDestacados);

    $soloNormales = ' AND destacado = 0';
}

// Consultar productos del menú
$sqlMenu = "SELECT * FROM productos
            WHERE disponible = 1
            AND nombre LIKE ?"
            . $filtroCategoria
            . $soloNormales
            . " ORDER BY id_producto";

// Preparar la consulta
$stmt = mysqli_prepare($conexion, $sqlMenu);

if (!$stmt) {

    error_log('Error al preparar menú: ' . mysqli_error($conexion));

    http_response_code(500);
    exit('No fue posible consultar el menú.');
}

// Asignar los parámetros según el filtro
if ($cat > 0) {

    $parametros = mysqli_stmt_bind_param(
        $stmt,
        "si",
        $like,
        $cat
    );

} else {

    $parametros = mysqli_stmt_bind_param(
        $stmt,
        "s",
        $like
    );
}

// Ejecutar la consulta
if (!$parametros || !mysqli_stmt_execute($stmt)) {

    error_log('Error al consultar menú: ' . mysqli_stmt_error($stmt));

    mysqli_stmt_close($stmt);

    http_response_code(500);
    exit('No fue posible consultar el menú.');
}

// Obtener los resultados
$resultadoMenu = mysqli_stmt_get_result($stmt);

if (!$resultadoMenu) {

    error_log('Error al obtener menú: ' . mysqli_stmt_error($stmt));

    mysqli_stmt_close($stmt);

    http_response_code(500);
    exit('No fue posible obtener los productos del menú.');
}

$resMenu = [];

// Guardar los productos del menú
while ($producto = mysqli_fetch_assoc($resultadoMenu)) {

    $resMenu[] = $producto;
}

mysqli_free_result($resultadoMenu);

mysqli_stmt_close($stmt);

?>

<!-- Cafés destacados -->
<?php if (count($resDestacados) > 0): ?>

    <h2 class="text-sm font-semibold mb-5">

        CAFÉS DESTACADOS DE HOY (<?= count($resDestacados) ?>)

    </h2>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-8">

        <?php foreach ($resDestacados as $p): ?>

            <?php

            $idProducto = (int) $p['id_producto'];

            $esFavorito = isset($favoritosUsuario[$idProducto]);

            ?>

            <article class="bg-white border border-borde
                            rounded-2xl overflow-hidden flex
                            min-h-52 md:min-h-60">

                <!-- Imagen del producto -->
                <div class="relative w-1/2 bg-crema shrink-0">

                    <?php if (!empty($p['imagen_url']) &&
                        file_exists(__DIR__ . '/../' . $p['imagen_url'])): ?>

                        <img src="<?= htmlspecialchars($p['imagen_url']) ?>"
                             alt="<?= htmlspecialchars($p['nombre']) ?>"
                             class="absolute inset-0 w-full h-full object-cover">

                    <?php else: ?>

                        <div class="absolute inset-0 flex items-center
                                    justify-center text-4xl">

                            ☕

                        </div>

                    <?php endif; ?>

                    <!-- Corazón de favoritos -->
                    <?php

                    mostrarCorazonCatalogo(
                        $idProducto,
                        $esFavorito,
                        $sesionActiva
                    );

                    ?>

                </div>

                <!-- Información del producto -->
                <div class="w-1/2 p-4 md:p-5 flex flex-col gap-3">

                    <div>

                        <h3 class="text-sm font-bold mb-2">

                            <?= htmlspecialchars($p['nombre']) ?>

                        </h3>

                        <p class="text-xs leading-relaxed">

                            <?= htmlspecialchars($p['descripcion'] ?? '') ?>

                        </p>

                    </div>

                    <!-- Precio y botón agregar -->
                    <div class="flex items-center justify-between gap-2">

                        <span class="text-sm font-bold text-cafe">

                            $<?= number_format($p['precio'], 2) ?>

                        </span>

                        <a href="personalizar.php?id=<?= $idProducto ?>"
                           class="w-9 h-9 rounded-xl bg-cafe
                                  text-white flex items-center
                                  justify-center hover:bg-cafeHover
                                  transition-colors shrink-0"
                           aria-label="Personalizar producto">

                            <i class="bi bi-plus-lg text-base"></i>

                        </a>

                    </div>

                </div>

            </article>

        <?php endforeach; ?>

    </div>

<?php endif; ?>


<!-- Menú completo -->
<h2 id="menu" class="text-sm font-semibold mb-5">

    <?= $filtrando ? 'RESULTADOS' : 'MENÚ COMPLETO' ?>

</h2>

<?php if (count($resMenu) > 0): ?>

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4
                gap-3 md:gap-5">

        <?php foreach ($resMenu as $p): ?>

            <?php

            $idProducto = (int) $p['id_producto'];

            $esFavorito = isset($favoritosUsuario[$idProducto]);

            ?>

            <article class="bg-white border border-borde
                            rounded-2xl overflow-hidden
                            flex flex-col h-full">

                <!-- Imagen del producto -->
                <div class="relative aspect-[3/2] bg-crema">

                    <?php if (!empty($p['imagen_url']) &&
                        file_exists(__DIR__ . '/../' . $p['imagen_url'])): ?>

                        <img src="<?= htmlspecialchars($p['imagen_url']) ?>"
                             alt="<?= htmlspecialchars($p['nombre']) ?>"
                             class="w-full h-full object-cover">

                    <?php else: ?>

                        <div class="w-full h-full flex items-center
                                    justify-center text-4xl">

                            ☕

                        </div>

                    <?php endif; ?>

                    <!-- Corazón de favoritos -->
                    <?php

                    mostrarCorazonCatalogo(
                        $idProducto,
                        $esFavorito,
                        $sesionActiva
                    );

                    ?>

                </div>

                <!-- Información del producto -->
                <div class="p-3 md:p-4 flex flex-col flex-1 gap-3">

                    <div>

                        <h3 class="text-sm font-bold mb-2">

                            <?= htmlspecialchars($p['nombre']) ?>

                        </h3>

                        <p class="text-xs leading-relaxed">

                            <?= htmlspecialchars($p['descripcion'] ?? '') ?>

                        </p>

                    </div>

                    <!-- Precio y botón agregar -->
                    <div class="flex items-center justify-between
                                gap-2 mt-auto">

                        <span class="text-sm font-bold text-cafe">

                            $<?= number_format($p['precio'], 2) ?>

                        </span>

                        <a href="personalizar.php?id=<?= $idProducto ?>"
                           class="w-9 h-9 rounded-xl bg-cafe
                                  text-white flex items-center
                                  justify-center hover:bg-cafeHover
                                  transition-colors shrink-0"
                           aria-label="Personalizar producto">

                            <i class="bi bi-plus-lg text-base"></i>

                        </a>

                    </div>

                </div>

            </article>

        <?php endforeach; ?>

    </div>

<?php else: ?>

    <p class="text-sm text-secundario py-5">

        No encontramos productos.
        Prueba con otra búsqueda o categoría.

    </p>

<?php endif; ?>