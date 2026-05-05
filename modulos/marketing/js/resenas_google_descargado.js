/**
 * JavaScript para la herramienta de Reseñas de Google
 * Batidos Pitaya ERP
 *
 * Sync via GMB Worker (Node.js en VPS) — reemplaza el Google Apps Script anterior.
 */

let paginaActual = 1;
let registrosPorPagina = 25;
let filtrosActivos = {};
let ordenActivo = { columna: 'createTime', direccion: 'desc' };
let panelFiltroAbierto = null;
let totalRegistros = 0;
let scrollTopInicial = 0;

$(document).ready(function () {
    cargarResenas();
    loadGmbStatus(); // Panel de control del bot GMB

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
        tbody.append('<tr><td colspan="8" class="text-center py-4 text-muted">No se encontraron reseñas registradas con los filtros aplicados.</td></tr>');
        return;
    }

    data.forEach(item => {
        const estrellas = generarEstrellas(item.starRatingNum);

        const row = `
            <tr>
                <td class="fw-bold" style="color: #0E544C;">${item.SucursalNombre}</td>
                <td class="reviewer-name">${item.reviewerName}</td>
                <td class="text-center">${estrellas}</td>
                <td><div class="review-comment">${item.comment || '<span class="text-muted italic">Sin comentario</span>'}</div></td>
                <td class="text-center">${item.fechaFormateada}</td>
                <td class="text-center">${item.horaFormateada}</td>
                <td><div class="review-comment">${item.reviewReplyComment || '<span class="text-muted italic">Sin respuesta</span>'}</div></td>
                <td class="text-center">${item.fechaRptaFormateada}</td>
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

// ── GMB Bot Control ───────────────────────────────────────────────────────────

let gmbStatusInterval = null;

/**
 * Carga el estado del último sync al cargar la página.
 * Solo se llama si el panel #gmbBotPanel existe (usuario con permiso).
 */
async function loadGmbStatus() {
    if (!document.getElementById('gmbBotPanel')) return;

    try {
        const res  = await $.ajax({ url: 'ajax/gmb_sync_status.php', type: 'GET', dataType: 'json' });
        renderGmbStatus(res);
    } catch (e) {
        $('#gmbLastSyncInfo').text('No se pudo conectar con el worker.');
    }
}

/**
 * Renderiza el estado del bot en el panel superior.
 */
function renderGmbStatus(data) {
    const infoEl  = $('#gmbLastSyncInfo');
    const btnLog  = $('#btnVerLog');
    const btnSync = $('#btnSyncNow');

    if (data.running) {
        infoEl.html('<span class="badge bg-warning text-dark me-1"><i class="fas fa-circle-notch fa-spin"></i> Sync en curso...</span>' +
                    'Iniciado: ' + formatSyncTime(data.startedAt));
        btnSync.prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin me-1"></i> Sincronizando...');
        // Polling automático mientras corre
        if (!gmbStatusInterval) {
            gmbStatusInterval = setInterval(loadGmbStatus, 4000);
        }
        return;
    }

    // Sync no está corriendo
    clearInterval(gmbStatusInterval);
    gmbStatusInterval = null;
    btnSync.prop('disabled', false).html('<i class="fas fa-sync-alt me-1"></i> Sincronizar Ahora');

    const last = data.lastSync;
    if (!last) {
        infoEl.text('Sin sincronizaciones registradas.');
        return;
    }

    const result = last.result;
    if (result && result.success && result.totals) {
        const t = result.totals;
        infoEl.html(
            `Último sync: <strong>${formatSyncTime(last.finishedAt)}</strong> — ` +
            `<span class="text-success">+${t.inserted} nuevas</span>, ` +
            `<span class="text-primary">~${t.updated} actualizadas</span>, ` +
            `<span class="text-danger">-${t.deleted} eliminadas</span>` +
            (t.errors > 0 ? ` <span class="text-warning">(${t.errors} errores)</span>` : '')
        );
        btnLog.show();
    } else if (result && !result.success) {
        infoEl.html(`<span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Error en último sync: ${result.error || 'desconocido'}</span>`);
        btnLog.show();
    } else {
        infoEl.text('Sin datos del último sync.');
    }
}

function formatSyncTime(isoStr) {
    if (!isoStr) return '-';
    try {
        return new Date(isoStr).toLocaleString('es-NI', { dateStyle: 'short', timeStyle: 'short' });
    } catch { return isoStr; }
}

/**
 * Dispara el sync manual.
 */
async function sincronizarAhora() {
    const confirm = await Swal.fire({
        title: '¿Sincronizar Reseñas?',
        text: 'Se descargarán todas las reseñas desde Google Business Profile. Puede tardar varios minutos.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#51B8AC',
        cancelButtonColor:  '#6c757d',
        confirmButtonText:  'Sí, sincronizar',
        cancelButtonText:   'Cancelar'
    });

    if (!confirm.isConfirmed) return;

    $('#btnSyncNow').prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin me-1"></i> Iniciando...');

    try {
        const res = await $.ajax({ url: 'ajax/gmb_sync_trigger.php', type: 'POST', dataType: 'json' });

        if (res.success) {
            Swal.fire({
                title: 'Sync Iniciado',
                text: 'El bot está sincronizando las reseñas. El panel se actualizará automáticamente.',
                icon: 'success',
                confirmButtonColor: '#51B8AC',
                timer: 3000,
                timerProgressBar: true
            });
            // Comenzar polling
            gmbStatusInterval = setInterval(loadGmbStatus, 4000);
            loadGmbStatus();
        } else if (res.running) {
            Swal.fire('En curso', 'El sync ya está en ejecución. Espera a que termine.', 'info');
            loadGmbStatus();
        } else {
            Swal.fire('Error', res.message || 'No se pudo iniciar el sync.', 'error');
            $('#btnSyncNow').prop('disabled', false).html('<i class="fas fa-sync-alt me-1"></i> Sincronizar Ahora');
        }
    } catch (e) {
        Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
        $('#btnSyncNow').prop('disabled', false).html('<i class="fas fa-sync-alt me-1"></i> Sincronizar Ahora');
    }
}

/**
 * Muestra el log del último sync en un modal.
 */
async function verLogSync() {
    const modal = new bootstrap.Modal(document.getElementById('logSyncModal'));
    $('#logSyncContent').text('Cargando...');
    modal.show();

    try {
        const res = await $.ajax({ url: 'ajax/gmb_sync_status.php', type: 'GET', dataType: 'json' });
        const log = res.lastSync?.result?.log;
        if (Array.isArray(log) && log.length) {
            $('#logSyncContent').text(log.join('\n'));
        } else {
            $('#logSyncContent').text('Sin log disponible.');
        }
    } catch (e) {
        $('#logSyncContent').text('Error al cargar el log.');
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
    } else if (tipo === 'sort-only') {
        // No se agrega sección de búsqueda o lista, solo quedan los botones de orden
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
    } else if (columna === 'locationId') {
        $.ajax({
            url: 'ajax/resenas_google_descargado_get_opciones_filtro.php',
            method: 'POST',
            data: { columna: columna },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    renderOpcionesFiltro(panel, columna, response.opciones, icon);
                }
            }
        });
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
