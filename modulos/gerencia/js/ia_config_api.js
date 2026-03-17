/**
 * modulos/gerencia/js/ia_config_api.js
 * Lógica estándar para la gestión de proveedores de IA con AJAX, Filtros y Paginación.
 */

let paginaActual = 1;
let registrosPorPagina = 25;
let filtrosActivos = {};
let ordenActivo = { columna: 'id', direccion: 'asc' };
let panelFiltroAbierto = null;
let totalRegistros = 0;

$(document).ready(function () {
    cargarDatos();

    // Cerrar filtros al hacer clic fuera
    $(document).on('mousedown', function (e) {
        if (!$(e.target).closest('.filter-panel, .filter-icon').length) {
            cerrarTodosFiltros();
        }
    });

    // Manejar el cambio de label en el switch del modal de edición
    const switchActiva = document.getElementById('editActiva');
    if (switchActiva) {
        switchActiva.addEventListener('change', function () {
            document.getElementById('editActivaLabel').textContent = this.checked ? 'Si' : 'No';
        });
    }
});

/**
 * Carga los datos de la tabla vía AJAX
 */
function cargarDatos() {
    const tbody = $('#tablaApisBody');
    // Mostrar loading
    tbody.html(`
        <tr>
            <td colspan="7" class="text-center py-5">
                <i class="fas fa-spinner fa-spin fa-2x text-primary mb-2"></i>
                <p class="text-muted small">Cargando datos...</p>
            </td>
        </tr>
    `);

    $.ajax({
        url: 'ajax/ia_config_api_get_datos.php',
        method: 'POST',
        data: {
            pagina: paginaActual,
            registros_por_pagina: registrosPorPagina,
            filtros: JSON.stringify(filtrosActivos),
            orden: JSON.stringify(ordenActivo)
        },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                totalRegistros = response.total_registros;
                renderizarTabla(response.datos);
                renderizarPaginacion(response.total_registros);
            } else {
                tbody.html(`<tr><td colspan="7" class="text-center text-danger py-4">Error: ${response.message}</td></tr>`);
            }
        },
        error: function (xhr, status, error) {
            tbody.html(`<tr><td colspan="7" class="text-center text-danger py-4">Error de comunicación: ${status} - ${error}<br><small>${xhr.responseText.substring(0, 200)}</small></td></tr>`);
        }
    });
}

/**
 * Renderiza las filas de la tabla
 */
