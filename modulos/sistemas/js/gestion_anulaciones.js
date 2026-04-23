/**
 * gestion_anulaciones.js
 * Lógica principal de la herramienta de Aprobación de Anulaciones.
 */

'use strict';

// ── Estado global ───────────────────────────────────────────
const AJAX_GET      = 'ajax/anulaciones_get.php';
const AJAX_APROBAR  = 'ajax/anulaciones_aprobar.php';
const AJAX_DETALLE  = 'ajax/anulaciones_detalle_pedido.php';
const AJAX_NUEVA    = 'ajax/anulaciones_nueva_web.php';

let paginaActual = 1;
let registrosPorPagina = 25;
let filtrosActivos = { 'Status': ['0'], 'Modalidad': ['2'] };
let ordenActivo = { columna: null, direccion: 'asc' };
let panelFiltroAbierto = null;
let totalRegistros = 0;
let pendingDecision = null;
let countdownVal = 60;
let countdownTimer = null;
let scrollTopInicial = 0;

// ── Bootstrap ───────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    cargarStats();
    actualizarVisualToggle();
    actualizarVisualToggleModalidad();
    cargarDatos(1);
    iniciarAutoRefresh();

    // Cerrar filtros solo si se hace clic fuera del panel Y del icono
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.filter-panel, .filter-icon').length) {
            cerrarTodosFiltros();
        }

        // Cerrar FAB si se hace clic fuera
        if (!$(e.target).closest('.fab-container').length) {
            $('.fab-container').removeClass('active');
            $('.btn-floating-pitaya').removeClass('active');
        }
    });

    // Manejar clic en el botón flotante (para móvil y para asegurar acción)
    $(document).on('click', '.btn-floating-pitaya', function (e) {
        const container = $(this).closest('.fab-container');
        if (container.length) {
            container.toggleClass('active');
            $(this).toggleClass('active');
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

    poblarSucursalesNuevaAnulacion();
});

async function poblarSucursalesNuevaAnulacion() {
    try {
        const data = await $.post('ajax/anulaciones_get_opciones_filtro.php', { columna: 'Sucursal' });
        if (data.success) {
            const sel = $('#new_sucursal');
            if (sel.length) {
                data.opciones.forEach(opt => {
                    sel.append(`<option value="${opt.valor}">${opt.texto}</option>`);
                });
            }
        }
    } catch (e) { console.error(e); }
}

// ── Stats ────────────────────────────────────────────────────
async function cargarStats() {
    try {
        const [rAll, rPend, rApro] = await Promise.all([
            fetch(AJAX_GET + '?status=-1&limit=1').then(r => r.json()),
            fetch(AJAX_GET + '?status=0&limit=1').then(r => r.json()),
            fetch(AJAX_GET + '?status=1&limit=1').then(r => r.json()),
        ]);
        document.getElementById('statTotal').textContent      = rAll.total  ?? '—';
        document.getElementById('statPendientes').textContent  = rPend.total ?? '—';
        document.getElementById('statAprobadas').textContent   = rApro.total ?? '—';

        // Ejecutadas: aprobadas con EjecutadoEnTienda=1
        const rEje = await fetch(AJAX_GET + '?status=1&limit=500').then(r => r.json());
        const eje = (rEje.registros || []).filter(r => parseInt(r.EjecutadoEnTienda) === 1).length;
        document.getElementById('statEjecutadas').textContent = eje;
    } catch (e) {
        console.warn('Stats error', e);
    }
}

// Cargar datos
async function cargarDatos(page = paginaActual) {
    paginaActual = page;
    const limit = $('#registrosPorPagina').val();
    registrosPorPagina = parseInt(limit);

    const tableBody = $('#tableBody');
    tableBody.html('<tr><td colspan="10" class="text-center py-4"><div class="spinner-border spinner-border-sm text-secondary"></div></td></tr>');
    $('#tableInfo').text('Cargando...');

    $.ajax({
        url: AJAX_GET,
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
                totalRegistros = response.total;
                const totalPages = response.paginas || 1;
                $('#tableInfo').text(`${response.registros.length} de ${response.total} registros (pág. ${paginaActual}/${totalPages})`);
                renderTabla(response.registros);
                renderizarPaginacion(response.total);
                actualizarIndicadoresFiltros();
            } else {
                tableBody.html(`<tr><td colspan="10" class="text-center py-4 text-danger"><i class="bi bi-exclamation-triangle me-1"></i>${response.error}</td></tr>`);
            }
        },
        error: function () {
            tableBody.html(`<tr><td colspan="10" class="text-center py-4 text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Error al cargar los datos</td></tr>`);
        }
    });
}

