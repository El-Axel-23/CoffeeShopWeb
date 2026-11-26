// Elementos del formulario
const formRecuperar = document.getElementById("formRecuperar");
const correo = document.getElementById("correo");

const errorCorreo = document.getElementById("errorCorreo");
const mensajeError = document.getElementById("mensajeError");
const mensajeExito = document.getElementById("mensajeExito");

// Eventos
formRecuperar.addEventListener("submit", validarFormulario);

correo.addEventListener("input", limpiarErrorCorreo);

// Validar formulario
function validarFormulario(evento) {

    limpiarErrorCorreo();

    const valorCorreo = correo.value.trim();

    if (valorCorreo === "") {

        evento.preventDefault();

        mostrarError("Por favor, ingresa tu correo electrónico.");

        correo.focus();

    } else if (!validarCorreo(valorCorreo)) {

        evento.preventDefault();

        mostrarError("Ingresa un correo electrónico válido.");

        correo.focus();

    } else if (valorCorreo.length > 120) {

        evento.preventDefault();

        mostrarError("El correo electrónico es demasiado largo.");

        correo.focus();

    } else {

        correo.value = valorCorreo;
    }
}

// Comprobar formato del correo
function validarCorreo(valor) {

    const expresion = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    return expresion.test(valor);
}

// Mostrar mensaje de error
function mostrarError(mensaje) {

    correo.classList.add("is-invalid");

    correo.setAttribute("aria-invalid", "true");

    errorCorreo.textContent = mensaje;

    errorCorreo.classList.add("d-block");
}

// Limpiar mensaje de error
function limpiarErrorCorreo() {

    correo.classList.remove("is-invalid");

    correo.removeAttribute("aria-invalid");

    errorCorreo.textContent = "";

    errorCorreo.classList.remove("d-block");

    mensajeError.classList.add("d-none");

    mensajeExito.classList.add("d-none");
}