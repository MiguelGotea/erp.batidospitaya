/**
 * JavaScript para la herramienta de Reseñas de Google
 * Batidos Pitaya ERP
 */

const GOOGLE_SCRIPT_URL = 'https://script.google.com/macros/s/AKfycbxBLL010Mer3x5CwjTqJjTJ8DFKcLYPZoqBXu0tJ3wEOOjAsZcZnGfNPAZQIKAeclnWJQ/exec';

let paginaActual = 1;
let registrosPorPagina = 25;
let filtrosActivos = {};
let ordenActivo = { columna: 'createTime', direccion: 'desc' };
let panelFiltroAbierto = null;
let totalRegistros = 0;
let scrollTopInicial = 0;

$(document).ready(function() {
    cargarResenas();

    // Cerrar filtros solo si se hace clic fuera del panel Y del icono
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.filter-panel, .filter-icon').length) {
            cerrarTodosFiltros();
        }
    });

    $(window).on('resize', function () {
        if (panelFiltroAbierto) {
            cerrarTodosFiltros();
        }
    });
});

/**
 * Carga las reseñas mediante AJAX con filtros y paginación
 */
async function cargarResenas() {
    mostrarCargando(true);
    
    try {
        const response = await $.ajax({
            url: 'ajax/resenas_google_descargado_get_datos.php',
            type: 'POST',
            data: {
                pagina: paginaActual,
                registros_por_pagina: registrosPorPagina,
                filtros: JSON.stringify(filtrosActivos),
                orden: JSON.stringify(ordenActivo)
            },
            dataType: 'json'
        });

        if (response.success) {
            totalRegistros = response.total_registros;
            renderizarTabla(response.data);
            renderizarPaginacion(response.total_registros);
            actualizarIndicadoresFiltros();
        } else {
            Swal.fire('Error', response.message || 'No se pudieron cargar las reseñas', 'error');
        }
    } catch (error) {
        console.error('Error al cargar reseñas:', error);
        Swal.fire('Error', 'Hubo un problema de conexión con el servidor', 'error');
    } finally {
        mostrarCargando(false);
    }
}

/**
 * Renderiza los datos en la tabla HTML
 */
function renderizarTabla(data) {
    const tbody = $('#tbodyResenas');
    tbody.empty();

    if (!data || data.length === 0) {
        tbody.append('<tr><td colspan="5" class="text-center py-4 text-muted">No se encontraron reseñas registradas con los filtros aplicados.</td></tr>');
        return;
    }

    data.forEach(item => {
        const estrellas = generarEstrellas(item.starRatingNum);
        
        const row = `
            <tr>
                <td><span class="badge sucursal-badge">${item.SucursalNombre}</span></td>
                <td class="reviewer-name">${item.reviewerName}</td>
                <td class="text-center">${estrellas}</td>
                <td><div class="review-comment">${item.comment || '<span class="text-muted italic">Sin comentario</span>'}</div></td>
                <td class="text-center">${item.fechaFormateada}</td>
            </tr>
        `;
        tbody.append(row);
    });
}

/**
 * Genera el HTML de las estrellas según el rating
 */
function generarEstrellas(rating) {
    let html = '<div class="star-rating">';
    for (let i = 1; i <= 5; i++) {
        if (i <= rating) {
            html += '<i class="fas fa-star"></i>';
        } else {
            html += '<i class="far fa-star"></i>';
        }
    }
    html += '</div>';
    return html;
}

/**
 * Inicia el proceso de actualización llamando al Google Script
 */
