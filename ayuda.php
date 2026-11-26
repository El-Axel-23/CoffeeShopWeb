<?php

session_start();

require_once __DIR__ . '/config/conexion.php';

$resPreguntas = $pdo->query(
    "SELECT pregunta, respuesta
     FROM preguntas_frecuentes
     ORDER BY orden"
)->fetchAll();

$resCafe = $pdo->query(
    "SELECT telefono, correo
     FROM cafeteria_info
     ORDER BY id_cafeteria LIMIT 1"
);

$cafe = $resCafe->fetch();

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

    <!-- Preguntas frecuentes: se abren y cierran con un clic (sin JavaScript) -->

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