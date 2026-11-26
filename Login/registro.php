<?php

// Importar el control de sesiones
require_once __DIR__ . '/sesion.php';

header('Cache-Control: no-store');

// Comprobar si el usuario ya inició sesión
if (isset($_SESSION['id_usuario'])) {

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
if (!isset($_SESSION['csrf_registro'])) {

    $_SESSION['csrf_registro'] = bin2hex(random_bytes(32));
}

$error = '';
$nombre = '';
$correo = '';
$aceptoTerminos = 0;

// Recibir los datos del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nombre = $_POST['nombre_completo'] ?? '';
    $correo = $_POST['correo'] ?? '';
    $contrasena = $_POST['contrasena'] ?? '';
    $confirmarContrasena = $_POST['confirmar_contrasena'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    $aceptoTerminos = ($_POST['acepto_terminos'] ?? '') === '1' ? 1 : 0;

    // Validar token de seguridad
    if (!is_string($token) ||
        !hash_equals($_SESSION['csrf_registro'], $token)) {

        $error = 'Solicitud no válida. Intenta nuevamente.';

    } elseif (!is_string($nombre) ||
              !is_string($correo) ||
              !is_string($contrasena) ||
              !is_string($confirmarContrasena)) {

        $error = 'Los datos enviados no son válidos.';

    } else {

        $nombre = trim($nombre);
        $correo = trim($correo);

        // Validar campos obligatorios
        if ($nombre === '' ||
            $correo === '' ||
            trim($contrasena) === '' ||
            trim($confirmarContrasena) === '') {

            $error = 'Todos los campos son obligatorios.';

        } elseif (mb_strlen($nombre) > 120) {

            $error = 'El nombre supera la longitud permitida.';

        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL) ||
                  strlen($correo) > 120) {

            $error = 'Ingresa un correo electrónico válido.';

        // Validar longitud de la contraseña
        } elseif (mb_strlen($contrasena) < 8) {

            $error = 'La contraseña debe tener al menos 8 caracteres.';

        } elseif (strlen($contrasena) > 72 ||
                  strpos($contrasena, "\0") !== false) {

            $error = 'La contraseña es demasiado larga o contiene caracteres no válidos.';

        // Validar contraseña segura
        } elseif (!preg_match('/[A-Z]/', $contrasena) ||
                  !preg_match('/[a-z]/', $contrasena) ||
                  !preg_match('/[0-9]/', $contrasena) ||
                  !preg_match('/[^\p{L}\p{N}\s]/u', $contrasena)) {

            $error = 'La contraseña debe contener una mayúscula, una minúscula, un número y un símbolo.';

        // Comprobar que las contraseñas coincidan
        } elseif ($contrasena !== $confirmarContrasena) {

            $error = 'Las contraseñas no coinciden.';

        // Validar términos y condiciones
        } elseif ($aceptoTerminos !== 1) {

            $error = 'Debes aceptar los términos y condiciones.';

        } else {

            // Conexión a la base de datos
            require_once __DIR__ . '/../config/conexion.php';

            try {

                // Comprobar si el correo ya existe
                $sql = "SELECT id_usuario
                        FROM usuarios
                        WHERE correo = ?
                        LIMIT 1";

                $stmt = $pdo->prepare($sql);

                $stmt->execute([$correo]);

                $usuario = $stmt->fetch();

                if ($usuario) {

                    $error = 'Este correo electrónico ya está registrado.';

                } else {

                    // Proteger la contraseña
                    $contrasenaHash = password_hash(
                        $contrasena,
                        PASSWORD_DEFAULT
                    );

                    // Registrar al nuevo usuario
                    $sql = "INSERT INTO usuarios (
                                nombre_completo,
                                correo,
                                contrasena_hash,
                                proveedor,
                                acepto_terminos,
                                rol,
                                activo
                            ) VALUES (?, ?, ?, ?, ?, ?, ?)";

                    $stmt = $pdo->prepare($sql);

                    $stmt->execute([
                        $nombre,
                        $correo,
                        $contrasenaHash,
                        'local',
                        $aceptoTerminos,
                        'cliente',
                        1
                    ]);

                    // Obtener el ID del usuario registrado
                    $idUsuario = $pdo->lastInsertId();

                    // Regenerar identificador de sesión
                    session_regenerate_id(true);

                    // Guardar datos del nuevo usuario
                    $_SESSION['id_usuario'] = (int) $idUsuario;
                    $_SESSION['nombre_completo'] = $nombre;
                    $_SESSION['correo'] = $correo;
                    $_SESSION['rol'] = 'cliente';

                    // Registrar última actividad
                    $_SESSION['ultima_actividad'] = time();

                    // Guardar firma de la contraseña
                    $_SESSION['firma_contrasena'] = hash(
                        'sha256',
                        $contrasenaHash
                    );

                    // Eliminar los tokens utilizados
                    unset($_SESSION['csrf_registro']);
                    unset($_SESSION['csrf_token']);
                    unset($_SESSION['registro_exitoso']);

                    // Redirigir al inicio de Coffee Shop
                    header(
                        'Location: ../index.php',
                        true,
                        303
                    );

                    exit;
                }

            } catch (PDOException $e) {

                // Comprobar si el correo está duplicado
                if (($e->errorInfo[1] ?? 0) == 1062) {

                    $error = 'Este correo electrónico ya está registrado.';

                } else {

                    error_log(
                        'Error al registrar usuario: ' . $e->getMessage()
                    );

                    $error = 'No fue posible crear la cuenta. Intenta nuevamente.';
                }
            }
        }
    }
}

// Evitar valores incorrectos al mostrar el formulario
if (!is_string($nombre)) {

    $nombre = '';
}

if (!is_string($correo)) {

    $correo = '';
}

// Cargar la vista de registro
$html = file_get_contents(__DIR__ . '/registro.html');

// Mostrar u ocultar mensaje de error
$claseError = $error === '' ? 'd-none' : '';

// Conservar la aceptación de términos
$terminosMarcados = $aceptoTerminos === 1 ? 'checked' : '';

// Reemplazar los datos de la vista
$html = strtr($html, [

    '{{clase_error}}' => $claseError,

    '{{mensaje_error}}' => htmlspecialchars(
        $error,
        ENT_QUOTES,
        'UTF-8'
    ),

    '{{nombre_completo}}' => htmlspecialchars(
        $nombre,
        ENT_QUOTES,
        'UTF-8'
    ),

    '{{correo}}' => htmlspecialchars(
        $correo,
        ENT_QUOTES,
        'UTF-8'
    ),

    '{{csrf_token}}' => htmlspecialchars(
        $_SESSION['csrf_registro'],
        ENT_QUOTES,
        'UTF-8'
    ),

    '{{terminos_marcados}}' => $terminosMarcados

]);

// Mostrar la vista
echo $html;