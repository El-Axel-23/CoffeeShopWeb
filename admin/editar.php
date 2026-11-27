<?php

// Importar el control de sesiones
require_once __DIR__ . '/../Login/sesion.php';

// Comprobar que el usuario sea administrador
verificarSesion('../Login/login.php', 'admin');

// Importar la conexión
require_once __DIR__ . '/../config/conexion.php';

// Generar token de seguridad
if (!isset($_SESSION['csrf_admin'])) {

    $_SESSION['csrf_admin'] = bin2hex(random_bytes(32));
}

// 1. Recibir el ID de la URL y buscar el producto
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$producto = null;

if ($id && $id > 0) {

    $sql = "SELECT * FROM productos WHERE id_producto = ?";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {

        error_log("Error al preparar consulta: " . mysqli_error($conexion));

        http_response_code(500);

        exit("No fue posible consultar el producto.");
    }

    mysqli_stmt_bind_param($stmt, "i", $id);

    if (!mysqli_stmt_execute($stmt)) {

        error_log("Error al consultar producto: " . mysqli_stmt_error($stmt));

        mysqli_stmt_close($stmt);

        http_response_code(500);

        exit("No fue posible consultar el producto.");
    }

    $resultado = mysqli_stmt_get_result($stmt);

    if (!$resultado) {

        mysqli_stmt_close($stmt);

        http_response_code(500);

        exit("No fue posible obtener el producto.");
    }

    $producto = mysqli_fetch_assoc($resultado);

    mysqli_free_result($resultado);

    mysqli_stmt_close($stmt);
}

// 2. Si el producto no existe, regresar al listado
if (!$producto) {

    header('Location: index.php');

    exit;
}

// Obtener los datos actuales del producto
$error = '';
$ext = '';

$nombre = $producto['nombre'];
$descripcion = $producto['descripcion'] ?? '';
$precio = $producto['precio'];
$id_categoria = $producto['id_categoria'];
$destacado = $producto['destacado'];
$disponible = $producto['disponible'];

// Obtener las categorías
$sqlCategorias = "SELECT * FROM categorias ORDER BY orden";

$resultadoCategorias = mysqli_query($conexion, $sqlCategorias);

if (!$resultadoCategorias) {

    error_log("Error al consultar categorías: " . mysqli_error($conexion));

    http_response_code(500);

    exit("No fue posible obtener las categorías.");
}

$resCategorias = [];

while ($categoria = mysqli_fetch_assoc($resultadoCategorias)) {

    $resCategorias[] = $categoria;
}

