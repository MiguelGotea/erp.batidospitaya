/**
 * gestion_proyectos.js
 * Lógica refinada con estándares UI/UX del ERP
 */

let fechaInicioVista = new Date();
fechaInicioVista.setDate((new Date()).getDate() - 15);

let globalData = { cargos: [], proyectos: [] };

// Variables de paginación y filtros estándar
let paginaActual = 1;
let registrosPorPagina = 25;
let filtrosActivos = {};
let ordenActivo = { columna: 'fecha_fin', direccion: 'DESC' };
let panelFiltroAbierto = null;

$(document).ready(function () {
    initGantt();
    cargarDatosHistorial();
    setupListeners();

    // Cerrar filtros al hacer clic fuera
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.filter-panel, .filter-icon').length) {
            cerrarTodosFiltros();
        }
    });

    $(window).on('resize', () => panelFiltroAbierto && cerrarTodosFiltros());
});

function initGantt() {
    irAHoy();
}

function setupListeners() {
    // Escuchar cambios en el modal para validaciones básicas
    $('#editFechaInicio, #editFechaFin').on('change', function () {
        const start = new Date($('#editFechaInicio').val());
        const end = new Date($('#editFechaFin').val());
        if (end < start) {
            $('#editFechaFin').val($('#editFechaInicio').val());
        }
    });

    $('#btnGuardarProyecto').on('click', guardarProyecto);
}

// --- GANTT CORE ---

async function fetchGanttData() {
    try {
        const response = await fetch('ajax/gestion_proyectos_get_datos.php');
        const res = await response.json();
        if (res.success) {
            globalData.cargos = res.cargos;
            globalData.proyectos = res.proyectos;
            renderGantt();
        }
    } catch (e) { console.error(e); }
}

function renderGantt() {
    const container = $('#ganttContainer').empty();
    const grid = $('<div class="gantt-grid"></div>');

    grid.append(generateTimeHeaders());

    globalData.cargos.forEach(cargo => {
        const row = $('<div class="gantt-row"></div>');

        // Fila de Cargo - Click para crear proyecto
        const cargoCell = $(`<div class="gantt-cargo-name" title="Click para nuevo proyecto">${cargo.Nombre}</div>`);
        cargoCell.on('click', () => PERMISO_CREAR && abrirModalNuevo(cargo.CodNivelesCargos));
        row.append(cargoCell);

        const content = $(`<div class="gantt-content" data-cargo-id="${cargo.CodNivelesCargos}"></div>`);

        // Hoy
        const hoyPos = calculateDatePosition(new Date());
        if (hoyPos !== null) content.append(`<div class="gantt-today-line" style="left: ${hoyPos}px"></div>`);

        const proyectosCargo = globalData.proyectos.filter(p => p.CodNivelesCargos == cargo.CodNivelesCargos);

        // Render jerárquico
        proyectosCargo.filter(p => !p.es_subproyecto).forEach(padre => {
            content.append(renderProjectBar(padre));
            if (padre.esta_expandido == 1) {
                proyectosCargo.filter(p => p.proyecto_padre_id == padre.id).forEach(hijo => {
                    content.append(renderProjectBar(hijo, true));
                });
            }
        });

        row.append(content);
        grid.append(row);
    });

    container.append(grid);
    initInteractions();
}

function renderProjectBar(p, esSub = false) {
    const startPos = calculateDatePosition(new Date(p.fecha_inicio));
    const endPos = calculateDatePosition(new Date(p.fecha_fin));
    if (startPos === null && endPos === null) return '';

    const width = (endPos - startPos) + 40;

    const bar = $(`
        <div class="gantt-bar ${esSub ? 'subproject' : ''}" 
             id="bar-${p.id}" data-id="${p.id}"
             style="left: ${startPos}px; width: ${width}px;"
             title="${p.nombre}\n${p.fecha_inicio} a ${p.fecha_fin}"
        >
            <div class="gantt-bar-actions-top">
                ${!esSub ? `
                    <button class="gantt-btn-sm" onclick="expandirGantt(${p.id}, ${p.esta_expandido}, event)" title="Ver Subproyectos">
                        <i class="fas fa-chevron-${p.esta_expandido == 1 ? 'up' : 'down'}"></i>
                    </button>
                    <button class="gantt-btn-sm" onclick="abrirModalNuevo(${p.CodNivelesCargos}, ${p.id}, event)" title="Añadir Subproyecto">
                        <i class="fas fa-plus"></i>
                    </button>
                ` : ''}
                <button class="gantt-btn-sm bg-danger" onclick="eliminarGantt(${p.id}, event)" title="Eliminar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="gantt-bar-title">${p.nombre}</div>
            <div class="gantt-resize-handle"></div>
        </div>
    `);

    bar.on('dblclick', (e) => { e.stopPropagation(); abrirModalEditar(p); });
    return bar;
}

