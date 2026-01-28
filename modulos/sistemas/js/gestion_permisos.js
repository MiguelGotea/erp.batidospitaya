// Variables globales
let herramientaActual = null;
let accionActual = null;
let cambiosPendientes = false;
let permisosOriginales = {};
let permisosModificados = {};
let datosHerramientas = {
    herramientas: {},
    indicadores: {},
    balances: {}
};

// Mapeo de tipos plural a singular para la API
const tipoApiMap = {
    'herramientas': 'herramienta',
    'indicadores': 'indicador',
    'balances': 'balance'
};

/**
 * Cambiar tab personalizado
 */
function cambiarTab(tipo) {
    console.log('Cambiando a tab:', tipo);
    
    // Desactivar todos los tabs
    $('.tab-btn-custom').removeClass('tab-active');
    $('.tab-content-custom').removeClass('tab-content-active');
    
    // Activar el tab seleccionado
    $('#tab-' + tipo).addClass('tab-active');
    $('#content-' + tipo).addClass('tab-content-active');
    
    // Cargar datos si no existen
    if (Object.keys(datosHerramientas[tipo]).length === 0) {
        cargarHerramientasPorTipo(tipo);
    }
}

// Cargar al inicio
$(document).ready(function() {
    cargarEstructuraHerramientas();
    
    // Búsqueda de herramientas por tipo
    $('.buscar-input').on('input', function() {
        const tipo = $(this).data('tipo');
        filtrarHerramientas($(this).val(), tipo);
    });
});

/**
 * Función auxiliar para obtener containerId
 */
function getContainerId(tipo) {
    switch(tipo) {
        case 'herramientas': return '#treeHerramientas';
        case 'indicadores': return '#treeIndicadores';
        case 'balances': return '#treeBalances';
        default: return '#treeHerramientas';
    }
}

/**
 * Cargar árbol de grupos y herramientas (tipo herramienta por defecto)
 */
function cargarEstructuraHerramientas() {
    cargarHerramientasPorTipo('herramientas');
}

/**
 * Cargar herramientas por tipo de componente
 */
function cargarHerramientasPorTipo(tipo) {
    const containerId = getContainerId(tipo);
    const tipoApi = tipoApiMap[tipo];
    
    console.log('Cargando tipo:', tipo, '- API tipo:', tipoApi, '- Container:', containerId);
    
    $.ajax({
        url: 'ajax/obtener_estructura_permisos.php',
        method: 'GET',
        data: { tipo_componente: tipoApi },
        dataType: 'json',
        success: function(response) {
            console.log('Response recibida:', response);
            if (response.success) {
                datosHerramientas[tipo] = response.data;
                renderizarArbolHerramientas(response.data, containerId, tipo);
            } else {
                mostrarError('Error al cargar ' + tipo + ': ' + response.message);
                $(containerId).html('<div class="alert alert-danger m-3">Error: ' + response.message + '</div>');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX:', {
                status: status,
                error: error,
                responseText: xhr.responseText,
                url: 'ajax/obtener_estructura_permisos.php'
            });
            mostrarError('Error de conexión al cargar ' + tipo);
            $(containerId).html('<div class="alert alert-danger m-3">Error de conexión. Verifica que el archivo ajax/obtener_estructura_permisos.php exista.</div>');
        }
    });
}

/**
 * Renderizar árbol de herramientas agrupadas
 */
