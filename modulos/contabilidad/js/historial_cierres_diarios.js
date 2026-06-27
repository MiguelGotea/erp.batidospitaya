/**
 * historial_cierres_diarios.js
 * Lógica del Historial de Cierres Diarios
 * Patrón de filtros por columna heredado de cupones.js
 */

let paginaActual        = 1;
let registrosPorPagina  = 25;
let filtrosActivos      = {};
let ordenActivo         = { columna: 'Fecha', direccion: 'desc' };
let panelFiltroAbierto  = null;
let totalRegistros      = 0;
let scrollTopInicial    = 0;

// ── Inicialización ────────────────────────────────────────────
$(document).ready(function () {
    cargarDatos();

    // Cerrar filtros al hacer clic fuera
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

    // Cerrar filtros al hacer scroll significativo
    $(window).on('scroll', function () {
        if (panelFiltroAbierto && Math.abs($(window).scrollTop() - scrollTopInicial) > 50) {
            cerrarTodosFiltros();
        }
    });

    $(window).on('resize', function () {
        if (panelFiltroAbierto) cerrarTodosFiltros();
    });

    // No cerrar filtros al scrollear dentro de la tabla
    $('.table-responsive').on('scroll', function (e) {
        e.stopPropagation();
    });

    // Toggle FAB al hacer clic en el botón flotante
    $(document).on('click', '.btn-floating-pitaya', function (e) {
        const container = $(this).closest('.fab-container');
        if (container.length) {
            container.toggleClass('active');
            $(this).toggleClass('active');
        }
    });
});

// ── Cargar datos ──────────────────────────────────────────────
function cargarDatos() {
    const tbody = $('#hcdTbody');
    tbody.html('<tr><td colspan="11" class="text-center py-5"><div class="spinner-border text-success spinner-border-sm"></div> <span class="text-muted ms-2">Cargando historial...</span></td></tr>');

    $.ajax({
        url:      'ajax/hcd_get_datos.php',
        method:   'POST',
        dataType: 'json',
        data: {
            pagina:               paginaActual,
            registros_por_pagina: registrosPorPagina,
            filtros:              JSON.stringify(filtrosActivos),
            orden:                JSON.stringify(ordenActivo)
        },
        success: function (resp) {
            if (!resp.success) {
                tbody.html(`<tr><td colspan="11" class="text-center text-danger py-4">${resp.message || 'Error al cargar datos'}</td></tr>`);
                return;
            }
            totalRegistros = resp.total_registros;
            renderizarTabla(resp.datos);
            renderizarPaginacion(totalRegistros);
            actualizarIndicadoresFiltros();
        },
        error: function () {
            tbody.html('<tr><td colspan="11" class="text-center text-danger py-4">Error de conexión con el servidor.</td></tr>');
        }
    });
}

