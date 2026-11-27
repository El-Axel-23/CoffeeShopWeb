<?php

session_start();

// Evitar guardar la página en caché
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

// Generar token de seguridad
if (!isset($_SESSION['csrf_restablecer'])) {

    $_SESSION['csrf_restablecer'] = bin2hex(random_bytes(32));
}

$error = '';
$exito = '';
$token = '';
$mostrarFormulario = false;
$mostrarSolicitar = false;

$transaccionActiva = false;

// Recibir el token de recuperación
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $token = $_GET['token'] ?? '';

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['token'] ?? '';

} else {

    http_response_code(405);

    exit('Método no permitido.');
}

// Validar formato del token
if (!is_string($token) ||
    !preg_match('/^[a-f0-9]{64}$/D', $token)) {

    $token = '';

    $error = 'El enlace de recuperación no es válido.';

    $mostrarSolicitar = true;

} else {

    // Importar la conexión
    require_once __DIR__ . '/../config/conexion.php';

    // Obtener el hash del token recibido
    $tokenHash = hash('sha256', $token);

    try {

        // Buscar el token y comprobar su vigencia
        $sql = "SELECT r.id, r.id_usuario
                FROM recuperacion_contrasena r
                INNER JOIN usuarios u
                    ON r.id_usuario = u.id_usuario
                WHERE r.token = ?
                AND r.usado = 0
                AND r.expira > NOW()
                AND u.activo = 1
                AND u.proveedor = 'local'
                LIMIT 1";

        $stmt = mysqli_prepare($conexion, $sql);

        if (!$stmt) {

            throw new RuntimeException(
                mysqli_error($conexion)
            );
        }

        // Asignar el token y ejecutar la consulta
        if (!mysqli_stmt_bind_param($stmt, "s", $tokenHash) ||
            !mysqli_stmt_execute($stmt)) {

            $mensajeError = mysqli_stmt_error($stmt);

            mysqli_stmt_close($stmt);

            throw new RuntimeException($mensajeError);
        }

        // Obtener los datos de recuperación
        $resultado = mysqli_stmt_get_result($stmt);

        if (!$resultado) {

            $mensajeError = mysqli_stmt_error($stmt);

            mysqli_stmt_close($stmt);

            throw new RuntimeException($mensajeError);
        }

        $recuperacion = mysqli_fetch_assoc($resultado);

        mysqli_free_result($resultado);

        mysqli_stmt_close($stmt);

        // Comprobar si el enlace es válido
        if (!$recuperacion) {

            $error = 'El enlace ha expirado, ya fue utilizado o no es válido.';

            $mostrarSolicitar = true;

        } else {

            $mostrarFormulario = true;

            $idRecuperacion = (int) $recuperacion['id'];

            $idUsuario = (int) $recuperacion['id_usuario'];

            // Recibir las nuevas contraseñas
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {

                $contrasena = $_POST['contrasena'] ?? '';

                $confirmar = $_POST['confirmar_contrasena'] ?? '';

                $csrf = $_POST['csrf_token'] ?? '';

                // Validar token CSRF
                if (!is_string($csrf) ||
                    !hash_equals(
                        $_SESSION['csrf_restablecer'],
                        $csrf
                    )) {

                    $error = 'Solicitud no válida. Intenta nuevamente.';

                } elseif (!is_string($contrasena) ||
                          !is_string($confirmar)) {

                    $error = 'Los datos enviados no son válidos.';

                } elseif (trim($contrasena) === '' ||
                          trim($confirmar) === '') {

                    $error = 'Todos los campos son obligatorios.';

                } elseif (!mb_check_encoding($contrasena, 'UTF-8') ||
                          strpos($contrasena, "\0") !== false ||
                          strlen($contrasena) > 72) {

                    $error = 'La contraseña contiene caracteres no válidos o es demasiado larga.';

                } elseif (mb_strlen($contrasena, 'UTF-8') < 8) {

                    $error = 'La contraseña debe contener al menos 8 caracteres.';

                } elseif (!preg_match('/\p{Lu}/u', $contrasena)) {

                    $error = 'La contraseña debe contener una letra mayúscula.';

                } elseif (!preg_match('/\p{Ll}/u', $contrasena)) {

                    $error = 'La contraseña debe contener una letra minúscula.';

                } elseif (!preg_match('/[0-9]/', $contrasena)) {

                    $error = 'La contraseña debe contener un número.';

                } elseif (!preg_match('/[\p{P}\p{S}]/u', $contrasena)) {

                    $error = 'La contraseña debe contener un carácter especial.';

                } elseif ($contrasena !== $confirmar) {

                    $error = 'Las contraseñas no coinciden.';

                } else {

                    // Generar hash de la nueva contraseña
                    $contrasenaHash = password_hash(
                        $contrasena,
                        PASSWORD_DEFAULT
                    );

                    // Iniciar transacción
                    if (!mysqli_begin_transaction($conexion)) {

                        throw new RuntimeException(
                            mysqli_error($conexion)
                        );
                    }

                    $transaccionActiva = true;

                    // Comprobar y bloquear al usuario
                    $sqlUsuario = "SELECT id_usuario
                                   FROM usuarios
                                   WHERE id_usuario = ?
                                   AND activo = 1
                                   AND proveedor = 'local'
                                   FOR UPDATE";

                    $stmtUsuario = mysqli_prepare(
                        $conexion,
                        $sqlUsuario
                    );

                    if (!$stmtUsuario) {

                        throw new RuntimeException(
                            mysqli_error($conexion)
                        );
                    }

                    // Asignar el ID del usuario
                    if (!mysqli_stmt_bind_param(
                            $stmtUsuario,
                            "i",
                            $idUsuario
                        ) ||
                        !mysqli_stmt_execute($stmtUsuario) ||
                        !mysqli_stmt_store_result($stmtUsuario)) {

                        $mensajeError = mysqli_stmt_error($stmtUsuario);

                        mysqli_stmt_close($stmtUsuario);

                        throw new RuntimeException($mensajeError);
                    }

                    // Comprobar si el usuario existe
                    $usuario = mysqli_stmt_num_rows($stmtUsuario) > 0;

                    mysqli_stmt_close($stmtUsuario);

                    // Verificar nuevamente el token
                    $sqlToken = "SELECT id
                                 FROM recuperacion_contrasena
                                 WHERE id = ?
                                 AND id_usuario = ?
                                 AND token = ?
                                 AND usado = 0
                                 AND expira > NOW()
                                 FOR UPDATE";

                    $stmtToken = mysqli_prepare(
                        $conexion,
                        $sqlToken
                    );

                    if (!$stmtToken) {

                        throw new RuntimeException(
                            mysqli_error($conexion)
                        );
                    }

                    // Asignar los parámetros del token
                    if (!mysqli_stmt_bind_param(
                            $stmtToken,
                            "iis",
                            $idRecuperacion,
                            $idUsuario,
                            $tokenHash
                        ) ||
                        !mysqli_stmt_execute($stmtToken) ||
                        !mysqli_stmt_store_result($stmtToken)) {

                        $mensajeError = mysqli_stmt_error($stmtToken);

                        mysqli_stmt_close($stmtToken);

                        throw new RuntimeException($mensajeError);
                    }

                    // Comprobar si el token sigue siendo válido
                    $tokenValido = mysqli_stmt_num_rows($stmtToken) > 0;

                    mysqli_stmt_close($stmtToken);

                    if (!$usuario || !$tokenValido) {

                        // Cancelar la transacción
                        if (!mysqli_rollback($conexion)) {

                            throw new RuntimeException(
                                mysqli_error($conexion)
                            );
                        }

                        $transaccionActiva = false;

                        $error = 'El enlace de recuperación ya no es válido.';

                        $mostrarFormulario = false;

                        $mostrarSolicitar = true;

                    } else {

                        // Actualizar contraseña del usuario
                        $sqlActualizar = "UPDATE usuarios
                                          SET contrasena_hash = ?
                                          WHERE id_usuario = ?";

                        $stmtActualizar = mysqli_prepare(
                            $conexion,
                            $sqlActualizar
                        );

                        if (!$stmtActualizar) {

                            throw new RuntimeException(
                                mysqli_error($conexion)
                            );
                        }

                        // Asignar la contraseña y el ID
                        if (!mysqli_stmt_bind_param(
                                $stmtActualizar,
                                "si",
                                $contrasenaHash,
                                $idUsuario
                            ) ||
                            !mysqli_stmt_execute($stmtActualizar)) {

                            $mensajeError = mysqli_stmt_error(
                                $stmtActualizar
                            );

                            mysqli_stmt_close($stmtActualizar);

                            throw new RuntimeException($mensajeError);
                        }

                        mysqli_stmt_close($stmtActualizar);

                        // Invalidar los enlaces de recuperación
                        $sqlInvalidar = "UPDATE recuperacion_contrasena
                                         SET usado = 1
                                         WHERE id_usuario = ?
                                         AND usado = 0";

                        $stmtInvalidar = mysqli_prepare(
                            $conexion,
                            $sqlInvalidar
                        );

                        if (!$stmtInvalidar) {

                            throw new RuntimeException(
                                mysqli_error($conexion)
                            );
                        }

                        // Ejecutar la consulta
                        if (!mysqli_stmt_bind_param(
                                $stmtInvalidar,
                                "i",
                                $idUsuario
                            ) ||
                            !mysqli_stmt_execute($stmtInvalidar)) {

                            $mensajeError = mysqli_stmt_error(
                                $stmtInvalidar
                            );

                            mysqli_stmt_close($stmtInvalidar);

                            throw new RuntimeException($mensajeError);
                        }

                        mysqli_stmt_close($stmtInvalidar);

                        // Confirmar los cambios
                        if (!mysqli_commit($conexion)) {

                            throw new RuntimeException(
                                mysqli_error($conexion)
                            );
                        }

                        $transaccionActiva = false;

                        // Renovar token de seguridad
                        $_SESSION['csrf_restablecer'] =
                            bin2hex(random_bytes(32));

                        $token = '';

                        $mostrarFormulario = false;

                        $exito = 'Tu contraseña se actualizó correctamente. Ya puedes iniciar sesión.';
                    }
                }
            }
        }

    } catch (RuntimeException $e) {

        // Cancelar cambios si ocurre un error
        if ($transaccionActiva) {

            mysqli_rollback($conexion);
        }

        error_log(
            'Error al restablecer contraseña: '
            . $e->getMessage()
        );

        $error = 'No fue posible actualizar la contraseña. Intenta nuevamente.';
    }
}

