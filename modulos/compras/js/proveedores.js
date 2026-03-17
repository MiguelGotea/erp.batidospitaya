// proveedores.js

let paginaActual = 1;
let registrosPorPagina = 25;
let filtrosActivos = {};
let ordenActivo = { columna: null, direccion: 'asc' };
let panelFiltroAbierto = null;
let totalRegistros = 0;

// Inicializar
$(document).ready(function() {
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
        url: 'ajax/proveedores_get_datos.php',
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
    const tbody = $('#tablaProveedoresBody');
    tbody.empty();
    
    if (datos.length === 0) {
        tbody.append('<tr><td colspan="7" class="text-center py-4">No se encontraron proveedores</td></tr>');
        return;
    }
    
    datos.forEach(row => {
        const tr = $('<tr>');
        
        // Nombre
        tr.append(`<td><strong>${row.nombre || '-'}</strong></td>`);
        
        // RUC/NIT
        tr.append(`<td>${row.ruc_nit || '-'}</td>`);
        
        // Dirección
        tr.append(`<td>${row.direccion || '-'}</td>`);
        
        // Sucursal
        tr.append(`<td>${row.nombre_sucursal || '-'}</td>`);
        
        // Estado
        const vigenciaClass = row.vigente == 1 ? 'bg-success' : 'bg-danger';
        const vigenciaTexto = row.vigente == 1 ? 'Vigente' : 'No Vigente';
        tr.append(`<td><span class="badge ${vigenciaClass}">${vigenciaTexto}</span></td>`);
        
        // Fecha Registro
        tr.append(`<td>${formatearFechaHora(row.fecha_registro)}</td>`);
        
        // Acciones
        let botones = `
            <div class="d-flex gap-2">
                <a href="proveedor_detalle.php?id=${row.id}" class="btn btn-sm btn-outline-primary" title="Editar">
                    <i class="bi bi-pencil"></i>
                </a>
            </div>
        `;
        tr.append(`<td>${botones}</td>`);
        
        tbody.append(tr);
    });
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
        url: 'ajax/proveedores_get_opciones_filtro.php',
        method: 'POST',
        data: { columna: columna },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let html = '<div class="filter-section" style="margin-top: 12px;">';
                html += '<span class="filter-section-title">Filtrar por:</span>';
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

// Formatear fecha y hora
function formatearFechaHora(fechaHora) {
    if (!fechaHora) return '-';
    const d = new Date(fechaHora);
    const fecha = `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`;
    const hora = `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
    return `${fecha} ${hora}`;
}