// ── Render tabla ─────────────────────────────────────────────
function renderTabla(registros) {
    const tbody = document.getElementById('tableBody');
    if (!registros.length) {
        tbody.innerHTML = `<tr><td colspan="10" class="text-center py-5 text-muted">
            <i class="bi bi-inbox fs-2 opacity-25 d-block mb-2"></i>
            No hay solicitudes con los filtros actuales.</td></tr>`;
        return;
    }

    tbody.innerHTML = registros.map(r => {
        const badge    = statusBadge(r.Status, r.EjecutadoEnTienda);
        // Mostrar solo la hora si la fecha es "irreal" o por preferencia del usuario
        const solicit  = r.HoraSolicitada ? r.HoraSolicitada.split(' ')[1] || r.HoraSolicitada : '—';
        // Limpiar segundos si existen (HH:mm:ss -> HH:mm)
        const solicitClean = solicit.length > 5 ? solicit.substring(0, 5) : solicit;
        
        const motivo   = r.Motivo ? escHtml(r.Motivo).substring(0, 50) : '<em class="text-muted">—</em>';
        const aprobPor = r.AprobadoPor || '—';
        const ejecutTime = r.HoraEjecutadaTienda ? (r.HoraEjecutadaTienda.split(' ')[1] || r.HoraEjecutadaTienda).substring(0, 5) : '';
        const ejecut   = parseInt(r.EjecutadoEnTienda) === 1
            ? `<span class="text-success small"><i class="bi bi-check-circle-fill"></i> ${ejecutTime}</span>`
            : `<span class="text-muted small">Pendiente</span>`;

        const modIcon = parseInt(r.Modalidad) === 2 
            ? '<i class="bi bi-globe text-primary" title="Web / Online"></i>' 
            : '<i class="bi bi-pc-display text-secondary" title="Local / Access"></i>';

        // Lógica de bloqueo por fecha pasada
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
        let esPasado = false;
        if (r.FechaPedido) {
            const fechaPed = new Date(r.FechaPedido + 'T00:00:00');
            if (fechaPed < hoy) esPasado = true;
        }

        const sucDesc = r.Sucursal_Nombre || `S${r.Sucursal}`;

        let acciones = `
            <button class="btn-accion btn-ver me-1" title="Ver detalle / decidir"
                    onclick="abrirModalDecision(${r.CodAnulacionHost},${r.CodPedido},${r.CodPedidoCambio || 0},${r.Sucursal},'${escHtml(sucDesc)}', ${esPasado})">
                <i class="bi bi-eye"></i>
            </button>`;

        if (PUEDE_APROBAR && parseInt(r.Status) === 0) {
            if (esPasado) {
                acciones += `
                <button class="btn-accion btn-disabled me-1" title="Bloqueado: Fecha pasada" disabled style="opacity:0.4; cursor:not-allowed">
                    <i class="bi bi-lock-fill"></i>
                </button>`;
            } else {
                acciones += `
                <button class="btn-accion btn-aprobar me-1" title="Aprobar"
                        onclick="accionRapida(${r.CodAnulacionHost},'aprobar')">
                    <i class="bi bi-check-lg"></i>
                </button>
                <button class="btn-accion btn-rechazar" title="Rechazar"
                        onclick="accionRapida(${r.CodAnulacionHost},'rechazar')">
                    <i class="bi bi-x-lg"></i>
                </button>`;
            }
        }

        return `<tr>
            <td><strong style="color:#dc3545">${r.CodPedido}</strong>
                ${r.CodPedidoCambio ? `<br><span class="text-primary small">↔ ${r.CodPedidoCambio}</span>` : ''}
            </td>
            <td style="font-size:12px; white-space:nowrap">
                <i class="bi bi-calendar3 text-muted me-1"></i> ${r.FechaPedido || '—'}
            </td>
            <td><span class="badge" style="background:#e8f5f3;color:#0E544C;font-size:11px">${sucDesc}</span></td>
            <td style="font-size:12px">${solicitClean}</td>
            <td class="text-center">${modIcon}</td>
            <td>${badge}</td>
            <td title="${escHtml(r.Motivo || '')}" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${motivo}</td>
            <td style="font-size:12px">${aprobPor}</td>
            <td>${ejecut}</td>
            <td class="text-center">${acciones}</td>
        </tr>`;
    }).join('');
}

