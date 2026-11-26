<?php

require_once __DIR__ . '/Login/sesion.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Validar la sesión solamente si inició sesión
if (isset($_SESSION['id_usuario'])) {
    verificarSesion('Login/login.php');
}

header('Cache-Control: no-store');

require_once __DIR__ . '/config/conexion.php';

$q = isset($_GET['q']) && is_string($_GET['q'])
    ? trim($_GET['q']) : '';

$cat = filter_input(INPUT_GET, 'cat', FILTER_VALIDATE_INT);
$cat = ($cat > 0) ? $cat : 0;

// Obtener las categorías
$resCategorias = $pdo->query(
    "SELECT * FROM categorias ORDER BY orden"
)->fetchAll();

$titulo = 'Coffee Shop - Inicio';

include __DIR__ . '/includes/header.php';

?>

<!-- Título principal -->
<h1 class="text-2xl font-bold mb-5">
    Inicio
</h1>

<!-- Buscador -->
<form id="form-buscar" class="mb-5 w-full max-w-lg">

    <div class="relative">

        <i class="bi bi-search absolute left-4 top-1/2
                  -translate-y-1/2 text-moka text-base
                  pointer-events-none"></i>

        <input type="text"
               id="buscador"
               value="<?= htmlspecialchars($q) ?>"
               autocomplete="off"
               placeholder="Busca tu café o postre favorito..."
               aria-label="Buscar productos"
               class="w-full bg-white border border-borde
                      rounded-lg pl-11 pr-4 py-3 text-xs
                      placeholder:text-secundario
                      focus:outline-none focus:ring-1
                      focus:ring-cafe focus:border-cafe">

    </div>

</form>

<!-- Categorías -->
<div class="flex flex-wrap gap-2 mb-6 text-xs">

    <button type="button"
            data-cat="0"
            aria-pressed="<?= $cat == 0 ? 'true' : 'false' ?>"
            class="rounded-lg px-4 py-3 font-semibold
                   transition-colors
                   <?= $cat == 0
                       ? 'bg-cafe text-white'
                       : 'bg-crema hover:bg-borde' ?>">

        Todos

    </button>

    <?php foreach ($resCategorias as $c): ?>

        <button type="button"
                data-cat="<?= (int) $c['id_categoria'] ?>"
                aria-pressed="<?= $cat == $c['id_categoria']
                    ? 'true' : 'false' ?>"
                class="rounded-lg px-4 py-3 font-semibold
                       transition-colors
                       <?= $cat == $c['id_categoria']
                           ? 'bg-cafe text-white'
                           : 'bg-crema hover:bg-borde' ?>">

            <?= htmlspecialchars($c['nombre']) ?>

        </button>

    <?php endforeach; ?>

</div>

<!-- Catálogo de productos -->
<div id="catalogo"
     data-sesion="<?= isset($_SESSION['id_usuario']) ? '1' : '0' ?>"
     data-categoria="<?= $cat ?>">

    <?php include __DIR__ . '/includes/catalogo.php'; ?>

</div>

<script>

    const buscador = document.getElementById('buscador');
    const catalogo = document.getElementById('catalogo');
    const botones = document.querySelectorAll('[data-cat]');

    const usuarioLogueado = catalogo.dataset.sesion === '1';

    let cat = Number(catalogo.dataset.categoria);
    let espera = null;
    let peticion = null;

    // Buscar productos
    function buscar() {

        if (peticion) {
            peticion.abort();
        }

        peticion = new AbortController();

        const url = 'buscar.php?q='
            + encodeURIComponent(buscador.value.trim())
            + '&cat=' + cat;

        fetch(url, { signal: peticion.signal })
            .then(recibirRespuesta)
            .then(mostrarResultados)
            .catch(mostrarError);
    }

    // Recibir respuesta del servidor
    function recibirRespuesta(respuesta) {

        // Redirigir al Login si la sesión terminó
        if (respuesta.redirected) {

            window.location.href = respuesta.url;

            throw new DOMException(
                'Sesión finalizada',
                'AbortError'
            );
        }

        if (!respuesta.ok) {
            throw new Error('Error en la consulta');
        }

        return respuesta.text();
    }

    // Mostrar los productos encontrados
    function mostrarResultados(html) {

        catalogo.innerHTML = html;
    }

    // Mostrar mensaje si ocurre un error
    function mostrarError(error) {

        if (error.name !== 'AbortError') {

            catalogo.innerHTML =
                '<p class="text-sm">No se pudo buscar. Intenta de nuevo.</p>';
        }
    }

    // Esperar antes de realizar la búsqueda
    function esperarBusqueda() {

        clearTimeout(espera);

        espera = setTimeout(buscar, 50);
    }

    // Evitar que se recargue la página
    function evitarEnvio(evento) {

        evento.preventDefault();
    }

    // Seleccionar una categoría
    function seleccionarCategoria(evento) {

        cat = Number(evento.currentTarget.dataset.cat);

        for (let i = 0; i < botones.length; i++) {

            const boton = botones[i];

            const activo = Number(boton.dataset.cat) === cat;

            boton.classList.toggle('bg-cafe', activo);
            boton.classList.toggle('text-white', activo);
            boton.classList.toggle('bg-crema', !activo);
            boton.classList.toggle('hover:bg-borde', !activo);

            boton.setAttribute(
                'aria-pressed',
                activo ? 'true' : 'false'
            );
        }

        buscar();
    }

    // Comprobar sesión antes de agregar al carrito
    function verificarAccesoCarrito(evento) {

        const boton = evento.target.closest(
            '[data-agregar-carrito], button[aria-label="Agregar"]'
        );

        if (boton && !usuarioLogueado) {

            evento.preventDefault();
            evento.stopImmediatePropagation();

            window.location.href = 'Login/login.php?error=carrito';
        }
    }

    // Eventos del buscador
    buscador.addEventListener('input', esperarBusqueda);

    document.getElementById('form-buscar')
        .addEventListener('submit', evitarEnvio);

    // Eventos de las categorías
    for (let i = 0; i < botones.length; i++) {

        botones[i].addEventListener(
            'click',
            seleccionarCategoria
        );
    }

    // Evento del carrito
    catalogo.addEventListener(
        'click',
        verificarAccesoCarrito,
        true
    );

</script>

<?php include __DIR__ . '/includes/footer.php'; ?>