// --- INTERACCIONES ---

function initInteractions() {
    if (!PERMISO_CREAR) return;

    $('.gantt-bar').each(function () {
        const bar = $(this);
        const id = bar.data('id');

        // Drag Horizontal
        let isMoving = false;
        bar.on('mousedown', function (e) {
            if ($(e.target).hasClass('gantt-resize-handle') || $(e.target).closest('.gantt-bar-actions-top').length) return;
            isMoving = true;
            let startX = e.clientX;
            let initialLeft = parseInt(bar.css('left'));

            $(document).on('mousemove.ganttMove', (me) => {
                const dx = me.clientX - startX;
                const newLeft = Math.round((initialLeft + dx) / 40) * 40;
                bar.css('left', newLeft + 'px');
            });

            $(document).on('mouseup.ganttMove', async () => {
                $(document).off('.ganttMove');
                if (!isMoving) return;
                isMoving = false;
                const finalLeft = parseInt(bar.css('left'));
                if (finalLeft !== initialLeft) {
                    const days = (finalLeft - initialLeft) / 40;
                    const proj = globalData.proyectos.find(x => x.id == id);
                    await apiCall('ajax/gestion_proyectos_actualizar.php', { id, campo: 'fecha_inicio', valor: addDays(proj.fecha_inicio, days) });
                    await apiCall('ajax/gestion_proyectos_actualizar.php', { id, campo: 'fecha_fin', valor: addDays(proj.fecha_fin, days) });
                    fetchGanttData();
                }
            });
        });

        // Resize Derecho
        bar.find('.gantt-resize-handle').on('mousedown', function (e) {
            e.stopPropagation();
            let startX = e.clientX;
            let startWidth = parseInt(bar.css('width'));

            $(document).on('mousemove.ganttResize', (me) => {
                const dx = me.clientX - startX;
                const newWidth = Math.max(40, Math.round((startWidth + dx) / 40) * 40);
                bar.css('width', newWidth + 'px');
            });

            $(document).on('mouseup.ganttResize', async () => {
                $(document).off('.ganttResize');
                const finalWidth = parseInt(bar.css('width'));
                if (finalWidth !== startWidth) {
                    const days = (finalWidth - startWidth) / 40;
                    const proj = globalData.proyectos.find(x => x.id == id);
                    await apiCall('ajax/gestion_proyectos_actualizar.php', { id, campo: 'fecha_fin', valor: addDays(proj.fecha_fin, days) });
                    fetchGanttData();
                }
            });
        });
    });
}

// --- MODAL ---

function abrirModalNuevo(cargoId, padreId = null, e = null) {
    if (e) e.stopPropagation();
    $('#formProyecto')[0].reset();
    $('#editProyectoId').val('');
    $('#editCargoId').val(cargoId);
    $('#editProyectoPadreId').val(padreId || '');
    $('#editEsSubproyecto').val(padreId ? 1 : 0);
    $('#modalTitulo').text(padreId ? 'Nuevo Subproyecto' : 'Nuevo Proyecto');

    // Default dates based on view or parent
    const today = new Date().toISOString().split('T')[0];
    $('#editFechaInicio').val(today);
    $('#editFechaFin').val(today);

    $('#modalProyecto').modal('show');
}

function abrirModalEditar(p) {
    $('#editProyectoId').val(p.id);
    $('#editNombre').val(p.nombre);
    $('#editDescripcion').val(p.descripcion);
    $('#editFechaInicio').val(p.fecha_inicio);
    $('#editFechaFin').val(p.fecha_fin);
    $('#editEsSubproyecto').val(p.es_subproyecto);
    $('#modalTitulo').text('Editar Proyecto');
    $('#modalProyecto').modal('show');
}

async function guardarProyecto() {
    const data = {
        id: $('#editProyectoId').val(),
        nombre: $('#editNombre').val(),
        descripcion: $('#editDescripcion').val(),
        fecha_inicio: $('#editFechaInicio').val(),
        fecha_fin: $('#editFechaFin').val(),
        CodNivelesCargos: $('#editCargoId').val(),
        proyecto_padre_id: $('#editProyectoPadreId').val(),
        es_subproyecto: $('#editEsSubproyecto').val()
    };

    if (!data.nombre) return Swal.fire('Error', 'El nombre es obligatorio', 'error');

    let url = data.id ? 'ajax/gestion_proyectos_actualizar.php' : 'ajax/gestion_proyectos_crear.php';

    // Para simplificar, si es actualizar, el backend actual espera campo/valor. 
    // Refactoreamos para enviar todo el objeto si es necesario o iterar campos.
    if (data.id) {
        const fields = ['nombre', 'descripcion', 'fecha_inicio', 'fecha_fin'];
        for (const f of fields) {
            await apiCall(url, { id: data.id, campo: f, valor: data[f] });
        }
    } else {
        await apiCall(url, data);
    }

    $('#modalProyecto').modal('hide');
    fetchGanttData();
}

