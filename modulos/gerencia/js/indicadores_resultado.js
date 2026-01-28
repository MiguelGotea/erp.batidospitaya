// Variables globales
let modalAbierto = false;

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    inicializarEventos();
    verificarMensajes();
});

// Inicializar eventos
function inicializarEventos() {
    // Cerrar modal al hacer clic fuera
    const modal = document.getElementById('modalMeta');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target.id === 'modalMeta') {
                cerrarModalMeta();
            }
        });
    }
    
    // Manejar envío del formulario
    const formMeta = document.getElementById('formMeta');
    if (formMeta) {
        formMeta.addEventListener('submit', function(e) {
            e.preventDefault();
            guardarMeta();
        });
    }
}

// Verificar y ocultar mensajes automáticamente
function verificarMensajes() {
    const mensajes = document.querySelectorAll('.mensaje-success, .mensaje-error');
    
    mensajes.forEach(mensaje => {
        setTimeout(() => {
            mensaje.style.opacity = '0';
            setTimeout(() => {
                mensaje.style.display = 'none';
            }, 500);
        }, 3000);
    });
}

// Abrir modal para editar meta general
function editarMetaGeneral(idIndicador, celda) {
    // Obtener valor actual
    let valorActual = celda.textContent.trim();
    if (valorActual === '--') valorActual = '';
    
    // Obtener el ID de la semana desde el atributo data
    let semanaId = celda.getAttribute('data-semana');
    if (!semanaId) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'No se puede editar la meta para esta semana',
            confirmButtonColor: '#51B8AC'
        });
        return;
    }
    
    // Rellenar formulario
    document.getElementById('modalMetaIdIndicador').value = idIndicador;
    document.getElementById('modalMetaSemana').value = semanaId;
    document.getElementById('modalMetaValor').value = valorActual;
    
    // Mostrar modal
    document.getElementById('modalMeta').style.display = 'block';
    modalAbierto = true;
    
    // Focus en input
    setTimeout(() => {
        document.getElementById('modalMetaValor').focus();
        document.getElementById('modalMetaValor').select();
    }, 100);
}

// Cerrar modal
function cerrarModalMeta() {
    document.getElementById('modalMeta').style.display = 'none';
    modalAbierto = false;
}

// Guardar meta via AJAX
function guardarMeta() {
    const formData = new FormData(document.getElementById('formMeta'));
    
    // Mostrar loading
    Swal.fire({
        title: 'Guardando...',
        text: 'Por favor espera',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        willOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('ajax/guardar_meta.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Éxito',
                text: 'Meta guardada correctamente',
                confirmButtonColor: '#51B8AC'
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'Error al guardar la meta',
                confirmButtonColor: '#51B8AC'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error de conexión',
            text: 'No se pudo conectar con el servidor',
            confirmButtonColor: '#51B8AC'
        });
    });
}

// Cerrar modal con tecla ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && modalAbierto) {
        cerrarModalMeta();
    }
});