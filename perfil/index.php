<?php

// Validar la sesión del usuario
require_once __DIR__ . '/../Login/sesion.php';

verificarSesion('../Login/login.php');

header('Cache-Control: no-store');

// Conexión a la base de datos
require_once __DIR__ . '/../config/conexion.php';

// Obtener los datos del usuario
$sql = "SELECT nombre_completo, correo, telefono, fecha_registro
        FROM usuarios
        WHERE id_usuario = ? AND activo = 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['id_usuario']]);

$usuario = $stmt->fetch();

if (!$usuario) {
    header('Location: ../index.php');
    exit;
}

$base = '../';
$titulo = 'Mi perfil';

include __DIR__ . '/../includes/header.php';

?>

<div class="max-w-xl">

    <div class="flex items-center gap-4 mb-5">
        <a href="../perfil.php" class="text-2xl">←</a>
        <h1 class="text-xl font-bold">Mi perfil</h1>
    </div>

    <p class="text-sm mb-5">
        Consulta y actualiza tu información personal.
    </p>

    <div class="bg-durazno rounded-2xl p-4 mb-4">
        <p class="text-xs">Nombre completo</p>
        <p class="font-semibold">
            <?= htmlspecialchars($usuario['nombre_completo']) ?>
        </p>
    </div>

    <div class="bg-durazno rounded-2xl p-4 mb-4">
        <p class="text-xs">Correo electrónico</p>
        <p class="font-semibold">
            <?= htmlspecialchars($usuario['correo']) ?>
        </p>
    </div>

    <div class="bg-durazno rounded-2xl p-4 mb-4">
        <p class="text-xs">Teléfono</p>
        <p class="font-semibold">
            <?= htmlspecialchars($usuario['telefono'] ?: 'No registrado') ?>
        </p>
    </div>

    <div class="bg-durazno rounded-2xl p-4 mb-5">
        <p class="text-xs">Fecha de registro</p>
        <p class="font-semibold">
            <?= date('d/m/Y', strtotime($usuario['fecha_registro'])) ?>
        </p>
    </div>

    <a href="editar_perfil.php"
       class="block text-center bg-moka text-crema rounded-xl py-3 font-semibold">

        Editar perfil

    </a>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>