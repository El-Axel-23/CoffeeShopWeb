<?php

$base = $base ?? '';
$titulo = $titulo ?? 'Coffee Shop';

$logueado = isset($_SESSION['id_usuario']);
$esAdmin = $logueado && ($_SESSION['rol'] ?? '') === 'admin';

$inicialesNav = '';

// Obtener las iniciales del usuario
if ($logueado) {

    $palabras = explode(' ', trim($_SESSION['nombre_completo'] ?? ''));

    $inicialesNav = mb_strtoupper(mb_substr($palabras[0], 0, 1));

    if (count($palabras) > 1) {

        $inicialesNav .= mb_strtoupper(mb_substr($palabras[1], 0, 1));
    }

    // Token para cerrar sesión
    if (!isset($_SESSION['csrf_logout'])) {

        $_SESSION['csrf_logout'] = bin2hex(random_bytes(32));
    }
}

// Identificar la página actual
$paginaActual = basename($_SERVER['SCRIPT_NAME'] ?? '');

$claseInicio = '';
$claseUbicacion = '';
$claseAyuda = '';
$claseFavoritos = '';
$clasePedidos = '';

$claseActiva = 'text-cafe underline underline-offset-4';

if ($paginaActual === 'index.php' && $base === '') {

    $claseInicio = $claseActiva;

} elseif ($paginaActual === 'ubicacion.php') {

    $claseUbicacion = $claseActiva;

} elseif ($paginaActual === 'ayuda.php') {

    $claseAyuda = $claseActiva;

} elseif ($paginaActual === 'favoritos.php') {

    $claseFavoritos = $claseActiva;

} elseif ($paginaActual === 'mis_pedidos.php' ||
          $paginaActual === 'detalle_pedido.php') {

    $clasePedidos = $claseActiva;
}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= htmlspecialchars($titulo) ?></title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Iconos -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Colores de Coffee Shop -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        fondo: '#FAF5EC',
                        blanco: '#FFFFFF',
                        cafe: '#C3470D',
                        cafeHover: '#A83809',
                        crema: '#F2E6D5',
                        durazno: '#F9E5D6',
                        moka: '#482519',
                        borde: '#EBD8C2',
                        secundario: '#805542'
                    },
                    fontFamily: {
                        sans: ['Arial', 'Helvetica', 'sans-serif']
                    }
                }
            }
        }
    </script>

</head>

<body class="bg-fondo text-moka min-h-screen font-sans">

<div class="min-h-screen flex flex-col">

    <!-- Barra de navegación -->
    <nav class="bg-white border-b border-borde">

        <div class="max-w-6xl mx-auto px-4 md:px-6 py-4
                    flex flex-wrap items-center gap-6">

            <!-- Logotipo -->
            <a href="<?= $base ?>index.php"
               class="flex items-center gap-3 whitespace-nowrap">

                <i class="bi bi-cup-hot text-2xl"></i>

                <span class="text-sm">
                    COFFEE SHOP
                </span>

            </a>

            <!-- Enlaces de navegación -->
            <div class="flex flex-wrap items-center gap-5
                        text-xs font-semibold flex-1">

                <a href="<?= $base ?>index.php"
                   class="hover:text-cafe <?= $claseInicio ?>">

                    Inicio

                </a>

                <a href="<?= $base ?>index.php#menu"
                   class="hover:text-cafe">

                    Menú

                </a>

                <a href="<?= $base ?>Favoritos/favoritos.php"
                   class="hover:text-cafe <?= $claseFavoritos ?>">

                    Favoritos

                </a>

                <a href="<?= $base ?>Pedidos/mis_pedidos.php"
                   class="hover:text-cafe <?= $clasePedidos ?>">

                    Mis pedidos

                </a>

                <a href="<?= $base ?>ubicacion.php"
                   class="hover:text-cafe <?= $claseUbicacion ?>">

                    Ubicación

                </a>

                <a href="<?= $base ?>ayuda.php"
                   class="hover:text-cafe <?= $claseAyuda ?>">

                    Ayuda

                </a>

                <a href="#"
                   class="hover:text-cafe">

                    Carrito (0)

                </a>

            </div>

            <!-- Cuenta del usuario -->
            <div class="flex items-center gap-5">

                <!-- Notificaciones -->
                <span title="Notificaciones">

                    <i class="bi bi-bell text-lg"></i>

                </span>

                <?php if ($esAdmin): ?>

                    <a href="<?= $base ?>admin/index.php"
                       class="bg-cafe hover:bg-cafeHover text-white
                              rounded-lg px-3 py-2 text-xs font-semibold">

                        Administrar productos

                    </a>

                <?php endif; ?>

                <?php if ($logueado): ?>

                    <!-- Perfil del usuario -->
                    <a href="<?= $base ?>perfil.php"
                       title="Mi perfil"
                       class="w-9 h-9 rounded-full bg-crema
                              flex items-center justify-center
                              text-xs font-bold hover:bg-borde">

                        <?= htmlspecialchars($inicialesNav) ?>

                    </a>

                    <!-- Cerrar sesión -->
                    <form action="<?= $base ?>Login/logout.php"
                          method="POST">

                        <input type="hidden"
                               name="csrf_token"
                               value="<?= htmlspecialchars($_SESSION['csrf_logout']) ?>">

                        <button type="submit"
                                class="text-xs font-semibold hover:text-cafe">

                            Salir

                        </button>

                    </form>

                <?php else: ?>

                    <!-- Iniciar sesión -->
                    <a href="<?= $base ?>Login/login.php"
                       class="bg-cafe hover:bg-cafeHover text-white
                              rounded-lg px-4 py-2 text-xs font-semibold">

                        Iniciar sesión

                    </a>

                <?php endif; ?>

            </div>

        </div>

    </nav>

    <!-- Contenido principal -->
    <main class="w-full max-w-6xl mx-auto flex-1 px-4 md:px-6 py-8">