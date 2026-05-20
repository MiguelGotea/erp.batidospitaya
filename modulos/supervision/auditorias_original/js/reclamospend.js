// reclamospend.js
let paginaActual = 1;
let registrosPorPagina = 25;
let filtrosActivos = {};
let ordenActivo = { columna: null, direccion: 'desc' };
let panelFiltroAbierto = null;
let totalRegistros = 0;
let scrollTopInicial = 0;

// Inicializar
$(document).ready(function() {
    cargarDatos();
    
    // Cerrar filtros solo si se hace clic fuera del panel Y del icono
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.filter-panel, .filter-icon').length) {
            cerrarTodosFiltros();
        }
    });
    
    // Permitir scroll dentro del panel de filtro sin cerrarlo
    $(document).on('wheel', '.filter-panel', function(e) {
        e.stopPropagation();
        
        const panel = $(this);
        const scrollTop = panel.scrollTop();
        const scrollHeight = panel.prop('scrollHeight');
        const height = panel.outerHeight();
        const delta = e.originalEvent.deltaY;
        
        if ((delta > 0 && scrollTop + height < scrollHeight) || 
            (delta < 0 && scrollTop > 0)) {
            // Dejar que el scroll funcione naturalmente
        } else {
            e.preventDefault();
        }
    });
    
    // Cerrar filtros al hacer scroll en la página principal
    $(window).on('scroll', function(e) {
        if (panelFiltroAbierto && Math.abs($(window).scrollTop() - scrollTopInicial) > 100) {
            cerrarTodosFiltros();
        }
    });
    
    $(window).on('resize', function() {
        if (panelFiltroAbierto) {
            cerrarTodosFiltros();
        }
    });
});

// Cargar datos
function cargarDatos() {
    $.ajax({
        url: 'ajax/reclamos_get_datos.php',
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
                mostrarError('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX:', error);
            mostrarError('Error al cargar los datos');
        }
    });
}

// Mostrar error en la tabla
function mostrarError(mensaje) {
    const tbody = $('.reclamos-table tbody');
    tbody.html(`<tr><td colspan="7" class="text-center py-4 text-danger">${mensaje}</td></tr>`);
}

// Renderizar tabla
function renderizarTabla(datos) {
    const tbody = $('.reclamos-table tbody');
    tbody.empty();
    
    // Si no hay datos totales y no hay filtros activos, mostrar placeholder premium
    if (totalRegistros === 0 && Object.keys(filtrosActivos).length === 0) {
        $('#tablaReclamosContenedor').addClass('d-none');
        $('#noDataPlaceholder').removeClass('d-none');
        return;
    } else {
        $('#tablaReclamosContenedor').removeClass('d-none');
        $('#noDataPlaceholder').addClass('d-none');
    }
    
    if (datos.length === 0) {
        tbody.append('<tr><td colspan="7" class="text-center py-4 text-muted">No se encontraron registros con los filtros aplicados</td></tr>');
        return;
    }
    
    datos.forEach(row => {
        const tr = $('<tr>');
        
        // Código
        tr.append(`<td class="text-center fw-bold text-muted">${row.id}</td>`);
        
        // Fecha Reclamo
        tr.append(`
            <td>
                <div class="d-flex align-items-center">
                    <i class="far fa-calendar-check me-2 text-success"></i>
                    ${formatearFecha(row.fecha_reclamo)}
                </div>
            </td>
        `);
        
        // Hora de evento
        let horaHtml = '';
        if (row.hora_evento) {
            const partesHora = row.hora_evento.split(':');
            if (partesHora.length >= 2) {
                let horas = parseInt(partesHora[0]);
                const minutos = partesHora[1];
                const ampm = horas >= 12 ? 'PM' : 'AM';
                horas = horas % 12;
                horas = horas ? horas : 12;
                horaHtml = `
                    <div class="text-muted small mt-1" style="padding-left: 24px;">
                        <i class="far fa-clock me-1"></i>
                        ${String(horas).padStart(2, '0')}:${minutos} ${ampm}
                    </div>
                `;
            }
        }
        
        // Fecha Evento
        tr.append(`
            <td>
                <div class="d-flex align-items-center">
                    <i class="far fa-calendar-alt me-2 text-primary"></i>
                    ${formatearFecha(row.fecha_evento)}
                </div>
                ${horaHtml}
            </td>
        `);
        
        // Sucursal
        tr.append(`
            <td>
                <div class="fw-semibold text-dark">
                    ${row.sucursal || '-'}
                </div>
            </td>
        `);
        
        // Medio
        tr.append(`
            <td>
                <span class="badge bg-light text-dark border">${row.medio_compra || '--'}</span>
            </td>
        `);
        
        // Estado
        const esPendiente = !row.reporte_id;
        const badgeClass = esPendiente ? 'badge-pendiente' : 'badge-resuelto';
        const badgeTexto = esPendiente ? 'Abierto' : 'Cerrado';
        tr.append(`
            <td class="text-center">
                <span class="${badgeClass}">${badgeTexto}</span>
            </td>
        `);
        
        // Acciones
        let btnAccion = '';
        if (esPendiente) {
            btnAccion = `
                <a href="reportereclamo.php?reclamo_id=${row.id}"
                    class="btn-action-icon btn-investigar-icon" title="Investigar Reclamo">
                    <i class="fas fa-search"></i>
                </a>
            `;
        } else {
            btnAccion = `
                <a href="ver_reclamo.php?id=${row.id}"
                    class="btn-action-icon btn-ver-icon" title="Ver Detalle del Reclamo">
                    <i class="fas fa-eye"></i>
                </a>
            `;
        }
        tr.append(`<td class="text-center">${btnAccion}</td>`);
        
        tbody.append(tr);
    });
}

