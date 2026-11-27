<?php

session_start();

// Conexión a la base de datos
require_once __DIR__ . '/config/conexion.php';

// Consultar los datos de la cafetería
$sql = "SELECT * FROM cafeteria_info
        ORDER BY id_cafeteria LIMIT 1";

$resCafe = mysqli_query($conexion, $sql);

// Validar la consulta
if (!$resCafe) {

    error_log(
        'Error al consultar ubicación: ' .
        mysqli_error($conexion)
    );

    http_response_code(500);

    exit('No fue posible consultar los datos de la cafetería.');
}

// Obtener los datos de la cafetería
$cafe = mysqli_fetch_assoc($resCafe);

// Liberar los resultados
mysqli_free_result($resCafe);

$titulo = 'Ubicación';

include __DIR__ . '/includes/header.php';

?>

<div class="flex items-center gap-4 mb-5">

    <a href="index.php"
       onclick="history.back(); return false;"
       class="text-2xl leading-none"
       aria-label="Regresar">←</a>

    <h1 class="text-xl font-bold">Ubicación</h1>

</div>

<?php if (!$cafe): ?>

    <p class="text-sm">
        Todavía no hay datos de la cafetería.
    </p>

<?php else: ?>

    <?php

    // Comprobar si existen las coordenadas
    $tieneMapa = ($cafe['latitud'] !== null
               && $cafe['longitud'] !== null);

    if ($tieneMapa) {

        $lat = (float) $cafe['latitud'];

        $lon = (float) $cafe['longitud'];

        // Generar el mapa de OpenStreetMap
        $bbox = ($lon - 0.005) . ','
              . ($lat - 0.003) . ','
              . ($lon + 0.005) . ','
              . ($lat + 0.003);

        $urlMapa = 'https://www.openstreetmap.org/export/embed.html?bbox='
                   . $bbox . '&layer=mapnik&marker=' . $lat . ',' . $lon;

        // Generar la ruta de Google Maps
        $urlRuta = 'https://www.google.com/maps/dir/?api=1&destination='
                   . urlencode($lat . ',' . $lon);

    } else {

        // Buscar la ubicación mediante la dirección
        $urlRuta = 'https://www.google.com/maps/search/?api=1&query='
                   . urlencode($cafe['direccion']);
    }

    ?>

    <div class="grid md:grid-cols-2 gap-6">

        <!-- Mapa -->

        <div class="rounded-2xl overflow-hidden bg-durazno min-h-64">

            <?php if ($tieneMapa): ?>

                <iframe src="<?= htmlspecialchars($urlMapa) ?>"
                        title="Mapa de ubicación de la cafetería"
                        loading="lazy"
                        class="w-full h-72 md:h-full border-0"></iframe>

            <?php else: ?>

                <div class="h-full min-h-64 flex items-center justify-center text-5xl">

                    📍

                </div>

            <?php endif; ?>

        </div>

        <!-- Datos de la cafetería -->

        <div class="grid gap-4 content-start">

            <h2 class="text-xl font-bold">

                <?= htmlspecialchars($cafe['nombre']) ?>

            </h2>

            <p class="flex gap-3 text-sm">

                <span>📍</span>

                <span>

                    <?= htmlspecialchars($cafe['direccion']) ?>

                </span>

            </p>

            <div class="flex gap-3 text-sm">

                <span>🕒</span>

                <div>

                    <p class="font-bold">Horario:</p>

                    <p>

                        <?= htmlspecialchars($cafe['dias_atencion']) ?>:

                        <?= date('g:i a', strtotime($cafe['hora_apertura'])) ?>

                        a

                        <?= date('g:i a', strtotime($cafe['hora_cierre'])) ?>

                    </p>

                </div>

            </div>

            <a href="<?= htmlspecialchars($urlRuta) ?>"
               target="_blank"
               rel="noopener"
               class="mt-2 bg-moka text-crema rounded-xl h-12 flex items-center justify-center text-sm font-bold tracking-wide">

                VER RUTA

            </a>

        </div>

    </div>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>