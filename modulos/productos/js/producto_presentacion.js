let paginaActual = 1;
let registrosPorPagina = 25;
let filtrosActivos = {};
let ordenActivo = { columna: null, direccion: 'asc' };
let panelFiltroAbierto = null;
let totalRegistros = 0;
let scrollTopInicial = 0;

// Inicializar
$(document).ready(function() {
    cargarDatos();
    
    // Cerrar filtros solo si se hace clic fuera del panel Y del icono
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.filter-panel, .filter-icon, .tristate-toggle-inline').length) {
            cerrarTodosFiltros();
        }
    });
    
    // NO cerrar filtros al hacer scroll en la tabla
    $('.table-responsive').on('scroll', function(e) {
        e.stopPropagation();
    });
    
    // NO cerrar filtros al hacer scroll en la página
    $(window).on('scroll', function(e) {
        if (panelFiltroAbierto && Math.abs($(window).scrollTop() - scrollTopInicial) > 50) {
            cerrarTodosFiltros();
        }
    });
    
    $(window).on('resize', function() {
        if (panelFiltroAbierto) {
            cerrarTodosFiltros();
        }
    });
});

// ========== NUEVA FUNCIÓN: Toggle Tri-State en Encabezado ==========
function toggleTriStateHeader(element, columna) {
    const $toggle = $(element);
    const estadoActual = $toggle.attr('data-state') || 'null';
    
    // Ciclo: null → SI → NO → null
    let nuevoEstado;
    if (estadoActual === 'null' || estadoActual === null) {
        nuevoEstado = 'SI';
    } else if (estadoActual === 'SI') {
        nuevoEstado = 'NO';
    } else {
        nuevoEstado = 'null';
    }
    
    // Actualizar atributo y aplicar filtro
    $toggle.attr('data-state', nuevoEstado);
    
    // Actualizar filtros
    if (nuevoEstado === 'null') {
        delete filtrosActivos[columna];
        $toggle.removeClass('has-filter');
    } else {
        filtrosActivos[columna] = nuevoEstado;
        $toggle.addClass('has-filter');
    }
    
    // Recargar datos
    paginaActual = 1;
    cargarDatos();
}

// Cargar datos
function cargarDatos() {
    $.ajax({
        url: 'ajax/producto_presentacion_get_datos.php',
        method: 'POST',
        data: {
            pagina: paginaActual,
            registros_por_pagina: registrosPorPagina,
            filtros: JSON.stringify(filtrosActivos),
            orden: JSON.stringify(ordenActivo)
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                totalRegistros = response.total_registros;
                renderizarTabla(response.datos);
                renderizarPaginacion(response.total_registros);
                actualizarIndicadoresFiltros();
            } else {
                console.error('Error:', response.message);
                alert('Error al cargar los datos: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX:', error);
            alert('Error al cargar los datos');
        }
    });
}

