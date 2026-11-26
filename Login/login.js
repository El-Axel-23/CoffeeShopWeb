// Elementos del formulario
const formLogin = document.getElementById("formLogin");
const correo = document.getElementById("correo");
const contrasena = document.getElementById("contrasena");

const errorCorreo = document.getElementById("errorCorreo");
const errorContrasena = document.getElementById("errorContrasena");
const mensajeError = document.getElementById("mensajeError");

const btnMostrarContrasena = document.getElementById("btnMostrarContrasena");
const iconoOjo = document.getElementById("iconoOjo");

// Eventos
formLogin.addEventListener("submit", validarFormulario);
btnMostrarContrasena.addEventListener("click", mostrarContrasena);

correo.addEventListener("input", limpiarErrorCorreo);
contrasena.addEventListener("input", limpiarErrorContrasena);

// Validar formulario
function validarFormulario(evento) {

    let esValido = true;

    limpiarErrorCorreo();
    limpiarErrorContrasena();

    mensajeError.classList.add("d-none");

    // Validar correo electrónico
    if (correo.value.trim() === "") {

        mostrarError(
            correo,
            errorCorreo,
            "Por favor, ingresa tu correo electrónico."
        );

        esValido = false;

    } else if (!validarCorreo(correo.value.trim())) {

        mostrarError(
            correo,
            errorCorreo,
            "Ingresa un correo electrónico válido."
        );

        esValido = false;
    }

    // Validar contraseña
    if (contrasena.value.trim() === "") {

        mostrarError(
            contrasena,
            errorContrasena,
            "Por favor, ingresa tu contraseña."
        );

        esValido = false;
    }

    // Detener el envío si existen errores
    if (!esValido) {

        evento.preventDefault();

        if (correo.classList.contains("is-invalid")) {
            correo.focus();
        } else {
            contrasena.focus();
        }
    }
}

// Comprobar formato del correo
function validarCorreo(valor) {

    const expresion = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    return expresion.test(valor);
}

// Mostrar mensaje de error
function mostrarError(campo, elementoError, mensaje) {

    campo.classList.add("is-invalid");
    campo.setAttribute("aria-invalid", "true");

    elementoError.textContent = mensaje;
    elementoError.classList.add("d-block");
}

// Limpiar error del correo
function limpiarErrorCorreo() {

    correo.classList.remove("is-invalid");
    correo.removeAttribute("aria-invalid");

    errorCorreo.textContent = "";
    errorCorreo.classList.remove("d-block");

    mensajeError.classList.add("d-none");
}

// Limpiar error de la contraseña
function limpiarErrorContrasena() {

    contrasena.classList.remove("is-invalid");
    contrasena.removeAttribute("aria-invalid");

    errorContrasena.textContent = "";
    errorContrasena.classList.remove("d-block");

    mensajeError.classList.add("d-none");
}

// Mostrar y ocultar contraseña
function mostrarContrasena() {

    if (contrasena.type === "password") {

        contrasena.type = "text";

        iconoOjo.classList.remove("bi-eye-slash");
        iconoOjo.classList.add("bi-eye");

        btnMostrarContrasena.setAttribute(
            "aria-label",
            "Ocultar contraseña"
        );

    } else {

        contrasena.type = "password";

        iconoOjo.classList.remove("bi-eye");
        iconoOjo.classList.add("bi-eye-slash");

        btnMostrarContrasena.setAttribute(
            "aria-label",
            "Mostrar contraseña"
        );
    }
}