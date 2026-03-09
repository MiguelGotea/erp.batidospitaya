/**
 * JavaScript para la herramienta de Reseñas de Google
 * Batidos Pitaya ERP
 */

const GOOGLE_SCRIPT_URL = 'https://script.google.com/macros/s/AKfycbxBLL010Mer3x5CwjTqJjTJ8DFKcLYPZoqBXu0tJ3wEOOjAsZcZnGfNPAZQIKAeclnWJQ/exec';

$(document).ready(function() {
    cargarResenas();
});

/**
 * Carga las reseñas mediante AJAX
 */
async function cargarResenas() {
    mostrarCargando(true);
    
    try {
        const response = await $.ajax({
            url: 'ajax/resenas_google_descargado_get_datos.php',
            type: 'GET',
            dataType: 'json'
        });

        if (response.success) {
            renderizarTabla(response.data);
        } else {
            Swal.fire('Error', response.message || 'No se pudieron cargar las reseñas', 'error');
        }
    } catch (error) {
        console.error('Error al cargar reseñas:', error);
        Swal.fire('Error', 'Hubo un problema de conexión con el servidor', 'error');
    } finally {
        mostrarCargando(false);
    }
}

/**
 * Renderiza los datos en la tabla HTML
 */
function renderizarTabla(data) {
    const tbody = $('#tbodyResenas');
    tbody.empty();

    if (data.length === 0) {
        tbody.append('<tr><td colspan="5" class="text-center py-4 text-muted">No se encontraron reseñas registradas.</td></tr>');
        return;
    }

    data.forEach(item => {
        const estrellas = generarEstrellas(item.starRatingNum);
        
        const row = `
            <tr>
                <td><span class="badge sucursal-badge">${item.SucursalNombre}</span></td>
                <td class="reviewer-name">${item.reviewerName}</td>
                <td class="text-center">${estrellas}</td>
                <td><div class="review-comment">${item.comment || '<span class="text-muted italic">Sin comentario</span>'}</div></td>
                <td class="text-center">${item.fechaFormateada}</td>
            </tr>
        `;
        tbody.append(row);
    });
}

/**
 * Genera el HTML de las estrellas según el rating
 */
function generarEstrellas(rating) {
    let html = '<div class="star-rating">';
    for (let i = 1; i <= 5; i++) {
        if (i <= rating) {
            html += '<i class="fas fa-star"></i>';
        } else {
            html += '<i class="far fa-star"></i>';
        }
    }
    html += '</div>';
    return html;
}

/**
 * Inicia el proceso de actualización llamando al Google Script
 */
async function actualizarResenas() {
    const result = await Swal.fire({
        title: '¿Actualizar Reseñas?',
        text: "Este proceso descargará las últimas reseñas desde Google Business. Puede tardar unos momentos.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#51B8AC',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, actualizar',
        cancelButtonText: 'Cancelar'
    });

    if (result.isConfirmed) {
        // Mostrar loader persistente
        Swal.fire({
            title: 'Actualizando...',
            text: 'Estamos conectando con Google Business, por favor espera.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Abrir en una pestaña oculta o hacer el fetch
        // NOTA: Google Apps Script a veces tiene problemas con CORS en fetch directo desde JS ERP
        // Por lo que abriremos una ventana pequeña y la cerraremos o usaremos un iframe oculto.
        
        try {
            // Intento de fetch primero (si está configurado con CORS adecuado)
            // Usamos mode no-cors si es solo para disparar la ejecución
            await fetch(GOOGLE_SCRIPT_URL, { mode: 'no-cors' });
            
            // Esperamos un poco para que el script procese (arbitrario)
            setTimeout(() => {
                Swal.fire({
                    title: 'Proceso Iniciado',
                    text: 'Se ha enviado la señal de actualización. Los datos aparecerán en unos instantes.',
                    icon: 'success',
                    confirmButtonColor: '#51B8AC'
                }).then(() => {
                    cargarResenas(); // Recargar tabla
                });
            }, 3000);

        } catch (error) {
            console.error('Error al actualizar:', error);
            Swal.fire('Error', 'No se pudo iniciar el script de actualización.', 'error');
        }
    }
}

function mostrarCargando(show) {
    if (show) {
        $('#loaderResenas').show();
        $('.table-resenas').css('opacity', '0.5');
    } else {
        $('#loaderResenas').hide();
        $('.table-resenas').css('opacity', '1');
    }
}
