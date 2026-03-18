// historial_solicitudes.js

let paginaActual = 1;
let registrosPorPagina = 25;
let filtrosActivos = {};
let ordenActivo = { columna: null, direccion: 'asc' };
let panelFiltroAbierto = null;
let totalRegistros = 0;
let actionModal;

// Inicializar
$(document).ready(function() {
    actionModal = new bootstrap.Modal(document.getElementById('actionModal'));
    cargarDatos();
    
    // Cerrar filtros al hacer clic fuera
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.filter-panel, .filter-icon').length) {
            cerrarTodosFiltros();
        }
    });
    
    $(window).on('resize', function() {
        if (panelFiltroAbierto) {
            cerrarTodosFiltros();
        }
    });
});

let scrollTopInicial = 0;

// Cargar datos
function cargarDatos() {
    $.ajax({
        url: 'ajax/solicitudes_get_datos.php',
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
                alert('Error: ' + response.message);
            }
        },
        error: function() {
            alert('Error al cargar los datos');
        }
    });
}

// Renderizar tabla
function renderizarTabla(datos) {
    const tbody = $('#tablaSolicitudesBody');
    tbody.empty();
    
    if (datos.length === 0) {
        tbody.append('<tr><td colspan="8" class="text-center py-4">No se encontraron solicitudes</td></tr>');
        return;
    }
    
    datos.forEach(row => {
        const tr = $('<tr>');
        
        // Código
        tr.append(`<td><strong>${row.codigo || '-'}</strong><br><small>v${row.version}</small></td>`);
        
        // Fecha
        tr.append(`<td>${formatearFecha(row.fecha_solicitud)}</td>`);
        
        // Solicitante
        tr.append(`<td>${row.solicitante_nombre || '-'}</td>`);
        
        // Productos
        const productosResumen = row.productos_resumen || '-';
        const productosCorto = productosResumen.length > 100 
            ? productosResumen.substring(0, 100) + '...' 
            : productosResumen;
        tr.append(`<td title="${productosResumen}">${productosCorto}<br><small>${row.total_productos} producto(s)</small></td>`);
        
        // Estado
        const estadoClass = 'estado-' + row.estado;
        const estadoTexto = ucfirst(row.estado.replace('_', ' '));
        tr.append(`<td><span class="estado-badge ${estadoClass}">${estadoTexto}</span></td>`);
        
        // Gerencia
        let gerenciaHtml = '<span style="color: #999; font-style: italic;">Sin aprobar</span>';
        if (row.gerente_aprobador_nombre) {
            gerenciaHtml = `
                <div style="font-weight: bold;">${row.gerente_aprobador_nombre}</div>
                <div style="font-size: 12px; color: #666;">${formatearFecha(row.fecha_aprobacion)}</div>
            `;
        }
        tr.append(`<td>${gerenciaHtml}</td>`);
        
        // Última actualización
        tr.append(`<td>${formatearFechaHora(row.updated_at)}</td>`);
        
        // Acciones
        const btnAcciones = generarBotonesAcciones(row);
        tr.append(`<td>${btnAcciones}</td>`);
        
        tbody.append(tr);
    });
}

// Generar botones de acciones
function generarBotonesAcciones(row) {
    let botones = '';
    
    // Botón Ver (siempre visible)
    botones += `
        <a href="ver_solicitud_cotizacion.php?id=${row.id}" class="btn-accion btn-ver" title="Ver Detalles">
            <i class="bi bi-eye"></i>
        </a>
    `;
    
    // Acciones según permisos y estado
    const acciones = row.acciones_permitidas ? row.acciones_permitidas.split(',') : [];
    
    if (acciones.includes('aprobar')) {
        botones += `
            <button class="btn-accion btn-aprobar" onclick="mostrarModalAccion(${row.id}, 'aprobar')" title="Aprobar">
                <i class="bi bi-check-circle"></i>
            </button>
        `;
    }
    
    if (acciones.includes('rechazar')) {
        botones += `
            <button class="btn-accion btn-rechazar" onclick="mostrarModalAccion(${row.id}, 'rechazar')" title="Rechazar">
                <i class="bi bi-x-circle"></i>
            </button>
        `;
    }
    
    if (acciones.includes('completar')) {
        botones += `
            <button class="btn-accion btn-completar" onclick="mostrarModalAccion(${row.id}, 'completar')" title="Completar">
                <i class="fas fa-check-double"></i>
            </button>
        `;
    }
    
    return botones;
}

