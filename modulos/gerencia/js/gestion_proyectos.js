// gestion_proyectos.js
// Lógica principal del Diagrama de Gantt

let fechaInicioGantt = new Date();
fechaInicioGantt.setDate(fechaInicioGantt.getDate() - 30); // Mostrar desde 30 días atrás
let proyectosData = [];
let cargandoGantt = false;

// Configuración Historial
let currentHistorialPage = 1;
let currentFilters = {};
let filterOptions = { cargo: [] };

$(document).ready(function () {
    initGantt();
    cargarHistorial();
    cargarOpcionesFiltro();

    $('#btnGuardarProyecto').on('click', guardarProyecto);

    // Cerrar paneles de filtro al hacer clic fuera
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.filter-panel, .filter-icon').length) {
            $('.filter-panel').removeClass('show');
        }
    });
});

/** --- GANTT CORE --- **/

async function initGantt() {
    await cargarDatosGantt();
}

async function cargarDatosGantt() {
    if (cargandoGantt) return;
    cargandoGantt = true;
    $('.gantt-loader').show();

    try {
        const response = await fetch('ajax/gestion_proyectos_get_datos.php');
        const res = await response.json();

        if (res.success) {
            proyectosData = res.datos;
            renderGantt();
        } else {
            console.error(res.message);
        }
    } catch (e) {
        console.error("Error cargando Gantt:", e);
    } finally {
        cargandoGantt = false;
        $('.gantt-loader').hide();
    }
}

function renderGantt() {
    const container = $('#ganttContainer');
    container.empty();

    const endDate = new Date(fechaInicioGantt);
    endDate.setDate(endDate.getDate() + 90); // 3 meses vista

    const wrapper = $('<div class="gantt-grid"></div>');

    // 1. Render Headers
    const headerTop = $('<div class="gantt-header-top"></div>');
    const headerDays = $('<div class="gantt-header-days"></div>');

    let current = new Date(fechaInicioGantt);
    while (current < endDate) {
        const month = current.toLocaleString('es-ES', { month: 'long', year: 'numeric' });
        const daysInMonth = new Date(current.getFullYear(), current.getMonth() + 1, 0).getDate();
        const startDay = current.getDate();
        const daysToShow = Math.min(daysInMonth - startDay + 1, (endDate - current) / (1000 * 60 * 60 * 24));

        headerTop.append(`<div class="gantt-month" style="flex: 0 0 ${daysToShow * 40}px">${month}</div>`);

        for (let i = 0; i < daysToShow; i++) {
            const dayDate = new Date(current);
            dayDate.setDate(dayDate.getDate() + i);
            const isWeekend = dayDate.getDay() === 0 || dayDate.getDay() === 6;
            headerDays.append(`<div class="gantt-day-label ${isWeekend ? 'bg-light' : ''}">${dayDate.getDate()}</div>`);
        }
        current.setDate(current.getDate() + daysToShow);
    }
    wrapper.append(headerTop).append(headerDays);

    // 2. Render Rows per Cargo
    const cargos = [...new Set(proyectosData.map(p => p.cargo_nombre))].sort();

    cargos.forEach(cargo => {
        const row = $('<div class="gantt-row"></div>');
        const cargoId = proyectosData.find(p => p.cargo_nombre === cargo).CodNivelesCargos;

        row.append(`<div class="gantt-cargo-name" onclick="nuevoProyecto(${cargoId}, '${cargo}')">${cargo}</div>`);

        const content = $('<div class="gantt-content"></div>');
        const proyectosCargo = proyectosData.filter(p => p.cargo_nombre === cargo);

        // --- STACKING LOGIC ---
        // Organizamos los proyectos en "niveles" para que no se superpongan
        let levels = [[]]; // Array de arrays, cada uno es un nivel de visualización

        // Primero los proyectos padre, luego hijos
        const sortedProyectos = proyectosCargo.sort((a, b) => {
            if (a.es_subproyecto !== b.es_subproyecto) return a.es_subproyecto - b.es_subproyecto;
            return new Date(a.fecha_inicio) - new Date(b.fecha_inicio);
        });

        sortedProyectos.forEach(p => {
            // Un subproyecto solo se muestra si su padre está expandido (por defecto true en esta versión, pero controlable)
            if (p.es_subproyecto == 1) {
                const padre = proyectosData.find(parent => parent.id == p.proyecto_padre_id);
                if (padre && padre.expandido === false) return;
            }

            let levelAssigned = -1;
            for (let i = 0; i < levels.length; i++) {
                const collides = levels[i].some(item => {
                    return (new Date(p.fecha_inicio) < new Date(item.fecha_fin)) &&
                        (new Date(p.fecha_fin) > new Date(item.fecha_inicio));
                });
                if (!collides) {
                    levelAssigned = i;
                    break;
                }
            }

            if (levelAssigned === -1) {
                levelAssigned = levels.length;
                levels.push([]);
            }
            levels[levelAssigned].push(p);

            const bar = renderProyectoBar(p, levelAssigned);
            content.append(bar);
        });

        // Ajustar altura de la fila según niveles
        const rowHeight = Math.max(60, (levels.length * 45) + 10);
        content.css('height', rowHeight + 'px');
        row.find('.gantt-cargo-name').css('height', rowHeight + 'px');

        row.append(content);
        wrapper.append(row);
    });

    // 3. Today Line - Continuous
    const hoy = new Date();
    const difHoy = (hoy - fechaInicioGantt) / (1000 * 60 * 60 * 24);
    if (difHoy >= 0 && difHoy <= 90) {
        const left = 180 + (difHoy * 40); // 180 is cargo width
        wrapper.append(`<div class="gantt-today-line-full" style="left: ${left}px"></div>`);
    }

    container.append(wrapper);
    initInteractions();
}

