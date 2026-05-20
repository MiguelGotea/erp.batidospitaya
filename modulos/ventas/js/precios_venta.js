// precios_venta.js

let datosOriginales = [];
let datosFiltrados = [];
let paginaActual = 1;
let registrosPorPagina = 25;
let filtrosActivos = {};

$(document).ready(function() {
    cargarDatos();
    cargarSucursales();
});

function cargarDatos() {
    $.ajax({
        url: 'ajax/precios_get_datos.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                datosOriginales = response.data;
                
                // Poblar select de productos en modal
                const select = $('#productoPresentacion');
                select.empty().append('<option value="">Seleccione un producto</option>');
                datosOriginales.forEach(item => {
                    select.append(`<option value="${item.id}">${item.sku} - ${item.nombre_producto}</option>`);
                });

                aplicarFiltros();
            } else {
                alert('Error al cargar datos: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error(error);
            alert('Error de conexión al cargar datos.');
        }
    });
}

function cargarSucursales() {
    $.ajax({
        url: 'ajax/precios_get_sucursales.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const select = $('#sucursalSelect');
                select.empty().append('<option value="">Global (Aplica a todas)</option>');
                response.data.forEach(suc => {
                    select.append(`<option value="${suc.codigo}">${suc.nombre}</option>`);
                });
            }
        }
    });
}

function aplicarFiltros() {
    // Aquí se implementaría la lógica de filtrado por columna si fuera necesario
    // Por ahora, solo pasa todos los datos
    datosFiltrados = [...datosOriginales];
    paginaActual = 1;
    renderizarTabla();
}

function renderizarTabla() {
    const tbody = $('#tablaPreciosBody');
    tbody.empty();

    const inicio = (paginaActual - 1) * registrosPorPagina;
    const fin = inicio + registrosPorPagina;
    const datosPagina = datosFiltrados.slice(inicio, fin);

    if (datosPagina.length === 0) {
        tbody.append('<tr><td colspan="6" class="text-center py-4 text-muted">No se encontraron productos</td></tr>');
        actualizarPaginacion();
        return;
    }

    datosPagina.forEach(item => {
        let precioGlobalHtml = item.precio_global 
            ? `<span class="precio-monto">C$ ${parseFloat(item.precio_global).toFixed(2)}</span>` 
            : '<span class="text-muted fst-italic">Sin precio</span>';
            
        let overridesHtml = '';
        if (item.overrides && item.overrides.length > 0) {
            item.overrides.forEach(ov => {
                overridesHtml += `<span class="badge-sucursal" title="C$ ${parseFloat(ov.precio).toFixed(2)}">${ov.cod_sucursal}</span>`;
            });
        } else {
            overridesHtml = '<span class="text-muted small">Ninguno</span>';
        }

        let actionButtons = '';
        if (typeof permisoNuevoRegistro !== 'undefined' && permisoNuevoRegistro) {
            actionButtons += `
                <button class="btn action-btn text-primary" onclick="abrirModalNuevoPrecio(${item.id})" title="Fijar Precio">
                    <i class="fas fa-tag"></i>
                </button>
            `;
        }
        if (typeof permisoVistaHistorial !== 'undefined' && permisoVistaHistorial) {
            actionButtons += `
                <button class="btn action-btn text-info" onclick="verHistorial(${item.id}, '${item.nombre_producto}')" title="Ver Historial">
                    <i class="fas fa-history"></i>
                </button>
            `;
        }

        const tr = `
            <tr>
                <td class="fw-medium text-secondary">${item.sku || 'N/A'}</td>
                <td class="fw-bold">${item.nombre_producto}</td>
                <td>${precioGlobalHtml}</td>
                <td>${item.fecha_desde || '-'}</td>
                <td>${overridesHtml}</td>
                <td>
                    ${actionButtons || '<span class="text-muted small">Sin permisos</span>'}
                </td>
            </tr>
        `;
        tbody.append(tr);
    });

    actualizarPaginacion();
}