function renderizarTabla(datos) {
    const tbody = $('#tablaApisBody');
    tbody.empty();

    if (datos.length === 0) {
        tbody.append('<tr><td colspan="7" class="text-center py-5 text-muted">No se encontraron resultados.</td></tr>');
        return;
    }

    datos.forEach(p => {
        const apiKeyHidden = p.api_key.substring(0, 8) + '...' + p.api_key.slice(-4);
        const statusBadge = p.limite_alcanzado_hoy == 1 
            ? '<span class="badge badge-warning">AGOTADA</span>' 
            : '<span class="badge badge-success">DISPONIBLE</span>';
        
        const fechaUso = p.ultimo_uso ? moment(p.ultimo_uso).format('DD/MM/YYYY HH:mm') : 'Nunca usado';

        const row = `
            <tr id="row-${p.id}">
                <td class="fw-bold text-dark">${p.proveedor}</td>
                <td class="small text-muted">${p.cuenta_correo || '-'}</td>
                <td class="font-monospace small">${apiKeyHidden}</td>
                <td>
                    <div class="form-check form-switch table-switch">
                        <input class="form-check-input" type="checkbox" 
                               onchange="toggleStatus(${p.id}, this)"
                               ${p.activa == 1 ? 'checked' : ''}>
                    </div>
                </td>
                <td>${statusBadge}</td>
                <td class="small text-muted">${fechaUso}</td>
                <td>
                    <div class="action-btns" style="justify-content: center;">
                        ${p.limite_alcanzado_hoy == 1 ? `
                            <button class="btn-action ping text-warning" onclick="reiniciarLimite(${p.id})" title="Reiniciar Límite">
                                <i class="fas fa-sync-alt"></i>
                            </button>` : ''}
                        <button class="btn-action ping" onclick="probarConexion(${p.id})" title="Probar Conexión">
                            <i class="fas fa-bolt"></i>
                        </button>
                        <button class="btn-action edit" onclick='editar(${JSON.stringify(p)})' title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-action delete" onclick="eliminarProveedor(${p.id})" title="Eliminar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
        tbody.append(row);
    });
}

/**
 * Renderiza el control de paginación
 */
function renderizarPaginacion(total) {
    const paginacion = $('#paginacion');
    paginacion.empty();

    const totalPaginas = Math.ceil(total / registrosPorPagina);
    if (totalPaginas <= 1) return;

    let html = '<div class="btn-group shadow-sm">';
    
    // Botón Anterior
    html += `<button class="btn btn-white btn-sm px-3" ${paginaActual === 1 ? 'disabled' : ''} onclick="cambiarPagina(${paginaActual - 1})">
                <i class="fas fa-chevron-left"></i>
             </button>`;

    // Páginas (simplificado)
    for (let i = 1; i <= totalPaginas; i++) {
        if (i === 1 || i === totalPaginas || (i >= paginaActual - 1 && i <= paginaActual + 1)) {
            html += `<button class="btn ${i === paginaActual ? 'btn-primary' : 'btn-white'} btn-sm px-3" onclick="cambiarPagina(${i})">${i}</button>`;
        } else if (i === paginaActual - 2 || i === paginaActual + 2) {
            html += `<button class="btn btn-white btn-sm" disabled>...</button>`;
        }
    }

    // Botón Siguiente
    html += `<button class="btn btn-white btn-sm px-3" ${paginaActual === totalPaginas ? 'disabled' : ''} onclick="cambiarPagina(${paginaActual + 1})">
                <i class="fas fa-chevron-right"></i>
             </button>`;

    html += '</div>';
    paginacion.append(html);
}

function cambiarPagina(p) {
    paginaActual = p;
    cargarDatos();
}

function cambiarRegistrosPorPagina() {
    registrosPorPagina = parseInt($('#registrosPorPagina').val());
    paginaActual = 1;
    cargarDatos();
}

/**
 * LÓGICA DE FILTROS ESTÁNDAR
 */

function toggleFilter(icon) {
    const th = $(icon).closest('th');
    const columna = th.data('column');
    const tipo = th.data('type');

    if (panelFiltroAbierto === columna) {
        cerrarTodosFiltros();
        return;
    }

    cerrarTodosFiltros();
    crearPanelFiltro(th, columna, tipo, icon);
    panelFiltroAbierto = columna;
    $(icon).addClass('active');
}

function cerrarTodosFiltros() {
    $('.filter-panel').remove();
    $('.filter-icon').removeClass('active');
    panelFiltroAbierto = null;
}

function crearPanelFiltro(th, columna, tipo, icon) {
    const panel = $('<div class="filter-panel show"></div>');
    
    // Título / Ordenamiento
    panel.append(`
        <div class="filter-section">
            <span class="filter-section-title">ORDENAR</span>
            <div class="filter-sort-buttons">
                <button class="filter-sort-btn ${ordenActivo.columna === columna && ordenActivo.direccion === 'asc' ? 'active' : ''}" 
                        onclick="aplicarOrden('${columna}', 'asc')">
                    <i class="fas fa-sort-amount-down-alt"></i> Asc
                </button>
                <button class="filter-sort-btn ${ordenActivo.columna === columna && ordenActivo.direccion === 'desc' ? 'active' : ''}" 
                        onclick="aplicarOrden('${columna}', 'desc')">
                    <i class="fas fa-sort-amount-up"></i> Desc
                </button>
            </div>
        </div>
    `);

    // Filtros por Tipo
    panel.append('<div class="filter-section"><span class="filter-section-title">FILTRAR</span></div>');

    if (tipo === 'text') {
        const valor = filtrosActivos[columna] || '';
        panel.find('.filter-section:last').append(`
            <input type="text" class="filter-search" placeholder="Buscar..." 
                   value="${valor}" id="input-filtro-${columna}">
        `);
    } else if (tipo === 'list') {
        const opciones = obtenerOpcionesUnicasParaLista(columna);
        let html = '<div class="filter-options">';
        opciones.forEach(opt => {
            const checked = (filtrosActivos[columna] && filtrosActivos[columna].includes(opt)) ? 'checked' : '';
            html += `
                <label class="filter-option">
                    <input type="checkbox" value="${opt}" ${checked} class="check-filtro-${columna}">
                    <span>${opt}</span>
                </label>
            `;
        });
        html += '</div>';
        panel.find('.filter-section:last').append(html);
    } else if (tipo === 'daterange') {
        const d = filtrosActivos[columna] || { desde: '', hasta: '' };
        panel.find('.filter-section:last').append(`
            <div class="daterange-inputs">
                <div class="mb-2">
                    <label class="small text-muted mb-1">Desde:</label>
                    <input type="date" class="form-control form-control-sm" value="${d.desde || ''}" id="fecha-desde-${columna}">
                </div>
                <div>
                    <label class="small text-muted mb-1">Hasta:</label>
                    <input type="date" class="form-control form-control-sm" value="${d.hasta || ''}" id="fecha-hasta-${columna}">
                </div>
            </div>
        `);
    }

    // Botones de Acción
    panel.append(`
        <div class="filter-actions">
            <button class="filter-action-btn clear" onclick="limpiarFiltro('${columna}')">
                <i class="fas fa-broom"></i> Limpiar
            </button>
            <button class="filter-action-btn btn-primary text-white" onclick="aplicarFiltro('${columna}', '${tipo}')">
                <i class="fas fa-filter"></i> Aplicar
            </button>
        </div>
    `);

    $('body').append(panel);
    posicionarPanelFiltro(panel, icon);
}

function posicionarPanelFiltro(panel, icon) {
    const offset = $(icon).offset();
    const panelWidth = panel.outerWidth();
    const windowWidth = $(window).width();
    
    let left = offset.left - (panelWidth / 2) + 10;
    
    // Ajuste si se sale por la derecha
    if (left + panelWidth > windowWidth - 20) {
        left = windowWidth - panelWidth - 20;
    }
    // Ajuste si se sale por la izquierda
    if (left < 20) left = 20;

    panel.css({
        top: (offset.top + 30) + 'px',
        left: left + 'px'
    });
}

function aplicarOrden(columna, direccion) {
    ordenActivo = { columna, direccion };
    cerrarTodosFiltros();
    paginaActual = 1;
    cargarDatos();
}

function aplicarFiltro(columna, tipo) {
    if (tipo === 'text') {
        const val = $(`#input-filtro-${columna}`).val().trim();
        if (val === '') delete filtrosActivos[columna];
        else filtrosActivos[columna] = val;
    } else if (tipo === 'list') {
        const seleccionados = [];
        $(`.check-filtro-${columna}:checked`).each(function() {
            seleccionados.push($(this).val());
        });
        if (seleccionados.length === 0) delete filtrosActivos[columna];
        else filtrosActivos[columna] = seleccionados;
    } else if (tipo === 'daterange') {
        const desde = $(`#fecha-desde-${columna}`).val();
        const hasta = $(`#fecha-hasta-${columna}`).val();
        if (!desde && !hasta) delete filtrosActivos[columna];
        else filtrosActivos[columna] = { desde, hasta };
    }

    // Actualizar icono en el header
    if (filtrosActivos[columna]) {
        $(`th[data-column="${columna}"] .filter-icon`).addClass('has-filter');
    } else {
        $(`th[data-column="${columna}"] .filter-icon`).removeClass('has-filter');
    }

    cerrarTodosFiltros();
    paginaActual = 1;
    cargarDatos();
}

function limpiarFiltro(columna) {
    delete filtrosActivos[columna];
    $(`th[data-column="${columna}"] .filter-icon`).removeClass('has-filter');
    cerrarTodosFiltros();
    paginaActual = 1;
    cargarDatos();
}

function obtenerOpcionesUnicasParaLista(columna) {
    if (columna === 'activa') return ['SI', 'NO'];
    if (columna === 'estado') return ['DISPONIBLE', 'AGOTADA'];
    return [];
}

/**
 * ACCIONES DE REGISTRO
 */

function guardarProveedor() {
    const form = document.getElementById('apiForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);
    formData.append('is_ajax', '1');

    $.ajax({
        url: 'ajax/ia_config_api_handler.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                bootstrap.Modal.getInstance(document.getElementById('apiModal')).hide();
                cargarDatos();
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function () {
            alert('Error de red al guardar');
        }
    });
}

function nuevoProveedor() {
    limpiarForm();
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('apiModal'));
    document.getElementById('apiModalLabel').textContent = "Registrar Nuevo Proveedor";
    modal.show();
}

function editar(data) {
    document.getElementById('apiModalLabel').textContent = "Editar Proveedor: " + data.proveedor.toUpperCase();
    document.getElementById('editId').value = data.id;
    document.getElementById('editProveedor').value = data.proveedor;
    document.getElementById('editEmail').value = data.cuenta_correo || '';
    document.getElementById('editKey').value = data.api_key;
    document.getElementById('editPassword').value = data.password || '';

    const switchActiva = document.getElementById('editActiva');
    switchActiva.checked = data.activa == 1;
    document.getElementById('editActivaLabel').textContent = switchActiva.checked ? 'Si' : 'No';

    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('apiModal'));
    modal.show();
}