// Renderizar tabla
function renderizarTabla(datos) {
    const tbody = $('#tablaProductosBody');
    tbody.empty();
    
    if (datos.length === 0) {
        tbody.append('<tr><td colspan="9" class="text-center py-4">No se encontraron registros</td></tr>');
        return;
    }
    
    datos.forEach(row => {
        const tr = $('<tr>');
        
        // SKU
        tr.append(`<td style="text-align: left;">${row.SKU || '-'}</td>`);
        
        // Nombre
        tr.append(`<td style="text-align: left;">${row.Nombre || '-'}</td>`);
        
        // Unidad
        tr.append(`<td>${row.unidad_nombre || '-'}</td>`);
        
        // ========== ÍCONOS EN LUGAR DE BADGES - NUEVO ==========
        
        // Venta (icono)
        const ventaIcono = row.es_vendible === 'SI' 
            ? '<i class="bi bi-check-circle-fill status-icon si"></i>' 
            : '<i class="bi bi-x-circle-fill status-icon no"></i>';
        tr.append(`<td>${ventaIcono}</td>`);
        
        // Compra (icono)
        const compraIcono = row.es_comprable === 'SI' 
            ? '<i class="bi bi-check-circle-fill status-icon si"></i>' 
            : '<i class="bi bi-x-circle-fill status-icon no"></i>';
        tr.append(`<td>${compraIcono}</td>`);
        
        // Fabricación (icono)
        const fabricacionIcono = row.es_fabricable === 'SI' 
            ? '<i class="bi bi-check-circle-fill status-icon si"></i>' 
            : '<i class="bi bi-x-circle-fill status-icon no"></i>';
        tr.append(`<td>${fabricacionIcono}</td>`);
        
        // Receta (icono)
        const recetaIcono = row.tiene_receta === 'SI' 
            ? '<i class="bi bi-check-circle-fill status-icon si"></i>' 
            : '<i class="bi bi-x-circle-fill status-icon no"></i>';
        tr.append(`<td>${recetaIcono}</td>`);
        
        // Activo (toggle solo si tiene permiso)
        let activoHtml = '';
        if (PUEDE_DESACTIVAR) {
            const checked = row.Activo === 'SI' ? 'checked' : '';
            activoHtml = `
                <label class="toggle-activo">
                    <input type="checkbox" ${checked} onchange="toggleActivo(${row.id}, this.checked)">
                    <span class="toggle-slider"></span>
                </label>
            `;
        } else {
            const activoIcono = row.Activo === 'SI' 
                ? '<i class="bi bi-check-circle-fill status-icon si"></i>' 
                : '<i class="bi bi-x-circle-fill status-icon no"></i>';
            activoHtml = activoIcono;
        }
        tr.append(`<td>${activoHtml}</td>`);
        
        // Botón de acciones
        const btnVer = `
            <button class="btn-accion btn-ver" onclick="verProducto(${row.id})" title="Ver/Editar">
                <i class="bi bi-eye"></i>
            </button>
        `;
        tr.append(`<td>${btnVer}</td>`);
        
        tbody.append(tr);
    });
}

// Ver producto (ir a página de edición)
function verProducto(id) {
    window.location.href = `registro_producto_global.php?id=${id}`;
}

// Toggle estado activo
function toggleActivo(id, nuevoEstado) {
    if (!PUEDE_DESACTIVAR) {
        alert('No tiene permisos para cambiar el estado');
        return;
    }
    
    const estadoTexto = nuevoEstado ? 'SI' : 'NO';
    const accionTexto = nuevoEstado ? 'activar' : 'desactivar';
    
    if (!confirm(`¿Está seguro de ${accionTexto} este producto?`)) {
        // Revertir el toggle
        cargarDatos();
        return;
    }
    
    $.ajax({
        url: 'ajax/producto_presentacion_toggle_activo.php',
        method: 'POST',
        data: { 
            id: id, 
            estado: estadoTexto 
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // No recargar toda la tabla, solo actualizar visualmente
                // (Ya está actualizado por el toggle)
            } else {
                alert('Error: ' + response.message);
                cargarDatos(); // Recargar en caso de error
            }
        },
        error: function() {
            alert('Error al cambiar el estado');
            cargarDatos(); // Recargar en caso de error
        }
    });
}

// Toggle filtro (solo para SKU, Nombre, Unidad - NO para tri-state)
function toggleFilter(icon) {
    const th = $(icon).closest('th');
    const columna = th.data('column');
    const tipo = th.data('type');
    
    // Si es tri-state, no abrir panel (se maneja directamente en el header)
    if (tipo === 'tristate') {
        return;
    }
    
    if (panelFiltroAbierto === columna) {
        cerrarTodosFiltros();
        return;
    }
    
    cerrarTodosFiltros();
    scrollTopInicial = $(window).scrollTop();
    crearPanelFiltro(th, columna, tipo, icon);
    panelFiltroAbierto = columna;
    $(icon).addClass('active');
    actualizarIndicadoresFiltros();
}

