let paginaActual = 1;
let registrosPorPagina = 25;
let filtrosActivos = {};
let ordenActivo = { columna: null, direccion: 'asc' };
let panelFiltroAbierto = null;
let totalRegistros = 0;
let scrollTopInicial = 0;
let windowWidthInicial = $(window).width();
let modalDetalle = null;

$(document).ready(function () {
    modalDetalle = new bootstrap.Modal(document.getElementById('modalDetalle'));
    cargarDatos();

    $(document).on('click', function (e) {
        if (!$(e.target).closest('.filter-panel, .filter-icon').length) {
            cerrarTodosFiltros();
        }
    });

    $('.table-responsive').on('scroll', function (e) {
        e.stopPropagation();
    });

    $(window).on('scroll', function () {
        if (panelFiltroAbierto && Math.abs($(window).scrollTop() - scrollTopInicial) > 100) {
            cerrarTodosFiltros();
        }
    });

    $(window).on('resize', function () {
        const currentWidth = $(window).width();
        if (panelFiltroAbierto && currentWidth !== windowWidthInicial) {
            cerrarTodosFiltros();
            windowWidthInicial = currentWidth;
        }
    });
});

function cargarDatos() {
    $('#tablaFacturasBody').html(`
        <tr>
            <td colspan="8" class="text-center text-muted py-4">
                <div class="spinner-border spinner-border-sm me-2"></div> Cargando...
            </td>
        </tr>`);

    $.ajax({
        url: 'ajax/facturas_get_historial.php',
        method: 'POST',
        dataType: 'json',
        data: {
            pagina: paginaActual,
            registros_por_pagina: registrosPorPagina,
            filtros: JSON.stringify(filtrosActivos),
            orden: JSON.stringify(ordenActivo)
        },
        success: function (response) {
            if (response.success) {
                totalRegistros = response.total_registros;
                renderizarTabla(response.datos || []);
                renderizarPaginacion(totalRegistros);
                actualizarIndicadoresFiltros();
            } else {
                $('#tablaFacturasBody').html(`<tr><td colspan="8" class="text-center text-danger">${escHtml(response.message || 'Error al cargar')}</td></tr>`);
            }
        },
        error: function () {
            $('#tablaFacturasBody').html('<tr><td colspan="8" class="text-center text-danger">Error al cargar los datos.</td></tr>');
        }
    });
}

function renderizarTabla(facturas) {
    if (!facturas || facturas.length === 0) {
        $('#tablaFacturasBody').html(`
            <tr><td colspan="8" class="text-center text-muted py-4">
                <i class="bi bi-inbox me-2"></i>No se encontraron registros.
            </td></tr>`);
        return;
    }

    let html = '';
    facturas.forEach(f => {
        const badgeEstado = f.estado === 'activa'
            ? '<span class="badge-activa">Activa</span>'
            : '<span class="badge-anulada">Anulada</span>';

        html += `
        <tr>
            <td><strong>${escHtml(f.numero_factura)}</strong></td>
            <td>${formatearFecha(f.fecha)}</td>
            <td>${escHtml(f.nombre_proveedor || '-')}</td>
            <td class="text-end fw-bold" style="font-variant-numeric: tabular-nums;">${formatearMonto(f.total_factura)}</td>
            <td>${badgeEstado}</td>
            <td style="font-size:.82rem;">${escHtml(f.registrado_por_nombre || '-')}</td>
            <td style="font-size:.78rem; color:#64748b;">${formatearFechaHora(f.fecha_hora_regsys)}</td>
            <td class="text-center">
                <button class="btn-ver-detalle" onclick="verDetalle(${f.id})">
                    <i class="bi bi-eye"></i> Ver
                </button>
            </td>
        </tr>`;
    });

    $('#tablaFacturasBody').html(html);
}

function toggleFilter(icon) {
    const th = $(icon).closest('th');
    const columna = th.data('column');
    const tipo = th.data('type');

    if (!columna || !tipo) {
        return;
    }

    if (panelFiltroAbierto === columna) {
        cerrarTodosFiltros();
        return;
    }

    cerrarTodosFiltros();
    scrollTopInicial = $(window).scrollTop();
    windowWidthInicial = $(window).width();
    crearPanelFiltro(th, columna, tipo, icon);
    panelFiltroAbierto = columna;
    $(icon).addClass('active');
    actualizarIndicadoresFiltros();
}