function renderizarArbolHerramientas(grupos, containerId, tipo) {
    const container = $(containerId);
    container.empty();
    
    if (Object.keys(grupos).length === 0) {
        container.html('<p class="text-center text-muted p-3">No hay ' + tipo + 's disponibles</p>');
        return;
    }
    
    Object.keys(grupos).sort().forEach(nombreGrupo => {
        const herramientas = grupos[nombreGrupo];
        
        const grupoHtml = `
            <div class="tree-group" data-tipo="${tipo}">
                <div class="tree-group-header" onclick="toggleGrupo(this)">
                    <i class="bi bi-chevron-right"></i>
                    <i class="bi bi-folder"></i>
                    <strong>${nombreGrupo}</strong>
                    <span class="badge bg-secondary ms-auto">${herramientas.length}</span>
                </div>
                <div class="tree-group-items">
                    ${herramientas.map(h => `
                        <div class="tree-item" data-id="${h.id}" data-nombre="${h.nombre}" data-descripcion="${h.descripcion || ''}" onclick="seleccionarHerramienta(${h.id}, '${h.nombre}', '${(h.descripcion || '').replace(/'/g, "\\'")}')">
                            <i class="bi bi-file-earmark-code"></i>
                            ${h.nombre}
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
        
        container.append(grupoHtml);
    });
}

/**
 * Toggle grupo en árbol
 */
function toggleGrupo(element) {
    const $header = $(element);
    const $items = $header.next('.tree-group-items');
    
    $header.toggleClass('expanded');
    $items.toggleClass('show');
}

/**
 * Seleccionar herramienta
 */
function seleccionarHerramienta(id, nombre, titulo, descripcion, urlReal) {
    // Verificar cambios pendientes
    if (cambiosPendientes) {
        if (!confirm('Hay cambios sin guardar. ¿Desea continuar sin guardar?')) {
            return;
        }
    }
    
    // Marcar como activa
    $('.tree-item').removeClass('active');
    $(`.tree-item[data-id="${id}"]`).addClass('active');
    
    // Actualizar header con título
    $('#herramientaSeleccionadaNombre').text(titulo || nombre);
    $('#headerActions').show();
    
    // Mostrar descripción y URL si existen
    if ((descripcion && descripcion.trim() !== '') || (urlReal && urlReal.trim() !== '')) {
        if (descripcion && descripcion.trim() !== '') {
            $('#descripcionTexto').text(descripcion);
            $('#descripcionTexto').parent().show();
        } else {
            $('#descripcionTexto').parent().hide();
        }
        
        if (urlReal && urlReal.trim() !== '') {
            $('#urlRealTexto').text(urlReal);
            $('#urlRealTexto').parent().show();
        } else {
            $('#urlRealTexto').parent().hide();
        }
        
        $('#herramientaDescripcion').show();
    } else {
        $('#herramientaDescripcion').hide();
    }
    
    // Guardar datos actuales
    herramientaActual = { id, nombre, titulo, descripcion, urlReal };
    cambiosPendientes = false;
    $('#btnGuardarFlotante').hide();
    
    // Cargar permisos
    cargarPermisosHerramienta(id);
}

/**
 * Cargar permisos de una herramienta
 */
function cargarPermisosHerramienta(toolId) {
    const panel = $('#panelPermisos');
    panel.html('<div class="text-center p-5"><div class="spinner-border" role="status"></div><p class="mt-2">Cargando permisos...</p></div>');
    
    $.ajax({
        url: 'ajax/obtener_permisos_herramienta.php',
        method: 'GET',
        data: { tool_id: toolId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                permisosOriginales = JSON.parse(JSON.stringify(response.data.permisos));
                permisosModificados = JSON.parse(JSON.stringify(response.data.permisos));
                renderizarPanelPermisos(response.data);
            } else {
                mostrarError('Error al cargar permisos: ' + response.message);
            }
        },
        error: function() {
            mostrarError('Error de conexión al cargar permisos');
        }
    });
}

/**
 * Renderizar panel de permisos con tabs
 */
