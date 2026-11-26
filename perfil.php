<?php

// Importar el control de sesiones
require_once __DIR__ . '/Login/sesion.php';

// Comprobar que la sesión sea válida
verificarSesion('Login/login.php');

header('Cache-Control: no-store');

require_once __DIR__ . '/config/conexion.php';

// Buscar los datos del usuario
$sql = "SELECT nombre_completo, correo
        FROM usuarios
        WHERE id_usuario = ?
        AND activo = 1";

$stmt = $pdo->prepare($sql);

$stmt->execute([$_SESSION['id_usuario']]);

$usuario = $stmt->fetch();

// Comprobar que la cuenta siga activa
if (!$usuario) {

    finalizarSesion('Login/login.php', 'usuario_inactivo');
    exit;
}

// Obtener las iniciales del usuario
$palabras = explode(' ', trim($usuario['nombre_completo']));

$iniciales = mb_strtoupper(mb_substr($palabras[0], 0, 1));

if (count($palabras) > 1) {

    $iniciales .= mb_strtoupper(mb_substr($palabras[1], 0, 1));
}

// Opciones del menú
$opciones = [

    ['bi-person', 'Ver mi perfil', 'perfil/index.php'],

    ['bi-receipt', 'Mis pedidos', 'Pedidos/mis_pedidos.php'],

    ['bi-heart', 'Favoritos', 'Favoritos/favoritos.php'],

    ['bi-geo-alt', 'Ubicación', 'ubicacion.php'],

    ['bi-gear', 'Configuración', '#'],

    ['bi-question-circle', 'Ayuda', 'ayuda.php']

];

// Opciones del administrador
if (($_SESSION['rol'] ?? '') === 'admin') {

    $opciones[] = [
        'bi-box-seam',
        'Administrar productos',
        'admin/index.php'
    ];
}

// Opción para cerrar sesión
$opciones[] = [
    'bi-box-arrow-right',
    'Cerrar sesión',
    'Login/logout.php'
];

$titulo = 'Mi perfil';

include __DIR__ . '/includes/header.php';

?>

<div class="max-w-xl mx-auto">

    <!-- Encabezado -->
    <div class="flex items-center gap-4 mb-6">

        <a href="index.php"
           class="w-10 h-10 flex items-center justify-center
                  rounded-full bg-crema hover:bg-borde"
           aria-label="Regresar">

            <i class="bi bi-arrow-left text-xl"></i>

        </a>

        <h1 class="text-2xl font-bold">

            Mi perfil

        </h1>

    </div>

    <!-- Información del usuario -->
    <div class="bg-white border border-borde
                rounded-2xl p-5 flex items-center gap-4">

        <div class="w-20 h-20 rounded-full bg-crema
                    flex items-center justify-center
                    text-2xl font-bold shrink-0">

            <?= htmlspecialchars($iniciales) ?>

        </div>

        <div class="min-w-0">

            <p class="text-lg font-bold truncate">

                <?= htmlspecialchars($usuario['nombre_completo']) ?>

            </p>

            <p class="text-sm text-secundario truncate mt-1">

                <?= htmlspecialchars($usuario['correo']) ?>

            </p>

        </div>

    </div>

    <p class="text-sm text-secundario mt-5 mb-4">

        Tu espacio, tu pausa.

    </p>

    <!-- Menú de opciones -->
    <nav class="bg-white border border-borde
                rounded-2xl overflow-hidden">

        <?php foreach ($opciones as $op): ?>

            <?php if ($op[2] === 'Login/logout.php'): ?>

                <!-- Cerrar sesión -->
                <form method="POST" action="Login/logout.php">

                    <input type="hidden"
                           name="csrf_token"
                           value="<?= htmlspecialchars($_SESSION['csrf_logout']) ?>">

                    <button type="submit"
                            class="w-full flex items-center gap-4
                                   py-5 px-5 text-left text-cafe
                                   hover:bg-crema transition-colors">

                        <i class="bi <?= $op[0] ?> text-xl w-6"></i>

                        <span class="flex-1 text-sm font-semibold">

                            <?= htmlspecialchars($op[1]) ?>

                        </span>

                        <i class="bi bi-chevron-right text-xs"></i>

                    </button>

                </form>

            <?php else: ?>

                <!-- Opciones del perfil -->
                <a href="<?= htmlspecialchars($op[2]) ?>"
                   class="flex items-center gap-4 py-5 px-5
                          border-b border-borde
                          hover:bg-crema transition-colors">

                    <i class="bi <?= $op[0] ?> text-xl w-6"></i>

                    <span class="flex-1 text-sm font-semibold">

                        <?= htmlspecialchars($op[1]) ?>

                    </span>

                    <i class="bi bi-chevron-right
                              text-xs text-secundario"></i>

                </a>

            <?php endif; ?>

        <?php endforeach; ?>

    </nav>

    <p class="text-xs text-secundario text-center mt-6">

        Coffee Shop · Un momento delicioso en cada sorbo

    </p>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>