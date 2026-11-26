<?php

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

    $stmtFavoritos = $pdo->prepare($sqlFavoritos);

    $stmtFavoritos->execute([
        $_SESSION['id_usuario']
    ]);

    $resultadosFavoritos = $stmtFavoritos->fetchAll();

    foreach ($resultadosFavoritos as $favorito) {

        $favoritosUsuario[(int) $favorito['id_producto']] = true;
    }
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

    $filtroCategoria = ' AND id_categoria = :categoria';
}

$resDestacados = [];
$soloNormales = '';

// Consultar productos destacados
if (!$filtrando) {

    $sqlDestacados = "SELECT * FROM productos
                      WHERE disponible = 1
                      AND destacado = 1
                      ORDER BY id_producto";

    $stmtDestacados = $pdo->query($sqlDestacados);

    $resDestacados = $stmtDestacados->fetchAll();

    $soloNormales = ' AND destacado = 0';
}

// Consultar productos del menú
$sqlMenu = "SELECT * FROM productos
            WHERE disponible = 1
            AND nombre LIKE :nombre"
            . $filtroCategoria
            . $soloNormales
            . " ORDER BY id_producto";

$stmt = $pdo->prepare($sqlMenu);

$stmt->bindValue(':nombre', $like, PDO::PARAM_STR);

if ($cat > 0) {

    $stmt->bindValue(':categoria', $cat, PDO::PARAM_INT);
}

$stmt->execute();

$resMenu = $stmt->fetchAll();

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