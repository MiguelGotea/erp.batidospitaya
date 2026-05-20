// puntos_reglas.js

let datosOriginales = [];
let datosFiltrados = [];
let paginaActual = 1;
let registrosPorPagina = 25;
let mapGrupos = {}; 

$(document).ready(function() {
    cargarReglas();
    cargarCatalogosModales();
});

function cargarReglas() {
    $.ajax({
        url: 'ajax/puntos_get_reglas.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                datosOriginales = response.data;
                aplicarFiltros();
            } else {
                alert('Error al cargar reglas: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error(error);
            alert('Error de conexión al cargar reglas.');
        }
    });
}

function cargarCatalogosModales() {
    $.ajax({
        url: 'ajax/puntos_get_grupos_productos.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                mapGrupos = response.data;
                const selectGrupo = $('#grupoSelect');
                selectGrupo.empty().append('<option value="">Seleccione un grupo de productos</option>');
                
                Object.values(mapGrupos).forEach(grupo => {
                    selectGrupo.append(`<option value="${grupo.id}">${grupo.nombre}</option>`);
                });
            }
        }
    });
}

function filtrarProductosPorGrupo() {
    const idGrupo = $('#grupoSelect').val();
    const selectProd = $('#productoSelect');
    selectProd.empty().append('<option value="">Aplica para todo el grupo</option>');
    
    if (idGrupo && mapGrupos[idGrupo]) {
        mapGrupos[idGrupo].productos.forEach(prod => {
            selectProd.append(`<option value="${prod.id}">${prod.sku} - ${prod.nombre}</option>`);
        });
    }
}

function aplicarFiltros() {
    datosFiltrados = [...datosOriginales];
    paginaActual = 1;
    renderizarTabla();
}

function renderizarTabla() {
    const tbody = $('#tablaReglasBody');
    tbody.empty();

    const inicio = (paginaActual - 1) * registrosPorPagina;
    const fin = inicio + registrosPorPagina;
    const datosPagina = datosFiltrados.slice(inicio, fin);

    if (datosPagina.length === 0) {
        tbody.append('<tr><td colspan="7" class="text-center py-4 text-muted">No se encontraron reglas</td></tr>');
        actualizarPaginacion();
        return;
    }

    datosPagina.forEach(item => {
        let excepcionHtml = item.producto_nombre 
            ? `<span class="badge-producto"><i class="fas fa-exclamation-circle me-1"></i>${item.producto_nombre}</span>` 
            : '<span class="text-muted small">Ninguna (General)</span>';
            
        let statusHtml = item.es_vigente 
            ? '<span class="badge-status-active">Vigente</span>'
            : '<span class="badge-status-closed">Cerrada</span>';

        let actionButtons = '';
        if (typeof permisoEditarRegistro !== 'undefined' && permisoEditarRegistro) {
            // Pasamos todo el objeto item codificado
            actionButtons += `
                <button class="btn action-btn text-primary" onclick='editarRegla(${JSON.stringify(item)})' title="Editar Regla">
                    <i class="fas fa-pen"></i>
                </button>
            `;
        }

        const tr = `
            <tr>
                <td><span class="badge-grupo">${item.grupo_nombre}</span></td>
                <td>${excepcionHtml}</td>
                <td><span class="puntos-monto">${parseFloat(item.puntos).toFixed(2)}</span></td>
                <td>${item.fecha_desde}</td>
                <td class="${!item.fecha_hasta ? 'text-muted fst-italic' : ''}">${item.fecha_hasta || 'Indefinido'}</td>
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

function cambiarRegistrosPorPagina() {
    registrosPorPagina = parseInt($('#registrosPorPagina').val());
    paginaActual = 1;
    renderizarTabla();
}

function abrirModalNuevaRegla() {
    $('#formRegla')[0].reset();
    $('#reglaId').val('');
    $('#modalReglaTitulo').text('Nueva Regla de Acumulación');
    $('#grupoSelect').prop('disabled', false);
    $('#productoSelect').empty().append('<option value="">Aplica para todo el grupo</option>').prop('disabled', false);
    
    const today = new Date().toISOString().split('T')[0];
    $('#fechaDesde').val(today);
    
    const modal = new bootstrap.Modal(document.getElementById('modalRegla'));
    modal.show();
}

function editarRegla(item) {
    $('#formRegla')[0].reset();
    $('#reglaId').val(item.id);
    $('#modalReglaTitulo').text('Editar Regla de Acumulación');
    
    $('#grupoSelect').val(item.id_grupo).prop('disabled', true); // No se permite cambiar el grupo de una regla existente
    filtrarProductosPorGrupo();
    
    if(item.id_producto) {
        $('#productoSelect').val(item.id_producto);
    } else {
        $('#productoSelect').val('');
    }
    $('#productoSelect').prop('disabled', true); // Tampoco el producto
    
    $('#puntos').val(parseFloat(item.puntos).toFixed(2));
    $('#fechaDesde').val(item.fecha_desde);
    if(item.fecha_hasta) {
        $('#fechaHasta').val(item.fecha_hasta);
    }
    
    const modal = new bootstrap.Modal(document.getElementById('modalRegla'));
    modal.show();
}

function guardarRegla() {
    const form = $('#formRegla')[0];
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    // Como disabled inputs don't serialize, we get them manually
    const data = {
        id: $('#reglaId').val(),
        id_grupo: $('#grupoSelect').val(),
        id_producto: $('#productoSelect').val(),
        puntos: $('#puntos').val(),
        fecha_desde: $('#fechaDesde').val(),
        fecha_hasta: $('#fechaHasta').val()
    };

    $.ajax({
        url: 'ajax/puntos_guardar_regla.php',
        type: 'POST',
        data: data,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalRegla')).hide();
                cargarReglas(); 
                alert('Regla guardada exitosamente.');
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
