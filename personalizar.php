<?php

// Importar el control de sesiones
require_once __DIR__ . '/Login/sesion.php';

// Iniciar sesión
if (session_status() !== PHP_SESSION_ACTIVE) {

    session_start();
}

// Validar la sesión si el usuario inició sesión
if (isset($_SESSION['id_usuario'])) {

    verificarSesion('Login/login.php');
}

// Conexión a la base de datos
require_once __DIR__ . '/config/conexion.php';

// Obtener el ID del producto
$idProducto = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

// Validar el ID del producto
if (!$idProducto || $idProducto <= 0) {

    header('Location: index.php#menu');
    exit;
}

// Consultar la información del producto
$sql = "SELECT * FROM productos
        WHERE id_producto = ?
        AND disponible = 1";

// Preparar la consulta
$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {

    error_log(
        'Error al preparar producto: ' .
        mysqli_error($conexion)
    );

    http_response_code(500);
    exit('No fue posible consultar el producto.');
}

// Asignar el ID del producto y ejecutar
if (!mysqli_stmt_bind_param($stmt, "i", $idProducto) ||
    !mysqli_stmt_execute($stmt)) {

    error_log(
        'Error al consultar producto: ' .
        mysqli_stmt_error($stmt)
    );

    mysqli_stmt_close($stmt);

    http_response_code(500);
    exit('No fue posible consultar el producto.');
}

// Obtener el resultado
$resultado = mysqli_stmt_get_result($stmt);

if (!$resultado) {

    error_log(
        'Error al obtener producto: ' .
        mysqli_stmt_error($stmt)
    );

    mysqli_stmt_close($stmt);

    http_response_code(500);
    exit('No fue posible obtener el producto.');
}

$producto = mysqli_fetch_assoc($resultado);

mysqli_free_result($resultado);

mysqli_stmt_close($stmt);

// Comprobar que el producto exista
if (!$producto) {

    header('Location: index.php#menu');
    exit;
}

// Comprobar si el producto está en favoritos
$esFavorito = false;

if (isset($_SESSION['id_usuario'])) {

    $idUsuario = (int) $_SESSION['id_usuario'];

    // Generar token de seguridad
    if (!isset($_SESSION['csrf_favoritos'])) {

        $_SESSION['csrf_favoritos'] =
            bin2hex(random_bytes(32));
    }

    // Consultar si el producto está en favoritos
    $sqlFavorito = "SELECT 1 FROM favoritos
                    WHERE id_usuario = ?
                    AND id_producto = ?";

    $favoritoStmt = mysqli_prepare(
        $conexion,
        $sqlFavorito
    );

    if (!$favoritoStmt) {

        error_log(
            'Error al preparar favoritos: ' .
            mysqli_error($conexion)
        );

        http_response_code(500);
        exit('No fue posible consultar los favoritos.');
    }

    // Asignar los parámetros
    if (!mysqli_stmt_bind_param(
            $favoritoStmt,
            "ii",
            $idUsuario,
            $idProducto
        ) ||
        !mysqli_stmt_execute($favoritoStmt) ||
        !mysqli_stmt_store_result($favoritoStmt)) {

        error_log(
            'Error al consultar favoritos: ' .
            mysqli_stmt_error($favoritoStmt)
        );

        mysqli_stmt_close($favoritoStmt);

        http_response_code(500);
        exit('No fue posible consultar los favoritos.');
    }

    // Comprobar si existe el favorito
    $esFavorito = mysqli_stmt_num_rows($favoritoStmt) > 0;

    mysqli_stmt_close($favoritoStmt);
}

$titulo = 'Personalizar - Coffee Shop';

include __DIR__ . '/includes/header.php';

?>