function renderProyectoBar(p, level) {
    const start = new Date(p.fecha_inicio);
    const end = new Date(p.fecha_fin);
    const difStart = (start - fechaInicioGantt) / (1000 * 60 * 60 * 24);
    const duration = (end - start) / (1000 * 60 * 60 * 24) + 1;

    const left = difStart * 40;
    const width = duration * 40;
    const top = (level * 45) + 5;

    const isPadre = (p.es_subproyecto == 0);
    const isExpandido = p.expandido !== false;

    const bar = $(`
        <div class="gantt-bar ${p.es_subproyecto == 1 ? 'subproject' : ''}" 
             data-id="${p.id}" 
             style="left: ${left}px; width: ${width}px; top: ${top}px;"
             title="${p.nombre}: ${p.fecha_inicio} al ${p.fecha_fin}">
            
            <div class="gantt-bar-actions-top">
                ${isPadre ? `
                    <div class="gantt-btn-sm" onclick="toggleExpandir(${p.id}, event)" title="${isExpandido ? 'Contraer' : 'Expandir'}">
                        <i class="fas ${isExpandido ? 'fa-chevron-up' : 'fa-chevron-down'}"></i>
                    </div>
                    <div class="gantt-btn-sm" onclick="nuevoSubproyecto(${p.id}, ${p.CodNivelesCargos}, event)" title="Añadir Subproyecto">
                        <i class="fas fa-plus"></i>
                    </div>
                ` : ''}
                <div class="gantt-btn-sm bg-danger" onclick="confirmarEliminar(${p.id}, event)" title="Eliminar">
                    <i class="fas fa-times"></i>
                </div>
            </div>

            <div class="gantt-bar-title text-truncate">${p.nombre}</div>
            ${PERMISO_CREAR ? '<div class="gantt-resize-handle"></div>' : ''}
        </div>
    `);

    bar.on('dblclick', (e) => { e.stopPropagation(); editarProyecto(p); });

    return bar;
}

/** --- ACTIONS & MODALS --- **/