// ── Renderizar tabla ──────────────────────────────────────────
function renderizarTabla(datos) {
    const tbody = $('#hcdTbody');
    tbody.empty();

    if (!datos || datos.length === 0) {
        tbody.html(`
            <tr>
                <td colspan="11">
                    <div class="hcd-empty">
                        <i class="bi bi-inbox"></i>
                        <p>No se encontraron cierres con los filtros aplicados.</p>
                    </div>
                </td>
            </tr>
        `);
        return;
    }

    datos.forEach(function (row) {
        const faltanteAcum  = parseInt(row.Faltante) || 0;
        const faltanteDesag = (row.FaltanteDesagregado != null)
                              ? parseInt(row.FaltanteDesagregado)
                              : faltanteAcum;
        const badgeSF       = buildBadgeSF(faltanteDesag);
        const badgeSFAcum   = buildBadgeSF(faltanteAcum);
        const obs       = (row.Observaciones || '').trim();
        const hiStr     = row.HoraInicial ? row.HoraInicial.substring(0, 5) : '—';
        const hfStr     = row.HoraFinal   ? row.HoraFinal.substring(0, 5)   : '—';
        const fechaStr  = formatFecha(row.Fecha);
        const cajeroStr = row.cajero || 'Sin cajero';

        // URL para botón Ver: pasa fecha, sucursal y código de cierre
        const urlVer = `balance_cierre_diario.php?fecha=${encodeURIComponent(row.Fecha)}&sucursal=${encodeURIComponent(row.Sucursal)}&cierre=${encodeURIComponent(row.CodigoCierre)}`;

        // Renderizar Alertas
        let alertasHtml = '';
        if (row.alertas && row.alertas.length > 0) {
            alertasHtml = '<div class="d-flex flex-wrap gap-1">' + row.alertas.map(a => `<span class="badge bg-${a.tipo}">${escHtml(a.texto)}</span>`).join('') + '</div>';
        } else {
            alertasHtml = '<span class="text-muted small">—</span>';
        }

        const tr = `
            <tr>
                <td>${escHtml(row.nombre_sucursal || '—')}</td>
                <td class="text-nowrap">${fechaStr}</td>
                <td>${row.CodigoCierre}</td>
                <td>${escHtml(cajeroStr)}</td>
                <td>${badgeSF}</td>
                <td>${badgeSFAcum}</td>
                <td class="text-nowrap">${hiStr}</td>
                <td class="text-nowrap">${hfStr}</td>
                <td>${escHtml(obs || '—')}</td>
                <td>${alertasHtml}</td>
                <td class="text-center">
                    <a href="${urlVer}" target="_blank" class="btn-hcd-ver">
                        <i class="bi bi-eye"></i> Ver
                    </a>
                </td>
            </tr>
        `;
        tbody.append(tr);
    });
}

// Construye badge de sobrante / faltante
function buildBadgeSF(faltante) {
    if (faltante === 0) {
        return '<span class="hcd-badge-cero"><i class="bi bi-check-circle"></i> Exacto</span>';
    } else if (faltante > 0) {
        return `<span class="hcd-badge-sobrante"><i class="bi bi-arrow-up-circle-fill"></i> Sobrante: ${faltante}</span>`;
    } else {
        return `<span class="hcd-badge-faltante"><i class="bi bi-arrow-down-circle-fill"></i> Faltante: ${Math.abs(faltante)}</span>`;
    }
}

