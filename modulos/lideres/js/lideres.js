// Funciones para el modal de tardanzas
function mostrarModalTardanzas() {
    document.getElementById('modalTardanzas').style.display = 'block';
}

function cerrarModalTardanzas() {
    document.getElementById('modalTardanzas').style.display = 'none';
}

// Funciones para el modal de faltas
function mostrarModalFaltas() {
    document.getElementById('modalFaltas').style.display = 'block';
}

function cerrarModalFaltas() {
    document.getElementById('modalFaltas').style.display = 'none';
}

// Cerrar modal al hacer clic fuera
window.onclick = function (event) {
    const modalTardanzas = document.getElementById('modalTardanzas');
    const modalFaltas = document.getElementById('modalFaltas');

    if (event.target === modalTardanzas) {
        cerrarModalTardanzas();
    }
    if (event.target === modalFaltas) {
        cerrarModalFaltas();
    }
}

// Cerrar modal con tecla ESC
document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        cerrarModalTardanzas();
        cerrarModalFaltas();
    }
});

// ========================================
// FUNCIONALIDAD DE VENTAS VS META
// ========================================

let datosVentas = null;
let diaOffset = 0; // Para controlar qué días se muestran

// Cargar datos de ventas al cargar la página
document.addEventListener('DOMContentLoaded', function () {
    cargarDatosVentas();

    // Event listeners para botones de scroll
    const scrollLeft = document.getElementById('scrollLeft');
    const scrollRight = document.getElementById('scrollRight');

    if (scrollLeft) {
        scrollLeft.addEventListener('click', function () {
            if (diaOffset > 0) {
                diaOffset--;
                renderizarTablaVentas(datosVentas);
            }
        });
    }

    if (scrollRight) {
        scrollRight.addEventListener('click', function () {
            if (datosVentas && diaOffset + 7 < datosVentas.datos.length) {
                diaOffset++;
                renderizarTablaVentas(datosVentas);
            }
        });
    }
});

function cargarDatosVentas() {
    fetch('ajax/get_ventas_balance.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                datosVentas = data;
                renderizarTablaVentas(data);
            } else {
                console.error('Error:', data.message);
                mostrarErrorVentas(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarErrorVentas('Error al cargar datos de ventas');
        });
}

function renderizarTablaVentas(data) {
    if (!data || !data.datos) return;

    // Obtener los 7 días a mostrar según el offset
    const datosVisibles = data.datos.slice(diaOffset, diaOffset + 7);

    // Generar encabezados
    const header = document.getElementById('ventasMetaHeader');
    if (!header) return;

    let headerHTML = '<th>Meta: ' + data.meta_mensual.toFixed(1) + '</th>';
    headerHTML += '<th>' + data.mes_actual + '</th>';

    datosVisibles.forEach(dato => {
        headerHTML += '<th>' + dato.dia + '</th>';
    });
    header.innerHTML = headerHTML;

    // Fila de ventas reales
    const ventasRow = document.getElementById('ventasReales');
    if (ventasRow) {
        let ventasHTML = '<td>Real</td>';
        ventasHTML += '<td>' + data.promedio_mes.ventas_reales + '</td>';
        datosVisibles.forEach(dato => {
            ventasHTML += '<td>' + dato.ventas_reales + '</td>';
        });
        ventasRow.innerHTML = ventasHTML;
    }

    // Fila de cumplimiento
    const cumplimientoRow = document.getElementById('cumplimientoRow');
    if (cumplimientoRow) {
        let cumplimientoHTML = '<td>Cumplimiento</td>';
        cumplimientoHTML += '<td><span class="semaforo ' + data.promedio_mes.color + '"></span>' + data.promedio_mes.cumplimiento + '%</td>';
        datosVisibles.forEach(dato => {
            cumplimientoHTML += '<td><span class="semaforo ' + dato.color + '"></span>' + dato.cumplimiento + '%</td>';
        });
        cumplimientoRow.innerHTML = cumplimientoHTML;
    }

    // Actualizar visibilidad de botones de scroll
    updateScrollButtons();
}

function updateScrollButtons() {
    const scrollLeft = document.getElementById('scrollLeft');
    const scrollRight = document.getElementById('scrollRight');

    if (!datosVentas || !scrollLeft || !scrollRight) return;

    // Mostrar botón izquierdo si hay días anteriores
    if (diaOffset > 0) {
        scrollLeft.classList.add('visible');
    } else {
        scrollLeft.classList.remove('visible');
    }

    // Mostrar botón derecho si hay más días
    if (diaOffset + 7 < datosVentas.datos.length) {
        scrollRight.classList.add('visible');
    } else {
        scrollRight.classList.remove('visible');
    }
}

function mostrarErrorVentas(mensaje) {
    const ventasTableWrapper = document.getElementById('ventasTableWrapper');
    if (ventasTableWrapper) {
        ventasTableWrapper.innerHTML = '<div style="text-align: center; padding: 20px; color: #dc3545;">' +
            '<i class="fas fa-exclamation-triangle"></i> ' + mensaje +
            '</div>';
    }
}