async function eliminarGantt(id, e) {
    e.stopPropagation();
    const res = await apiCall('ajax/gestion_proyectos_eliminar.php', { id });
    if (res.success) fetchGanttData();
    else Swal.fire('Error', res.message, 'error');
}

async function expandirGantt(id, actual, e) {
    e.stopPropagation();
    await apiCall('ajax/gestion_proyectos_toggle_expandir.php', { id, expandido: actual == 1 ? 0 : 1 });
    fetchGanttData();
}

// --- HISTORIAL & FILTROS ESTÁNDAR ---

async function cargarDatosHistorial() {
    try {
        const response = await fetch('ajax/gestion_proyectos_get_historial.php?' + new URLSearchParams({
            pagina: paginaActual,
            registros_por_pagina: registrosPorPagina,
            filtros: JSON.stringify(filtrosActivos),
            orden_columna: ordenActivo.columna,
            orden_direccion: ordenActivo.direccion
        }));
        const res = await response.json();
        if (res.success) {
            renderizarTablaHistorial(res.datos);
            renderizarPaginacion(res.total_registros);
        }
    } catch (e) { console.error(e); }
}

function renderizarTablaHistorial(datos) {
    const tbody = $('#historialBody').empty();
    if (!datos.length) return tbody.append('<tr><td colspan="5" class="text-center py-4">No hay registros</td></tr>');

    datos.forEach(row => {
        tbody.append(`
            <tr>
                <td><span class="badge badge-light border">${row.cargo_nombre}</span></td>
                <td class="font-weight-bold">${row.nombre}</td>
                <td>${formatFechaLetras(row.fecha_inicio)}</td>
                <td>${formatFechaLetras(row.fecha_fin)}</td>
                <td class="small text-muted">${row.descripcion || '-'}</td>
            </tr>
        `);
    });
}

// --- LOGICA DE FILTROS (Basada en Estándar Core) ---

function toggleFilter(icon) {
    const th = $(icon).closest('th');
    const col = th.data('column');
    const type = th.data('type');

    if (panelFiltroAbierto && panelFiltroAbierto.col === col) {
        cerrarTodosFiltros();
        return;
    }

    cerrarTodosFiltros();

    const rect = icon.getBoundingClientRect();
    const panel = $('<div class="filter-panel show"></div>').css({
        top: (rect.bottom + window.scrollY + 10) + 'px',
        left: (rect.left - 200) + 'px'
    });

    panel.append(`<div class="filter-section-title">Filtrar ${th.text().trim()}</div>`);

    if (type === 'text') {
        const input = $(`<input type="text" class="filter-search" placeholder="Buscar..." value="${filtrosActivos[col] || ''}">`);
        input.on('keyup', (e) => { if (e.key === 'Enter') aplicarFiltro(col, input.val()); });
        panel.append(input);
    } else if (type === 'list') {
        const listCont = $('<div class="filter-options"><div class="text-center p-2"><i class="fas fa-spinner fa-spin"></i></div></div>');
        panel.append(listCont);
        cargarOpcionesLista(col, listCont);
    } else if (type === 'daterange') {
        const dcont = $('<div class="daterange-inputs"></div>');
        dcont.append(`<input type="date" class="form-control form-control-sm mb-2" id="f-desde" value="${filtrosActivos[col + '_desde'] || ''}">`);
        dcont.append(`<input type="date" class="form-control form-control-sm" id="f-hasta" value="${filtrosActivos[col + '_hasta'] || ''}">`);
        panel.append(dcont);
        const btn = $(`<button class="btn btn-sm btn-primary btn-block mt-2">Aplicar</button>`).on('click', () => {
            filtrosActivos[col + '_desde'] = $('#f-desde').val();
            filtrosActivos[col + '_hasta'] = $('#f-hasta').val();
            cerrarTodosFiltros();
            cargarDatosHistorial();
        });
        panel.append(btn);
    }

    $('body').append(panel);
    panelFiltroAbierto = { col, panel };
}

