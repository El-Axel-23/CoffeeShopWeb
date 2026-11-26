<?php

session_start();

// Evitar guardar la página en caché
header('Cache-Control: no-store');

// Generar token de seguridad
if (!isset($_SESSION['csrf_recuperar'])) {

    $_SESSION['csrf_recuperar'] = bin2hex(random_bytes(32));
}

$error = '';
$exito = '';

// Recibir los datos del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $correo = $_POST['correo'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    // Validar token de seguridad
    if (!is_string($token) ||
        !hash_equals($_SESSION['csrf_recuperar'], $token)) {

        $error = 'Solicitud no válida. Intenta nuevamente.';

    } elseif (!is_string($correo)) {

        $error = 'El correo electrónico no es válido.';

    } else {

        $correo = trim($correo);

        // Validar correo electrónico
        if ($correo === '') {

            $error = 'Ingresa tu correo electrónico.';

        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)
                   || strlen($correo) > 120) {

            $error = 'Ingresa un correo electrónico válido.';

        } elseif (isset($_SESSION['ultima_recuperacion']) &&
                  time() - $_SESSION['ultima_recuperacion'] < 60) {

            $error = 'Espera un minuto antes de solicitar otro enlace.';

        } else {

            // Conexión a la base de datos
            require_once __DIR__ . '/../config/conexion.php';

            $_SESSION['ultima_recuperacion'] = time();

            try {

                // Buscar al usuario
                $sql = "SELECT id_usuario, nombre_completo, correo
                        FROM usuarios
                        WHERE correo = ?
                        AND activo = 1
                        AND proveedor = 'local'
                        LIMIT 1";

                $stmt = $pdo->prepare($sql);

                $stmt->execute([$correo]);

                $usuario = $stmt->fetch();

                // Comprobar si existe el usuario
                if ($usuario) {

                    // Evitar solicitudes demasiado frecuentes
                    $sql = "SELECT id
                            FROM recuperacion_contrasena
                            WHERE id_usuario = ?
                            AND expira > DATE_ADD(
                                NOW(), INTERVAL 29 MINUTE
                            )
                            LIMIT 1";

                    $stmt = $pdo->prepare($sql);

                    $stmt->execute([$usuario['id_usuario']]);

                    $solicitudReciente = $stmt->fetch();

                    if (!$solicitudReciente) {

                        // Generar token de recuperación
                        $tokenRecuperacion = bin2hex(random_bytes(32));

                        // Guardar el token de forma segura
                        $tokenHash = hash('sha256', $tokenRecuperacion);

                        // Registrar la solicitud
                        $sql = "INSERT INTO recuperacion_contrasena
                                (id_usuario, token, expira, usado)
                                VALUES (
                                    ?, ?,
                                    DATE_ADD(NOW(), INTERVAL 30 MINUTE),
                                    0
                                )";

                        $stmt = $pdo->prepare($sql);

                        $stmt->execute([
                            $usuario['id_usuario'],
                            $tokenHash
                        ]);

                        // Crear enlace de recuperación
                        $enlace = 'https://madison.4buthh.com/coffeshopweb1/CoffeeShopWeb/Login/restablecer.php?token='
                                  . urlencode($tokenRecuperacion);

                        // Datos del correo
                        $destinatario = $usuario['correo'];

                        $asunto = 'Recuperar contraseña | Coffee Shop';

                        $mensaje = "Hola, "
                                   . $usuario['nombre_completo']
                                   . ".\n\n";

                        $mensaje .= "Recibimos una solicitud para recuperar tu contraseña.\n\n";

                        $mensaje .= "Ingresa al siguiente enlace para establecer una nueva contraseña:\n\n";

                        $mensaje .= $enlace . "\n\n";

                        $mensaje .= "Este enlace estará disponible durante 30 minutos.\n\n";

                        $mensaje .= "Si no solicitaste este cambio, puedes ignorar este correo.\n\n";

                        $mensaje .= "Saludos, Coffee Shop.";

                        // Correo remitente autorizado por el hosting
                        $remitente = 'TU_CORREO_DEL_HOSTING';

                        $enviado = false;

                        if (filter_var($remitente, FILTER_VALIDATE_EMAIL)) {

                            $cabeceras = "From: Coffee Shop <"
                                         . $remitente . ">\r\n";

                            $cabeceras .= "MIME-Version: 1.0\r\n";

                            $cabeceras .= "Content-Type: text/plain; charset=UTF-8\r\n";

                            // Enviar correo electrónico
                            $enviado = mail(
                                $destinatario,
                                $asunto,
                                $mensaje,
                                $cabeceras
                            );
                        }

                        // Eliminar el token si no se pudo enviar
                        if (!$enviado) {

                            $sql = "DELETE FROM recuperacion_contrasena
                                    WHERE id_usuario = ?
                                    AND token = ?";

                            $stmt = $pdo->prepare($sql);

                            $stmt->execute([
                                $usuario['id_usuario'],
                                $tokenHash
                            ]);

                            error_log('No se pudo enviar el correo de recuperación.');
                        }
                    }
                }

                // Mostrar el mismo mensaje para proteger las cuentas
                $exito = 'Si el correo está asociado a una cuenta, recibirás un enlace para recuperar tu contraseña.';

            } catch (PDOException $e) {

                error_log(
                    'Error de recuperación: ' . $e->getMessage()
                );

                $error = 'No fue posible procesar la solicitud. Intenta nuevamente.';
            }
        }
    }
}

// Cargar la vista HTML
$html = file_get_contents(__DIR__ . '/recuperar.html');

// Mostrar u ocultar los mensajes
$claseError = 'd-none';
$claseExito = 'd-none';

if ($error !== '') {

    $claseError = '';
}

if ($exito !== '') {

    $claseExito = '';
}

// Enviar token de seguridad a la vista
$html = str_replace(
    '{{csrf_token}}',
    htmlspecialchars(
        $_SESSION['csrf_recuperar'],
        ENT_QUOTES,
        'UTF-8'
    ),
    $html
);

// Mostrar mensaje de error
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

// Mostrar mensaje de confirmación
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