function nuevoProyecto(cargoId, cargoNombre) {
    if (!PERMISO_CREAR) return;
    $('#formProyecto')[0].reset();
    $('#editProyectoId').val('');
    $('#editProyectoPadreId').val('');
    $('#editCargoId').val(cargoId);
    $('#editEsSubproyecto').val(0);
    $('#modalTitulo').text(`Nuevo Proyecto para ${cargoNombre}`);
    $('#modalProyecto').modal('show');
}

function nuevoSubproyecto(padreId, cargoId, event) {
    event.stopPropagation();
    if (!PERMISO_CREAR) return;
    $('#formProyecto')[0].reset();
    $('#editProyectoId').val('');
    $('#editProyectoPadreId').val(padreId);
    $('#editCargoId').val(cargoId);
    $('#editEsSubproyecto').val(1);

    const padre = proyectosData.find(p => p.id == padreId);
    $('#editNombre').val(`Sub: ${padre.nombre}`);
    $('#editFechaInicio').val(padre.fecha_inicio);
    $('#editFechaFin').val(padre.fecha_fin);

    $('#modalTitulo').text(`Añadir Subproyecto`);
    $('#modalProyecto').modal('show');
}

function editarProyecto(p) {
    if (!PERMISO_CREAR) return;
    $('#editProyectoId').val(p.id);
    $('#editProyectoPadreId').val(p.proyecto_padre_id);
    $('#editCargoId').val(p.CodNivelesCargos);
    $('#editEsSubproyecto').val(p.es_subproyecto);
    $('#editNombre').val(p.nombre);
    $('#editDescripcion').val(p.descripcion);
    $('#editFechaInicio').val(p.fecha_inicio);
    $('#editFechaFin').val(p.fecha_fin);
    $('#modalTitulo').text('Editar Proyecto');
    $('#modalProyecto').modal('show');
}

async function guardarProyecto() {
    const data = {
        id: $('#editProyectoId').val(),
        proyecto_padre_id: $('#editProyectoPadreId').val(),
        CodNivelesCargos: $('#editCargoId').val(),
        es_subproyecto: $('#editEsSubproyecto').val(),
        nombre: $('#editNombre').val(),
        descripcion: $('#editDescripcion').val(),
        fecha_inicio: $('#editFechaInicio').val(),
        fecha_fin: $('#editFechaFin').val()
    };

    if (!data.nombre || !data.fecha_inicio || !data.fecha_fin) {
        Swal.fire('Error', 'Completa los campos obligatorios', 'error');
        return;
    }

    const endpoint = data.id ? 'ajax/gestion_proyectos_actualizar.php' : 'ajax/gestion_proyectos_crear.php';

    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
        const res = await response.json();
        if (res.success) {
            $('#modalProyecto').modal('hide');
            Swal.fire('Éxito', res.message, 'success');
            cargarDatosGantt();
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    } catch (e) {
        Swal.fire('Error', 'Fallo en la comunicación con el servidor', 'error');
    }
}

function confirmarEliminar(id, event) {
    event.stopPropagation();
    if (!PERMISO_CREAR) return;

    Swal.fire({
        title: '¿Estás seguro?',
        text: "Esta acción no se puede deshacer.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e74c3c',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            eliminarProyecto(id);
        }
    });
}

