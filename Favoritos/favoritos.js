// Obtener la lista de favoritos
const listaFavoritos = document.getElementById('listaFavoritos');

// Confirmar antes de eliminar un favorito
function confirmarEliminar(evento) {

    const formulario = evento.target;

    if (formulario.classList.contains('form-eliminar-favorito')) {

        const respuesta = confirm(
            '¿Deseas quitar este producto de tus favoritos?'
        );

        if (!respuesta) {
            evento.preventDefault();
        }
    }
}

// Evento para eliminar favoritos
listaFavoritos.addEventListener('submit', confirmarEliminar);