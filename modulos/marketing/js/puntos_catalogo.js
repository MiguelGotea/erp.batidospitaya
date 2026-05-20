// puntos_catalogo.js

let datosOriginales = [];
let datosFiltrados = [];
let paginaActual = 1;
let registrosPorPagina = 25;

$(document).ready(function() {
    cargarCatalogo();
    cargarProductosPos();
});

function cargarCatalogo() {
    $.ajax({
        url: 'ajax/puntos_get_catalogo.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                datosOriginales = response.data;
                aplicarFiltros();
            } else {
                alert('Error al cargar catálogo: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error(error);
            alert('Error de conexión al cargar el catálogo.');
        }
    });
}

function cargarProductosPos() {
    // Usamos el mismo endpoint de grupos y sacamos los productos
    $.ajax({
        url: 'ajax/puntos_get_grupos_productos.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const selectProd = $('#productoSelect');
                selectProd.empty().append('<option value="">Ninguno (Servicio o Premio Externo)</option>');
                
                Object.values(response.data).forEach(grupo => {
                    const optgroup = $('<optgroup>').attr('label', grupo.nombre);
                    grupo.productos.forEach(prod => {
                        optgroup.append(`<option value="${prod.id}">${prod.sku} - ${prod.nombre}</option>`);
                    });
                    selectProd.append(optgroup);
                });
            }
        }
    });
}

function aplicarFiltros() {
    datosFiltrados = [...datosOriginales];
    paginaActual = 1;
    renderizarTabla();
}

function renderizarTabla() {
    const tbody = $('#tablaCatalogoBody');
    tbody.empty();

    const inicio = (paginaActual - 1) * registrosPorPagina;
    const fin = inicio + registrosPorPagina;
    const datosPagina = datosFiltrados.slice(inicio, fin);

    if (datosPagina.length === 0) {
        tbody.append('<tr><td colspan="6" class="text-center py-4 text-muted">No se encontraron premios</td></tr>');
        actualizarPaginacion();
        return;
    }

    datosPagina.forEach(item => {
        let productoHtml = item.producto_nombre 
            ? `<span class="badge-producto"><i class="fas fa-box me-1"></i>${item.producto_nombre}</span>` 
            : '<span class="text-muted small fst-italic">Sin vincular</span>';
            
        // En BD guardado como 1 (activo) o 0 (inactivo)
        let statusHtml = (item.activo == 1) 
            ? '<span class="badge-status-active">Activo</span>'
            : '<span class="badge-status-closed">Inactivo</span>';

        let actionButtons = '';
        if (typeof permisoEditarRegistro !== 'undefined' && permisoEditarRegistro) {
            actionButtons += `
                <button class="btn action-btn text-primary" onclick='editarItem(${JSON.stringify(item)})' title="Editar Premio">
                    <i class="fas fa-pen"></i>
                </button>
            `;
        }

        const tr = `
            <tr>
                <td class="fw-bold text-muted text-center">${item.orden}</td>
                <td class="fw-bold">${item.nombre}</td>
                <td><span class="puntos-monto">${parseFloat(item.puntos_requeridos).toFixed(2)}</span></td>
                <td>${productoHtml}</td>
                <td>${statusHtml}</td>
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
    html += `<li class="page-item ${paginaActual === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="cambiarPagina(${paginaActual - 1}); return false;">Anterior</a>
            </li>`;

    for (let i = 1; i <= totalPaginas; i++) {
        if (i === 1 || i === totalPaginas || (i >= paginaActual - 2 && i <= paginaActual + 2)) {
            html += `<li class="page-item ${paginaActual === i ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="cambiarPagina(${i}); return false;">${i}</a>
                    </li>`;
        } else if (i === paginaActual - 3 || i === paginaActual + 3) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }

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

function abrirModalNuevoItem() {
    $('#formCatalogo')[0].reset();
    $('#itemId').val('');
    $('#modalCatalogoTitulo').text('Nuevo Premio');
    $('#activo').prop('checked', true);
    
    // Sugerir el siguiente número de orden
    let maxOrden = 0;
    datosOriginales.forEach(i => { if(i.orden > maxOrden) maxOrden = i.orden; });
    $('#orden').val(maxOrden + 1);

    const modal = new bootstrap.Modal(document.getElementById('modalCatalogo'));
    modal.show();
}

function editarItem(item) {
    $('#formCatalogo')[0].reset();
    $('#itemId').val(item.id);
    $('#modalCatalogoTitulo').text('Editar Premio');
    
    $('#nombre').val(item.nombre);
    $('#productoSelect').val(item.id_producto_canjeable || '');
    $('#puntos').val(parseFloat(item.puntos_requeridos).toFixed(2));
    $('#orden').val(item.orden);
    $('#activo').prop('checked', item.activo == 1);
    
    const modal = new bootstrap.Modal(document.getElementById('modalCatalogo'));
    modal.show();
}

function guardarCatalogo() {
    const form = $('#formCatalogo')[0];
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);
    // Añadir el checkbox explícitamente ya que si no está marcado no se envía en FormData
    formData.set('activo', $('#activo').is(':checked') ? 1 : 0);

    $.ajax({
        url: 'ajax/puntos_guardar_catalogo.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalCatalogo')).hide();
                cargarCatalogo(); 
                alert('Premio guardado exitosamente.');
            } else {
                alert('Error al guardar: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error(error);
            alert('Error de conexión al guardar.');
        }
    });
}
