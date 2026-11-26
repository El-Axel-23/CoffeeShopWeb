<?php
// Importar el control de sesiones
require_once __DIR__ . '/../Login/sesion.php';
// Comprobar que el usuario sea administrador
verificarSesion('../Login/login.php', 'admin');
require_once __DIR__ . '/../config/conexion.php';
if (!isset($_SESSION['csrf_admin'])) {
    $_SESSION['csrf_admin'] = bin2hex(random_bytes(32));
}

$error = '';
$nombre = '';
$descripcion = '';
$precio = '';
$id_categoria = 0;
$destacado = 0;
$disponible = 1;
$ext = '';

$resCategorias = $pdo->query(
    "SELECT * FROM categorias ORDER BY orden"
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_admin'], $token)) {

        http_response_code(403);

        exit('Solicitud no válida.');
    }

    $nombre = isset($_POST['nombre']) && is_string($_POST['nombre'])
        ? trim($_POST['nombre']) : '';

    $descripcion = isset($_POST['descripcion']) && is_string($_POST['descripcion'])
        ? trim($_POST['descripcion']) : '';

    $precio = isset($_POST['precio']) && is_string($_POST['precio'])
        ? $_POST['precio'] : '';

    $id_categoria = filter_input(INPUT_POST, 'id_categoria', FILTER_VALIDATE_INT);

    $destacado = isset($_POST['destacado']) ? 1 : 0;
    $disponible = isset($_POST['disponible']) ? 1 : 0;
    $imagen = $_FILES['imagen'] ?? null;

    if ($nombre == '') {

        $error = 'El nombre es obligatorio.';

    } elseif (mb_strlen($nombre) > 80 || mb_strlen($descripcion) > 255) {

        $error = 'El nombre o la descripción supera la longitud permitida.';

    } elseif (!is_numeric($precio) || $precio <= 0 || $precio > 999999.99) {

        $error = 'El precio debe ser un número válido mayor a 0 y menor o igual a 999999.99.';

    } elseif ($id_categoria <= 0) {

        $error = 'Elige una categoría.';

    } elseif (!$imagen || $imagen['error'] === UPLOAD_ERR_NO_FILE) {

        $error = 'Por favor selecciona una imagen.';

    } elseif ($imagen['error'] !== UPLOAD_ERR_OK) {

        $error = 'Ocurrió un error al subir el archivo.';

    } elseif ($imagen['size'] > 2 * 1024 * 1024) {

        $error = 'La imagen supera el peso máximo de 2MB.';

    } else {

        $extPermitidas = ['jpg', 'jpeg', 'png', 'webp'];

        $mimePermitidos = ['image/jpeg', 'image/png', 'image/webp'];

        $ext = strtolower(pathinfo($imagen['name'], PATHINFO_EXTENSION));

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        $mimeReal = finfo_file($finfo, $imagen['tmp_name']);

        finfo_close($finfo);

        if (!in_array($ext, $extPermitidas, true) ||
            !in_array($mimeReal, $mimePermitidos, true)) {

            $error = 'Formato no válido. Solo se admiten archivos JPG, PNG y WEBP.';
        }
    }

    if ($error == '') {

        $nombreUnico = uniqid('prod_', true) . '.' . $ext;

        $dirDestino = __DIR__ . '/../img/productos/';

        if (!is_dir($dirDestino)) {

            mkdir($dirDestino, 0755, true);
        }

        if (move_uploaded_file($imagen['tmp_name'], $dirDestino . $nombreUnico)) {

            $rutaBD = 'img/productos/' . $nombreUnico;

            try {

                $sql = "INSERT INTO productos (
                            id_categoria,
                            nombre,
                            descripcion,
                            precio,
                            imagen_url,
                            destacado,
                            disponible
                        ) VALUES (?, ?, ?, ?, ?, ?, ?)";

                $stmt = $pdo->prepare($sql);

                $stmt->execute([
                    $id_categoria,
                    $nombre,
                    $descripcion,
                    $precio,
                    $rutaBD,
                    $destacado,
                    $disponible
                ]);

                header('Location: index.php?msg=creado', true, 303);

                exit;

            } catch (PDOException $e) {

                if (is_file($dirDestino . $nombreUnico)) {

                    unlink($dirDestino . $nombreUnico);
                }

                error_log('Error al registrar producto: ' . $e->getMessage());

                $error = 'Error al registrar en la base de datos.';
            }

        } else {

            $error = 'No se pudo guardar la imagen en el servidor.';
        }
    }
}

$base = '../';
$titulo = 'Agregar producto';
$inputClase = 'w-full bg-durazno border border-moka rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cafe';
include __DIR__ . '/../includes/header.php';
?>
<h1 class="text-xl font-bold mb-2">Agregar producto</h1>
<a href="index.php" class="text-sm underline">← Ver listado</a>
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

        <select name="id_categoria" class="<?= $inputClase ?>" required>

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

    <label class="grid gap-1 text-sm font-semibold">

        Imagen (JPG, PNG o WEBP, máximo 2MB)

        <input type="file"
               name="imagen"
               accept="image/jpeg, image/png, image/webp"
               class="text-xs font-normal"
               required>

    </label>
    <div class="flex gap-3">
        <button type="submit"
                class="bg-moka text-crema rounded-xl px-5 py-2 text-sm font-semibold">
            Guardar producto
        </button>
        <a href="index.php"
           class="bg-durazno rounded-xl px-5 py-2 text-sm">
            Cancelar
        </a>
    </div>
</form>
<?php include __DIR__ . '/../includes/footer.php'; ?>