mysqli_free_result($resultadoCategorias);

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    // Validar el token de seguridad
    if (!is_string($token) || !hash_equals($_SESSION['csrf_admin'], $token)) {

        http_response_code(403);

        exit('Solicitud no válida.');
    }

    // Recibir los datos del formulario
    $nombre = isset($_POST['nombre']) && is_string($_POST['nombre'])
        ? trim($_POST['nombre']) : '';

    $descripcion = isset($_POST['descripcion']) && is_string($_POST['descripcion'])
        ? trim($_POST['descripcion']) : '';

    $precio = isset($_POST['precio']) && is_string($_POST['precio'])
        ? $_POST['precio'] : '';

    $id_categoria = filter_input(
        INPUT_POST,
        'id_categoria',
        FILTER_VALIDATE_INT
    );

    $destacado = isset($_POST['destacado']) ? 1 : 0;
    $disponible = isset($_POST['disponible']) ? 1 : 0;

    $imagen = $_FILES['imagen'] ?? null;

    $rutaNueva = null;

    // Conservar la imagen actual
    $rutaBD = $producto['imagen_url'];

    // Comprobar si seleccionó una imagen nueva
    $hayImagenNueva = (
        $imagen && $imagen['error'] !== UPLOAD_ERR_NO_FILE
    );

    // Validar los datos del producto
    if ($nombre == '') {

        $error = 'El nombre es obligatorio.';

    } elseif (mb_strlen($nombre) > 80 || mb_strlen($descripcion) > 255) {

        $error = 'El nombre o la descripción supera la longitud permitida.';

    } elseif (!is_numeric($precio) || $precio <= 0 || $precio > 999999.99) {

        $error = 'El precio debe ser un número válido mayor a 0 y menor o igual a 999999.99.';

    } elseif ($id_categoria <= 0) {

        $error = 'Elige una categoría.';

    } elseif ($hayImagenNueva) {

        // Validar la imagen nueva
        if ($imagen['error'] !== UPLOAD_ERR_OK) {

            $error = 'Ocurrió un error al subir el archivo.';

        } elseif ($imagen['size'] > 2 * 1024 * 1024) {

            $error = 'La imagen supera el peso máximo de 2MB.';

        } else {

            $extPermitidas = ['jpg', 'jpeg', 'png', 'webp'];

            $mimePermitidos = [
                'image/jpeg',
                'image/png',
                'image/webp'
            ];

            $ext = strtolower(
                pathinfo($imagen['name'], PATHINFO_EXTENSION)
            );

            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            if ($finfo === false) {

                $error = 'No fue posible comprobar el formato de la imagen.';

            } else {

                $mimeReal = finfo_file($finfo, $imagen['tmp_name']);

                finfo_close($finfo);

                if (!in_array($ext, $extPermitidas, true) ||
                    !in_array($mimeReal, $mimePermitidos, true)) {

                    $error = 'Formato no válido. Solo se admiten archivos JPG, PNG y WEBP.';
                }
            }
        }
    }

    // Guardar la imagen nueva
    if ($error == '') {

        if ($hayImagenNueva) {

            $nombreUnico = uniqid('prod_', true) . '.' . $ext;

            $dirDestino = __DIR__ . '/../img/productos/';

            // Crear la carpeta si no existe
            if (!is_dir($dirDestino)) {

                mkdir($dirDestino, 0755, true);
            }

            if (move_uploaded_file(
                $imagen['tmp_name'],
                $dirDestino . $nombreUnico
            )) {

                $rutaBD = 'img/productos/' . $nombreUnico;

                $rutaNueva = $dirDestino . $nombreUnico;

            } else {

                $error = 'No se pudo guardar la imagen en el servidor.';
            }
        }
    }

    // Actualizar el producto
    if ($error == '') {

        $sql = "UPDATE productos
                SET id_categoria = ?,
                    nombre = ?,
                    descripcion = ?,
                    precio = ?,
                    imagen_url = ?,
                    destacado = ?,
                    disponible = ?
                WHERE id_producto = ?";

        // Preparar la consulta
        $stmt = mysqli_prepare($conexion, $sql);

        if (!$stmt) {

            error_log(
                'Error al preparar consulta: ' .
                mysqli_error($conexion)
            );

            $error = 'Error al preparar la actualización del producto.';

        } else {

            // Convertir el precio a decimal
            $precioProducto = (float) $precio;

            // Asignar los parámetros
            $parametros = mysqli_stmt_bind_param(
                $stmt,
                "issdsiii",
                $id_categoria,
                $nombre,
                $descripcion,
                $precioProducto,
                $rutaBD,
                $destacado,
                $disponible,
                $id
            );

            // Ejecutar la actualización
            if ($parametros && mysqli_stmt_execute($stmt)) {

                mysqli_stmt_close($stmt);

                // Eliminar la imagen anterior si fue reemplazada
                if ($hayImagenNueva) {

                    $imagenAnterior = $producto['imagen_url'] ?? '';

                    $vieja = __DIR__ . '/../' . $imagenAnterior;

                    // Solo eliminar imágenes subidas desde el panel
                    if (
                        strpos($imagenAnterior, 'img/productos/prod_') === 0 &&
                        basename($imagenAnterior) === substr(
                            $imagenAnterior,
                            strlen('img/productos/')
                        ) &&
                        is_file($vieja)
                    ) {

                        unlink($vieja);
                    }
                }

                header('Location: index.php?msg=editado', true, 303);

                exit;

            } else {

                error_log(
                    'Error al editar producto: ' .
                    mysqli_stmt_error($stmt)
                );

                $error = 'Error al actualizar en la base de datos.';
            }

            mysqli_stmt_close($stmt);
        }

        // Eliminar la imagen nueva si falló la actualización
        if ($error != '') {

            if ($rutaNueva !== null && is_file($rutaNueva)) {

                unlink($rutaNueva);
            }
        }
    }
}

