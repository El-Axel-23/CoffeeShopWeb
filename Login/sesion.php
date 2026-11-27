<?php

// Iniciar sesión si todavía no existe
if (session_status() === PHP_SESSION_NONE) {

    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) &&
                    $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

// Destruir la sesión
function finalizarSesion($rutaLogin, $motivo) {

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

    session_destroy();

    header('Location: ' . $rutaLogin . '?error=' . $motivo);

    exit;
}

// Comprobar que la sesión sea válida
function verificarSesion($rutaLogin, $rolRequerido = null) {

    header('Cache-Control: no-store');

    // Comprobar que el usuario haya iniciado sesión
    if (!isset($_SESSION['id_usuario'])) {

        finalizarSesion($rutaLogin, 'acceso_denegado');
    }

    // Tiempo máximo de inactividad: 30 minutos
    $tiempoMaximo = 1800;

    if (!isset($_SESSION['ultima_actividad']) ||
        time() - $_SESSION['ultima_actividad'] >= $tiempoMaximo) {

        finalizarSesion($rutaLogin, 'sesion_expirada');
    }

    // Conexión a la base de datos
    global $conexion;

    require_once __DIR__ . '/../config/conexion.php';

    $idUsuario = (int) $_SESSION['id_usuario'];

    try {

        // Comprobar que el usuario siga activo
        $sql = "SELECT id_usuario,
                       nombre_completo,
                       correo,
                       rol,
                       activo,
                       contrasena_hash
                FROM usuarios
                WHERE id_usuario = ?
                LIMIT 1";

        // Preparar la consulta
        $stmt = mysqli_prepare($conexion, $sql);

        if (!$stmt) {

            throw new RuntimeException(
                mysqli_error($conexion)
            );
        }

        // Asignar el ID del usuario y ejecutar
        if (!mysqli_stmt_bind_param($stmt, "i", $idUsuario) ||
            !mysqli_stmt_execute($stmt)) {

            $mensajeError = mysqli_stmt_error($stmt);

            mysqli_stmt_close($stmt);

            throw new RuntimeException($mensajeError);
        }

        // Obtener los resultados
        $resultado = mysqli_stmt_get_result($stmt);

        if (!$resultado) {

            $mensajeError = mysqli_stmt_error($stmt);

            mysqli_stmt_close($stmt);

            throw new RuntimeException($mensajeError);
        }

        // Obtener los datos del usuario
        $usuario = mysqli_fetch_assoc($resultado);

        mysqli_free_result($resultado);

        mysqli_stmt_close($stmt);

        // Comprobar que el usuario exista y siga activo
        if (!$usuario || (int) $usuario['activo'] !== 1) {

            finalizarSesion($rutaLogin, 'usuario_inactivo');
        }

        // Comprobar si cambió la contraseña
        $firmaActual = hash(
            'sha256',
            $usuario['contrasena_hash'] ?? ''
        );

        if (!isset($_SESSION['firma_contrasena']) ||
            !hash_equals(
                $_SESSION['firma_contrasena'],
                $firmaActual
            )) {

            finalizarSesion($rutaLogin, 'sesion_expirada');
        }

        // Actualizar los datos de la sesión
        $_SESSION['nombre_completo'] = $usuario['nombre_completo'];

        $_SESSION['correo'] = $usuario['correo'];

        $_SESSION['rol'] = $usuario['rol'];

        // Validar el rol del usuario
        if ($rolRequerido !== null &&
            $_SESSION['rol'] !== $rolRequerido) {

            http_response_code(403);

            exit('No tienes permiso para acceder a esta página.');
        }

        // Actualizar el tiempo de actividad
        $_SESSION['ultima_actividad'] = time();

    } catch (RuntimeException $e) {

        error_log(
            'Error de sesión: ' . $e->getMessage()
        );

        http_response_code(500);

        exit('No fue posible validar la sesión.');
    }
}