// Cargar la vista HTML
$html = file_get_contents(
    __DIR__ . '/restablecer.html'
);

if ($html === false || trim($html) === '') {

    http_response_code(500);

    exit('No fue posible cargar la vista para restablecer la contraseña.');
}

// Mostrar u ocultar mensajes
$claseError = 'd-none';
$claseExito = 'd-none';
$claseFormulario = 'd-none';
$claseSolicitar = 'd-none';

if ($error !== '') {

    $claseError = '';
}

if ($exito !== '') {

    $claseExito = '';
}

if ($mostrarFormulario) {

    $claseFormulario = '';
}

if ($mostrarSolicitar) {

    $claseSolicitar = '';
}

// Enviar información a la vista
$html = strtr($html, [

    '{{csrf_token}}' => htmlspecialchars(
        $_SESSION['csrf_restablecer'],
        ENT_QUOTES,
        'UTF-8'
    ),

    '{{token}}' => htmlspecialchars(
        $token,
        ENT_QUOTES,
        'UTF-8'
    ),

    '{{clase_error}}' => $claseError,

    '{{mensaje_error}}' => htmlspecialchars(
        $error,
        ENT_QUOTES,
        'UTF-8'
    ),

    '{{clase_exito}}' => $claseExito,

    '{{mensaje_exito}}' => htmlspecialchars(
        $exito,
        ENT_QUOTES,
        'UTF-8'
    ),

    '{{clase_formulario}}' => $claseFormulario,

    '{{clase_solicitar}}' => $claseSolicitar

]);

// Mostrar la vista
echo $html;