// Mostrar modal de acción
function mostrarModalAccion(solicitudId, accion) {
    $('#solicitudId').val(solicitudId);
    $('#accionInput').val(accion);
    $('#observaciones_accion').val('');
    
    let titulo = '';
    let btnClass = '';
    let btnText = '';
    
    switch(accion) {
        case 'aprobar':
            titulo = 'Aprobar Solicitud';
            btnClass = 'btn-success';
            btnText = 'Aprobar';
            break;
        case 'rechazar':
            titulo = 'Rechazar Solicitud';
            btnClass = 'btn-danger';
            btnText = 'Rechazar';
            break;
        case 'completar':
            titulo = 'Completar Solicitud';
            btnClass = 'btn-primary';
            btnText = 'Completar';
            break;
    }
    
    $('#modalTitle').text(titulo);
    $('#modalActionBtn').removeClass().addClass('btn ' + btnClass).text(btnText);
    
    actionModal.show();
}

// Toggle filtro
function toggleFilter(icon) {
    const th = $(icon).closest('th');
    const columna = th.data('column');
    const tipo = th.data('type');
    
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
    
    // Ordenamiento
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
    
    // Botón limpiar
    panel.append(`
        <div class="filter-actions">
            <button class="filter-action-btn clear" onclick="limpiarFiltro('${columna}')">
                <i class="bi bi-x-circle"></i> Limpiar
            </button>
        </div>
    `);
    
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
    } else if (tipo === 'list') {
        cargarOpcionesFiltro(panel, columna);
    } else if (tipo === 'daterange') {
        crearCalendarioDoble(panel, columna);
    }
    
    $('body').append(panel);
    posicionarPanelFiltro(panel, icon);
}

// Crear calendario doble
function crearCalendarioDoble(panel, columna) {
    const fechaDesde = filtrosActivos[columna]?.desde || '';
    const fechaHasta = filtrosActivos[columna]?.hasta || '';
    
    const hoy = new Date();
    const mesActual = hoy.getMonth();
    const añoActual = hoy.getFullYear();
    
    panel.append(`
        <div class="filter-section" style="margin-top: 12px;">
            <span class="filter-section-title">Desde:</span>
            <div class="daterange-inputs">
                <div class="daterange-calendar-container">
                    <div class="daterange-month-selector">
                        <select id="mesDesde" onchange="actualizarCalendario('desde', '${columna}')"></select>
                        <select id="añoDesde" onchange="actualizarCalendario('desde', '${columna}')"></select>
                    </div>
                    <div class="daterange-calendar" id="calendarioDesde"></div>
                </div>
            </div>
        </div>
        <div class="filter-section">
            <span class="filter-section-title">Hasta:</span>
            <div class="daterange-inputs">
                <div class="daterange-calendar-container">
                    <div class="daterange-month-selector">
                        <select id="mesHasta" onchange="actualizarCalendario('hasta', '${columna}')"></select>
                        <select id="añoHasta" onchange="actualizarCalendario('hasta', '${columna}')"></select>
                    </div>
                    <div class="daterange-calendar" id="calendarioHasta"></div>
                </div>
            </div>
        </div>
    `);
    
    setTimeout(() => {
        inicializarSelectoresFecha(mesActual, añoActual, fechaDesde, fechaHasta);
        actualizarCalendario('desde', columna);
        actualizarCalendario('hasta', columna);
    }, 50);
}

// Inicializar selectores de fecha
function inicializarSelectoresFecha(mesActual, añoActual, fechaDesde, fechaHasta) {
    const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                   'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    
    let mesDesdeSeleccionado = mesActual;
    let añoDesdeSeleccionado = añoActual;
    let mesHastaSeleccionado = mesActual;
    let añoHastaSeleccionado = añoActual;
    
    if (fechaDesde) {
        const d = new Date(fechaDesde);
        mesDesdeSeleccionado = d.getMonth();
        añoDesdeSeleccionado = d.getFullYear();
    }
    
    if (fechaHasta) {
        const d = new Date(fechaHasta);
        mesHastaSeleccionado = d.getMonth();
        añoHastaSeleccionado = d.getFullYear();
    }
    
    const selectMesDesde = $('#mesDesde');
    const selectMesHasta = $('#mesHasta');
    meses.forEach((mes, idx) => {
        selectMesDesde.append(`<option value="${idx}" ${idx === mesDesdeSeleccionado ? 'selected' : ''}>${mes}</option>`);
        selectMesHasta.append(`<option value="${idx}" ${idx === mesHastaSeleccionado ? 'selected' : ''}>${mes}</option>`);
    });
    
    const selectAñoDesde = $('#añoDesde');
    const selectAñoHasta = $('#añoHasta');
    for (let año = añoActual - 5; año <= añoActual + 1; año++) {
        selectAñoDesde.append(`<option value="${año}" ${año === añoDesdeSeleccionado ? 'selected' : ''}>${año}</option>`);
        selectAñoHasta.append(`<option value="${año}" ${año === añoHastaSeleccionado ? 'selected' : ''}>${año}</option>`);
    }
}

