<?php

session_start();

// Comprobar si existe una sesión
if (!isset($_SESSION['id_usuario'])) {

    header('Location: login.php');
    exit;
}

// Validar el método de envío
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);
    exit('Método no permitido.');
}

// Validar el token de seguridad
$token = $_POST['csrf_token'] ?? '';

if (!isset($_SESSION['csrf_logout']) ||
    !is_string($token) ||
    !hash_equals($_SESSION['csrf_logout'], $token)) {

    http_response_code(403);
    exit('Solicitud no válida.');
}

// Eliminar las variables de sesión
$_SESSION = array();

// Eliminar la cookie de sesión
if (ini_get('session.use_cookies')) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destruir la sesión
session_destroy();

// Redirigir al inicio de sesión
header('Location: login.php', true, 303);
exit;