async function eliminarProyecto(id) {
    try {
        const response = await fetch('ajax/gestion_proyectos_eliminar.php', {
            method: 'POST',
            body: JSON.stringify({ id })
        });
        const res = await response.json();
        if (res.success) {
            Swal.fire('Eliminado', res.message, 'success');
            cargarDatosGantt();
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    } catch (e) {
        Swal.fire('Error', 'Ocurrió un error al eliminar', 'error');
    }
}

async function toggleExpandir(id, event) {
    event.stopPropagation();
    const p = proyectosData.find(item => item.id == id);
    if (!p) return;

    p.expandido = (p.expandido === false); // Invertir estado local

    // Opcional: Persistir en BD
    fetch('ajax/gestion_proyectos_toggle_expandir.php', {
        method: 'POST',
        body: JSON.stringify({ id, expandido: p.expandido })
    });

    renderGantt();
}

/** --- INTERACTIONS (DRAG & RESIZE) --- **/

function initInteractions() {
    if (!PERMISO_CREAR) return;

    const bars = document.querySelectorAll('.gantt-bar');
    bars.forEach(bar => {
        // Drag Horizontal
        bar.addEventListener('mousedown', iniciarDrag);

        // Resize Borde Derecho
        const handle = bar.querySelector('.gantt-resize-handle');
        if (handle) {
            handle.addEventListener('mousedown', iniciarResize);
        }
    });
}

let elementRef = null;
let startX = 0;
let originalLeft = 0;
let originalWidth = 0;

function iniciarDrag(e) {
    if (e.target.classList.contains('gantt-resize-handle') || e.target.closest('.gantt-bar-actions-top')) return;
    elementRef = e.currentTarget;
    startX = e.clientX;
    originalLeft = parseFloat(elementRef.style.left);

    document.addEventListener('mousemove', arrastrar);
    document.addEventListener('mouseup', finalizarDrag);
    elementRef.style.cursor = 'grabbing';
}

function arrastrar(e) {
    if (!elementRef) return;
    const dx = e.clientX - startX;
    let newLeft = originalLeft + dx;

    // Snap a días (40px)
    newLeft = Math.round(newLeft / 40) * 40;
    elementRef.style.left = newLeft + 'px';
}

async function finalizarDrag(e) {
    if (!elementRef) return;
    document.removeEventListener('mousemove', arrastrar);
    document.removeEventListener('mouseup', finalizarDrag);
    elementRef.style.cursor = 'grab';

    const id = elementRef.dataset.id;
    const newLeft = parseFloat(elementRef.style.left);
    const daysOffset = Math.round(newLeft / 40);

    const newStart = new Date(fechaInicioGantt);
    newStart.setDate(newStart.getDate() + daysOffset);

    const p = proyectosData.find(item => item.id == id);
    const duration = (new Date(p.fecha_fin) - new Date(p.fecha_inicio)) / (1000 * 60 * 60 * 24);

    const newEnd = new Date(newStart);
    newEnd.setDate(newEnd.getDate() + duration);

    actualizarFechas(id, formatDate(newStart), formatDate(newEnd));
    elementRef = null;
}

function iniciarResize(e) {
    e.stopPropagation();
    elementRef = e.currentTarget.parentElement;
    startX = e.clientX;
    originalWidth = parseFloat(elementRef.style.width);

    document.addEventListener('mousemove', redimensionar);
    document.addEventListener('mouseup', finalizarResize);
}

function redimensionar(e) {
    const dx = e.clientX - startX;
    let newWidth = originalWidth + dx;
    newWidth = Math.max(40, Math.round(newWidth / 40) * 40);
    elementRef.style.width = newWidth + 'px';
}

async function finalizarResize(e) {
    document.removeEventListener('mousemove', redimensionar);
    document.removeEventListener('mouseup', finalizarResize);

    const id = elementRef.dataset.id;
    const newWidth = parseFloat(elementRef.style.width);
    const durationDays = Math.round(newWidth / 40);

    const p = proyectosData.find(item => item.id == id);
    const newEnd = new Date(p.fecha_inicio);
    newEnd.setDate(newEnd.getDate() + durationDays - 1);

    actualizarFechas(id, p.fecha_inicio, formatDate(newEnd));
    elementRef = null;
}

async function actualizarFechas(id, inicio, fin) {
    try {
        const response = await fetch('ajax/gestion_proyectos_actualizar.php', {
            method: 'POST',
            body: JSON.stringify({ id, fecha_inicio: inicio, fecha_fin: fin })
        });
        const res = await response.json();
        if (res.success) {
            cargarDatosGantt();
        } else {
            Swal.fire('Error', res.message, 'error');
            renderGantt(); // Revertir visualmente
        }
    } catch (e) {
        renderGantt();
    }
}

/** --- HISTORIAL & FILTROS --- **/

async function cargarHistorial(pagina = 1) {
    currentHistorialPage = pagina;
    const limit = $('#registrosPorPagina').val();

    try {
        const params = new URLSearchParams({
            pagina: pagina,
            registros_por_pagina: limit,
            filtros: JSON.stringify(currentFilters)
        });

        const response = await fetch(`ajax/gestion_proyectos_get_historial.php?${params}`);
        const res = await response.json();

        if (res.success) {
            renderTablaHistorial(res.datos);
            renderPaginacion(res.total_registros, limit, pagina);
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    } catch (e) {
        console.error("Error historial:", e);
    }
}

function renderTablaHistorial(datos) {
    const body = $('#historialBody');
    body.empty();

    if (datos.length === 0) {
        body.append('<tr><td colspan="5" class="text-center text-muted py-4">No hay proyectos finalizados con estos filtros</td></tr>');
        return;
    }

    datos.forEach(p => {
        body.append(`
            <tr>
                <td><span class="badge badge-pill badge-light border px-3 py-2">${p.cargo_nombre}</span></td>
                <td class="font-weight-bold">${p.nombre}</td>
                <td><i class="far fa-calendar-alt text-muted mr-1"></i> ${p.fecha_inicio}</td>
                <td><i class="far fa-calendar-check text-success mr-1"></i> ${p.fecha_fin}</td>
                <td class="text-muted small">${p.descripcion || '-'}</td>
            </tr>
        `);
    });
}

function renderPaginacion(total, limit, actual) {
    const container = $('#paginacion');
    container.empty();
    const paginas = Math.ceil(total / limit);
    if (paginas <= 1) return;

    // Botón Prev
    container.append(`<button class="pagination-btn" ${actual === 1 ? 'disabled' : ''} onclick="cargarHistorial(${actual - 1})"><i class="fas fa-chevron-left"></i></button>`);

    // Páginas (simplificado)
    for (let i = 1; i <= paginas; i++) {
        if (i === 1 || i === paginas || (i >= actual - 1 && i <= actual + 1)) {
            container.append(`<button class="pagination-btn ${i === actual ? 'active' : ''}" onclick="cargarHistorial(${i})">${i}</button>`);
        } else if (i === actual - 2 || i === actual + 2) {
            container.append('<span class="px-2">...</span>');
        }
    }

    // Botón Next
    container.append(`<button class="pagination-btn" ${actual === paginas ? 'disabled' : ''} onclick="cargarHistorial(${actual + 1})"><i class="fas fa-chevron-right"></i></button>`);
}

/** --- DINAMIC FILTERS CORE --- **/

function toggleFilter(icon) {
    const th = $(icon).closest('th');
    const column = th.data('column');
    const type = th.data('type');

    // Si ya existe el panel de este icono, lo cerramos
    if ($(`.filter-panel[data-col="${column}"]`).is(':visible')) {
        $('.filter-panel').removeClass('show');
        return;
    }

    $('.filter-panel').remove(); // Limpiar previos

    const panel = $(`<div class="filter-panel show" data-col="${column}"></div>`);
    const pos = $(icon).offset();
    panel.css({ top: pos.top + 25, left: Math.min(pos.left, $(window).width() - 280) });

    if (type === 'list') {
        renderListFilter(panel, column);
    } else if (type === 'daterange') {
        renderDateFilter(panel, column);
    } else {
        renderTextFilter(panel, column);
    }

    $('body').append(panel);
}

function renderListFilter(panel, column) {
    const options = filterOptions[column] || [];
    const selected = currentFilters[column] || [];

    panel.append('<span class="filter-section-title">Seleccionar opciones</span>');
    panel.append('<input type="text" class="filter-search mb-2" placeholder="Buscar..." onkeyup="filterOptionsList(this)">');

    const list = $('<div class="filter-options"></div>');
    options.forEach(opt => {
        const isChecked = selected.includes(opt);
        list.append(`
            <label class="filter-option">
                <input type="checkbox" value="${opt}" ${isChecked ? 'checked' : ''} onchange="updateListFilter('${column}')">
                ${opt}
            </label>
        `);
    });
    panel.append(list);
    panel.append('<button class="btn btn-sm btn-block btn-primary mt-3" onclick="aplicarFiltros()">Aplicar</button>');
}

function renderDateFilter(panel, column) {
    panel.append('<span class="filter-section-title">Rango de fechas</span>');
    panel.append(`
        <div class="daterange-inputs">
            <input type="date" class="form-control form-control-sm" id="f_desde" value="${currentFilters[column + '_desde'] || ''}" placeholder="Desde">
            <input type="date" class="form-control form-control-sm" id="f_hasta" value="${currentFilters[column + '_hasta'] || ''}" placeholder="Hasta">
        </div>
        <button class="btn btn-sm btn-block btn-primary mt-3" onclick="updateDateFilter('${column}')">Aplicar</button>
        <button class="btn btn-sm btn-block btn-link text-muted" onclick="limpiarFiltro('${column}')">Limpiar</button>
    `);
}

function renderTextFilter(panel, column) {
    panel.append('<span class="filter-section-title">Buscar texto</span>');
    panel.append(`
        <input type="text" class="filter-search" value="${currentFilters[column] || ''}" onkeypress="if(event.key==='Enter') updateTextFilter('${column}', this.value)">
        <button class="btn btn-sm btn-block btn-primary mt-3" onclick="updateTextFilter('${column}', $(this).prev().val())">Buscar</button>
    `);
}

function updateListFilter(column) {
    const selected = [];
    $(`.filter-panel[data-col="${column}"] input:checked`).each(function () {
        selected.push($(this).val());
    });
    currentFilters[column] = selected;
    actualizarIconoFiltro(column, selected.length > 0);
}

function updateDateFilter(column) {
    currentFilters[column + '_desde'] = $('#f_desde').val();
    currentFilters[column + '_hasta'] = $('#f_hasta').val();
    actualizarIconoFiltro(column, !!(currentFilters[column + '_desde'] || currentFilters[column + '_hasta']));
    aplicarFiltros();
}

function updateTextFilter(column, value) {
    currentFilters[column] = value;
    actualizarIconoFiltro(column, !!value);
    aplicarFiltros();
}

function actualizarIconoFiltro(column, active) {
    $(`th[data-column="${column}"] .filter-icon`).toggleClass('active', active);
}

function aplicarFiltros() {
    $('.filter-panel').removeClass('show');
    cargarHistorial(1);
}

function limpiarFiltro(column) {
    delete currentFilters[column];
    delete currentFilters[column + '_desde'];
    delete currentFilters[column + '_hasta'];
    actualizarIconoFiltro(column, false);
    aplicarFiltros();
}

async function cargarOpcionesFiltro() {
    try {
        const response = await fetch('ajax/gestion_proyectos_get_opciones_filtro.php');
        const res = await response.json();
        if (res.success) {
            filterOptions.cargo = res.cargos;
        }
    } catch (e) { }
}

function filterOptionsList(input) {
    const val = input.value.toLowerCase();
    $(input).next('.filter-options').find('.filter-option').each(function () {
        const text = $(this).text().toLowerCase();
        $(this).toggle(text.includes(val));
    });
}

/** --- UTILS --- **/

function navegarGantt(dir) {
    const offset = dir === 'anterior' ? -30 : 30;
    fechaInicioGantt.setDate(fechaInicioGantt.getDate() + offset);
    renderGantt();
}

function irAHoy() {
    fechaInicioGantt = new Date();
    fechaInicioGantt.setDate(fechaInicioGantt.getDate() - 30);
    renderGantt();
}

function cambiarRegistrosPorPagina() {
    cargarHistorial(1);
}

function formatDate(date) {
    return date.toISOString().split('T')[0];
}