// ── Toggle filtro por columna ─────────────────────────────────
function toggleFilter(icon) {
    const th     = $(icon).closest('th');
    const columna = th.data('column');
    const tipo    = th.data('type');

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

// ── Crear panel de filtro ─────────────────────────────────────
function crearPanelFiltro(th, columna, tipo, icon) {
    const panel = $('<div class="filter-panel show"></div>');

    if (tipo === 'daterange') panel.addClass('has-daterange');

    // Sección de ordenamiento
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

    $('body').append(panel);

    // Contenido según tipo
    if (tipo === 'text') {
        const v = filtrosActivos[columna] || '';
        panel.append(`
            <div class="filter-section" style="margin-top:12px;">
                <span class="filter-section-title">Buscar:</span>
                <input type="text" class="filter-search" placeholder="Escribir..."
                       value="${escHtml(v)}"
                       oninput="filtrarBusqueda('${columna}', this.value)">
            </div>
        `);
        posicionarPanel(panel, icon);
    } else if (tipo === 'number') {
        crearFiltroNumerico(panel, columna);
        posicionarPanel(panel, icon);
    } else if (tipo === 'list') {
        cargarOpcionesLista(panel, columna, icon);
    } else if (tipo === 'daterange') {
        crearCalendarioDoble(panel, columna);
        posicionarPanel(panel, icon);
    }
}

// ── Filtro numérico (rango) ───────────────────────────────────
function crearFiltroNumerico(panel, columna) {
    const vMin = filtrosActivos[columna]?.min ?? '';
    const vMax = filtrosActivos[columna]?.max ?? '';
    panel.append(`
        <div class="filter-section" style="margin-top:12px;">
            <span class="filter-section-title">Rango:</span>
            <div class="numeric-inputs">
                <input type="number" class="filter-search" placeholder="Mínimo"
                       value="${vMin}" onchange="filtrarNumerico('${columna}', 'min', this.value)">
                <input type="number" class="filter-search" placeholder="Máximo"
                       value="${vMax}" onchange="filtrarNumerico('${columna}', 'max', this.value)">
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

// ── Filtro lista (sucursal) ───────────────────────────────────
function cargarOpcionesLista(panel, columna, icon) {
    $.ajax({
        url:      'ajax/hcd_get_opciones_filtro.php',
        method:   'POST',
        dataType: 'json',
        data:     { columna: columna },
        success: function (resp) {
            if (!resp.success) return;

            let html = '<div class="filter-section" style="margin-top:12px;">';
            html    += '<span class="filter-section-title">Filtrar por:</span>';
            html    += '<input type="text" class="filter-search" placeholder="Buscar..." onkeyup="buscarEnOpciones(this)">';
            html    += '<div class="filter-options">';

            resp.opciones.forEach(function (op) {
                const checked = filtrosActivos[columna] && filtrosActivos[columna].includes(op.valor) ? 'checked' : '';
                html += `
                    <div class="filter-option">
                        <input type="checkbox" value="${escHtml(op.valor)}" ${checked}
                               onchange="toggleOpcionFiltro('${columna}', '${escHtml(op.valor)}', this.checked)">
                        <span>${escHtml(op.texto)}</span>
                    </div>`;
            });

            html += '</div></div>';
            panel.append(html);
            posicionarPanel(panel, icon);
        }
    });
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

// ── Filtro rango de fechas (calendario) ──────────────────────
function crearCalendarioDoble(panel, columna) {
    const hoy        = new Date();
    const mesActual  = hoy.getMonth();
    const añoActual  = hoy.getFullYear();

    panel.append(`
        <div class="filter-section" style="margin-top:8px;">
            <span class="filter-section-title">Seleccionar Rango:</span>
            <div class="daterange-calendar-container">
                <div class="daterange-month-selector">
                    <select id="hcdMesCalendario" onchange="hcdActualizarCalendario('${columna}')"></select>
                    <select id="hcdAñoCalendario" onchange="hcdActualizarCalendario('${columna}')"></select>
                </div>
                <div class="daterange-calendar" id="hcdCalendario"></div>
            </div>
            <div class="mt-2" style="font-size:0.78rem; color:#666;">
                <i class="bi bi-info-circle"></i> Clic en dos fechas para definir el rango.
            </div>
        </div>
    `);

    setTimeout(function () {
        const meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        const sel   = $('#hcdMesCalendario');
        const selA  = $('#hcdAñoCalendario');

        meses.forEach((m, i) => sel.append(`<option value="${i}" ${i === mesActual ? 'selected' : ''}>${m}</option>`));
        for (let a = añoActual - 5; a <= añoActual + 1; a++) {
            selA.append(`<option value="${a}" ${a === añoActual ? 'selected' : ''}>${a}</option>`);
        }
        hcdActualizarCalendario(columna);
    }, 50);
}

function hcdActualizarCalendario(columna) {
    const mes  = parseInt($('#hcdMesCalendario').val());
    const año  = parseInt($('#hcdAñoCalendario').val());
    const primerDia = new Date(año, mes, 1).getDay();
    const diasMes   = new Date(año, mes + 1, 0).getDate();
    const dias      = ['D','L','M','M','J','V','S'];

    let html = '<div class="daterange-calendar-header">';
    dias.forEach(d => html += `<div class="daterange-calendar-day-name">${d}</div>`);
    html += '</div><div class="daterange-calendar-days">';

    for (let i = 0; i < primerDia; i++) html += '<div class="daterange-calendar-day empty"></div>';
    for (let d = 1; d <= diasMes; d++) {
        const fStr  = `${año}-${String(mes+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const cls   = hcdClasesCalendario(fStr, columna);
        html += `<div class="daterange-calendar-day ${cls}" onclick="event.stopPropagation(); hcdSeleccionarFecha('${fStr}','${columna}')">${d}</div>`;
    }
    html += '</div>';
    $('#hcdCalendario').html(html);
}

function hcdClasesCalendario(fecha, columna) {
    const fD = filtrosActivos[columna]?.desde;
    const fH = filtrosActivos[columna]?.hasta;
    const cls = [];
    if (fD && fecha === fD) cls.push('selected');
    if (fH && fecha === fH) cls.push('selected');
    if (fD && fH && fecha > fD && fecha < fH) cls.push('in-range');
    return cls.join(' ');
}

function hcdSeleccionarFecha(fecha, columna) {
    if (!filtrosActivos[columna]) filtrosActivos[columna] = { desde: null, hasta: null };

    const fD = filtrosActivos[columna].desde;
    const fH = filtrosActivos[columna].hasta;

    if (!fD) {
        filtrosActivos[columna].desde = fecha;
    } else if (!fH) {
        if (fecha < fD) { filtrosActivos[columna].desde = fecha; filtrosActivos[columna].hasta = fD; }
        else             { filtrosActivos[columna].hasta = fecha; }
    } else {
        if (fecha < fD)      filtrosActivos[columna].desde = fecha;
        else if (fecha > fH) filtrosActivos[columna].hasta = fecha;
        else                 filtrosActivos[columna].hasta  = fecha;
    }

    hcdActualizarCalendario(columna);

    if (filtrosActivos[columna].desde && filtrosActivos[columna].hasta) {
        paginaActual = 1;
        cargarDatos();
    }
}

// ── Filtro texto ──────────────────────────────────────────────
function filtrarBusqueda(columna, valor) {
    if (valor.trim() === '') delete filtrosActivos[columna];
    else filtrosActivos[columna] = valor;
    paginaActual = 1;
    cargarDatos();
}

// ── Limpiar filtro ────────────────────────────────────────────
function limpiarFiltro(columna) {
    delete filtrosActivos[columna];
    cerrarTodosFiltros();
    paginaActual = 1;
    cargarDatos();
}

// ── Cerrar todos los filtros ──────────────────────────────────
function cerrarTodosFiltros() {
    $('.filter-panel').remove();
    $('.filter-icon').removeClass('active');
    panelFiltroAbierto = null;
}

// ── Aplicar ordenamiento ──────────────────────────────────────
function aplicarOrden(columna, direccion) {
    ordenActivo = { columna, direccion };
    cerrarTodosFiltros();
    paginaActual = 1;
    cargarDatos();
}

// ── Indicadores de filtro activo en íconos ────────────────────
function actualizarIndicadoresFiltros() {
    $('.filter-icon').removeClass('has-filter');
    Object.keys(filtrosActivos).forEach(function (col) {
        const v = filtrosActivos[col];
        const tieneValor =
            (Array.isArray(v) && v.length > 0) ||
            (!Array.isArray(v) && typeof v === 'object' && v !== null && Object.keys(v).length > 0) ||
            (typeof v === 'string' && v !== '');
        if (tieneValor) {
            $(`th[data-column="${col}"] .filter-icon`).addClass('has-filter');
        }
    });
}

// ── Posicionar el panel junto al ícono ────────────────────────
function posicionarPanel(panel, icon) {
    const offset    = $(icon).offset();
    const iW        = $(icon).outerWidth();
    const iH        = $(icon).outerHeight();
    const pW        = panel.outerWidth();
    const pH        = panel.outerHeight();
    const winW      = $(window).width();
    const winH      = $(window).height();
    const scrollTop = $(window).scrollTop();

    let top  = offset.top + iH + 5;
    let left = offset.left - pW + iW;

    if (left + pW > winW) left = winW - pW - 10;
    if (left < 10)        left = 10;

    const espacioAbajo  = winH + scrollTop - top;
    const espacioArriba = offset.top - scrollTop;

    if (espacioAbajo < pH && espacioArriba > pH) top = offset.top - pH - 5;
    if (top + pH > winH + scrollTop) top = Math.max(scrollTop + 10, winH + scrollTop - pH - 10);
    if (top < scrollTop + 10)        top = scrollTop + 10;

    panel.css({ top: top + 'px', left: left + 'px' });
}

// ── Búsqueda dentro de opciones lista ────────────────────────
function buscarEnOpciones(input) {
    const q = input.value.toLowerCase();
    $(input).siblings('.filter-options').find('.filter-option').each(function () {
        $(this).toggle($(this).text().toLowerCase().includes(q));
    });
}

// ── Paginación ────────────────────────────────────────────────
function renderizarPaginacion(total) {
    const totalPag = Math.ceil(total / registrosPorPagina);
    const pag      = $('#hcdPaginacion');
    pag.empty();

    pag.append(`<button class="pagination-btn" onclick="cambiarPagina(${paginaActual - 1})" ${paginaActual === 1 ? 'disabled' : ''}><i class="bi bi-chevron-left"></i></button>`);

    let inicio = Math.max(1, paginaActual - 2);
    let fin    = Math.min(totalPag, paginaActual + 2);

    if (inicio > 1) {
        pag.append(`<button class="pagination-btn" onclick="cambiarPagina(1)">1</button>`);
        if (inicio > 2) pag.append(`<span class="pagination-btn" style="cursor:default;">...</span>`);
    }

    for (let i = inicio; i <= fin; i++) {
        pag.append(`<button class="pagination-btn ${i === paginaActual ? 'active' : ''}" onclick="cambiarPagina(${i})">${i}</button>`);
    }

    if (fin < totalPag) {
        if (fin < totalPag - 1) pag.append(`<span class="pagination-btn" style="cursor:default;">...</span>`);
        pag.append(`<button class="pagination-btn" onclick="cambiarPagina(${totalPag})">${totalPag}</button>`);
    }

    pag.append(`<button class="pagination-btn" onclick="cambiarPagina(${paginaActual + 1})" ${paginaActual === totalPag || totalPag === 0 ? 'disabled' : ''}><i class="bi bi-chevron-right"></i></button>`);
}

function cambiarPagina(p) {
    const totalPag = Math.ceil(totalRegistros / registrosPorPagina);
    if (p < 1 || p > totalPag) return;
    paginaActual = p;
    cargarDatos();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function cambiarRegistrosPorPagina() {
    registrosPorPagina = parseInt($('#hcdPorPagina').val());
    paginaActual = 1;
    cargarDatos();
}

// ── Utilidades ────────────────────────────────────────────────
function formatFecha(f) {
    if (!f) return '—';
    const partes = f.split('-');
    if (partes.length !== 3) return f;
    const meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    return `${partes[2]}-${meses[parseInt(partes[1]) - 1]}-${partes[0].slice(-2)}`;
}

function escHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// ── Descargar Excel (con filtros activos) ─────────────────────
function descargarExcel() {
    // Cerrar el FAB
    $('.fab-container').removeClass('active');
    $('.btn-floating-pitaya').removeClass('active');

    // Crear un form temporal y enviar los filtros + orden al endpoint PHP
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'ajax/hcd_exportar_excel.php';
    form.target = '_blank';  // abre/descarga en nueva pestaña sin salir de la página

    const addField = (name, value) => {
        const input = document.createElement('input');
        input.type  = 'hidden';
        input.name  = name;
        input.value = value;
        form.appendChild(input);
    };

    addField('filtros', JSON.stringify(filtrosActivos));
    addField('orden',   JSON.stringify(ordenActivo));

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}