function statusBadge(status, ejecutado) {
    const s = parseInt(status);
    const e = parseInt(ejecutado);
    if (s === 0) return '<span class="badge-an badge-pending">Pendiente</span>';
    if (s === 1 && e === 1) return '<span class="badge-an badge-done">Ejecutado</span>';
    if (s === 1) return '<span class="badge-an badge-approved">Aprobado</span>';
    if (s === 2) return '<span class="badge-an badge-rejected">Rechazado</span>';
    return `<span class="badge-an bg-secondary text-white">${status}</span>`;
}

// ── Paginación ───────────────────────────────────────────────
function renderizarPaginacion(total) {
    const totalPaginas = Math.ceil(total / registrosPorPagina);
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
        if (inicio > 2) paginacion.append(`<span class="pagination-btn" disabled>...</span>`);
    }

    for (let i = inicio; i <= fin; i++) {
        const activeClass = i === paginaActual ? 'active' : '';
        paginacion.append(`<button class="pagination-btn ${activeClass}" onclick="cambiarPagina(${i})">${i}</button>`);
    }

    if (fin < totalPaginas) {
        if (fin < totalPaginas - 1) paginacion.append(`<span class="pagination-btn" disabled>...</span>`);
        paginacion.append(`<button class="pagination-btn" onclick="cambiarPagina(${totalPaginas})">${totalPaginas}</button>`);
    }

    paginacion.append(`
        <button class="pagination-btn" onclick="cambiarPagina(${paginaActual + 1})" ${paginaActual === totalPaginas ? 'disabled' : ''}>
            <i class="bi bi-chevron-right"></i>
        </button>
    `);
}

function cambiarPagina(pagina) {
    if (pagina < 1 || pagina > Math.ceil(totalRegistros / registrosPorPagina)) return;
    paginaActual = pagina;
    cargarDatos();
}

function cambiarRegistrosPorPagina() {
    registrosPorPagina = parseInt($('#registrosPorPagina').val());
    paginaActual = 1;
    cargarDatos();
}

// ── Toggle filtro ──────────────────────────────────────────
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
        posicionarPanelFiltro(panel, icon);
    } else if (tipo === 'number') {
        crearFiltroNumerico(panel, columna);
        posicionarPanelFiltro(panel, icon);
    } else if (tipo === 'list') {
        cargarOpcionesFiltro(panel, columna, icon);
    } else if (tipo === 'daterange') {
        crearCalendarioDoble(panel, columna);
        posicionarPanelFiltro(panel, icon);
    }
}

function crearFiltroNumerico(panel, columna) {
    const valorMin = filtrosActivos[columna]?.min || '';
    const valorMax = filtrosActivos[columna]?.max || '';
    panel.append(`
        <div class="filter-section" style="margin-top: 12px;">
            <span class="filter-section-title">Rango:</span>
            <div class="numeric-inputs">
                <input type="number" class="filter-search" placeholder="Mínimo" value="${valorMin}" onchange="filtrarNumerico('${columna}', 'min', this.value)">
                <input type="number" class="filter-search" placeholder="Máximo" value="${valorMax}" onchange="filtrarNumerico('${columna}', 'max', this.value)">
            </div>
        </div>
    `);
}

function filtrarNumerico(columna, tipo, valor) {
    if (!filtrosActivos[columna]) filtrosActivos[columna] = {};
    if (valor === '') {
        delete filtrosActivos[columna][tipo];
        if (Object.keys(filtrosActivos[columna]).length === 0) delete filtrosActivos[columna];
    } else {
        filtrosActivos[columna][tipo] = valor;
    }
    paginaActual = 1;
    cargarDatos();
}

