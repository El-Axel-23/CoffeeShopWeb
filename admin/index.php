<?php

// Importar el control de sesiones
require_once __DIR__ . '/../Login/sesion.php';

// Candado de seguridad: solo el administrador entra
verificarSesion('../Login/login.php', 'admin');

require_once __DIR__ . '/../config/conexion.php';

if (!isset($_SESSION['csrf_admin'])) {

    $_SESSION['csrf_admin'] = bin2hex(random_bytes(32));
}

// Búsqueda por nombre (si no escribió nada, trae todos)

$q = isset($_GET['q']) && is_string($_GET['q'])
    ? trim($_GET['q']) : '';

$like = '%' . $q . '%';

$sql = "SELECT p.*, c.nombre AS categoria
        FROM productos p
        LEFT JOIN categorias c ON c.id_categoria = p.id_categoria
        WHERE p.nombre LIKE ?
        ORDER BY p.id_producto DESC";

$stmt = $pdo->prepare($sql);

$stmt->execute([$like]);

$resultado = $stmt->fetchAll();

// Mensaje que llega después de agregar, editar o eliminar

$msg = $_GET['msg'] ?? '';
$mensaje = '';

if ($msg == 'creado') {

    $mensaje = 'Producto agregado.';

} elseif ($msg == 'editado') {

    $mensaje = 'Producto actualizado.';

} elseif ($msg == 'eliminado') {

    $mensaje = 'Producto eliminado.';

} elseif ($msg == 'en_uso') {

    $mensaje = 'No se puede eliminar: el producto está en pedidos o carritos. Mejor edítalo y desmarca "Disponible".';

} elseif ($msg == 'error') {

    $mensaje = 'No fue posible eliminar el producto.';

} elseif ($msg == 'no_encontrado') {

    $mensaje = 'El producto no existe.';
}

$base = '../';
$titulo = 'Administrar productos';

include __DIR__ . '/../includes/header.php';

?>

<div class="flex flex-wrap items-center justify-between gap-3 mb-4">

    <h1 class="text-xl font-bold">Gestión de productos</h1>

    <a href="crear.php"
       class="bg-moka text-crema rounded-xl px-4 py-2 text-sm font-semibold">

        + Agregar producto

    </a>

</div>

<?php if ($mensaje != ''): ?>

    <p class="border rounded-xl p-3 mb-4 text-sm <?= in_array($msg, ['en_uso', 'error', 'no_encontrado'], true) ? 'bg-red-100 border-red-400 text-red-800' : 'bg-green-100 border-green-500 text-green-800' ?>">

        <?= htmlspecialchars($mensaje) ?>

    </p>

<?php endif; ?>

<form method="get" class="mb-4 flex gap-2">

    <input type="text"
           name="q"
           value="<?= htmlspecialchars($q) ?>"
           placeholder="Buscar por nombre..."
           class="flex-1 bg-durazno border border-moka rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cafe">

    <button class="bg-durazno rounded-xl px-4 text-sm">
        Buscar
    </button>

</form>

<div class="overflow-x-auto">

    <table class="w-full text-sm text-left">

        <thead>

            <tr class="border-b border-moka">

                <th class="py-2 pr-3">ID</th>
                <th class="py-2 pr-3">Vista previa</th>
                <th class="py-2 pr-3">Nombre</th>
                <th class="py-2 pr-3">Categoría</th>
                <th class="py-2 pr-3">Precio</th>
                <th class="py-2 pr-3">Destacado</th>
                <th class="py-2 pr-3">Estado</th>
                <th class="py-2">Acciones</th>

            </tr>

        </thead>

        <tbody>

        <?php if (count($resultado) > 0): ?>

            <?php foreach ($resultado as $row): ?>

                <tr class="border-b border-moka/20 align-middle">

                    <td class="py-2 pr-3">
                        <?= $row['id_producto'] ?>
                    </td>

                    <td class="py-2 pr-3">

                        <?php if (!empty($row['imagen_url']) && file_exists(__DIR__ . '/../' . $row['imagen_url'])): ?>

                            <img src="../<?= htmlspecialchars($row['imagen_url']) ?>"
                                 width="60"
                                 class="rounded-lg"
                                 alt="Vista previa">

                        <?php else: ?>

                            <div class="w-14 h-14 rounded-lg bg-durazno flex items-center justify-center">
                                ☕
                            </div>

                        <?php endif; ?>

                    </td>

                    <td class="py-2 pr-3">

                        <p class="font-semibold">
                            <?= htmlspecialchars($row['nombre']) ?>
                        </p>

                        <p class="text-xs max-w-xs truncate">
                            <?= htmlspecialchars($row['descripcion'] ?? '') ?>
                        </p>

                    </td>

                    <td class="py-2 pr-3">
                        <?= htmlspecialchars($row['categoria'] ?? '-') ?>
                    </td>

                    <td class="py-2 pr-3">
                        $<?= number_format($row['precio'], 2) ?>
                    </td>

                    <td class="py-2 pr-3">
                        <?= $row['destacado'] ? 'Sí' : 'No' ?>
                    </td>

                    <td class="py-2 pr-3">
                        <?= $row['disponible'] ? 'Disponible' : 'No disponible' ?>
                    </td>

                    <td class="py-2">

                        <div class="flex gap-2">

                            <a href="editar.php?id=<?= $row['id_producto'] ?>"
                               class="bg-durazno rounded-lg px-3 py-1">

                                Editar

                            </a>

                            <form method="post"
                                  action="eliminar.php"
                                  onsubmit="return confirm('¿Eliminar este producto?');">

                                <input type="hidden"
                                       name="id"
                                       value="<?= $row['id_producto'] ?>">

                                <input type="hidden"
                                       name="csrf_token"
                                       value="<?= htmlspecialchars($_SESSION['csrf_admin']) ?>">

                                <button class="bg-red-700 text-white rounded-lg px-3 py-1">
                                    Eliminar
                                </button>

                            </form>

                        </div>

                    </td>

                </tr>

            <?php endforeach; ?>

        <?php else: ?>

            <tr>

                <td colspan="8" class="py-6 text-center">
                    No hay productos registrados.
                </td>

            </tr>

        <?php endif; ?>

        </tbody>

    </table>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>