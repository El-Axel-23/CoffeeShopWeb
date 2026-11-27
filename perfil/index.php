<?php

// Validar la sesión del usuario
require_once __DIR__ . '/../Login/sesion.php';

verificarSesion('../Login/login.php');

header('Cache-Control: no-store');

// Conexión a la base de datos
require_once __DIR__ . '/../config/conexion.php';

// Obtener el ID del usuario
$idUsuario = (int) $_SESSION['id_usuario'];

// Obtener los datos del usuario
$sql = "SELECT nombre_completo, correo, telefono, fecha_registro
        FROM usuarios
        WHERE id_usuario = ? AND activo = 1";

// Preparar la consulta
$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {

    error_log('Error al preparar perfil: ' . mysqli_error($conexion));

    http_response_code(500);
    exit('No fue posible consultar el perfil.');
}

// Asignar el ID del usuario y ejecutar
if (!mysqli_stmt_bind_param($stmt, "i", $idUsuario) ||
    !mysqli_stmt_execute($stmt)) {

    error_log('Error al consultar perfil: ' . mysqli_stmt_error($stmt));

    mysqli_stmt_close($stmt);

    http_response_code(500);
    exit('No fue posible consultar el perfil.');
}

// Obtener los resultados
$resultado = mysqli_stmt_get_result($stmt);

if (!$resultado) {

    error_log('Error al obtener perfil: ' . mysqli_stmt_error($stmt));

    mysqli_stmt_close($stmt);

    http_response_code(500);
    exit('No fue posible obtener el perfil.');
}

// Obtener los datos del usuario
$usuario = mysqli_fetch_assoc($resultado);

// Liberar los resultados y cerrar la consulta
mysqli_free_result($resultado);

mysqli_stmt_close($stmt);

// Comprobar que el usuario exista
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