function renderizarPanelPermisos(data) {
    const { acciones, areas, permisos } = data;
    
    if (acciones.length === 0) {
        $('#panelPermisos').html(`
            <div class="empty-state">
                <i class="bi bi-exclamation-circle display-1 text-warning"></i>
                <p class="lead text-muted mt-3">Esta herramienta no tiene acciones configuradas</p>
                ${PUEDE_CREAR_ACCIONES ? '<button class="btn btn-success mt-2" onclick="abrirModalNuevaAccion()"><i class="bi bi-plus-circle"></i> Crear Primera Acción</button>' : ''}
            </div>
        `);
        return;
    }
    
    // Crear tabs
    const tabsHtml = `
        <ul class="nav nav-tabs nav-tabs-custom" role="tablist">
            ${acciones.map((accion, index) => `
                <li class="nav-item" role="presentation">
                    <button class="nav-link ${index === 0 ? 'active' : ''}" 
                            id="tab-${accion.id}" 
                            data-bs-toggle="tab" 
                            data-bs-target="#content-${accion.id}" 
                            type="button">
                        <i class="bi bi-key"></i> ${accion.nombre_accion}
                    </button>
                </li>
            `).join('')}
        </ul>
        <div class="tab-content mt-3">
            ${acciones.map((accion, index) => `
                <div class="tab-pane fade ${index === 0 ? 'show active' : ''}" 
                     id="content-${accion.id}" 
                     role="tabpanel">
                    ${renderizarContenidoAccion(accion, areas, permisos)}
                </div>
            `).join('')}
        </div>
    `;
    
    $('#panelPermisos').html(tabsHtml);
}

/**
 * Renderizar contenido de una acción (áreas y cargos)
 */
function renderizarContenidoAccion(accion, areas, permisos) {
    const stats = calcularEstadisticas(accion.id, permisos);
    
    let html = `
        <div class="permisos-stats">
            <div class="stat-item">
                <div class="stat-number">${stats.total}</div>
                <div class="stat-label">Total Cargos</div>
            </div>
            <div class="stat-item">
                <div class="stat-number text-success">${stats.permitidos}</div>
                <div class="stat-label">Con Permiso</div>
            </div>
            <div class="stat-item">
                <div class="stat-number text-danger">${stats.denegados}</div>
                <div class="stat-label">Sin Permiso</div>
            </div>
        </div>
    `;
    
    // Agrupar áreas
    const areasAgrupadas = {};
    areas.forEach(area => {
        if (!areasAgrupadas[area.Area]) {
            areasAgrupadas[area.Area] = [];
        }
        areasAgrupadas[area.Area].push(area);
    });
    
    // Renderizar cada área
    Object.keys(areasAgrupadas).sort().forEach(nombreArea => {
        const cargosArea = areasAgrupadas[nombreArea];
        const permitidosArea = cargosArea.filter(c => 
            permisos[accion.id] && permisos[accion.id][c.CodNivelesCargos] === 'allow'
        ).length;
        
        // Determinar si todos los cargos tienen permiso
        const todosPermitidos = permitidosArea === cargosArea.length;
        
        html += `
            <div class="area-section" data-accion-id="${accion.id}" data-area-nombre="${nombreArea}">
                <div class="area-header" onclick="toggleArea(this)">
                    <div class="area-header-left">
                        <i class="bi bi-chevron-right"></i>
                        <i class="bi bi-building"></i>
                        <strong>${nombreArea}</strong>
                    </div>
                    <div class="area-header-right">
                        <span class="badge area-badge bg-secondary">${permitidosArea}/${cargosArea.length}</span>
                        <label class="toggle-switch toggle-switch-area ms-2" onclick="event.stopPropagation()">
                            <input type="checkbox" 
                                   ${todosPermitidos ? 'checked' : ''}
                                   onchange="toggleAreaCompleta(${accion.id}, '${nombreArea}', this.checked)">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="area-cargos">
                    ${cargosArea.map(cargo => {
                        const tienePermiso = permisos[accion.id] && permisos[accion.id][cargo.CodNivelesCargos] === 'allow';
                        return `
                            <div class="cargo-item" data-cargo-id="${cargo.CodNivelesCargos}">
                                <span class="cargo-nombre">${cargo.Nombre}</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" 
                                           ${tienePermiso ? 'checked' : ''}
                                           onchange="togglePermiso(${accion.id}, ${cargo.CodNivelesCargos}, this.checked)">
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        `;
                    }).join('')}
                </div>
            </div>
        `;
    });
    
    return html;
}

/**
 * Toggle área (expandir/colapsar)
 */
