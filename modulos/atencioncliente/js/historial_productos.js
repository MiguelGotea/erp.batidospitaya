let paginaActual = 1;
let registrosPorPagina = 25;
let totalRegistros = 0;
let membresiaActual = '';

// Inicializar
$(document).ready(function() {
    if (membresiaInicial) {
        membresiaActual = membresiaInicial;
        cargarDatos();
    } else {
        window.location.href = 'historial_clientes.php';
    }
});

// Cargar datos
function cargarDatos() {
    if (!membresiaActual) {
        window.location.href = 'historial_clientes.php';
        return;
    }
    
    $.ajax({
        url: 'ajax/productos_get_datos.php',
        method: 'POST',
        data: {
            membresia: membresiaActual,
            pagina: paginaActual,
            registros_por_pagina: registrosPorPagina
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                totalRegistros = response.total_registros;
                
                if (response.cliente) {
                    mostrarInfoCliente(response.cliente);
                }
                
                if (response.datos.length > 0) {
                    renderizarTabla(response.datos);
                    renderizarPaginacion(response.total_registros);
                } else {
                    mostrarMensajeVacio('No se encontraron productos para esta membresía');
                }
            } else {
                alert('Error: ' + response.message);
                mostrarMensajeVacio('No se encontraron datos');
            }
        },
        error: function() {
            alert('Error al cargar los datos');
            mostrarMensajeVacio('Error al cargar los datos');
        }
    });
}

// Mostrar info del cliente
function mostrarInfoCliente(cliente) {
    const infoDiv = $('#infoCliente');
    
    if (cliente) {
        infoDiv.html(`
            <div>
                <strong>Membresía:</strong> ${membresiaActual}
                <strong class="ms-3">Nombre:</strong> ${cliente.nombre} ${cliente.apellido || ''} 
                <strong class="ms-3">Fecha Registro:</strong> ${formatearFecha(cliente.fecha_registro)}
            </div>
        `).removeClass('empty');
    } else {
        infoDiv.html('Cliente no encontrado').addClass('empty');
    }
}

// Renderizar tabla
function renderizarTabla(datos) {
    const tbody = $('#tablaProductosBody');
    tbody.empty();
    
    if (datos.length === 0) {
        mostrarMensajeVacio('No se encontraron productos');
        return;
    }
    
    datos.forEach(row => {
        const esAnulado = parseInt(row.Anulado) !== 0;
        const tr = $('<tr>');
        
        if (esAnulado) {
            tr.addClass('fila-anulada');
        }
        
        tr.append(`<td>${row.Sucursal_Nombre || '-'}</td>`);
        tr.append(`<td>${row.CodPedido || '-'}</td>`);
        tr.append(`<td>${formatearFecha(row.Fecha)}</td>`);
        tr.append(`<td>${formatearHora(row.Hora)}</td>`);
        tr.append(`<td>${row.DBBatidos_Nombre || '-'}</td>`);
        tr.append(`<td>${row.Medida || '-'}</td>`);
        tr.append(`<td>${row.Cantidad || 0}</td>`);
        tr.append(`<td class="col-puntos-totales">${row.PuntosTotales || 0}</td>`);
        tr.append(`<td class="col-puntos-acumulados">${row.PuntosAcumulados || 0}</td>`);
        
        tbody.append(tr);
    });
}

// Mostrar mensaje vacío
function mostrarMensajeVacio(mensaje) {
    const tbody = $('#tablaProductosBody');
    tbody.html(`
        <tr>
            <td colspan="9" class="empty-state">
                <i class="bi bi-inbox"></i>
                <p>${mensaje}</p>
            </td>
        </tr>
    `);
    $('#paginacion').empty();
}

// Cambiar registros por página
function cambiarRegistrosPorPagina() {
    registrosPorPagina = parseInt($('#registrosPorPagina').val());
    paginaActual = 1;
    cargarDatos();
}

// Renderizar paginación
function renderizarPaginacion(totalRegistros) {
    const totalPaginas = Math.ceil(totalRegistros / registrosPorPagina);
    const paginacion = $('#paginacion');
    paginacion.empty();
    
    if (totalPaginas <= 1) return;
    
    paginacion.append(`
        <button class="pagination-btn" onclick="cambiarPagina(${paginaActual - 1})" ${paginaActual === 1 ? 'disabled' : ''}>
            <i class="bi bi-chevron-left"></i>
        </button>
    `);
    
    let inicio = Math.max(1, paginaActual - 2);
    let fin = Math.min(totalPaginas, paginaActual + 2);
    
    if (inicio > 1) {
        paginacion.append(`<button class="pagination-btn" onclick="cambiarPagina(1)">1</button>`);
        if (inicio > 2) {
            paginacion.append(`<span class="pagination-btn" disabled>...</span>`);
        }
    }
    
    for (let i = inicio; i <= fin; i++) {
        const activeClass = i === paginaActual ? 'active' : '';
        paginacion.append(`<button class="pagination-btn ${activeClass}" onclick="cambiarPagina(${i})">${i}</button>`);
    }
    
    if (fin < totalPaginas) {
        if (fin < totalPaginas - 1) {
            paginacion.append(`<span class="pagination-btn" disabled>...</span>`);
        }
        paginacion.append(`<button class="pagination-btn" onclick="cambiarPagina(${totalPaginas})">${totalPaginas}</button>`);
    }
    
    paginacion.append(`
        <button class="pagination-btn" onclick="cambiarPagina(${paginaActual + 1})" ${paginaActual === totalPaginas ? 'disabled' : ''}>
            <i class="bi bi-chevron-right"></i>
        </button>
    `);
}

// Cambiar página
function cambiarPagina(pagina) {
    if (pagina < 1 || pagina > Math.ceil(totalRegistros / registrosPorPagina)) return;
    paginaActual = pagina;
    cargarDatos();
}

// Formatear fecha
function formatearFecha(fecha) {
    if (!fecha) return '-';
    const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    const d = new Date(fecha);
    const año = String(d.getFullYear()).slice(-2);
    return `${String(d.getDate()).padStart(2, '0')}-${meses[d.getMonth()]}-${año}`;
}

// Formatear hora
function formatearHora(hora) {
    if (!hora) return '-';
    return hora.substring(0, 5);
}