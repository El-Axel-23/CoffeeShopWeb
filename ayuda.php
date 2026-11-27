<?php

session_start();

// Conexión a la base de datos
require_once __DIR__ . '/config/conexion.php';

// Consultar las preguntas frecuentes
$sqlPreguntas = "SELECT pregunta, respuesta
                 FROM preguntas_frecuentes
                 ORDER BY orden";

$resultadoPreguntas = mysqli_query(
    $conexion,
    $sqlPreguntas
);

// Validar la consulta
if (!$resultadoPreguntas) {

    error_log(
        'Error al consultar preguntas: ' .
        mysqli_error($conexion)
    );

    http_response_code(500);
    exit('No fue posible consultar las preguntas frecuentes.');
}

$resPreguntas = [];

// Obtener las preguntas frecuentes
while ($pregunta = mysqli_fetch_assoc($resultadoPreguntas)) {

    $resPreguntas[] = $pregunta;
}

mysqli_free_result($resultadoPreguntas);

// Consultar la información de contacto
$sqlCafe = "SELECT telefono, correo
            FROM cafeteria_info
            ORDER BY id_cafeteria LIMIT 1";

$resCafe = mysqli_query($conexion, $sqlCafe);

// Validar la consulta
if (!$resCafe) {

    error_log(
        'Error al consultar la cafetería: ' .
        mysqli_error($conexion)
    );

    http_response_code(500);
    exit('No fue posible consultar la información de contacto.');
}

// Obtener los datos de la cafetería
$cafe = mysqli_fetch_assoc($resCafe);

mysqli_free_result($resCafe);

$titulo = 'Ayuda';

include __DIR__ . '/includes/header.php';

?>

<div class="max-w-2xl">

    <div class="flex items-center gap-4 mb-5">

        <a href="index.php"
           onclick="history.back(); return false;"
           class="text-2xl leading-none"
           aria-label="Regresar">←</a>

        <h1 class="text-xl font-bold">Ayuda</h1>

    </div>

    <!-- Preguntas frecuentes -->

    <div class="grid gap-3">

        <?php if (count($resPreguntas) > 0): ?>

            <?php foreach ($resPreguntas as $p): ?>

                <details class="bg-durazno rounded-2xl p-4">

                    <summary class="cursor-pointer text-sm font-semibold">

                        <?= htmlspecialchars($p['pregunta']) ?>

                    </summary>

                    <p class="mt-3 text-sm">

                        <?= htmlspecialchars($p['respuesta']) ?>

                    </p>

                </details>

            <?php endforeach; ?>

        <?php else: ?>

            <p class="text-sm">

                Todavía no hay preguntas frecuentes.

            </p>

        <?php endif; ?>

    </div>

    <!-- Contacto -->

    <?php if ($cafe): ?>

        <div class="bg-durazno rounded-2xl p-4 mt-5 grid gap-3">

            <h2 class="text-base font-bold">

                ¿Necesitas ayuda?

            </h2>

            <?php if ($cafe['telefono']): ?>

                <p class="text-sm flex gap-3">

                    <span>📞</span>

                    <a href="tel:<?= htmlspecialchars($cafe['telefono']) ?>"
                       class="hover:underline">

                        <?= htmlspecialchars($cafe['telefono']) ?>

                    </a>

                </p>

            <?php endif; ?>

            <?php if ($cafe['correo']): ?>

                <p class="text-sm flex gap-3">

                    <span>✉️</span>

                    <a href="mailto:<?= htmlspecialchars($cafe['correo']) ?>"
                       class="hover:underline">

                        <?= htmlspecialchars($cafe['correo']) ?>

                    </a>

                </p>

            <?php endif; ?>

        </div>

    <?php endif; ?>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>