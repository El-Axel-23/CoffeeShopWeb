<?php

$base = $base ?? '';

?>

    </main>

    <!-- Pie de página -->
    <footer class="w-full bg-crema text-moka">

        <div class="max-w-6xl mx-auto px-4 md:px-6 py-6
                    flex flex-col md:flex-row items-center
                    justify-between gap-4 text-xs">

            <!-- Información de Coffee Shop -->
            <span class="text-center md:text-left">

                Coffee Shop · Un momento delicioso en cada sorbo

            </span>

            <!-- Enlaces del pie de página -->
            <div class="flex items-center gap-4">

                <a href="<?= $base ?>index.php#menu"
                   class="hover:text-cafe hover:underline">

                    Menú

                </a>

                <span>·</span>

                <a href="<?= $base ?>ubicacion.php"
                   class="hover:text-cafe hover:underline">

                    Ubicación

                </a>

                <span>·</span>

                <a href="<?= $base ?>ayuda.php"
                   class="hover:text-cafe hover:underline">

                    Ayuda

                </a>

            </div>

        </div>

    </footer>

</div>

</body>

</html>