<?php

require_once __DIR__ . '/Login/sesion.php';

// Iniciar sesión
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Validar la sesión si el usuario inició sesión
if (isset($_SESSION['id_usuario'])) {
    verificarSesion('Login/login.php');
}

require_once __DIR__ . '/config/conexion.php';

// Evitar que se guarden resultados en caché
header('Cache-Control: no-store');
header('Content-Type: text/html; charset=utf-8');

// Obtener el texto de búsqueda
$q = isset($_GET['q']) && is_string($_GET['q'])
    ? trim($_GET['q'])
    : '';

// Obtener la categoría seleccionada
$cat = filter_input(INPUT_GET, 'cat', FILTER_VALIDATE_INT);

$cat = ($cat > 0) ? $cat : 0;

// Mostrar los productos encontrados
include __DIR__ . '/includes/catalogo.php';