function toggleArea(element) {
    const $header = $(element);
    const $cargos = $header.next('.area-cargos');
    
    $header.toggleClass('expanded');
    $cargos.toggleClass('show');
}

/**
 * Toggle permiso individual
 */
function togglePermiso(accionId, cargoId, tienePermiso) {
    if (!permisosModificados[accionId]) {
        permisosModificados[accionId] = {};
    }
    
    permisosModificados[accionId][cargoId] = tienePermiso ? 'allow' : 'deny';
    
    // Marcar como cambios pendientes
    cambiosPendientes = true;
    $('#btnGuardarFlotante').fadeIn();
    
    // Actualizar estadísticas
    actualizarEstadisticas();
    
    // Verificar si afecta al switch del área
    actualizarSwitchArea(accionId, cargoId);
}

/**
 * Toggle área completa (todos los cargos del área)
 */
function toggleAreaCompleta(accionId, nombreArea, permitir) {
    // Encontrar todos los cargos del área actual
    const $areaSection = $(`.area-section[data-accion-id="${accionId}"][data-area-nombre="${nombreArea}"]`);
    const $cargoItems = $areaSection.find('.cargo-item');
    
    // Actualizar permisos de todos los cargos del área
    $cargoItems.each(function() {
        const cargoId = parseInt($(this).data('cargo-id'));
        const $checkbox = $(this).find('input[type="checkbox"]');
        
        // Actualizar checkbox
        $checkbox.prop('checked', permitir);
        
        // Actualizar permisos modificados
        if (!permisosModificados[accionId]) {
            permisosModificados[accionId] = {};
        }
        permisosModificados[accionId][cargoId] = permitir ? 'allow' : 'deny';
    });
    
    // Marcar como cambios pendientes
    cambiosPendientes = true;
    $('#btnGuardarFlotante').fadeIn();
    
    // Actualizar estadísticas
    actualizarEstadisticas();
}

/**
 * Actualizar switch de área cuando cambia un cargo individual
 */
function actualizarSwitchArea(accionId, cargoId) {
    // Encontrar el área del cargo modificado
    const $cargoItem = $(`.cargo-item[data-cargo-id="${cargoId}"]`);
    const $areaSection = $cargoItem.closest('.area-section');
    
    if ($areaSection.length === 0) return;
    
    // Contar cuántos cargos tienen permiso en esta área
    const totalCargos = $areaSection.find('.cargo-item').length;
    const cargosPermitidos = $areaSection.find('.cargo-item input[type="checkbox"]:checked').length;
    
    // Actualizar badge
    $areaSection.find('.area-badge').text(`${cargosPermitidos}/${totalCargos}`);
    
    // Actualizar switch del área (solo encendido si TODOS tienen permiso)
    const $switchArea = $areaSection.find('.toggle-switch-area input[type="checkbox"]');
    $switchArea.prop('checked', cargosPermitidos === totalCargos);
}

/**
 * Calcular estadísticas de permisos
 */
function calcularEstadisticas(accionId, permisos) {
    const permisosAccion = permisos[accionId] || {};
    const valores = Object.values(permisosAccion);
    
    return {
        total: valores.length,
        permitidos: valores.filter(p => p === 'allow').length,
        denegados: valores.filter(p => p === 'deny').length
    };
}

/**
 * Actualizar estadísticas en tiempo real
 */
function actualizarEstadisticas() {
    // Actualizar stats generales del tab activo
    const $activeTab = $('.tab-pane.active');
    const totalCargos = $activeTab.find('.cargo-item').length;
    const permitidos = $activeTab.find('input[type="checkbox"]:checked').length;
    const denegados = totalCargos - permitidos;
    
    $activeTab.find('.stat-number').eq(0).text(totalCargos);
    $activeTab.find('.stat-number').eq(1).text(permitidos);
    $activeTab.find('.stat-number').eq(2).text(denegados);
}

/**
 * Guardar cambios de permisos
 */