function limpiarForm() {
    document.getElementById('apiForm').reset();
    document.getElementById('editId').value = '';
    document.getElementById('editActiva').checked = true;
    document.getElementById('editActivaLabel').textContent = 'Si';
}

function toggleStatus(id, checkbox) {
    const activa = checkbox.checked ? 1 : 0;
    
    $.ajax({
        url: 'ajax/ia_config_api_handler.php',
        method: 'POST',
        data: { accion: 'toggle_status', id: id, activa: activa },
        dataType: 'json',
        success: function(response) {
            if (!response.success) {
                alert('Error: ' + response.message);
                checkbox.checked = !checkbox.checked;
            }
            // Opcional: refrescar datos para actualizar labels o estados relacionados
            // cargarDatos(); 
        },
        error: function() {
            alert('Error de red');
            checkbox.checked = !checkbox.checked;
        }
    });
}

function reiniciarLimite(id) {
    if (!confirm('¿Reiniciar límite diario para este proveedor?')) return;

    $.ajax({
        url: 'ajax/ia_config_api_handler.php',
        method: 'POST',
        data: { accion: 'reiniciar_limite', id: id },
        dataType: 'json',
        success: function (response) {
            if (response.success) cargarDatos();
            else alert('Error: ' + response.message);
        }
    });
}