<div class="max-w-6xl mx-auto w-full px-4 md:px-6 py-6">

    <a href="index.php#menu"
       class="text-xs text-secundario hover:text-cafe mb-5 inline-block">

        <i class="bi bi-arrow-left"></i>

        Menú / Personalización

    </a>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8">

        <!-- Imagen del producto -->
        <div class="relative rounded-2xl overflow-hidden bg-crema
                    min-h-64 md:min-h-[420px]">

            <?php if (!empty($producto['imagen_url']) &&
                is_file(__DIR__ . '/' . $producto['imagen_url'])): ?>

                <img src="<?= htmlspecialchars($producto['imagen_url']) ?>"
                     alt="<?= htmlspecialchars($producto['nombre']) ?>"
                     class="absolute inset-0 w-full h-full object-cover">

            <?php else: ?>

                <div class="absolute inset-0 flex items-center
                            justify-center text-6xl">

                    ☕

                </div>

            <?php endif; ?>

            <!-- Botón de favoritos -->
            <?php if (isset($_SESSION['id_usuario'])): ?>

                <form method="POST"
                      action="Favoritos/<?= $esFavorito
                          ? 'eliminar_favorito.php'
                          : 'agregar_favorito.php' ?>"
                      class="absolute top-3 right-3">

                    <input type="hidden"
                           name="id_producto"
                           value="<?= $idProducto ?>">

                    <input type="hidden"
                           name="csrf_token"
                           value="<?= htmlspecialchars($_SESSION['csrf_favoritos']) ?>">

                    <input type="hidden"
                           name="origen"
                           value="catalogo">

                    <button type="submit"
                            aria-label="<?= $esFavorito
                                ? 'Quitar favorito'
                                : 'Agregar favorito' ?>"
                            class="w-9 h-9 bg-white rounded-full
                                   flex items-center justify-center
                                   shadow-sm">

                        <i class="bi <?= $esFavorito
                            ? 'bi-heart-fill text-cafe'
                            : 'bi-heart text-moka' ?>"></i>

                    </button>

                </form>

            <?php else: ?>

                <a href="Login/login.php"
                   class="absolute top-3 right-3 w-9 h-9 bg-white
                          rounded-full flex items-center justify-center
                          shadow-sm"
                   aria-label="Inicia sesión para guardar favoritos">

                    <i class="bi bi-heart"></i>

                </a>

            <?php endif; ?>

        </div>

        <!-- Información del producto -->
        <section class="flex flex-col gap-4">

            <div class="flex items-start justify-between gap-3">

                <h1 class="text-lg font-bold">

                    <?= htmlspecialchars($producto['nombre']) ?>

                </h1>

                <span class="font-bold text-sm whitespace-nowrap">

                    $<?= number_format($producto['precio'], 2) ?>

                </span>

            </div>

            <p class="text-sm leading-relaxed">

                <?= htmlspecialchars($producto['descripcion'] ?? '') ?>

            </p>

            <h2 class="font-semibold text-sm">

                Personalización

            </h2>

            <!-- Tamaño -->
            <div>

                <label class="block text-xs font-semibold mb-2">
                    Tamaño
                </label>

                <div class="flex flex-wrap gap-2 text-xs">

                    <label class="rounded-full bg-durazno px-3 py-2">

                        <input type="radio"
                               name="tamano"
                               value="Chico"
                               checked>

                        Chico

                    </label>

                    <label class="rounded-full bg-durazno px-3 py-2">

                        <input type="radio"
                               name="tamano"
                               value="Mediano">

                        Mediano

                    </label>

                    <label class="rounded-full bg-durazno px-3 py-2">

                        <input type="radio"
                               name="tamano"
                               value="Grande">

                        Grande

                    </label>

                </div>

            </div>

            <!-- Tipo de leche -->
            <div>

                <label class="block text-xs font-semibold mb-2">
                    Leche
                </label>

                <div class="flex flex-wrap gap-2 text-xs">

                    <label class="rounded-full bg-durazno px-3 py-2">

                        <input type="radio"
                               name="leche"
                               value="Entera"
                               checked>

                        Entera

                    </label>

                    <label class="rounded-full bg-durazno px-3 py-2">

                        <input type="radio"
                               name="leche"
                               value="Deslactosada">

                        Deslactosada

                    </label>

                    <label class="rounded-full bg-durazno px-3 py-2">

                        <input type="radio"
                               name="leche"
                               value="Avena">

                        Avena

                    </label>

                    <label class="rounded-full bg-durazno px-3 py-2">

                        <input type="radio"
                               name="leche"
                               value="Almendra">

                        Almendra

                    </label>

                </div>

            </div>

            <!-- Extras -->
            <div>

                <label class="block text-xs font-semibold mb-2">
                    Extra
                </label>

                <div class="flex flex-wrap gap-2 text-xs">

                    <label class="rounded-full bg-durazno px-3 py-2">

                        <input type="checkbox"
                               name="extras[]"
                               value="Sirope Vainilla">

                        Sirope Vainilla

                    </label>

                    <label class="rounded-full bg-durazno px-3 py-2">

                        <input type="checkbox"
                               name="extras[]"
                               value="Canela">

                        Canela

                    </label>

                </div>

            </div>

            <!-- Agregar al carrito -->
            <button type="button"
                    onclick="alert('La pantalla de personalización ya está conectada. La integración del carrito se realizará en el siguiente paso.')"
                    class="w-full rounded-lg bg-moka text-white
                           py-3 text-xs font-semibold hover:opacity-90">

                AGREGAR AL CARRITO

            </button>

        </section>

    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>