async function cargarOpcionesLista(col, container) {
    const res = await (await fetch(`ajax/gestion_proyectos_get_opciones_filtro.php?columna=${col}`)).json();
    container.empty();
    if (res.success) {
        res.datos.forEach(opt => {
            const checked = (filtrosActivos[col] || []).includes(opt.nombre) ? 'checked' : '';
            const item = $(`
                <div class="filter-option">
                    <input type="checkbox" value="${opt.nombre}" ${checked}> ${opt.nombre}
                </div>
            `);
            item.on('click', function (e) {
                if (e.target.tagName !== 'INPUT') $(this).find('input').prop('checked', !$(this).find('input').prop('checked'));
                actualizarFiltroLista(col, container);
            });
            container.append(item);
        });
    }
}

function actualizarFiltroLista(col, container) {
    const vals = [];
    container.find('input:checked').each(function () { vals.push($(this).val()); });
    if (vals.length) filtrosActivos[col] = vals;
    else delete filtrosActivos[col];
    cargarDatosHistorial();
}

function aplicarFiltro(col, val) {
    if (val) filtrosActivos[col] = val;
    else delete filtrosActivos[col];
    cerrarTodosFiltros();
    cargarDatosHistorial();
}

function cerrarTodosFiltros() {
    $('.filter-panel').remove();
    panelFiltroAbierto = null;
}

// --- PAGINACIÓN CORE ---

function renderizarPaginacion(total) {
    const cont = $('#paginacion').empty();
    const totalPaginas = Math.ceil(total / registrosPorPagina);
    if (totalPaginas <= 1) return;

    const btnPrev = $(`<button class="pagination-btn" ${paginaActual === 1 ? 'disabled' : ''}><i class="bi bi-chevron-left"></i></button>`);
    btnPrev.on('click', () => { paginaActual--; cargarDatosHistorial(); });
    cont.append(btnPrev);

    for (let i = 1; i <= totalPaginas; i++) {
        if (i === 1 || i === totalPaginas || (i >= paginaActual - 2 && i <= paginaActual + 2)) {
            const btn = $(`<button class="pagination-btn ${i === paginaActual ? 'active' : ''}">${i}</button>`);
            btn.on('click', () => { paginaActual = i; cargarDatosHistorial(); });
            cont.append(btn);
        } else if (i === paginaActual - 3 || i === paginaActual + 3) {
            cont.append('<span class="px-2">...</span>');
        }
    }

    const btnNext = $(`<button class="pagination-btn" ${paginaActual === totalPaginas ? 'disabled' : ''}><i class="bi bi-chevron-right"></i></button>`);
    btnNext.on('click', () => { paginaActual++; cargarDatosHistorial(); });
    cont.append(btnNext);
}

function cambiarRegistrosPorPagina() {
    registrosPorPagina = parseInt($('#registrosPorPagina').val());
    paginaActual = 1;
    cargarDatosHistorial();
}

// --- UTILS ---

function calculateDatePosition(date) {
    const start = new Date(fechaInicioVista); start.setHours(0, 0, 0, 0);
    const target = new Date(date); target.setHours(0, 0, 0, 0);
    const diff = Math.floor((target - start) / (1000 * 60 * 60 * 24));
    if (diff < 0 || diff >= 90) return null;
    return diff * 40;
}

function generateTimeHeaders() {
    const h = $('<div class="gantt-header"></div>');
    const top = $('<div class="gantt-header-top"></div>');
    const days = $('<div class="gantt-header-days"></div>');
    let curr = new Date(fechaInicioVista);
    const meses = {};
    for (let i = 0; i < 90; i++) {
        const d = new Date(curr);
        const mKey = d.toLocaleString('es', { month: 'long', year: 'numeric' });
        meses[mKey] = (meses[mKey] || 0) + 1;
        days.append(`<div class="gantt-day-label">${d.getDate()}</div>`);
        curr.setDate(curr.getDate() + 1);
    }
    Object.keys(meses).forEach(m => {
        const w = meses[m] * 40;
        top.append(`<div class="gantt-month" style="width:${w}px; min-width:${w}px">${m}</div>`);
    });
    return h.append(top).append(days);
}

async function apiCall(url, data) {
    const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
    return await r.json();
}

function addDays(dStr, days) {
    const d = new Date(dStr + "T00:00:00");
    d.setDate(d.getDate() + days);
    return d.toISOString().split('T')[0];
}

function formatFechaLetras(s) {
    const d = new Date(s + "T00:00:00");
    return d.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: '2-digit' }).replace(/ /g, '-').replace(/\./g, '');
}

function irAHoy() {
    const h = new Date(); h.setDate(h.getDate() - 15);
    fechaInicioVista = h;
    fetchGanttData();
}

function navegarGantt(dir) {
    fechaInicioVista.setMonth(fechaInicioVista.getMonth() + (dir === 'anterior' ? -1 : 1));
    fetchGanttData();
}
