let paginaActual = 1;
let registrosPorPagina = 25;
let filtrosActivos = {};
let ordenActivo = { columna: null, direccion: 'asc' };
let panelFiltroAbierto = null;
let totalRegistros = 0;
let scrollTopInicial = 0;

// Inicializar
$(document).ready(function () {
    cargarDatos();

    // Cerrar filtros solo si se hace clic fuera del panel Y del icono
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.filter-panel, .filter-icon').length) {
            cerrarTodosFiltros();
        }
    });

    // NO cerrar filtros al hacer scroll en la tabla
    $('.table-responsive').on('scroll', function (e) {
        e.stopPropagation();
    });

    // NO cerrar filtros al hacer scroll en la página
    $(window).on('scroll', function (e) {
        if (panelFiltroAbierto && Math.abs($(window).scrollTop() - scrollTopInicial) > 50) {
            cerrarTodosFiltros();
        }
    });

    $(window).on('resize', function () {
        if (panelFiltroAbierto) {
            cerrarTodosFiltros();
        }
    });
});

// Cargar datos
function cargarDatos() {
    $.ajax({
        url: 'ajax/reembolsos_ia_get_datos.php',
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
                actualizarIndicadoresFiltros();
            } else {
                console.error('Error: ' + response.message);
            }
        },
        error: function () {
            console.error('Error al cargar los datos');
        }
    });
}

// Renderizar tabla
function renderizarTabla(datos) {
    const tbody = $('#tablaReembolsosBody');
    tbody.empty();

    if (datos.length === 0) {
        tbody.append('<tr><td colspan="8" class="text-center py-4 text-muted">No se encontraron registros</td></tr>');
        return;
    }

    datos.forEach(row => {
        const tr = $('<tr>');

        tr.append(`<td>${formatearFecha(row.fecha_solicitud)}</td>`);
        tr.append(`<td>${row.proveedor_nombre || '<span class="text-muted">N/A</span>'}</td>`);
        tr.append(`<td>${row.concepto}</td>`);
        tr.append(`<td><span class="badge bg-light text-dark">${row.ceco_nombre || row.ceco}</span></td>`);
        
        const monedaSimbolo = row.moneda === 'Dolares' ? 'US$' : 'C$';
        tr.append(`<td class="fw-bold text-primary">${monedaSimbolo} ${parseFloat(row.total_cordobas).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>`);

        const estadoClass = row.estado === 'pendiente' ? 'badge bg-warning text-dark' : 'badge bg-success';
        tr.append(`<td><span class="${estadoClass}">${row.estado.toUpperCase()}</span></td>`);
        
        tr.append(`<td>${row.usuario_nombre}</td>`);

        // Botones de acciones
        tr.append(`
            <td class="text-center">
                <a href="reembolsos_ia_imprimir.php?id=${row.id}&fotos=1" target="_blank" class="btn btn-sm btn-outline-success" title="Imprimir Reembolso">
                    <i class="fas fa-print"></i>
                </a>
                <button class="btn btn-sm btn-outline-primary" onclick="verDetalle(${row.id})" title="Ver Detalle">
                    <i class="fas fa-eye"></i>
                </button>
            </td>
        `);

        tbody.append(tr);
    });
}

function verDetalle(id) {
    location.href = 'reembolsos_ia_nuevo.php?id=' + id;
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
}

// Crear panel de filtro
function crearPanelFiltro(th, columna, tipo, icon) {
    const panel = $('<div class="filter-panel show"></div>');
    if (tipo === 'daterange') panel.addClass('has-daterange');

    panel.append(`
        <div class="filter-section">
            <span class="filter-section-title">Ordenar:</span>
            <div class="filter-sort-buttons">
                <button class="filter-sort-btn ${ordenActivo.columna === columna && ordenActivo.direccion === 'asc' ? 'active' : ''}" 
                        onclick="aplicarOrden('${columna}', 'asc')">
                    <i class="bi bi-sort-alpha-down"></i> Asc
                </button>
                <button class="filter-sort-btn ${ordenActivo.columna === columna && ordenActivo.direccion === 'desc' ? 'active' : ''}" 
                        onclick="aplicarOrden('${columna}', 'desc')">
                    <i class="bi bi-sort-alpha-up"></i> Desc
                </button>
            </div>
        </div>
    `);

    panel.append(`
        <div class="filter-actions">
            <button class="filter-action-btn clear" onclick="limpiarFiltro('${columna}')">
                <i class="bi bi-x-circle"></i> Limpiar
            </button>
        </div>
    `);

    $('body').append(panel);

    if (tipo === 'text') {
        const valorActual = filtrosActivos[columna] || '';
        panel.append(`
            <div class="filter-section" style="margin-top: 12px;">
                <input type="text" class="filter-search" placeholder="Buscar..." 
                       value="${valorActual}"
                       oninput="filtrarBusqueda('${columna}', this.value)">
            </div>
        `);
        posicionarPanelFiltro(panel, icon);
    } else if (tipo === 'number') {
        const valorMin = filtrosActivos[columna]?.min || '';
        const valorMax = filtrosActivos[columna]?.max || '';
        panel.append(`
            <div class="filter-section" style="margin-top: 12px;">
                <div class="numeric-inputs">
                    <input type="number" class="filter-search" placeholder="Mín" value="${valorMin}" onchange="filtrarNumerico('${columna}', 'min', this.value)">
                    <input type="number" class="filter-search" placeholder="Máx" value="${valorMax}" onchange="filtrarNumerico('${columna}', 'max', this.value)">
                </div>
            </div>
        `);
        posicionarPanelFiltro(panel, icon);
    } else if (tipo === 'list') {
        cargarOpcionesFiltro(panel, columna, icon);
    } else if (tipo === 'daterange') {
        crearCalendarioDoble(panel, columna);
        posicionarPanelFiltro(panel, icon);
    }
}