function guardarCambiosPermisos() {
    if (!cambiosPendientes) {
        return;
    }
    
    const btn = $('#btnGuardarFlotante button');
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Guardando...');
    
    $.ajax({
        url: 'ajax/guardar_permisos.php',
        method: 'POST',
        data: {
            tool_id: herramientaActual.id,
            permisos: JSON.stringify(permisosModificados)
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                mostrarExito('Permisos guardados correctamente');
                cambiosPendientes = false;
                permisosOriginales = JSON.parse(JSON.stringify(permisosModificados));
                $('#btnGuardarFlotante').fadeOut();
            } else {
                mostrarError('Error al guardar: ' + response.message);
            }
        },
        error: function() {
            mostrarError('Error de conexión al guardar');
        },
        complete: function() {
            btn.prop('disabled', false).html('<i class="bi bi-save"></i> Guardar Cambios');
        }
    });
}

/**
 * Abrir modal para crear nueva acción
 */
function abrirModalNuevaAccion() {
    if (!herramientaActual) {
        mostrarError('Seleccione una herramienta primero');
        return;
    }
    
    $('#toolIdNuevaAccion').val(herramientaActual.id);
    $('#nombreHerramientaModal').text(herramientaActual.nombre);
    $('#formNuevaAccion')[0].reset();
    
    const modal = new bootstrap.Modal(document.getElementById('modalNuevaAccion'));
    modal.show();
}

/**
 * Guardar nueva acción
 */
function guardarNuevaAccion() {
    const form = $('#formNuevaAccion');
    
    if (!form[0].checkValidity()) {
        form[0].reportValidity();
        return;
    }
    
    const data = {
        tool_id: $('#toolIdNuevaAccion').val(),
        nombre_accion: $('#nombreAccion').val().trim(),
        descripcion: $('#descripcionAccion').val().trim()
    };
    
    $.ajax({
        url: 'ajax/crear_accion.php',
        method: 'POST',
        data: data,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                mostrarExito('Acción creada correctamente');
                bootstrap.Modal.getInstance(document.getElementById('modalNuevaAccion')).hide();
                // Recargar permisos
                cargarPermisosHerramienta(herramientaActual.id);
            } else {
                mostrarError('Error: ' + response.message);
            }
        },
        error: function() {
            mostrarError('Error de conexión');
        }
    });
}

/**
 * Filtrar herramientas en árbol por tipo
 */
function filtrarHerramientas(texto, tipo) {
    const filtro = texto.toLowerCase().trim();
    const containerId = getContainerId(tipo);
    const $container = $(containerId);
    
    // Si el filtro está vacío, restaurar vista inicial
    if (filtro === '') {
        $container.find('.tree-item').show();
        $container.find('.tree-group').show();
        $container.find('.tree-group-header').removeClass('expanded');
        $container.find('.tree-group-items').removeClass('show');
        return;
    }
    
    // Mostrar todos primero para poder filtrar
    $container.find('.tree-item').show();
    $container.find('.tree-group-items').addClass('show');
    
    // Filtrar items
    $container.find('.tree-item').each(function() {
        const titulo = ($(this).data('titulo') || $(this).data('nombre') || '').toLowerCase();
        const coincide = titulo.includes(filtro);
        
        if (coincide) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
    
    // Mostrar/ocultar grupos según items visibles
    $container.find('.tree-group').each(function() {
        const $grupo = $(this);
        const tieneVisibles = $grupo.find('.tree-item:visible').length > 0;
        
        if (tieneVisibles) {
            $grupo.show();
            $grupo.find('.tree-group-header').addClass('expanded');
            $grupo.find('.tree-group-items').addClass('show');
        } else {
            $grupo.hide();
        }
    });
}

/**
 * Mostrar mensaje de éxito
 */
function mostrarExito(mensaje) {
    // Implementar según tu sistema de notificaciones
    alert(mensaje);
}

/**
 * Mostrar mensaje de error
 */
function mostrarError(mensaje) {
    // Implementar según tu sistema de notificaciones
    alert(mensaje);
}