function crearCalendarioDoble(panel, columna) {
    const hoy = new Date();
    panel.append(`
        <div class="filter-section" style="margin-top: 8px;">
            <span class="filter-section-title">Seleccionar Rango:</span>
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
        const selectMes = $('#mesCalendario');
        const selectAño = $('#añoCalendario');
        meses.forEach((mes, idx) => selectMes.append(`<option value="${idx}" ${idx === hoy.getMonth() ? 'selected' : ''}>${mes}</option>`));
        for (let año = hoy.getFullYear() - 5; año <= hoy.getFullYear() + 1; año++) {
            selectAño.append(`<option value="${año}" ${año === hoy.getFullYear() ? 'selected' : ''}>${año}</option>`);
        }
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
        html += `<div class="daterange-calendar-day ${clases}" onclick="event.stopPropagation(); seleccionarFechaUnico('${fechaStr}', '${columna}')">${dia}</div>`;
    }
    html += '</div>';
    $('#calendarioUnico').html(html);
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
    if (window.event) window.event.stopPropagation();
    if (!filtrosActivos[columna]) filtrosActivos[columna] = { desde: null, hasta: null };
    let fDesde = filtrosActivos[columna].desde;
    let fHasta = filtrosActivos[columna].hasta;

    if (!fDesde) {
        filtrosActivos[columna].desde = fecha;
    } else if (!fHasta) {
        if (fecha < fDesde) {
            filtrosActivos[columna].desde = fecha;
            filtrosActivos[columna].hasta = fDesde;
        } else {
            filtrosActivos[columna].hasta = fecha;
        }
    } else {
        if (fecha < fDesde) filtrosActivos[columna].desde = fecha;
        else filtrosActivos[columna].hasta = fecha;
    }
    actualizarCalendarioUnico(columna);
    if (filtrosActivos[columna].desde && filtrosActivos[columna].hasta) {
        paginaActual = 1;
        cargarDatos();
    }
}

function cargarOpcionesFiltro(panel, columna, icon) {
    $.ajax({
        url: 'ajax/anulaciones_get_opciones_filtro.php',
        method: 'POST',
        data: { columna: columna },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                let html = '<div class="filter-section" style="margin-top: 12px;"><span class="filter-section-title">Filtrar por:</span>';
                html += '<input type="text" class="filter-search" placeholder="Buscar..." onkeyup="buscarEnOpciones(this)">';
                html += '<div class="filter-options">';
                response.opciones.forEach(opcion => {
                    const checked = filtrosActivos[columna] && filtrosActivos[columna].includes(opcion.valor) ? 'checked' : '';
                    html += `<div class="filter-option"><input type="checkbox" value="${opcion.valor}" ${checked} onchange="toggleOpcionFiltro('${columna}', '${opcion.valor}', this.checked)"><span>${opcion.texto}</span></div>`;
                });
                html += '</div></div>';
                panel.append(html);
                posicionarPanelFiltro(panel, icon);
            }
        }
    });
}

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

    if (left + panelWidth > windowWidth) left = windowWidth - panelWidth - 10;
    if (left < 10) left = 10;
    if (top + panelHeight > windowHeight + scrollTop) top = Math.max(scrollTop + 10, windowHeight + scrollTop - panelHeight - 10);

    panel.css({ top: top + 'px', left: left + 'px' });
}

function actualizarIndicadoresFiltros() {
    $('.filter-icon').removeClass('has-filter');
    Object.keys(filtrosActivos).forEach(columna => {
        const valor = filtrosActivos[columna];
        if ((Array.isArray(valor) && valor.length > 0) || (typeof valor === 'object' && Object.keys(valor).length > 0) || (typeof valor !== 'object' && valor !== '')) {
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

function buscarEnOpciones(input) {
    const busqueda = input.value.toLowerCase();
    $(input).siblings('.filter-options').find('.filter-option').each(function () {
        $(this).toggle($(this).text().toLowerCase().includes(busqueda));
    });
}

function formatearFecha(fecha) {
    if (!fecha) return '-';
    const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    const d = new Date(fecha);
    return `${String(d.getDate()).padStart(2, '0')}-${meses[d.getMonth()]}-${String(d.getFullYear()).slice(-2)}`;
}

// ── Modal Decisión (Ver / Aprobar / Rechazar) ────────────────
async function abrirModalDecision(id, codPedido, codCambio, sucursal, sucursalNombre, esPasado) {
    pendingDecision = { id, codPedido, codCambio, sucursal, esPasado };

    document.getElementById('dec_codPedido').textContent = codPedido;
    document.getElementById('dec_codCambio').textContent = codCambio > 0 ? codCambio : '—';
    document.getElementById('dec_sucursal').textContent  = sucursalNombre || ('S' + sucursal);
    document.getElementById('dec_motivo').textContent    = '...';

    // Mostrar/ocultar tab cambio
    document.getElementById('tabCambioItem').style.display = codCambio > 0 ? '' : 'none';

    // Cargar motivo desde la fila (ya tenemos el dato en la tabla)
    const filas = document.querySelectorAll('#tableBody tr');
    filas.forEach(tr => {
        const tdPedido = tr.querySelector('td:nth-child(2) strong');
        if (tdPedido && tdPedido.textContent == codPedido) {
            const motivoEl = tr.querySelector('td:nth-child(6)');
            if (motivoEl) document.getElementById('dec_motivo').textContent = motivoEl.title || motivoEl.textContent;
        }
    });

    if (document.getElementById('dec_comentario')) {
        document.getElementById('dec_comentario').value = '';
    }

    // Bloquear botones si es pasado
    const btnApr = document.getElementById('btnAprobar');
    const btnRec = document.getElementById('btnRechazar');
    if (btnApr) btnApr.disabled = !!esPasado;
    if (btnRec) btnRec.disabled = !!esPasado;
    if (esPasado) {
        if (btnApr) btnApr.title = 'Bloqueado: Fecha pasada';
        if (btnRec) btnRec.title = 'Bloqueado: Fecha pasada';
    } else {
        if (btnApr) btnApr.title = 'Aprobar';
        if (btnRec) btnRec.title = 'Rechazar';
    }

    // Activar tab pedido
    setActiveTab('pedido');
    mostrarDetallePlaceholder();

    const modal = new bootstrap.Modal(document.getElementById('modalDecision'));
    modal.show();

    // Cargar detalle del pedido principal
    await cargarDetallePedido(codPedido, sucursal, 'pedido');
}

function setActiveTab(tipo) {
    document.getElementById('tab-pedido-link').classList.toggle('active', tipo === 'pedido');
    const cambioLink = document.getElementById('tab-cambio-link');
    if (cambioLink) cambioLink.classList.toggle('active', tipo === 'cambio');
}

function mostrarDetallePlaceholder() {
    document.getElementById('detallePlaceholder').style.display = '';
    document.getElementById('detalleContenido').style.display   = 'none';
}

async function verDetallePedido(tipo) {
    setActiveTab(tipo);
    const cod = tipo === 'pedido' ? pendingDecision.codPedido : pendingDecision.codCambio;
    if (!cod || cod === 0) return;
    mostrarDetallePlaceholder();
    await cargarDetallePedido(cod, pendingDecision.sucursal, tipo);
}

async function cargarDetallePedido(codPedido, sucursal, tipo) {
    try {
        const data = await fetch(`${AJAX_DETALLE}?cod_pedido=${codPedido}&sucursal=${sucursal}`)
            .then(r => r.json());

        document.getElementById('detallePlaceholder').style.display = 'none';
        const contenido = document.getElementById('detalleContenido');
        contenido.style.display = '';

        if (!data.success || !data.items || data.items.length === 0) {
            contenido.innerHTML = `<div class="alert alert-warning py-2 small">
                <i class="bi bi-exclamation-triangle me-1"></i>
                No se encontraron líneas para el pedido <strong>${codPedido}</strong> en la sucursal <strong>S${sucursal}</strong>.
            </div>`;
            return;
        }

        const info = data.resumen;
        const anulado = parseInt(info.Anulado) === -1 || parseInt(info.Anulado) === 1;

        contenido.innerHTML = `
            <div class="detalle-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="cod">#${codPedido} ${anulado ? '<span class="badge bg-danger ms-2" style="font-size:12px">ANULADO</span>' : ''}</div>
                        <div style="font-size:12px;opacity:.85">${info.Fecha || ''} ${info.Hora || ''} · ${info.Sucursal_Nombre || 'S' + sucursal}</div>
                    </div>
                    <div class="text-end">
                        <div style="font-size:12px;opacity:.85">${info.Modalidad || ''} · ${info.aPOS || ''}</div>
                        <div style="font-size:11px;opacity:.7">${info.Caja || ''}</div>
                    </div>
                </div>
            </div>
            <div class="detalle-resumen">
                ${chip('Cliente', info.CodCliente || '—')}
                ${chip('Tipo', info.Tipo || '—')}
                ${chip('Motorizado', info.Motorizado || '—')}
                ${chip('Delivery', info.Delivery_Nombre || '—')}
                ${chip('Monto Factura', info.MontoFactura ? 'C$ ' + parseFloat(info.MontoFactura).toFixed(2) : '—')}
                ${chip('Propina', info.Propina ? 'C$ ' + parseFloat(info.Propina).toFixed(2) : '—')}
                ${anulado ? chip('Motivo Anulación', info.MotivoAnulado || '—') : ''}
            </div>
            <table class="table table-detalle table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Medida</th>
                        <th class="text-center">Cant.</th>
                        <th class="text-end">P. Unit.</th>
                        <th class="text-end">Subtotal</th>
                        <th>Promo</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.items.map(it => {
                        const sub = (parseFloat(it.Cantidad || 0) * parseFloat(it.Precio_Unitario_Sin_Descuento || it.Precio || 0)).toFixed(2);
                        return `<tr class="${parseInt(it.Anulado) === -1 ? 'fila-anulada' : ''}">
                            <td>${escHtml(it.DBBatidos_Nombre || it.NombreGrupo || '—')}</td>
                            <td>${escHtml(it.Medida || '—')}</td>
                            <td class="text-center">${it.Cantidad}</td>
                            <td class="text-end">C$ ${parseFloat(it.Precio_Unitario_Sin_Descuento || it.Precio || 0).toFixed(2)}</td>
                            <td class="text-end">C$ ${sub}</td>
                            <td class="text-center">${it.CodigoPromocion ? `<span class="badge bg-warning text-dark" style="font-size:10px">${it.CodigoPromocion}</span>` : '—'}</td>
                        </tr>`;
                    }).join('')}
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-end fw-bold">Total Factura:</td>
                        <td class="text-end fw-bold text-success">C$ ${parseFloat(info.MontoFactura || 0).toFixed(2)}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        `;
    } catch (e) {
        document.getElementById('detallePlaceholder').style.display = 'none';
        document.getElementById('detalleContenido').style.display = '';
        document.getElementById('detalleContenido').innerHTML =
            `<div class="alert alert-danger py-2 small">Error al cargar detalle: ${e.message}</div>`;
    }
}

function chip(lbl, val) {
    return `<div class="det-chip"><span class="lbl">${lbl}</span><span class="val">${escHtml(String(val))}</span></div>`;
}

// ── Ejecutar decisión ────────────────────────────────────────
async function ejecutarDecision(accion) {
    if (!pendingDecision || !PUEDE_APROBAR) return;

    const comentario = document.getElementById('dec_comentario')?.value.trim() || '';
    const btnApr = document.getElementById('btnAprobar');
    const btnRec = document.getElementById('btnRechazar');
    if (btnApr) btnApr.disabled = true;
    if (btnRec) btnRec.disabled = true;

    try {
        const data = await fetch(AJAX_APROBAR, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                cod_anulacion_host: pendingDecision.id,
                accion,
                comentario,
                aprobado_por: USUARIO_ACTUAL
            })
        }).then(r => r.json());

        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalDecision')).hide();
            mostrarToast(data.message, 'success');
            cargarDatos(paginaActual);
            cargarStats();
        } else {
            mostrarToast('Error: ' + data.error, 'danger');
        }
    } catch (e) {
        mostrarToast('Error de red: ' + e.message, 'danger');
    } finally {
        if (btnApr) btnApr.disabled = false;
        if (btnRec) btnRec.disabled = false;
    }
}

// ── Acción rápida (sin abrir modal) ─────────────────────────
async function accionRapida(id, accion) {
    if (!PUEDE_APROBAR) return;
    const msg = accion === 'aprobar' ? '¿Aprobar esta solicitud?' : '¿Rechazar esta solicitud?';
    if (!confirm(msg)) return;

    try {
        const data = await fetch(AJAX_APROBAR, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                cod_anulacion_host: id, accion,
                comentario: '', aprobado_por: USUARIO_ACTUAL
            })
        }).then(r => r.json());

        if (data.success) {
            mostrarToast(data.message, 'success');
            cargarDatos(paginaActual);
            cargarStats();
        } else {
            mostrarToast('Error: ' + data.error, 'danger');
        }
    } catch (e) {
        mostrarToast('Error de red: ' + e.message, 'danger');
    }
}

// ── Nueva Anulación Web ──────────────────────────────────────
function abrirModalNuevaAnulacion() {
    document.getElementById('new_codPedido').value = '';
    document.getElementById('new_motivo').value    = '';
    document.getElementById('new_sucursal').value  = '';
    document.getElementById('newPedidoPreview').style.display = 'none';
    document.getElementById('newPedidoEmpty').style.display   = 'none';
    document.getElementById('btnEnviarAnulacionWeb').disabled = true;
    new bootstrap.Modal(document.getElementById('modalNuevaAnulacion')).show();
}

async function buscarPedidoWeb() {
    const cod = parseInt(document.getElementById('new_codPedido').value);
    const suc = parseInt(document.getElementById('new_sucursal').value);
    if (!cod || !suc) return;

    document.getElementById('newPedidoPreview').style.display = 'none';
    document.getElementById('newPedidoEmpty').style.display   = 'none';
    document.getElementById('btnEnviarAnulacionWeb').disabled = true;

    try {
        const data = await fetch(`${AJAX_DETALLE}?cod_pedido=${cod}&sucursal=${suc}`).then(r => r.json());
        if (!data.success || !data.items || !data.items.length) {
            document.getElementById('newPedidoEmpty').style.display = '';
            return;
        }
        const info = data.resumen;
        document.getElementById('newPedidoDetalle').innerHTML = `
            <div class="detalle-header">
                <div class="cod">#${cod} · ${info.Sucursal_Nombre || 'S' + suc}</div>
                <div style="font-size:12px;opacity:.85">${info.Fecha || ''} ${info.Hora || ''} · ${info.Modalidad || ''} · ${info.aPOS || ''}</div>
            </div>
            <div class="detalle-resumen">
                ${chip('Tipo', info.Tipo || '—')}
                ${chip('Cliente', info.CodCliente || '—')}
                ${chip('Monto', info.MontoFactura ? 'C$ ' + parseFloat(info.MontoFactura).toFixed(2) : '—')}
            </div>
            <table class="table table-detalle table-bordered mb-0">
                <thead><tr><th>Producto</th><th>Medida</th><th class="text-center">Cant.</th><th class="text-end">Precio</th></tr></thead>
                <tbody>
                ${data.items.map(it => `<tr>
                    <td>${escHtml(it.DBBatidos_Nombre || it.NombreGrupo || '—')}</td>
                    <td>${escHtml(it.Medida || '—')}</td>
                    <td class="text-center">${it.Cantidad}</td>
                    <td class="text-end">C$ ${parseFloat(it.Precio || 0).toFixed(2)}</td>
                </tr>`).join('')}
                </tbody>
            </table>`;
        document.getElementById('newPedidoPreview').style.display = '';
        document.getElementById('btnEnviarAnulacionWeb').disabled = false;
    } catch (e) {
        document.getElementById('newPedidoEmpty').style.display = '';
    }
}

async function enviarAnulacionWeb() {
    const cod    = parseInt(document.getElementById('new_codPedido').value);
    const suc    = parseInt(document.getElementById('new_sucursal').value);
    const motivo = document.getElementById('new_motivo').value.trim();

    if (!cod || !suc || !motivo) {
        mostrarToast('Completa todos los campos antes de enviar.', 'warning');
        return;
    }

    document.getElementById('btnEnviarAnulacionWeb').disabled = true;

    try {
        const data = await fetch(AJAX_NUEVA, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ cod_pedido: cod, sucursal: suc, motivo, aprobado_por: USUARIO_ACTUAL })
        }).then(r => r.json());

        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalNuevaAnulacion')).hide();
            mostrarToast(data.message, 'success');
            cargarDatos(1);
            cargarStats();
        } else {
            mostrarToast('Error: ' + data.error, 'danger');
            document.getElementById('btnEnviarAnulacionWeb').disabled = false;
        }
    } catch (e) {
        mostrarToast('Error de red: ' + e.message, 'danger');
        document.getElementById('btnEnviarAnulacionWeb').disabled = false;
    }
}

// ── Auto-refresh ─────────────────────────────────────────────
function iniciarAutoRefresh() {
    countdownVal = 60;
    clearInterval(countdownTimer);
    countdownTimer = setInterval(() => {
        countdownVal--;
        const el = document.getElementById('countdown');
        if (el) el.textContent = countdownVal;
        if (countdownVal <= 0) {
            cargarDatos(paginaActual);
            cargarStats();
            countdownVal = 60;
        }
    }, 1000);
}

// ── Helpers ──────────────────────────────────────────────────
function limpiarFiltros() {
    filtrosActivos = { 'Status': ['0'], 'Modalidad': ['2'] };
    ordenActivo = { columna: null, direccion: 'asc' };
    cerrarTodosFiltros();
    actualizarVisualToggle();
    actualizarVisualToggleModalidad();
    paginaActual = 1;
    cargarDatos();
}

function escHtml(str) {
    return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function mostrarToast(msg, tipo = 'success') {
    const icons = { success: 'check-circle-fill', danger: 'x-circle-fill', warning: 'exclamation-triangle-fill', info: 'info-circle-fill' };
    const colors = { success: '#198754', danger: '#dc3545', warning: '#ffc107', info: '#0dcaf0' };
    const toast = document.createElement('div');
    toast.style.cssText = `position:fixed;bottom:24px;right:24px;z-index:9999;
        background:#fff;border-left:4px solid ${colors[tipo] || colors.info};
        box-shadow:0 4px 16px rgba(0,0,0,.15);border-radius:8px;
        padding:12px 18px;font-size:13px;font-weight:500;min-width:280px;
        display:flex;align-items:center;gap:10px;
        animation:fadeInRight .3s ease`;
    toast.innerHTML = `<i class="bi bi-${icons[tipo] || 'info-circle-fill'}" style="color:${colors[tipo]};font-size:1.2rem"></i>${escHtml(msg)}`;
    document.body.appendChild(toast);

    // Animación CSS inline
    const style = document.createElement('style');
    style.textContent = `@keyframes fadeInRight{from{opacity:0;transform:translateX(30px)}to{opacity:1;transform:translateX(0)}}`;
    document.head.appendChild(style);

    setTimeout(() => toast.remove(), 4000);
}

// ── Filtro de círculos (Status) ──────────────────────────────
function setEstadoFilter(state) {
    if (state === 'all') {
        delete filtrosActivos['Status'];
    } else {
        filtrosActivos['Status'] = [state];
    }

    actualizarVisualToggle();
    paginaActual = 1;
    cargarDatos();
}

function actualizarVisualToggle() {
    let currentKey = 'all';
    if (filtrosActivos['Status'] && filtrosActivos['Status'].length > 0) {
        currentKey = filtrosActivos['Status'][0];
    }

    document.querySelectorAll('.estado-filter-circles .filter-circle').forEach(circle => {
        circle.classList.remove('active');
    });

    const activeCircle = document.querySelector(`.estado-filter-circles .filter-circle[data-state="${currentKey}"]`);
    if (activeCircle) activeCircle.classList.add('active');
}

// ── Filtro de círculos (Modalidad) ──────────────────────────
function setModalidadFilter(mod) {
    if (mod === 'all') {
        delete filtrosActivos['Modalidad'];
    } else {
        filtrosActivos['Modalidad'] = [mod];
    }

    actualizarVisualToggleModalidad();
    paginaActual = 1;
    cargarDatos();
}

function actualizarVisualToggleModalidad() {
    let currentKey = 'all';
    if (filtrosActivos['Modalidad'] && filtrosActivos['Modalidad'].length > 0) {
        currentKey = filtrosActivos['Modalidad'][0];
    }

    document.querySelectorAll('th[data-column="Modalidad"] .filter-circle').forEach(circle => {
        circle.classList.remove('active');
    });

    const activeCircle = document.querySelector(`th[data-column="Modalidad"] .filter-circle[data-mod="${currentKey}"]`);
    if (activeCircle) activeCircle.classList.add('active');
}
