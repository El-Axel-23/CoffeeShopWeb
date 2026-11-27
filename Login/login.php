<?php

// Configuración de la sesión
ini_set('session.use_strict_mode', '1');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

// Evitar guardar la página en caché
header('Cache-Control: no-store');

// Comprobar si el usuario ya inició sesión
if (isset($_SESSION['id_usuario'])) {

    require_once __DIR__ . '/sesion.php';

    // Comprobar que la sesión siga siendo válida
    verificarSesion('login.php');

    // Redirigir según el rol del usuario
    if ($_SESSION['rol'] === 'admin') {

        header('Location: ../perfil.php');

    } else {

        header('Location: ../index.php');
    }

    exit;
}

// Generar token de seguridad
if (!isset($_SESSION['csrf_token'])) {

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$exito = '';

// Comprobar si la cuenta se registró correctamente
if (isset($_SESSION['registro_exitoso']) &&
    $_SESSION['registro_exitoso'] === true) {

    $exito = 'Tu cuenta se ha creado correctamente. Ahora puedes iniciar sesión.';

    // Eliminar el mensaje para mostrarlo una sola vez
    unset($_SESSION['registro_exitoso']);
}

// Comprobar mensajes relacionados con la sesión
if (isset($_GET['error'])) {

    if ($_GET['error'] === 'acceso_denegado') {

        $error = 'Debes iniciar sesión para continuar.';

    } elseif ($_GET['error'] === 'sesion_expirada') {

        $error = 'Tu sesión ha expirado. Inicia sesión nuevamente.';

    } elseif ($_GET['error'] === 'usuario_inactivo') {

        $error = 'Tu cuenta no se encuentra activa.';

    } elseif ($_GET['error'] === 'carrito') {

        $error = 'Inicia sesión o crea una cuenta para agregar productos a tu carrito.';
    }
}

// Recibir los datos del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $correo = $_POST['correo'] ?? '';
    $contrasena = $_POST['contrasena'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    // Validar token de seguridad
    if (!is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)) {

        $error = 'Solicitud no válida. Intenta nuevamente.';

    } elseif (!is_string($correo) || !is_string($contrasena)) {

        $error = 'Los datos enviados no son válidos.';

    } else {

        $correo = trim($correo);

        // Validar campos
        if ($correo === '' || trim($contrasena) === '') {

            $error = 'Todos los campos son obligatorios.';

        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)
                   || strlen($correo) > 120) {

            $error = 'Ingresa un correo electrónico válido.';

        } else {

            // Conexión a la base de datos
            require_once __DIR__ . '/../config/conexion.php';

            try {

                // Buscar al usuario
                $sql = "SELECT id_usuario,
                               nombre_completo,
                               correo,
                               contrasena_hash,
                               rol,
                               activo,
                               proveedor
                        FROM usuarios
                        WHERE correo = ?
                        LIMIT 1";

                // Preparar la consulta
                $stmt = mysqli_prepare($conexion, $sql);

                if (!$stmt) {

                    throw new RuntimeException(
                        mysqli_error($conexion)
                    );
                }

                // Asignar el correo
                if (!mysqli_stmt_bind_param($stmt, "s", $correo) ||
                    !mysqli_stmt_execute($stmt)) {

                    $mensajeError = mysqli_stmt_error($stmt);

                    mysqli_stmt_close($stmt);

                    throw new RuntimeException($mensajeError);
                }

                // Obtener el resultado
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

                // Validar usuario y contraseña
                if ($usuario &&
                    (int) $usuario['activo'] === 1 &&
                    $usuario['proveedor'] === 'local' &&
                    is_string($usuario['contrasena_hash']) &&
                    password_verify(
                        $contrasena,
                        $usuario['contrasena_hash']
                    )) {

                    // Regenerar identificador de sesión
                    if (!session_regenerate_id(true)) {

                        throw new RuntimeException(
                            'No fue posible regenerar la sesión.'
                        );
                    }

                    // Guardar datos del usuario
                    $_SESSION['id_usuario'] = (int) $usuario['id_usuario'];

                    $_SESSION['nombre_completo'] = $usuario['nombre_completo'];

                    $_SESSION['correo'] = $usuario['correo'];

                    $_SESSION['rol'] = $usuario['rol'];

                    // Registrar última actividad
                    $_SESSION['ultima_actividad'] = time();

                    // Guardar firma para validar cambios de contraseña
                    $_SESSION['firma_contrasena'] = hash(
                        'sha256',
                        $usuario['contrasena_hash']
                    );

                    // Eliminar token utilizado
                    unset($_SESSION['csrf_token']);

                    // Redirigir según el rol del usuario
                    if ($_SESSION['rol'] === 'admin') {

                        header(
                            'Location: ../perfil.php',
                            true,
                            303
                        );

                    } else {

                        header(
                            'Location: ../index.php',
                            true,
                            303
                        );
                    }

                    exit;

                } else {

                    $error = 'Correo o contraseña incorrectos.';
                }

            } catch (RuntimeException $e) {

                error_log(
                    'Error en login: ' . $e->getMessage()
                );

                $error = 'No fue posible iniciar sesión. Intenta nuevamente.';
            }
        }
    }
}

// Cargar la vista HTML
$html = file_get_contents(__DIR__ . '/login.html');

if ($html === false || trim($html) === '') {

    http_response_code(500);

    exit('No fue posible cargar la vista del inicio de sesión.');
}

// Mostrar u ocultar mensaje de error
$claseError = 'd-none';

if ($error !== '') {

    $claseError = '';
}

// Mostrar u ocultar mensaje de éxito
$claseExito = 'd-none';

if ($exito !== '' && $error === '') {

    $claseExito = '';
}

// Enviar información a la vista
$html = str_replace(
    '{{csrf_token}}',
    htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'),
    $html
);

$html = str_replace(
    '{{clase_error}}',
    $claseError,
    $html
);

$html = str_replace(
    '{{mensaje_error}}',
    htmlspecialchars($error, ENT_QUOTES, 'UTF-8'),
    $html
);

$html = str_replace(
    '{{clase_exito}}',
    $claseExito,
    $html
);

$html = str_replace(
    '{{mensaje_exito}}',
    htmlspecialchars($exito, ENT_QUOTES, 'UTF-8'),
    $html
);

// Mostrar la vista
echo $html;