// Crear panel de filtro
function crearPanelFiltro(th, columna, tipo, icon) {
    const panel = $('<div class="filter-panel show"></div>');
    
    // Ordenamiento (siempre presente)
    panel.append(`
        <div class="filter-section">
            <span class="filter-section-title">Ordenar:</span>
            <div class="filter-sort-buttons">
                <button class="filter-sort-btn ${ordenActivo.columna === columna && ordenActivo.direccion === 'asc' ? 'active' : ''}" 
                        onclick="aplicarOrden('${columna}', 'asc')">
                    <i class="bi bi-sort-alpha-down"></i> A→Z
                </button>
                <button class="filter-sort-btn ${ordenActivo.columna === columna && ordenActivo.direccion === 'desc' ? 'active' : ''}" 
                        onclick="aplicarOrden('${columna}', 'desc')">
                    <i class="bi bi-sort-alpha-up"></i> Z→A
                </button>
            </div>
        </div>
    `);
    
    // Botones de acción
    panel.append(`
        <div class="filter-actions">
            <button class="filter-action-btn clear" onclick="limpiarFiltro('${columna}')">
                <i class="bi bi-x-circle"></i> Limpiar
            </button>
        </div>
    `);
    
    // Agregar al body
    $('body').append(panel);
    
    // Filtros según tipo
    if (tipo === 'text') {
        const valorActual = filtrosActivos[columna] || '';
        panel.append(`
            <div class="filter-section" style="margin-top: 12px;">
                <span class="filter-section-title">Buscar:</span>
                <input type="text" class="filter-search" placeholder="Escribir..." 
                       value="${valorActual}"
                       oninput="filtrarBusqueda('${columna}', this.value)">
            </div>
        `);
        posicionarPanelFiltro(panel, icon);
    } else if (tipo === 'list') {
        cargarOpcionesFiltro(panel, columna, icon);
    }
}

// Cargar opciones de filtro (para listas)
function cargarOpcionesFiltro(panel, columna, icon) {
    $.ajax({
        url: 'ajax/producto_presentacion_get_opciones_filtro.php',
        method: 'POST',
        data: { columna: columna },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let html = '<div class="filter-section" style="margin-top: 12px;">';
                html += '<span class="filter-section-title">Filtrar por:</span>';
                html += '<input type="text" class="filter-search" placeholder="Buscar..." onkeyup="buscarEnOpciones(this)">';
                html += '<div class="filter-options">';
                
                response.opciones.forEach(opcion => {
                    const checked = filtrosActivos[columna] && filtrosActivos[columna].includes(opcion.valor) ? 'checked' : '';
                    html += `
                        <div class="filter-option">
                            <input type="checkbox" value="${opcion.valor}" ${checked}
                                   onchange="toggleOpcionFiltro('${columna}', '${opcion.valor}', this.checked)">
                            <span>${opcion.texto}</span>
                        </div>
                    `;
                });
                
                html += '</div></div>';
                panel.append(html);
                
                posicionarPanelFiltro(panel, icon);
            }
        }
    });
}

// Posicionar panel
function posicionarPanelFiltro(panel, icon) {
    const iconOffset = $(icon).offset();
    const iconWidth = $(icon).outerWidth();
    const iconHeight = $(icon).outerHeight();
    const panelWidth = panel.outerWidth();
    const panelHeight = panel.outerHeight();
    const windowWidth = $(window).width();
    const windowHeight = $(window).height();
    const scrollTop = $(window).scrollTop();
    
    let top = iconOffset.top + iconHeight + 5;
    let left = iconOffset.left - panelWidth + iconWidth;
    
    if (left + panelWidth > windowWidth) {
        left = windowWidth - panelWidth - 10;
    }
    if (left < 10) {
        left = 10;
    }
    
    const espacioAbajo = windowHeight + scrollTop - top;
    const espacioArriba = iconOffset.top - scrollTop;
    
    if (espacioAbajo < panelHeight && espacioArriba > panelHeight) {
        top = iconOffset.top - panelHeight - 5;
    }
    
    if (top + panelHeight > windowHeight + scrollTop) {
        top = Math.max(scrollTop + 10, windowHeight + scrollTop - panelHeight - 10);
    }
    
    if (top < scrollTop + 10) {
        top = scrollTop + 10;
    }
    
    panel.css({ 
        top: top + 'px', 
        left: left + 'px',
        maxHeight: Math.min(windowHeight - 100, panelHeight) + 'px'
    });
}