function crearPanelFiltro(th, columna, tipo, icon) {
    const panel = $('<div class="filter-panel show"></div>');

    if (tipo === 'daterange') {
        panel.addClass('has-daterange');
    }

    const textoOrdenAsc = tipo === 'number' ? 'Menor a mayor' : 'A→Z';
    const textoOrdenDesc = tipo === 'number' ? 'Mayor a menor' : 'Z→A';

    panel.append(`
        <div class="filter-section">
            <span class="filter-section-title">Ordenar:</span>
            <div class="filter-sort-buttons">
                <button class="filter-sort-btn ${ordenActivo.columna === columna && ordenActivo.direccion === 'asc' ? 'active' : ''}" 
                        onclick="aplicarOrden('${columna}', 'asc')">
                    <i class="bi bi-sort-alpha-down"></i> ${textoOrdenAsc}
                </button>
                <button class="filter-sort-btn ${ordenActivo.columna === columna && ordenActivo.direccion === 'desc' ? 'active' : ''}" 
                        onclick="aplicarOrden('${columna}', 'desc')">
                    <i class="bi bi-sort-alpha-up"></i> ${textoOrdenDesc}
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
                       value="${escHtml(valorActual)}"
                       oninput="filtrarBusqueda('${columna}', this.value)">
            </div>
        `);
        posicionarPanelFiltro(panel, icon);
    } else if (tipo === 'list') {
        cargarOpcionesFiltro(panel, columna, icon);
    } else if (tipo === 'daterange') {
        crearCalendarioRango(panel, columna);
        posicionarPanelFiltro(panel, icon);
    } else if (tipo === 'number') {
        const valorActual = filtrosActivos[columna] || '';
        panel.append(`
            <div class="filter-section" style="margin-top: 12px;">
                <span class="filter-section-title">Contiene número:</span>
                <input type="text" class="filter-search" placeholder="Ej: 1500" 
                       value="${escHtml(valorActual)}"
                       oninput="filtrarBusqueda('${columna}', this.value)">
            </div>
        `);
        posicionarPanelFiltro(panel, icon);
    }
}

function crearCalendarioRango(panel, columna) {
    const hoy = new Date();
    const mesActual = hoy.getMonth();
    const anioActual = hoy.getFullYear();

    panel.append(`
        <div class="filter-section" style="margin-top: 8px;">
            <span class="filter-section-title">Seleccionar rango:</span>
            <div class="daterange-calendar-container">
                <div class="daterange-month-selector">
                    <select id="mesCalendario" onchange="actualizarCalendarioUnico('${columna}')"></select>
                    <select id="anioCalendario" onchange="actualizarCalendarioUnico('${columna}')"></select>
                </div>
                <div class="daterange-calendar" id="calendarioUnico"></div>
            </div>
        </div>
    `);

    setTimeout(() => {
        const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        const selectMes = $('#mesCalendario');
        const selectAnio = $('#anioCalendario');

        meses.forEach((mes, idx) => {
            selectMes.append(`<option value="${idx}" ${idx === mesActual ? 'selected' : ''}>${mes}</option>`);
        });

        for (let anio = anioActual - 8; anio <= anioActual + 1; anio++) {
            selectAnio.append(`<option value="${anio}" ${anio === anioActual ? 'selected' : ''}>${anio}</option>`);
        }

        actualizarCalendarioUnico(columna);
    }, 30);
}