async function actualizarResenas() {
    const result = await Swal.fire({
        title: '¿Actualizar Reseñas?',
        text: "Este proceso descargará las últimas reseñas desde Google Business. Puede tardar unos momentos.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#51B8AC',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, actualizar',
        cancelButtonText: 'Cancelar'
    });

    if (result.isConfirmed) {
        Swal.fire({
            title: 'Actualizando...',
            text: 'Estamos conectando con Google Business, por favor espera.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        try {
            await fetch(GOOGLE_SCRIPT_URL, { mode: 'no-cors' });
            
            setTimeout(() => {
                Swal.fire({
                    title: 'Proceso Iniciado',
                    text: 'Se ha enviado la señal de actualización. Los datos aparecerán en unos instantes.',
                    icon: 'success',
                    confirmButtonColor: '#51B8AC'
                }).then(() => {
                    cargarResenas();
                });
            }, 3000);

        } catch (error) {
            console.error('Error al actualizar:', error);
            Swal.fire('Error', 'No se pudo iniciar el script de actualización.', 'error');
        }
    }
}

// --- LÓGICA DE FILTROS ---

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

function crearPanelFiltro(th, columna, tipo, icon) {
    const panel = $('<div class="filter-panel show"></div>');
    if (tipo === 'daterange') panel.addClass('has-daterange');

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
                <span class="filter-section-title">Buscar:</span>
                <input type="text" class="filter-search" placeholder="Escribir..." 
                       value="${valorActual}"
                       oninput="filtrarBusqueda('${columna}', this.value)">
            </div>
        `);
    } else if (tipo === 'list') {
        cargarOpcionesFiltro(panel, columna, icon);
    } else if (tipo === 'daterange') {
        crearCalendarioDoble(panel, columna);
    }
    
    posicionarPanelFiltro(panel, icon);
}

function cargarOpcionesFiltro(panel, columna, icon) {
    if (columna === 'starRating') {
        const opciones = [
            { valor: 'FIVE', texto: '5 Estrellas' },
            { valor: 'FOUR', texto: '4 Estrellas' },
            { valor: 'THREE', texto: '3 Estrellas' },
            { valor: 'TWO', texto: '2 Estrellas' },
            { valor: 'ONE', texto: '1 Estrella' }
        ];
        renderOpcionesFiltro(panel, columna, opciones, icon);
    } else {
        panel.append('<div class="p-2 small text-muted">Cargando opciones...</div>');
    }
}

function renderOpcionesFiltro(panel, columna, opciones, icon) {
    panel.find('.small.text-muted').remove();
    let html = '<div class="filter-section" style="margin-top: 12px;">';
    html += '<span class="filter-section-title">Filtrar por:</span>';
    html += '<div class="filter-options" style="max-height: 200px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 6px; padding: 4px; margin-top: 8px;">';

    opciones.forEach(opcion => {
        const checked = filtrosActivos[columna] && filtrosActivos[columna].includes(opcion.valor) ? 'checked' : '';
        html += `
            <div class="filter-option" style="padding: 6px 8px; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" value="${opcion.valor}" ${checked}
                       onchange="toggleOpcionFiltro('${columna}', '${opcion.valor}', this.checked)">
                <span style="font-size: 13px;">${opcion.texto}</span>
            </div>
        `;
    });

    html += '</div></div>';
    panel.append(html);
    posicionarPanelFiltro(panel, icon);
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
    cargarResenas();
}

function filtrarBusqueda(columna, valor) {
    if (valor.trim() === '') delete filtrosActivos[columna];
    else filtrosActivos[columna] = valor;
    paginaActual = 1;
    cargarResenas();
}

function aplicarOrden(columna, direccion) {
    ordenActivo = { columna, direccion };
    cerrarTodosFiltros();
    paginaActual = 1;
    cargarResenas();
}

function limpiarFiltro(columna) {
    delete filtrosActivos[columna];
    cerrarTodosFiltros();
    paginaActual = 1;
    cargarResenas();
}

function cerrarTodosFiltros() {
    $('.filter-panel').remove();
    $('.filter-icon').removeClass('active');
    panelFiltroAbierto = null;
}

function posicionarPanelFiltro(panel, icon) {
    const iconOffset = $(icon).offset();
    const iconWidth = $(icon).outerWidth();
    const panelWidth = panel.outerWidth();
    let left = iconOffset.left - panelWidth + iconWidth;
    if (left < 10) left = 10;
    panel.css({ top: (iconOffset.top + $(icon).outerHeight() + 5) + 'px', left: left + 'px' });
}

function actualizarIndicadoresFiltros() {
    $('.filter-icon').removeClass('has-filter');
    Object.keys(filtrosActivos).forEach(columna => {
        $(`th[data-column="${columna}"] .filter-icon`).addClass('has-filter');
    });
}

// --- PAGINACIÓN ---

function cambiarRegistrosPorPagina() {
    registrosPorPagina = parseInt($('#registrosPorPagina').val());
    paginaActual = 1;
    cargarResenas();
}

function renderizarPaginacion(total) {
    const totalPaginas = Math.ceil(total / registrosPorPagina);
    const paginacion = $('#paginacion');
    paginacion.empty();

    if (totalPaginas <= 1) return;

    paginacion.append(`<button class="pagination-btn" onclick="cambiarPagina(${paginaActual - 1})" ${paginaActual === 1 ? 'disabled' : ''}><i class="bi bi-chevron-left"></i></button>`);

    for (let i = 1; i <= totalPaginas; i++) {
        if (i === 1 || i === totalPaginas || (i >= paginaActual - 2 && i <= paginaActual + 2)) {
            const activeClass = i === paginaActual ? 'active' : '';
            paginacion.append(`<button class="pagination-btn ${activeClass}" onclick="cambiarPagina(${i})">${i}</button>`);
        } else if (i === paginaActual - 3 || i === paginaActual + 3) {
            paginacion.append(`<span class="px-2">...</span>`);
        }
    }

    paginacion.append(`<button class="pagination-btn" onclick="cambiarPagina(${paginaActual + 1})" ${paginaActual === totalPaginas ? 'disabled' : ''}><i class="bi bi-chevron-right"></i></button>`);
}

function cambiarPagina(pagina) {
    const totalPaginas = Math.ceil(totalRegistros / registrosPorPagina);
    if (pagina < 1 || pagina > totalPaginas) return;
    paginaActual = pagina;
    cargarResenas();
}

// --- CALENDARIO INTELIGENTE (Basado en Estándar) ---

function crearCalendarioDoble(panel, columna) {
    const hoy = new Date();
    const mesActual = hoy.getMonth();
    const añoActual = hoy.getFullYear();

    panel.append(`
        <div class="filter-section" style="margin-top: 8px;">
            <span class="filter-section-title">Seleccionar Rango:</span>
            <div class="daterange-calendar-container">
                <div class="daterange-month-selector mb-2 d-flex gap-1">
                    <select id="mesCalendario" class="form-select form-select-sm" onchange="actualizarCalendarioUnico('${columna}')"></select>
                    <select id="añoCalendario" class="form-select form-select-sm" onchange="actualizarCalendarioUnico('${columna}')"></select>
                </div>
                <div class="daterange-calendar" id="calendarioUnico"></div>
            </div>
            <div class="daterange-info mt-2" style="font-size: 0.75rem; color: #666;">
                <i class="bi bi-info-circle"></i> Haz clic en dos fechas para definir el rango.
            </div>
        </div>
    `);

    setTimeout(() => {
        const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        const selectMes = $('#mesCalendario');
        const selectAño = $('#añoCalendario');
        meses.forEach((mes, idx) => selectMes.append(`<option value="${idx}" ${idx === mesActual ? 'selected' : ''}>${mes}</option>`));
        for (let año = añoActual - 10; año <= añoActual + 1; año++) selectAño.append(`<option value="${año}" ${año === añoActual ? 'selected' : ''}>${año}</option>`);
        actualizarCalendarioUnico(columna);
    }, 50);
}

function actualizarCalendarioUnico(columna) {
    const mes = parseInt($('#mesCalendario').val());
    const año = parseInt($('#añoCalendario').val());
    const primerDia = new Date(año, mes, 1).getDay();
    const diasEnMes = new Date(año, mes + 1, 0).getDate();
    const diasSemana = ['D', 'L', 'M', 'M', 'J', 'V', 'S'];
    
    let html = '<div class="daterange-calendar-header d-grid" style="grid-template-columns: repeat(7, 1fr); text-align: center; font-size: 10px; font-weight: bold; color: #666;">';
    diasSemana.forEach(dia => html += `<div>${dia}</div>`);
    html += '</div><div class="daterange-calendar-days d-grid" style="grid-template-columns: repeat(7, 1fr); gap: 2px; margin-top: 5px;">';

    for (let i = 0; i < primerDia; i++) html += '<div class="empty"></div>';
    
    for (let dia = 1; dia <= diasEnMes; dia++) {
        const fechaStr = `${año}-${String(mes + 1).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
        const clases = obtenerClasesCalendario(fechaStr, columna);
        html += `<div class="daterange-calendar-day ${clases}" 
                     style="text-align: center; cursor: pointer; padding: 4px; border-radius: 3px; font-size: 11px;"
                     onclick="event.stopPropagation(); seleccionarFechaUnico('${fechaStr}', '${columna}')">${dia}</div>`;
    }
    html += '</div>';
    $('#calendarioUnico').html(html);
}

function obtenerClasesCalendario(fecha, columna) {
    const fDesde = filtrosActivos[columna]?.desde;
    const fHasta = filtrosActivos[columna]?.hasta;
    if (fDesde && fecha === fDesde) return 'selected';
    if (fHasta && fecha === fHasta) return 'selected';
    if (fDesde && fHasta && fecha > fDesde && fecha < fHasta) return 'in-range';
    return '';
}

function seleccionarFechaUnico(fecha, columna) {
    if (!filtrosActivos[columna]) filtrosActivos[columna] = { desde: null, hasta: null };
    let { desde, hasta } = filtrosActivos[columna];

    if (!desde) {
        filtrosActivos[columna].desde = fecha;
    } else if (!hasta) {
        if (fecha < desde) {
            filtrosActivos[columna].desde = fecha;
            filtrosActivos[columna].hasta = desde;
        } else {
            filtrosActivos[columna].hasta = fecha;
        }
    } else {
        if (fecha < desde) filtrosActivos[columna].desde = fecha;
        else if (fecha > hasta) filtrosActivos[columna].hasta = fecha;
        else filtrosActivos[columna].hasta = fecha;
    }

    actualizarCalendarioUnico(columna);
    if (filtrosActivos[columna].desde && filtrosActivos[columna].hasta) {
        paginaActual = 1;
        cargarResenas();
    }
}

function mostrarCargando(show) {
    if (show) {
        $('#loaderResenas').show();
        $('.table-resenas').css('opacity', '0.5');
    } else {
        $('#loaderResenas').hide();
        $('.table-resenas').css('opacity', '1');
    }
}