function actualizarPaginacion() {
    const totalPaginas = Math.ceil(datosFiltrados.length / registrosPorPagina);
    const paginacion = $('#paginacion');
    paginacion.empty();

    if (totalPaginas <= 1) return;

    let html = '<ul class="pagination pagination-sm mb-0">';
    
    // Anterior
    html += `<li class="page-item ${paginaActual === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="cambiarPagina(${paginaActual - 1}); return false;">Anterior</a>
            </li>`;

    // Páginas (simplificado)
    for (let i = 1; i <= totalPaginas; i++) {
        if (i === 1 || i === totalPaginas || (i >= paginaActual - 2 && i <= paginaActual + 2)) {
            html += `<li class="page-item ${paginaActual === i ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="cambiarPagina(${i}); return false;">${i}</a>
                    </li>`;
        } else if (i === paginaActual - 3 || i === paginaActual + 3) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }

    // Siguiente
    html += `<li class="page-item ${paginaActual === totalPaginas ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="cambiarPagina(${paginaActual + 1}); return false;">Siguiente</a>
            </li>`;
            
    html += '</ul>';
    paginacion.append(html);
}

function cambiarPagina(pagina) {
    const totalPaginas = Math.ceil(datosFiltrados.length / registrosPorPagina);
    if (pagina >= 1 && pagina <= totalPaginas) {
        paginaActual = pagina;
        renderizarTabla();
    }
}

function cambiarRegistrosPorPagina() {
    registrosPorPagina = parseInt($('#registrosPorPagina').val());
    paginaActual = 1;
    renderizarTabla();
}

function abrirModalNuevoPrecio(idProducto = null) {
    $('#formPrecio')[0].reset();
    
    // Set default date to today
    const today = new Date().toISOString().split('T')[0];
    $('#fechaDesde').val(today);
    
    if (idProducto) {
        $('#productoPresentacion').val(idProducto);
    }
    
    const modal = new bootstrap.Modal(document.getElementById('modalPrecio'));
    modal.show();
}

function guardarPrecio() {
    const form = $('#formPrecio')[0];
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const data = {
        id_producto_presentacion: $('#productoPresentacion').val(),
        cod_sucursal: $('#sucursalSelect').val(),
        precio: $('#montoPrecio').val(),
        fecha_desde: $('#fechaDesde').val()
    };

    $.ajax({
        url: 'ajax/precios_guardar.php',
        type: 'POST',
        data: data,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalPrecio')).hide();
                cargarDatos(); // Recargar la tabla
                alert('Precio fijado exitosamente.');
            } else {
                alert('Error al guardar: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error(error);
            alert('Error de conexión al guardar el precio.');
        }
    });
}

function verHistorial(idProducto, nombreProducto) {
    $('#modalHistorialSubtitulo').text(nombreProducto);
    const tbody = $('#tablaHistorialBody');
    tbody.empty().append('<tr><td colspan="5" class="text-center py-3"><div class="spinner-border text-primary spinner-border-sm" role="status"></div> Cargando...</td></tr>');
    
    const modal = new bootstrap.Modal(document.getElementById('modalHistorial'));
    modal.show();

    $.ajax({
        url: 'ajax/precios_get_historial.php',
        type: 'GET',
        data: { id_producto: idProducto },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                tbody.empty();
                if (response.data.length === 0) {
                    tbody.append('<tr><td colspan="5" class="text-center text-muted">No hay historial de precios para este producto.</td></tr>');
                    return;
                }

                response.data.forEach(item => {
                    const alcance = item.cod_sucursal ? `<span class="badge-sucursal">${item.cod_sucursal}</span>` : '<span class="badge-global">Global</span>';
                    const fechaHasta = item.fecha_hasta ? item.fecha_hasta : '<span class="badge bg-success">Vigente</span>';
                    
                    tbody.append(`
                        <tr>
                            <td>${alcance}</td>
                            <td class="fw-bold">C$ ${parseFloat(item.precio).toFixed(2)}</td>
                            <td>${item.fecha_desde}</td>
                            <td>${fechaHasta}</td>
                            <td class="small text-muted">${item.fecha_hora_reg}</td>
                        </tr>
                    `);
                });
            } else {
                tbody.empty().append(`<tr><td colspan="5" class="text-danger text-center">Error: ${response.message}</td></tr>`);
            }
        },
        error: function() {
            tbody.empty().append('<tr><td colspan="5" class="text-danger text-center">Error de conexión</td></tr>');
        }
    });
}