function actualizarCalendarioUnico(columna) {
    const mes = parseInt($('#mesCalendario').val(), 10);
    const anio = parseInt($('#anioCalendario').val(), 10);
    const primerDia = new Date(anio, mes, 1).getDay();
    const diasEnMes = new Date(anio, mes + 1, 0).getDate();
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
        const fechaStr = `${anio}-${String(mes + 1).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
        const clases = obtenerClasesCalendario(fechaStr, columna);
        html += `<div class="daterange-calendar-day ${clases}" onclick="event.stopPropagation(); seleccionarFechaRango('${fechaStr}', '${columna}')">${dia}</div>`;
    }

    html += '</div>';
    $('#calendarioUnico').html(html);
}

function seleccionarFechaRango(fecha, columna) {
    if (!filtrosActivos[columna]) {
        filtrosActivos[columna] = { desde: null, hasta: null };
    }

    const desde = filtrosActivos[columna].desde;
    const hasta = filtrosActivos[columna].hasta;

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
        filtrosActivos[columna].desde = fecha;
        filtrosActivos[columna].hasta = null;
    }

    actualizarCalendarioUnico(columna);

    if (filtrosActivos[columna].desde && filtrosActivos[columna].hasta) {
        paginaActual = 1;
        cargarDatos();
    }
}

function obtenerClasesCalendario(fecha, columna) {
    const fechaDesde = filtrosActivos[columna]?.desde;
    const fechaHasta = filtrosActivos[columna]?.hasta;
    const clases = [];

    if (fecha === fechaDesde || fecha === fechaHasta) {
        clases.push('selected');
    } else if (fechaDesde && fechaHasta && fecha > fechaDesde && fecha < fechaHasta) {
        clases.push('in-range');
    }

    return clases.join(' ');
}

function cargarOpcionesFiltro(panel, columna, icon) {
    $.ajax({
        url: 'ajax/facturas_get_opciones_filtro.php',
        method: 'POST',
        dataType: 'json',
        data: { columna: columna },
        success: function (response) {
            if (!response.success) {
                panel.append('<div class="text-danger" style="margin-top:8px;">No se pudieron cargar opciones.</div>');
                posicionarPanelFiltro(panel, icon);
                return;
            }

            let html = '<div class="filter-section" style="margin-top: 12px;">';
            html += '<span class="filter-section-title">Filtrar por:</span>';
            html += '<input type="text" class="filter-search" placeholder="Buscar..." onkeyup="buscarEnOpciones(this)">';
            html += '<div class="filter-options">';

            (response.opciones || []).forEach(opcion => {
                const valor = String(opcion.valor ?? '');
                const texto = String(opcion.texto ?? '');
                const checked = filtrosActivos[columna] && filtrosActivos[columna].includes(valor) ? 'checked' : '';
                html += `
                    <div class="filter-option">
                        <input type="checkbox" value="${escHtml(valor)}" ${checked}
                               onchange="toggleOpcionFiltro('${columna}', '${escHtml(valor)}', this.checked)">
                        <span>${escHtml(texto)}</span>
                    </div>`;
            });

            html += '</div></div>';
            panel.append(html);
            posicionarPanelFiltro(panel, icon);
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

    panel.css({ top: `${top}px`, left: `${left}px` });
}

function actualizarIndicadoresFiltros() {
    $('.filter-icon').removeClass('has-filter');
    Object.keys(filtrosActivos).forEach(columna => {
        const valor = filtrosActivos[columna];
        const activo = (Array.isArray(valor) && valor.length > 0) ||
            (!Array.isArray(valor) && typeof valor === 'object' && (valor.desde || valor.hasta)) ||
            (!Array.isArray(valor) && typeof valor !== 'object' && String(valor).trim() !== '');
        if (activo) {
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
    if (valor.trim() === '') {
        delete filtrosActivos[columna];
    } else {
        filtrosActivos[columna] = valor.trim();
    }
    paginaActual = 1;
    cargarDatos();
}

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

function buscarEnOpciones(input) {
    const busqueda = input.value.toLowerCase();
    const opciones = $(input).siblings('.filter-options').find('.filter-option');
    opciones.each(function () {
        const texto = $(this).text().toLowerCase();
        $(this).toggle(texto.includes(busqueda));
    });
}

function cambiarRegistrosPorPagina() {
    registrosPorPagina = parseInt($('#registrosPorPagina').val(), 10) || 25;
    paginaActual = 1;
    cargarDatos();
}

function renderizarPaginacion(total) {
    const totalPaginas = Math.ceil(total / registrosPorPagina);
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
        paginacion.append('<button class="pagination-btn" onclick="cambiarPagina(1)">1</button>');
        if (inicio > 2) {
            paginacion.append('<span class="pagination-btn" disabled>...</span>');
        }
    }

    for (let i = inicio; i <= fin; i++) {
        const activeClass = i === paginaActual ? 'active' : '';
        paginacion.append(`<button class="pagination-btn ${activeClass}" onclick="cambiarPagina(${i})">${i}</button>`);
    }

    if (fin < totalPaginas) {
        if (fin < totalPaginas - 1) {
            paginacion.append('<span class="pagination-btn" disabled>...</span>');
        }
        paginacion.append(`<button class="pagination-btn" onclick="cambiarPagina(${totalPaginas})">${totalPaginas}</button>`);
    }

    paginacion.append(`
        <button class="pagination-btn" onclick="cambiarPagina(${paginaActual + 1})" ${paginaActual >= totalPaginas ? 'disabled' : ''}>
            <i class="bi bi-chevron-right"></i>
        </button>
    `);
}

function cambiarPagina(pagina) {
    const totalPaginas = Math.ceil(totalRegistros / registrosPorPagina);
    if (pagina < 1 || pagina > totalPaginas) {
        return;
    }
    paginaActual = pagina;
    cargarDatos();
}

/* ---- Ver detalle en modal ---- */
function verDetalle(id) {
    $('#infoCabeceraModal').html('<div class="col-12 text-muted"><span class="spinner-border spinner-border-sm me-1"></span>Cargando…</div>');
    $('#tablaDetalleModal').html('<tr><td colspan="5" class="text-center text-muted">Cargando…</td></tr>');
    $('#totalModal').text('');
    $('#notasModal').text('');
    modalDetalle.show();

    $.get('ajax/facturas_get_detalle.php', { id: id }, function (res) {
        if (!res.success) {
            Swal.fire('Error', res.message, 'error');
            modalDetalle.hide();
            return;
        }

        const f = res.factura;

        // Cabecera info
        $('#infoCabeceraModal').html(`
            <div class="col-sm-4">
                <small class="text-muted d-block fw-bold text-uppercase" style="font-size:.72rem;">N° Factura</small>
                <span class="fw-bold">${escHtml(f.numero_factura)}</span>
            </div>
            <div class="col-sm-4">
                <small class="text-muted d-block fw-bold text-uppercase" style="font-size:.72rem;">Fecha</small>
                <span>${formatearFecha(f.fecha)}</span>
            </div>
            <div class="col-sm-4">
                <small class="text-muted d-block fw-bold text-uppercase" style="font-size:.72rem;">Proveedor</small>
                <span>${escHtml(f.nombre_proveedor || '—')}</span>
            </div>
            <div class="col-sm-4 mt-1">
                <small class="text-muted d-block fw-bold text-uppercase" style="font-size:.72rem;">Registrado por</small>
                <span style="font-size:.875rem;">${escHtml(f.registrado_por_nombre || '—')}</span>
            </div>
            <div class="col-sm-4 mt-1">
                <small class="text-muted d-block fw-bold text-uppercase" style="font-size:.72rem;">Estado</small>
                ${f.estado === 'activa' ? '<span class="badge-activa">Activa</span>' : '<span class="badge-anulada">Anulada</span>'}
            </div>`);

        // Detalle
        if (!res.detalle || res.detalle.length === 0) {
            $('#tablaDetalleModal').html('<tr><td colspan="5" class="text-center text-muted">Sin detalle.</td></tr>');
        } else {
            let rows = '';
            res.detalle.forEach((d, i) => {
                rows += `
                <tr>
                    <td>${i+1}</td>
                    <td>
                        <strong>${escHtml(d.nombre_presentacion)}</strong>
                        <small class="d-block text-muted">${escHtml(d.nombre_maestro || '')}</small>
                    </td>
                    <td class="text-end">${parseFloat(d.cantidad).toFixed(2)} ${escHtml(d.nombre_unidad || '')}</td>
                    <td class="text-end">${formatearMonto(d.costo_total_iva)}</td>
                    <td class="text-end">${formatearMonto(d.costo_unitario)}</td>
                </tr>`;
            });
            $('#tablaDetalleModal').html(rows);
        }

        $('#totalModal').html(`<strong>Total:</strong> ${formatearMonto(f.total_factura)}`);
        if (f.notas) $('#notasModal').text('Notas: ' + f.notas);
        $('#modalDetalleLabel').html(`<i class="bi bi-receipt me-2"></i>Factura #${escHtml(f.numero_factura)}`);

    }, 'json').fail(function () {
        Swal.fire('Error', 'No se pudo cargar el detalle.', 'error');
        modalDetalle.hide();
    });
}

function formatearMonto(v) {
    const n = parseFloat(v) || 0;
    return 'C$ ' + n.toLocaleString('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatearFecha(str) {
    if (!str) return '-';
    const [y, m, d] = str.split('-');
    return `${d}/${m}/${y}`;
}

function formatearFechaHora(str) {
    if (!str) return '-';
    const dt = new Date(str);
    return dt.toLocaleDateString('es-NI') + ' ' + dt.toLocaleTimeString('es-NI', { hour: '2-digit', minute: '2-digit' });
}

function escHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

