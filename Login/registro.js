// Obtener los elementos del formulario
const formRegistro = document.getElementById('formRegistro');

const nombre = document.getElementById('nombre_completo');
const correo = document.getElementById('correo');
const contrasena = document.getElementById('contrasena');
const confirmarContrasena = document.getElementById('confirmar_contrasena');
const terminos = document.getElementById('acepto_terminos');

const mensajeError = document.getElementById('mensajeError');

// Obtener los botones e iconos
const btnMostrarContrasena = document.getElementById('btnMostrarContrasena');
const btnMostrarConfirmacion = document.getElementById('btnMostrarConfirmacion');

const iconoContrasena = document.getElementById('iconoContrasena');
const iconoConfirmacion = document.getElementById('iconoConfirmacion');

// Mostrar u ocultar contraseña
function cambiarVisibilidad(campo, icono, boton) {

    if (campo.type === 'password') {

        campo.type = 'text';

        icono.classList.remove('bi-eye-slash');
        icono.classList.add('bi-eye');

        boton.setAttribute('aria-label', 'Ocultar contraseña');

    } else {

        campo.type = 'password';

        icono.classList.remove('bi-eye');
        icono.classList.add('bi-eye-slash');

        boton.setAttribute('aria-label', 'Mostrar contraseña');
    }
}

// Mostrar u ocultar la primera contraseña
btnMostrarContrasena.addEventListener('click', function () {

    cambiarVisibilidad(
        contrasena,
        iconoContrasena,
        btnMostrarContrasena
    );
});

// Mostrar u ocultar la confirmación
btnMostrarConfirmacion.addEventListener('click', function () {

    cambiarVisibilidad(
        confirmarContrasena,
        iconoConfirmacion,
        btnMostrarConfirmacion
    );
});

// Mostrar mensaje de error
function mostrarError(mensaje) {

    mensajeError.textContent = mensaje;

    mensajeError.classList.remove('d-none');

    mensajeError.scrollIntoView({
        behavior: 'smooth',
        block: 'nearest'
    });
}

// Ocultar mensaje de error
function ocultarError() {

    mensajeError.textContent = '';

    mensajeError.classList.add('d-none');
}

// Validar correo electrónico
function validarCorreo(valor) {

    const expresion = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    return expresion.test(valor);
}

// Validar los requisitos de la contraseña
function validarContrasena(valor) {

    const mayuscula = /[A-Z]/;
    const minuscula = /[a-z]/;
    const numero = /[0-9]/;
    const simbolo = /[^\p{L}\p{N}\s]/u;

    return valor.length >= 8 &&
           mayuscula.test(valor) &&
           minuscula.test(valor) &&
           numero.test(valor) &&
           simbolo.test(valor);
}

// Validar el formulario antes de enviarlo
formRegistro.addEventListener('submit', function (evento) {

    ocultarError();

    const valorNombre = nombre.value.trim();
    const valorCorreo = correo.value.trim();
    const valorContrasena = contrasena.value;
    const valorConfirmacion = confirmarContrasena.value;

    // Validar campos vacíos
    if (valorNombre === '' ||
        valorCorreo === '' ||
        valorContrasena.trim() === '' ||
        valorConfirmacion.trim() === '') {

        evento.preventDefault();

        mostrarError('Todos los campos son obligatorios.');

        return;
    }

    // Validar longitud del nombre
    if (valorNombre.length > 120) {

        evento.preventDefault();

        mostrarError('El nombre supera la longitud permitida.');

        nombre.focus();

        return;
    }

    // Validar correo electrónico
    if (!validarCorreo(valorCorreo) || valorCorreo.length > 120) {

        evento.preventDefault();

        mostrarError('Ingresa un correo electrónico válido.');

        correo.focus();

        return;
    }

    // Validar longitud mínima de la contraseña
    if (valorContrasena.length < 8) {

        evento.preventDefault();

        mostrarError('La contraseña debe tener al menos 8 caracteres.');

        contrasena.focus();

        return;
    }

    // Validar contraseña segura
    if (!validarContrasena(valorContrasena)) {

        evento.preventDefault();

        mostrarError(
            'La contraseña debe contener una mayúscula, ' +
            'una minúscula, un número y un símbolo.'
        );

        contrasena.focus();

        return;
    }

    // Comprobar que las contraseñas coincidan
    if (valorContrasena !== valorConfirmacion) {

        evento.preventDefault();

        mostrarError('Las contraseñas no coinciden.');

        confirmarContrasena.focus();

        return;
    }

    // Comprobar aceptación de términos
    if (!terminos.checked) {

        evento.preventDefault();

        mostrarError('Debes aceptar los términos y condiciones.');

        terminos.focus();

        return;
    }

    // Limpiar espacios del nombre y correo
    nombre.value = valorNombre;
    correo.value = valorCorreo;

    // Si todo es correcto, se envía el formulario a PHP
});