function setTriStateFilter(button, columna, estado) {
    const $button = $(button);
    const $group = $button.closest('.tristate-toggle-group');
    
    // Remover clase 'active' de todos los botones del grupo
    $group.find('.tristate-btn').removeClass('active has-filter');
    
    // Agregar clase 'active' al botón clickeado
    $button.addClass('active');
    
    // Actualizar filtros
    if (estado === null) {
        delete filtrosActivos[columna];
    } else {
        filtrosActivos[columna] = estado;
        $button.addClass('has-filter');
    }
    
    // Recargar datos
    paginaActual = 1;
    cargarDatos();
}

// Actualizar indicadores (MODIFICADA)
function actualizarIndicadoresFiltros() {
    $('.filter-icon').removeClass('has-filter');
    
    // Para filtros normales (texto, lista)
    Object.keys(filtrosActivos).forEach(columna => {
        const valor = filtrosActivos[columna];
        if ((Array.isArray(valor) && valor.length > 0) || 
            (!Array.isArray(valor) && typeof valor === 'object' && Object.keys(valor).length > 0) ||
            (!Array.isArray(valor) && typeof valor !== 'object' && valor !== '')) {
            $(`th[data-column="${columna}"] .filter-icon`).addClass('has-filter');
        }
    });
    
    // Actualizar estados de tri-state toggles en el header
    $('.tristate-btn').each(function() {
        const $this = $(this);
        const columna = $this.data('column');
        const estado = $this.data('state');
        
        // Remover clases active y has-filter
        $this.removeClass('active has-filter');
        
        // Determinar si este botón debe estar activo
        if (filtrosActivos[columna] === undefined && estado === 'null') {
            // Sin filtro = botón "TODOS" activo
            $this.addClass('active');
        } else if (filtrosActivos[columna] === estado) {
            // Filtro activo = botón correspondiente activo
            $this.addClass('active has-filter');
        }
    });
}

// Limpiar filtro
function limpiarFiltro(columna) {
    delete filtrosActivos[columna];
    cerrarTodosFiltros();
    paginaActual = 1;
    cargarDatos();
}

// Cerrar filtros
function cerrarTodosFiltros() {
    $('.filter-panel').remove();
    $('.filter-icon').removeClass('active');
    panelFiltroAbierto = null;
}

// Aplicar orden
function aplicarOrden(columna, direccion) {
    ordenActivo = { columna, direccion };
    cerrarTodosFiltros();
    paginaActual = 1;
    cargarDatos();
}

// Filtrar búsqueda
function filtrarBusqueda(columna, valor) {
    if (valor.trim() === '') {
        delete filtrosActivos[columna];
    } else {
        filtrosActivos[columna] = valor;
    }
    paginaActual = 1;
    cargarDatos();
}

// Toggle opción filtro
function toggleOpcionFiltro(columna, valor, checked) {
    if (!filtrosActivos[columna]) {
        filtrosActivos[columna] = [];
    }
    if (checked) {
        if (!filtrosActivos[columna].includes(valor)) {
            filtrosActivos[columna].push(valor);
        }
    } else {
        filtrosActivos[columna] = filtrosActivos[columna].filter(v => v !== valor);
        if (filtrosActivos[columna].length === 0) {
            delete filtrosActivos[columna];
        }
    }
    paginaActual = 1;
    cargarDatos();
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

// Buscar en opciones
function buscarEnOpciones(input) {
    const busqueda = input.value.toLowerCase();
    const opciones = $(input).siblings('.filter-options').find('.filter-option');
    opciones.each(function() {
        const texto = $(this).text().toLowerCase();
        $(this).toggle(texto.includes(busqueda));
    });
}