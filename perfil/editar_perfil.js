// Obtener los elementos del formulario
const formPerfil = document.getElementById('formPerfil');
const nombre = document.getElementById('nombre_completo');
const correo = document.getElementById('correo');
const telefono = document.getElementById('telefono');
const contrasena = document.getElementById('contrasena_actual');

const mensajeError = document.getElementById('mensajeError');
const btnMostrarContrasena = document.getElementById('btnMostrarContrasena');
const iconoOjo = document.getElementById('iconoOjo');

// Guardar el correo actual
const correoActual = correo.value.trim().toLowerCase();

// Mostrar u ocultar contraseña
function cambiarVisibilidad() {

    if (contrasena.type === 'password') {

        contrasena.type = 'text';
        iconoOjo.classList.replace('bi-eye-slash', 'bi-eye');
        btnMostrarContrasena.setAttribute('aria-label', 'Ocultar contraseña');

    } else {

        contrasena.type = 'password';
        iconoOjo.classList.replace('bi-eye', 'bi-eye-slash');
        btnMostrarContrasena.setAttribute('aria-label', 'Mostrar contraseña');
    }
}

// Mostrar mensaje de error
function mostrarError(mensaje) {

    mensajeError.textContent = mensaje;
    mensajeError.classList.remove('d-none');
}

// Ocultar mensaje de error
function ocultarError() {

    mensajeError.textContent = '';
    mensajeError.classList.add('d-none');
}

// Validar formulario
function validarFormulario(evento) {

    ocultarError();

    const valorNombre = nombre.value.trim();
    const valorCorreo = correo.value.trim();
    const valorTelefono = telefono.value.trim();

    // Validar nombre
    if (valorNombre === '' || valorNombre.length > 120) {

        evento.preventDefault();
        mostrarError('Ingresa un nombre completo válido.');
        nombre.focus();
        return;
    }

    // Validar correo
    const expresionCorreo = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!expresionCorreo.test(valorCorreo) || valorCorreo.length > 120) {

        evento.preventDefault();
        mostrarError('Ingresa un correo electrónico válido.');
        correo.focus();
        return;
    }

    // Validar teléfono si fue ingresado
    const expresionTelefono = /^\+?[0-9]{10,14}$/;

    if (valorTelefono !== '' && !expresionTelefono.test(valorTelefono)) {

        evento.preventDefault();
        mostrarError('Ingresa un número de teléfono válido, sin espacios.');
        telefono.focus();
        return;
    }

    // Solicitar contraseña si cambia el correo
    if (valorCorreo.toLowerCase() !== correoActual &&
        contrasena.value.trim() === '') {

        evento.preventDefault();
        mostrarError('Ingresa tu contraseña actual para cambiar el correo.');
        contrasena.focus();
        return;
    }

    nombre.value = valorNombre;
    correo.value = valorCorreo;
    telefono.value = valorTelefono;
}

// Eventos
btnMostrarContrasena.addEventListener('click', cambiarVisibilidad);

formPerfil.addEventListener('submit', validarFormulario);