$base = '../';

$titulo = 'Editar producto';

$inputClase = 'w-full bg-durazno border border-moka rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cafe';

include __DIR__ . '/../includes/header.php';

?>

<h1 class="text-xl font-bold mb-2">
    Editar producto
</h1>

<a href="index.php" class="text-sm underline">
    ← Ver listado
</a>

<?php if ($error != ''): ?>

    <p class="bg-red-100 border border-red-400 text-red-800 rounded-xl p-3 my-4 text-sm max-w-xl">

        <?= htmlspecialchars($error) ?>

    </p>

<?php endif; ?>

<form method="post"
      enctype="multipart/form-data"
      class="grid gap-4 max-w-xl mt-4">

    <input type="hidden"
           name="csrf_token"
           value="<?= htmlspecialchars($_SESSION['csrf_admin']) ?>">

    <label class="grid gap-1 text-sm font-semibold">

        Nombre

        <input type="text"
               name="nombre"
               maxlength="80"
               value="<?= htmlspecialchars($nombre) ?>"
               class="<?= $inputClase ?>"
               required>

    </label>

    <label class="grid gap-1 text-sm font-semibold">

        Descripción

        <textarea name="descripcion"
                  rows="3"
                  maxlength="255"
                  class="<?= $inputClase ?>"><?= htmlspecialchars($descripcion) ?></textarea>

    </label>

    <label class="grid gap-1 text-sm font-semibold">

        Precio

        <input type="number"
               name="precio"
               step="0.01"
               min="0.01"
               value="<?= htmlspecialchars($precio) ?>"
               class="<?= $inputClase ?>"
               required>

    </label>

    <label class="grid gap-1 text-sm font-semibold">

        Categoría

        <select name="id_categoria"
                class="<?= $inputClase ?>"
                required>

            <option value="">Elige una categoría</option>

            <?php foreach ($resCategorias as $c): ?>

                <option value="<?= $c['id_categoria'] ?>"
                    <?= $id_categoria == $c['id_categoria'] ? 'selected' : '' ?>>

                    <?= htmlspecialchars($c['nombre']) ?>

                </option>

            <?php endforeach; ?>

        </select>

    </label>

    <label class="flex items-center gap-2 text-sm font-semibold">

        <input type="checkbox"
               name="destacado"
               value="1"
               <?= $destacado ? 'checked' : '' ?>>

        Mostrar en "Cafés destacados de hoy"

    </label>

    <label class="flex items-center gap-2 text-sm font-semibold">

        <input type="checkbox"
               name="disponible"
               value="1"
               <?= $disponible ? 'checked' : '' ?>>

        Disponible en el menú

    </label>

    <div class="grid gap-2 text-sm font-semibold">

        Imagen (JPG, PNG o WEBP, máximo 2MB)

        <?php if (!empty($producto['imagen_url']) && file_exists(__DIR__ . '/../' . $producto['imagen_url'])): ?>

            <img src="../<?= htmlspecialchars($producto['imagen_url']) ?>"
                 width="120"
                 class="rounded-xl"
                 alt="Imagen actual">

            <span class="text-xs font-normal">

                Si eliges otra imagen, reemplaza la actual.

            </span>

        <?php endif; ?>

        <input type="file"
               name="imagen"
               accept="image/jpeg, image/png, image/webp"
               class="text-xs font-normal">

    </div>

    <div class="flex gap-3">

        <button type="submit"
                class="bg-moka text-crema rounded-xl px-5 py-2 text-sm font-semibold">

            Guardar cambios

        </button>

        <a href="index.php"
           class="bg-durazno rounded-xl px-5 py-2 text-sm">

            Cancelar

        </a>

    </div>

</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>