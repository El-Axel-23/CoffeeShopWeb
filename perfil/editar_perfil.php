<?php

// Validar la sesión
require_once __DIR__ . '/../Login/sesion.php';

verificarSesion('../Login/login.php');

header('Cache-Control: no-store');

// Importar la conexión
require_once __DIR__ . '/../config/conexion.php';

// Obtener el ID del usuario
$idUsuario = (int) $_SESSION['id_usuario'];

// Consultar los datos actuales
$sql = "SELECT nombre_completo, correo, telefono, contrasena_hash
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

$usuario = mysqli_fetch_assoc($resultado);

mysqli_free_result($resultado);

mysqli_stmt_close($stmt);

// Comprobar que el usuario exista
if (!$usuario) {

    finalizarSesion('../Login/login.php', 'usuario_inactivo');

    exit;
}

// Generar token de seguridad
if (!isset($_SESSION['csrf_perfil'])) {

    $_SESSION['csrf_perfil'] = bin2hex(random_bytes(32));
}

// Obtener los datos actuales
$nombre = $usuario['nombre_completo'];
$correo = $usuario['correo'];
$telefono = $usuario['telefono'] ?? '';
$error = '';

$transaccionActiva = false;

// Recibir los datos del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';
    $nuevoNombre = $_POST['nombre_completo'] ?? '';
    $nuevoCorreo = $_POST['correo'] ?? '';
    $nuevoTelefono = $_POST['telefono'] ?? '';
    $contrasena = $_POST['contrasena_actual'] ?? '';

    // Validar token de seguridad
    if (!is_string($token) ||
        !hash_equals($_SESSION['csrf_perfil'], $token)) {

        $error = 'Solicitud no válida. Intenta nuevamente.';

    } elseif (!is_string($nuevoNombre) ||
              !is_string($nuevoCorreo) ||
              !is_string($nuevoTelefono) ||
              !is_string($contrasena)) {

        $error = 'Los datos enviados no son válidos.';

    } else {

        $nombre = trim($nuevoNombre);
        $correo = trim($nuevoCorreo);
        $telefono = trim($nuevoTelefono);

        // Comprobar si cambió el correo
        $cambioCorreo = strcasecmp(
            $correo,
            $usuario['correo']
        ) !== 0;

        // Validar nombre
        if ($nombre === '' || mb_strlen($nombre) > 120) {

            $error = 'Ingresa un nombre completo válido.';

        // Validar correo
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL) ||
                  strlen($correo) > 120) {

            $error = 'Ingresa un correo electrónico válido.';

        // Validar teléfono
        } elseif ($telefono !== '' &&
                  !preg_match('/^\+?[0-9]{10,14}$/', $telefono)) {

            $error = 'Ingresa un número de teléfono válido, sin espacios.';

        // Comprobar contraseña si cambia el correo
        } elseif ($cambioCorreo &&
                  (!is_string($usuario['contrasena_hash']) ||
                   !password_verify(
                       $contrasena,
                       $usuario['contrasena_hash']
                   ))) {

            $error = 'La contraseña actual es incorrecta o no fue ingresada.';

        } else {

            try {

                // Comprobar que el correo no pertenezca a otro usuario
                $sql = "SELECT id_usuario FROM usuarios
                        WHERE correo = ? AND id_usuario != ?";

                $stmt = mysqli_prepare($conexion, $sql);

                if (!$stmt) {

                    throw new RuntimeException(
                        mysqli_error($conexion)
                    );
                }

                // Asignar los parámetros
                if (!mysqli_stmt_bind_param(
                        $stmt,
                        "si",
                        $correo,
                        $idUsuario
                    ) ||
                    !mysqli_stmt_execute($stmt) ||
                    !mysqli_stmt_store_result($stmt)) {

                    $mensajeError = mysqli_stmt_error($stmt);

                    mysqli_stmt_close($stmt);

                    throw new RuntimeException($mensajeError);
                }

                // Comprobar si el correo ya existe
                $correoExiste = mysqli_stmt_num_rows($stmt) > 0;

                mysqli_stmt_close($stmt);

                if ($correoExiste) {

                    $error = 'Este correo ya está registrado por otro usuario.';

                } else {

                    // Iniciar transacción
                    if (!mysqli_begin_transaction($conexion)) {

                        throw new RuntimeException(
                            mysqli_error($conexion)
                        );
                    }

                    $transaccionActiva = true;

                    // Guardar NULL si no se ingresó un teléfono
                    $telefonoBD = $telefono === ''
                        ? null : $telefono;

                    // Actualizar los datos del usuario
                    $sql = "UPDATE usuarios
                            SET nombre_completo = ?,
                                correo = ?,
                                telefono = ?
                            WHERE id_usuario = ? AND activo = 1";

                    $stmt = mysqli_prepare($conexion, $sql);

                    if (!$stmt) {

                        throw new RuntimeException(
                            mysqli_error($conexion)
                        );
                    }

                    // Asignar los datos del usuario
                    if (!mysqli_stmt_bind_param(
                            $stmt,
                            "sssi",
                            $nombre,
                            $correo,
                            $telefonoBD,
                            $idUsuario
                        ) ||
                        !mysqli_stmt_execute($stmt)) {

                        $codigoError = mysqli_stmt_errno($stmt);

                        $mensajeError = mysqli_stmt_error($stmt);

                        mysqli_stmt_close($stmt);

                        throw new RuntimeException(
                            $mensajeError,
                            $codigoError
                        );
                    }

                    mysqli_stmt_close($stmt);

                    // Invalidar enlaces de recuperación anteriores
                    if ($cambioCorreo) {

                        $sql = "UPDATE recuperacion_contrasena
                                SET usado = 1
                                WHERE id_usuario = ?";

                        $stmt = mysqli_prepare($conexion, $sql);

                        if (!$stmt) {

                            throw new RuntimeException(
                                mysqli_error($conexion)
                            );
                        }

                        if (!mysqli_stmt_bind_param(
                                $stmt,
                                "i",
                                $idUsuario
                            ) ||
                            !mysqli_stmt_execute($stmt)) {

                            $mensajeError = mysqli_stmt_error($stmt);

                            mysqli_stmt_close($stmt);

                            throw new RuntimeException($mensajeError);
                        }

                        mysqli_stmt_close($stmt);
                    }

                    // Confirmar los cambios
                    if (!mysqli_commit($conexion)) {

                        throw new RuntimeException(
                            mysqli_error($conexion)
                        );
                    }

                    $transaccionActiva = false;

                    // Actualizar los datos de la sesión
                    $_SESSION['nombre_completo'] = $nombre;

                    $_SESSION['correo'] = $correo;

                    // Eliminar el token utilizado
                    unset($_SESSION['csrf_perfil']);

                    // Regresar a la vista del perfil
                    header('Location: index.php', true, 303);

                    exit;
                }

            } catch (RuntimeException $e) {

                // Cancelar los cambios si ocurre un error
                if ($transaccionActiva) {

                    mysqli_rollback($conexion);
                }

                // Comprobar si el correo está duplicado
                if ($e->getCode() === 1062) {

                    $error = 'Este correo ya está registrado por otro usuario.';

                } else {

                    error_log(
                        'Error al editar perfil: ' .
                        $e->getMessage()
                    );

                    $error = 'No fue posible actualizar el perfil.';
                }
            }
        }
    }
}

// Conservar el correo original si ocurrió un error
if ($error !== '') {

    $correo = $usuario['correo'];
}

// Cargar la vista HTML
$html = file_get_contents(__DIR__ . '/editar_perfil.html');

if ($html === false || trim($html) === '') {

    http_response_code(500);

    exit('No fue posible cargar la vista de editar perfil.');
}

// Mostrar u ocultar el mensaje de error
$claseError = $error === '' ? 'd-none' : '';

// Enviar los datos a la vista
$html = strtr($html, [

    '{{nombre_completo}}' => htmlspecialchars(
        $nombre, ENT_QUOTES, 'UTF-8'
    ),

    '{{correo}}' => htmlspecialchars(
        $correo, ENT_QUOTES, 'UTF-8'
    ),

    '{{telefono}}' => htmlspecialchars(
        $telefono, ENT_QUOTES, 'UTF-8'
    ),

    '{{csrf_token}}' => htmlspecialchars(
        $_SESSION['csrf_perfil'], ENT_QUOTES, 'UTF-8'
    ),

    '{{clase_error}}' => $claseError,

    '{{mensaje_error}}' => htmlspecialchars(
        $error, ENT_QUOTES, 'UTF-8'
    )

]);

// Mostrar la vista
echo $html;