// Actualizar calendario
function actualizarCalendario(tipo, columna) {
    const mes = parseInt($(`#mes${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`).val());
    const año = parseInt($(`#año${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`).val());
    const calendarioId = tipo === 'desde' ? '#calendarioDesde' : '#calendarioHasta';
    
    const primerDia = new Date(año, mes, 1).getDay();
    const diasEnMes = new Date(año, mes + 1, 0).getDate();
    
    const diasSemana = ['D', 'L', 'M', 'M', 'J', 'V', 'S'];
    let html = '<div class="daterange-calendar-header">';
    diasSemana.forEach(dia => {
        html += `<div class="daterange-calendar-day-name">${dia}</div>`;
    });
    html += '</div><div class="daterange-calendar-days">';
    
    for (let i = 0; i < primerDia; i++) {
        html += '<div class="daterange-calendar-day empty"></div>';
    }
    
    for (let dia = 1; dia <= diasEnMes; dia++) {
        const fechaStr = `${año}-${String(mes + 1).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
        const clases = obtenerClasesCalendario(fechaStr, columna);
        html += `<div class="daterange-calendar-day ${clases}" onclick="event.stopPropagation(); seleccionarFecha('${tipo}', '${fechaStr}', '${columna}')">${dia}</div>`;
    }
    
    html += '</div>';
    $(calendarioId).html(html);
}

// Obtener clases para días del calendario
function obtenerClasesCalendario(fecha, columna) {
    const fechaDesde = filtrosActivos[columna]?.desde;
    const fechaHasta = filtrosActivos[columna]?.hasta;
    
    let clases = [];
    
    if (fecha === fechaDesde || fecha === fechaHasta) {
        clases.push('selected');
    } else if (fechaDesde && fechaHasta) {
        if (fecha > fechaDesde && fecha < fechaHasta) {
            clases.push('in-range');
        }
    }
    
    return clases.join(' ');
}

// Seleccionar fecha
function seleccionarFecha(tipo, fecha, columna) {
    if (window.event) {
        window.event.stopPropagation();
    }
    
    if (!filtrosActivos[columna]) {
        filtrosActivos[columna] = {};
    }
    
    filtrosActivos[columna][tipo] = fecha;
    
    actualizarCalendario('desde', columna);
    actualizarCalendario('hasta', columna);
    
    if (filtrosActivos[columna].desde && filtrosActivos[columna].hasta) {
        paginaActual = 1;
        cargarDatos();
    }
}

// Cargar opciones de filtro
function cargarOpcionesFiltro(panel, columna) {
    $.ajax({
        url: 'ajax/solicitudes_get_opciones_filtro.php',
        method: 'POST',
        data: { columna: columna },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let html = '<div class="filter-section" style="margin-top: 12px;">';
                html += '<span class="filter-section-title">Filtrar por:</span>';
                
                // Campo de búsqueda para filtrar opciones
                if (columna === 'gerente_aprobador_nombre') {
                    html += '<input type="text" class="filter-options-search" placeholder="Buscar gerente..." onkeyup="buscarEnOpciones(this)">';
                }
                
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
    if (top + panelHeight > windowHeight + scrollTop) {
        top = iconOffset.top - panelHeight - 5;
    }
    if (top < scrollTop + 10) {
        top = scrollTop + 10;
        panel.css('max-height', (windowHeight - 60) + 'px');
    }
    
    panel.css({ top: top + 'px', left: left + 'px' });
}

// Actualizar indicadores
function actualizarIndicadoresFiltros() {
    $('.filter-icon').removeClass('has-filter');
    Object.keys(filtrosActivos).forEach(columna => {
        const valor = filtrosActivos[columna];
        if ((Array.isArray(valor) && valor.length > 0) || 
            (!Array.isArray(valor) && typeof valor === 'object' && Object.keys(valor).length > 0) ||
            (!Array.isArray(valor) && typeof valor !== 'object' && valor !== '')) {
            $(`th[data-column="${columna}"] .filter-icon`).addClass('has-filter');
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

// Buscar en opciones
function buscarEnOpciones(input) {
    const busqueda = input.value.toLowerCase();
    const opciones = $(input).siblings('.filter-options').find('.filter-option');
    opciones.each(function() {
        const texto = $(this).text().toLowerCase();
        $(this).toggle(texto.includes(busqueda));
    });
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

// Formatear fecha
function formatearFecha(fecha) {
    if (!fecha) return '-';
    const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    const d = new Date(fecha);
    const año = String(d.getFullYear()).slice(-2);
    return `${String(d.getDate()).padStart(2, '0')}-${meses[d.getMonth()]}-${año}`;
}

// Formatear fecha con hora
function formatearFechaHora(fecha) {
    if (!fecha) return '-';
    const d = new Date(fecha);
    return formatearFecha(fecha) + ' ' + d.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
}

// Capitalizar primera letra
function ucfirst(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}