function cargarOpcionesFiltro(panel, columna, icon) {
    $.ajax({
        url: 'ajax/reembolsos_ia_get_opciones_filtro.php',
        method: 'POST',
        data: { columna: columna },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                let html = '<div class="filter-section" style="margin-top: 12px;">';
                html += '<input type="text" class="filter-search" placeholder="Filtrar..." onkeyup="buscarEnOpciones(this)">';
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

function crearCalendarioDoble(panel, columna) {
    const hoy = new Date();
    const mesActual = hoy.getMonth();
    const añoActual = hoy.getFullYear();

    panel.append(`
        <div class="filter-section" style="margin-top: 8px;">
            <div class="daterange-calendar-container">
                <div class="daterange-month-selector">
                    <select id="mesCalendario" onchange="actualizarCalendarioUnico('${columna}')"></select>
                    <select id="añoCalendario" onchange="actualizarCalendarioUnico('${columna}')"></select>
                </div>
                <div class="daterange-calendar" id="calendarioUnico"></div>
            </div>
        </div>
    `);

    setTimeout(() => {
        const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        meses.forEach((mes, idx) => $('#mesCalendario').append(`<option value="${idx}" ${idx === mesActual ? 'selected' : ''}>${mes}</option>`));
        for (let año = añoActual - 10; año <= añoActual + 1; año++) $('#añoCalendario').append(`<option value="${año}" ${año === añoActual ? 'selected' : ''}>${año}</option>`);
        actualizarCalendarioUnico(columna);
    }, 50);
}

function actualizarCalendarioUnico(columna) {
    const mes = parseInt($('#mesCalendario').val());
    const año = parseInt($('#añoCalendario').val());
    const primerDia = new Date(año, mes, 1).getDay();
    const diasEnMes = new Date(año, mes + 1, 0).getDate();
    const diasSemana = ['D', 'L', 'M', 'M', 'J', 'V', 'S'];
    
    let html = '<div class="daterange-calendar-header">' + diasSemana.map(d => `<div class="daterange-calendar-day-name">${d}</div>`).join('') + '</div><div class="daterange-calendar-days">';
    for (let i = 0; i < primerDia; i++) html += '<div class="daterange-calendar-day empty"></div>';
    for (let dia = 1; dia <= diasEnMes; dia++) {
        const fechaStr = `${año}-${String(mes + 1).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
        const clases = obtenerClasesCalendario(fechaStr, columna);
        html += `<div class="daterange-calendar-day ${clases}" onclick="seleccionarFechaUnico('${fechaStr}', '${columna}')">${dia}</div>`;
    }
    $('#calendarioUnico').html(html + '</div>');
}

function obtenerClasesCalendario(fecha, columna) {
    const fDesde = filtrosActivos[columna]?.desde;
    const fHasta = filtrosActivos[columna]?.hasta;
    let clases = [];
    if (fDesde && fecha === fDesde) clases.push('selected');
    if (fHasta && fecha === fHasta) clases.push('selected');
    if (fDesde && fHasta && fecha > fDesde && fecha < fHasta) clases.push('in-range');
    return clases.join(' ');
}

function seleccionarFechaUnico(fecha, columna) {
    if (!filtrosActivos[columna]) filtrosActivos[columna] = { desde: null, hasta: null };
    let { desde, hasta } = filtrosActivos[columna];

    if (!desde) filtrosActivos[columna].desde = fecha;
    else if (!hasta) {
        if (fecha < desde) {
            filtrosActivos[columna].desde = fecha;
            filtrosActivos[columna].hasta = desde;
        } else {
            filtrosActivos[columna].hasta = fecha;
        }
    } else {
        if (fecha < desde) filtrosActivos[columna].desde = fecha;
        else filtrosActivos[columna].hasta = fecha;
    }
    actualizarCalendarioUnico(columna);
    if (filtrosActivos[columna].desde && filtrosActivos[columna].hasta) {
        paginaActual = 1;
        cargarDatos();
    }
}

function posicionarPanelFiltro(panel, icon) {
    const iconOffset = $(icon).offset();
    const panelWidth = panel.outerWidth();
    const windowWidth = $(window).width();
    let left = iconOffset.left - panelWidth + $(icon).outerWidth();
    if (left + panelWidth > windowWidth) left = windowWidth - panelWidth - 10;
    if (left < 10) left = 10;
    panel.css({ top: (iconOffset.top + $(icon).outerHeight() + 5) + 'px', left: left + 'px' });
}

function actualizarIndicadoresFiltros() {
    $('.filter-icon').removeClass('has-filter');
    Object.keys(filtrosActivos).forEach(columna => {
        const val = filtrosActivos[columna];
        if (val && (Object.keys(val).length > 0 || (Array.isArray(val) && val.length > 0))) {
            $(`th[data-column="${columna}"] .filter-icon`).addClass('has-filter');
        }
    });
}

function limpiarFiltro(columna) {
    delete filtrosActivos[columna];
    cerrarTodosFiltros();
    paginaActual = 1;
    cargarDatos();
}

function cerrarTodosFiltros() {
    $('.filter-panel').remove();
    $('.filter-icon').removeClass('active');
    panelFiltroAbierto = null;
}

function aplicarOrden(columna, direccion) {
    ordenActivo = { columna, direccion };
    cerrarTodosFiltros();
    paginaActual = 1;
    cargarDatos();
}

function filtrarBusqueda(columna, valor) {
    if (valor.trim() === '') delete filtrosActivos[columna];
    else filtrosActivos[columna] = valor;
    paginaActual = 1;
    cargarDatos();
}

function filtrarNumerico(columna, tipo, valor) {
    if (!filtrosActivos[columna]) filtrosActivos[columna] = {};
    if (valor === '') delete filtrosActivos[columna][tipo];
    else filtrosActivos[columna][tipo] = valor;
    if (Object.keys(filtrosActivos[columna]).length === 0) delete filtrosActivos[columna];
    paginaActual = 1;
    cargarDatos();
}

function toggleOpcionFiltro(columna, valor, checked) {
    if (!filtrosActivos[columna]) filtrosActivos[columna] = [];
    if (checked) {
        if (!filtrosActivos[columna].includes(valor)) filtrosActivos[columna].push(valor);
    } else {
        filtrosActivos[columna] = filtrosActivos[columna].filter(v => v !== valor);
        if (filtrosActivos[columna].length === 0) delete filtrosActivos[columna];
    }
    paginaActual = 1;
    cargarDatos();
}

function cambiarRegistrosPorPagina() {
    registrosPorPagina = parseInt($('#registrosPorPagina').val());
    paginaActual = 1;
    cargarDatos();
}

function renderizarPaginacion(total) {
    const paginacion = $('#paginacion');
    paginacion.empty();
    const totalPaginas = Math.ceil(total / registrosPorPagina);
    if (totalPaginas <= 1) return;

    paginacion.append(`<button class="pagination-btn" onclick="cambiarPagina(${paginaActual - 1})" ${paginaActual === 1 ? 'disabled' : ''}><i class="bi bi-chevron-left"></i></button>`);
    for (let i = 1; i <= totalPaginas; i++) {
        if (i === 1 || i === totalPaginas || (i >= paginaActual - 1 && i <= paginaActual + 1)) {
            paginacion.append(`<button class="pagination-btn ${i === paginaActual ? 'active' : ''}" onclick="cambiarPagina(${i})">${i}</button>`);
        } else if (i === 2 || i === totalPaginas - 1) {
            paginacion.append(`<span class="pagination-btn" disabled>...</span>`);
        }
    }
    paginacion.append(`<button class="pagination-btn" onclick="cambiarPagina(${paginaActual + 1})" ${paginaActual === totalPaginas ? 'disabled' : ''}><i class="bi bi-chevron-right"></i></button>`);
}

function cambiarPagina(p) {
    if (p < 1 || p > Math.ceil(totalRegistros / registrosPorPagina)) return;
    paginaActual = p;
    cargarDatos();
}

function buscarEnOpciones(input) {
    const busqueda = input.value.toLowerCase();
    $(input).siblings('.filter-options').find('.filter-option').each(function () {
        $(this).toggle($(this).text().toLowerCase().includes(busqueda));
    });
}

function formatearFecha(fecha) {
    if (!fecha) return '-';
    const d = new Date(fecha);
    if (isNaN(d.getTime())) return fecha;
    const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    return `${String(d.getDate()).padStart(2, '0')}-${meses[d.getMonth()]}-${String(d.getFullYear()).slice(-2)}`;
}