function eliminarProveedor(id) {
    if (!confirm('¿Estás seguro de eliminar este proveedor?')) return;

    $.ajax({
        url: 'ajax/ia_config_api_handler.php',
        method: 'POST',
        data: { accion: 'eliminar', id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success) cargarDatos();
            else alert('Error: ' + response.message);
        }
    });
}

function probarConexion(id) {
    const btn = event.currentTarget;
    const originalIcon = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btn.disabled = true;

    $.ajax({
        url: 'ajax/ia_config_api_handler.php',
        method: 'POST',
        data: { accion: 'test', id: id },
        dataType: 'json',
        success: function (response) {
            mostrarModalResultado(response.success, response.message);
        },
        error: function () {
            mostrarModalResultado(false, 'Error de conexión');
        },
        complete: function () {
            btn.innerHTML = originalIcon;
            btn.disabled = false;
        }
    });
}

function mostrarModalResultado(success, message) {
    const header = document.getElementById('pingModalHeader');
    const iconDiv = document.getElementById('pingModalIcon');
    const title = document.getElementById('pingModalTitle');
    const msg = document.getElementById('pingModalMessage');

    if (success) {
        header.className = 'modal-header border-0 bg-success';
        iconDiv.innerHTML = '<i class="fas fa-check-circle text-success fs-1"></i>';
        title.textContent = '¡Éxito!';
    } else {
        header.className = 'modal-header border-0 bg-danger';
        iconDiv.innerHTML = '<i class="fas fa-times-circle text-danger fs-1"></i>';
        title.textContent = 'Error';
    }

    msg.textContent = message;
    const modal = new bootstrap.Modal(document.getElementById('pingResultModal'));
    modal.show();
}