// Formatear fecha a dd-Mes-yy (ej. 20-May-26)
function formatearFecha(fecha) {
    if (!fecha || fecha === '0000-00-00') return '--';
    
    const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    const partesFecha = fecha.split(' ')[0].split('-');
    
    if (partesFecha.length !== 3) return fecha;
    
    const año = partesFecha[0].slice(-2);
    const mes = parseInt(partesFecha[1]) - 1;
    const dia = partesFecha[2];
    
    return `${dia.padStart(2, '0')}-${meses[mes]}-${año}`;
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
    
    // Sección de Ordenamiento
    let iconoAsc = '<i class="bi bi-sort-alpha-down"></i> A→Z';
    let iconoDesc = '<i class="bi bi-sort-alpha-up"></i> Z→A';
    
    if (tipo === 'daterange') {
        iconoAsc = '<i class="bi bi-sort-numeric-down"></i> Antigua→Reciente';
        iconoDesc = '<i class="bi bi-sort-numeric-up"></i> Reciente→Antigua';
    }
    
    panel.append(`
        <div class="filter-section">
            <span class="filter-section-title">Ordenar:</span>
            <div class="filter-sort-buttons">
                <button class="filter-sort-btn ${ordenActivo.columna === columna && ordenActivo.direccion === 'asc' ? 'active' : ''}" 
                        onclick="aplicarOrden('${columna}', 'asc')">
                    ${iconoAsc}
                </button>
                <button class="filter-sort-btn ${ordenActivo.columna === columna && ordenActivo.direccion === 'desc' ? 'active' : ''}" 
                        onclick="aplicarOrden('${columna}', 'desc')">
                    ${iconoDesc}
                </button>
            </div>
        </div>
    `);
    
    // Botón Limpiar
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

// Crear calendario doble para rango de fechas
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

// Seleccionar fecha en calendario
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
        url: 'ajax/reclamos_get_opciones_filtro.php',
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
        },
        error: function() {
            console.error('Error al cargar opciones de filtro');
        }
    });
}

// Posicionar panel de filtro
function posicionarPanelFiltro(panel, icon) {
    const iconRect = icon.getBoundingClientRect();
    const panelWidth = panel.outerWidth();
    const windowWidth = $(window).width();
    const windowHeight = $(window).height();
    
    let top = iconRect.bottom + 5;
    let left = iconRect.right - panelWidth;
    
    if (left + panelWidth > windowWidth - 10) {
        left = windowWidth - panelWidth - 10;
    }
    
    if (left < 10) {
        left = 10;
    }
    
    const espacioAbajo = windowHeight - iconRect.bottom - 20;
    const espacioArriba = iconRect.top - 20;
    
    if (espacioAbajo < 300 && espacioArriba > espacioAbajo) {
        panel.css('max-height', Math.min(espacioArriba, 500) + 'px');
        setTimeout(() => {
            const newPanelHeight = panel.outerHeight();
            panel.css({ 
                top: (iconRect.top - newPanelHeight - 5) + 'px', 
                left: left + 'px',
                position: 'fixed'
            });
        }, 10);
    } else {
        panel.css({ 
            top: top + 'px', 
            left: left + 'px',
            position: 'fixed',
            'max-height': Math.min(espacioAbajo, 500) + 'px'
        });
    }
}

// Actualizar indicadores de filtros activos
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

// Limpiar filtro de una columna
function limpiarFiltro(columna) {
    delete filtrosActivos[columna];
    cerrarTodosFiltros();
    paginaActual = 1;
    cargarDatos();
}

// Cerrar todos los filtros
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

// Filtrar por búsqueda de texto
function filtrarBusqueda(columna, valor) {
    if (valor.trim() === '') {
        delete filtrosActivos[columna];
    } else {
        filtrosActivos[columna] = valor;
    }
    paginaActual = 1;
    cargarDatos();
}

// Toggle opción de filtro
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
    
    if (totalPaginas <= 1) {
        return;
    }
    
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
            paginacion.append(`<span class="pagination-btn" style="cursor: default;">...</span>`);
        }
    }
    
    for (let i = inicio; i <= fin; i++) {
        const activeClass = i === paginaActual ? 'active' : '';
        paginacion.append(`<button class="pagination-btn ${activeClass}" onclick="cambiarPagina(${i})">${i}</button>`);
    }
    
    if (fin < totalPaginas) {
        if (fin < totalPaginas - 1) {
            paginacion.append(`<span class="pagination-btn" style="cursor: default;">...</span>`);
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
    const totalPaginas = Math.ceil(totalRegistros / registrosPorPagina);
    if (pagina < 1 || pagina > totalPaginas) return;
    paginaActual = pagina;
    cargarDatos();
}
