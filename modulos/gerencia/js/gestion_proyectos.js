/**
 * gestion_proyectos.js
 * Lógica principal del sistema Gantt para Batidos Pitaya
 */

let fechaInicioVista = new Date(); // Fecha de inicio de la visualización (lunes de la semana actual o 1ero del mes)
fechaInicioVista.setDate((new Date()).getDate() - 15); // Empezar 15 días antes de hoy por defecto para centrar

let globalData = {
    cargos: [],
    proyectos: []
};

let currentHistorialPage = 1;
let currentHistorialSort = { col: 'fecha_fin', dir: 'DESC' };

// --- INICIALIZACIÓN ---
$(document).ready(function () {
    initGantt();
    cargarHistorial();
    setupListeners();
});

function initGantt() {
    irAHoy(); // Establecer vista inicial en hoy
}

function setupListeners() {
    // Filtros de historial
    $('.filter-input').on('keydown', function (e) {
        if (e.key === 'Enter') cargarHistorial(1);
    });

    // Ordenamiento de tabla
    $('.sortable').on('click', function () {
        const col = $(this).data('col');
        if (currentHistorialSort.col === col) {
            currentHistorialSort.dir = currentHistorialSort.dir === 'ASC' ? 'DESC' : 'ASC';
        } else {
            currentHistorialSort.col = col;
            currentHistorialSort.dir = 'DESC';
        }
        cargarHistorial(1);
    });
}

// --- GANTT CORE ENGINE ---

async function fetchGanttData() {
    try {
        const response = await fetch('ajax/gestion_proyectos_get_datos.php');
        const res = await response.json();
        if (res.success) {
            globalData.cargos = res.cargos;
            globalData.proyectos = res.proyectos;
            renderGantt();
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    } catch (e) {
        console.error("Error fetching data", e);
    }
}

function renderGantt() {
    const container = $('#ganttContainer');
    container.empty();

    const grid = $('<div class="gantt-grid"></div>');

    // 1. Generar Encabezados de Tiempo (3 meses)
    const headers = generateTimeHeaders();
    grid.append(headers);

    // 2. Generar Filas por Cargo
    globalData.cargos.forEach(cargo => {
        const row = $('<div class="gantt-row"></div>');
        row.append(`<div class="gantt-cargo-name">${cargo.Nombre}</div>`);

        const content = $(`<div class="gantt-content" data-cargo-id="${cargo.CodNivelesCargos}"></div>`);

        // Agregar línea de HOY si está en el rango
        const hoy = new Date();
        const hoyPos = calculateDatePosition(hoy);
        if (hoyPos !== null) {
            content.append(`<div class="gantt-today-line" style="left: ${hoyPos}px"></div>`);
        }

        // Renderizar proyectos de este cargo
        const proyectosCargo = globalData.proyectos.filter(p => p.CodNivelesCargos == cargo.CodNivelesCargos);

        // Organizar proyectos: Padres primero, luego sus hijos si están expandidos
        const padres = proyectosCargo.filter(p => !p.es_subproyecto);
        padres.forEach(padre => {
            content.append(renderProjectBar(padre));

            if (padre.esta_expandido == 1) {
                const hijos = proyectosCargo.filter(p => p.proyecto_padre_id == padre.id);
                hijos.forEach(hijo => {
                    content.append(renderProjectBar(hijo, true));
                });
            }
        });

        // Click en celda vacía para crear proyecto
        content.on('click', function (e) {
            if ($(e.target).hasClass('gantt-content') && PERMISO_CREAR) {
                const rect = e.currentTarget.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const dateClicked = getDateFromPosition(x);
                crearProyectoRapido(cargo.CodNivelesCargos, dateClicked);
            }
        });

        row.append(content);
        grid.append(row);
    });

    container.append(grid);
    initDragAndDrop();
}

function generateTimeHeaders() {
    const headerRow = $('<div class="gantt-header"></div>');
    const topRow = $('<div class="gantt-header-top"></div>');
    const daysRow = $('<div class="gantt-header-days"></div>');

    let current = new Date(fechaInicioVista);
    current.setHours(0, 0, 0, 0);

    // Iterar por 90 días (~3 meses)
    const mesesMap = {}; // Para agrupar días por mes

    for (let i = 0; i < 90; i++) {
        const d = new Date(current);
        const mesKey = d.toLocaleString('es', { month: 'long', year: 'numeric' });

        if (!mesesMap[mesKey]) mesesMap[mesKey] = 0;
        mesesMap[mesKey]++;

        daysRow.append(`<div class="gantt-day-label">${d.getDate()}</div>`);
        current.setDate(current.getDate() + 1);
    }

    Object.keys(mesesMap).forEach(mes => {
        const width = mesesMap[mes] * 40; // width per day is 40px
        topRow.append(`<div class="gantt-month" style="width: ${width}px; min-width: ${width}px">${mes}</div>`);
    });

    headerRow.append(topRow).append(daysRow);
    return headerRow;
}

// --- LOGICA DE PROYECTOS ---

function renderProjectBar(p, esSub = false) {
    const startPos = calculateDatePosition(new Date(p.fecha_inicio));
    const endPos = calculateDatePosition(new Date(p.fecha_fin));

    // Si el proyecto está fuera de la vista totalmente, no renderizar (simplificado)
    if (startPos === null && endPos === null) return '';

    const width = (endPos - startPos) + 40; // Incluir el día final
    const avance = calcularAvance(p.fecha_inicio, p.fecha_fin);

    const bar = $(`
        <div class="gantt-bar ${esSub ? 'subproject' : ''}" 
             id="bar-${p.id}"
             data-id="${p.id}"
             style="left: ${startPos}px; width: ${width}px; z-index: ${esSub ? 3 : 4};"
             title="Inicio: ${p.fecha_inicio} | Fin: ${p.fecha_fin}"
        >
            <div class="gantt-bar-content">${p.nombre}</div>
            <span class="gantt-bar-progress">${avance}%</span>
            
            ${PERMISO_CREAR ? `
                <div class="gantt-bar-actions">
                    ${!esSub ? `<button class="gantt-btn" onclick="toggleExpandir(${p.id}, ${p.esta_expandido == 1 ? 0 : 1}, event)" title="${p.esta_expandido == 1 ? 'Contraer' : 'Expandir'}"><i class="fas fa-chevron-${p.esta_expandido == 1 ? 'up' : 'down'}"></i></button>` : ''}
                    ${!esSub ? `<button class="gantt-btn" onclick="agregarSubproyecto(${p.id}, event)" title="Agregar Subproyecto"><i class="fas fa-plus"></i></button>` : ''}
                    <button class="gantt-btn gantt-btn-delete" onclick="eliminarProyecto(${p.id}, event)" title="Eliminar"><i class="fas fa-times"></i></button>
                </div>
                <div class="gantt-resize-handle"></div>
            ` : ''}
        </div>
    `);

    // Doble click para editar detalles
    bar.on('dblclick', function () {
        abrirModalEditar(p);
    });

    return bar;
}

function calculateDatePosition(date) {
    const start = new Date(fechaInicioVista);
    start.setHours(0, 0, 0, 0);
    const target = new Date(date);
    target.setHours(0, 0, 0, 0);

    const diffTime = target - start;
    const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));

    if (diffDays < 0 || diffDays >= 90) return null; // Fuera de rango visual de 90 días

    return diffDays * 40; // 40px por día
}

function getDateFromPosition(px) {
    const days = Math.floor(px / 40);
    const date = new Date(fechaInicioVista);
    date.setDate(date.getDate() + days);
    return date.toISOString().split('T')[0];
}

function calcularAvance(inicio, fin) {
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    const start = new Date(inicio);
    const end = new Date(fin);

    if (hoy < start) return 0;
    if (hoy > end) return 100;

    const total = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
    const trans = Math.ceil((hoy - start) / (1000 * 60 * 60 * 24)) + 1;

    return Math.round((trans / total) * 100);
}

// --- ACCIONES AJAX ---

async function crearProyectoRapido(cargoId, fecha) {
    const { value: nombre } = await Swal.fire({
        title: 'Nuevo Proyecto',
        input: 'text',
        inputValue: 'Nuevo Proyecto',
        showCancelButton: true
    });

    if (nombre) {
        const res = await apiCall('ajax/gestion_proyectos_crear.php', {
            nombre: nombre,
            CodNivelesCargos: cargoId,
            fecha_inicio: fecha,
            fecha_fin: fecha,
            es_subproyecto: 0
        });
        if (res.success) fetchGanttData();
    }
}

async function agregarSubproyecto(padreId, e) {
    e.stopPropagation();
    const padre = globalData.proyectos.find(p => p.id == padreId);

    const res = await apiCall('ajax/gestion_proyectos_crear.php', {
        nombre: 'Subproyecto',
        CodNivelesCargos: padre.CodNivelesCargos,
        fecha_inicio: padre.fecha_inicio,
        fecha_fin: padre.fecha_fin,
        es_subproyecto: 1,
        proyecto_padre_id: padreId
    });

    if (res.success) {
        // Asegurar que el padre esté expandido para ver el nuevo hijo
        await apiCall('ajax/gestion_proyectos_toggle_expandir.php', { id: padreId, expandido: 1 });
        fetchGanttData();
    }
}

async function actualizarCampo(id, campo, valor) {
    const res = await apiCall('ajax/gestion_proyectos_actualizar.php', { id, campo, valor });
    if (!res.success) {
        Swal.fire('Error', res.message, 'error');
        return false;
    }
    return true;
}

async function toggleExpandir(id, expandido, e) {
    e.stopPropagation();
    const res = await apiCall('ajax/gestion_proyectos_toggle_expandir.php', { id, expandido });
    if (res.success) fetchGanttData();
}

async function eliminarProyecto(id, e) {
    e.stopPropagation();
    const p = globalData.proyectos.find(item => item.id == id);
    const holdsSub = globalData.proyectos.some(item => item.proyecto_padre_id == id);

    const confirm = await Swal.fire({
        title: '¿Eliminar proyecto?',
        text: holdsSub ? "Este proyecto tiene subproyectos que también serán eliminados." : "Esta acción no se puede deshacer.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, eliminar'
    });

    if (confirm.isConfirmed) {
        const res = await apiCall('ajax/gestion_proyectos_eliminar.php', { id });
        if (res.success) fetchGanttData();
    }
}

// --- DRAG AND DROP & RESIZE ---

function initDragAndDrop() {
    if (!PERMISO_CREAR) return;

    $('.gantt-bar').each(function () {
        const bar = $(this);
        const id = bar.data('id');

        // Logic for horizontal DRAG
        let isDragging = false;
        let startX = 0;
        let initialLeft = 0;

        bar.on('mousedown', function (e) {
            if ($(e.target).hasClass('gantt-resize-handle') || $(e.target).closest('.gantt-bar-actions').length) return;

            isDragging = true;
            startX = e.clientX;
            initialLeft = parseInt(bar.css('left'));
            bar.addClass('dragging');

            $(document).on('mousemove.gantt', function (e) {
                if (!isDragging) return;
                const deltaX = e.clientX - startX;
                const newLeft = Math.round((initialLeft + deltaX) / 40) * 40; // Snap to days
                bar.css('left', newLeft + 'px');
            });

            $(document).on('mouseup.gantt', async function () {
                if (!isDragging) return;
                isDragging = false;
                $(document).off('.gantt');

                const finalLeft = parseInt(bar.css('left'));
                if (finalLeft !== initialLeft) {
                    const diffDays = (finalLeft - initialLeft) / 40;
                    const p = globalData.proyectos.find(item => item.id == id);

                    const newStart = addDays(p.fecha_inicio, diffDays);
                    const newEnd = addDays(p.fecha_fin, diffDays);

                    // Actualizar ambos en la BD (backend gestiona padre/hijo)
                    const ok1 = await actualizarCampo(id, 'fecha_inicio', newStart);
                    if (ok1) await actualizarCampo(id, 'fecha_fin', newEnd);
                    fetchGanttData();
                }
            });
        });

        // Logic for RESIZE (Right edge)
        const handle = bar.find('.gantt-resize-handle');
        handle.on('mousedown', function (e) {
            e.stopPropagation();
            let isResizing = true;
            let startWidth = parseInt(bar.css('width'));
            let startClientX = e.clientX;

            $(document).on('mousemove.resizer', function (e) {
                if (!isResizing) return;
                const deltaX = e.clientX - startClientX;
                const newWidth = Math.max(40, Math.round((startWidth + deltaX) / 40) * 40);
                bar.css('width', newWidth + 'px');
            });

            $(document).on('mouseup.resizer', async function () {
                isResizing = false;
                $(document).off('.resizer');

                const finalWidth = parseInt(bar.css('width'));
                if (finalWidth !== startWidth) {
                    const diffDays = (finalWidth - startWidth) / 40;
                    const p = globalData.proyectos.find(item => item.id == id);
                    const newEnd = addDays(p.fecha_fin, diffDays);

                    const ok = await actualizarCampo(id, 'fecha_fin', newEnd);
                    fetchGanttData();
                }
            });
        });
    });
}

// --- NAVEGACIÓN ---

function navegarGantt(dir) {
    if (dir === 'anterior') {
        fechaInicioVista.setMonth(fechaInicioVista.getMonth() - 1);
    } else {
        fechaInicioVista.setMonth(fechaInicioVista.getMonth() + 1);
    }
    fetchGanttData();
}

function irAHoy() {
    const hoy = new Date();
    hoy.setDate(hoy.getDate() - 15); // Centrar
    fechaInicioVista = hoy;
    fetchGanttData();
}

// --- HISTORIAL ---

async function cargarHistorial(pagina = 1) {
    currentHistorialPage = pagina;
    const limit = $('#historialLimit').val();

    // Recolectar filtros
    const filtros = {};
    $('.filter-input').each(function () {
        const val = $(this).val();
        if (val) filtros[$(this).data('filter')] = val;
    });

    const query = new URLSearchParams({
        pagina: pagina,
        registros_por_pagina: limit,
        orden_columna: currentHistorialSort.col,
        orden_direccion: currentHistorialSort.dir,
        filtros: JSON.stringify(filtros)
    });

    try {
        const response = await fetch('ajax/gestion_proyectos_get_historial.php?' + query);
        const res = await response.json();

        if (res.success) {
            renderHistorial(res.datos, res.total_registros, limit);
        } else {
            Swal.fire('Error Historial', res.message, 'error');
        }
    } catch (e) {
        console.error("Error historial", e);
        Swal.fire('Error', 'No se pudo cargar el historial: ' + e.message, 'error');
    }
}

function renderHistorial(datos, total, limit) {
    const body = $('#historialBody');
    body.empty();

    if (datos.length === 0) {
        body.append('<tr><td colspan="5" class="text-center py-4">No hay proyectos finalizados</td></tr>');
        return;
    }

    datos.forEach(row => {
        body.append(`
            <tr>
                <td><span class="badge badge-light border">${row.cargo_nombre}</span></td>
                <td class="font-weight-bold">${row.nombre}</td>
                <td>${formatFechaLetras(row.fecha_inicio)}</td>
                <td>${formatFechaLetras(row.fecha_fin)}</td>
                <td class="small text-muted">${row.descripcion || '-'}</td>
            </tr>
        `);
    });

    // Stats
    const start = ((currentHistorialPage - 1) * limit) + 1;
    const end = Math.min(start + datos.length - 1, total);
    $('#historialStats').text(`Mostrando ${start}-${end} de ${total} registros`);

    // Paginación
    renderPagination(total, limit);
}

function renderPagination(total, limit) {
    const container = $('#historialPagination .pagination');
    container.empty();

    const totalPages = Math.ceil(total / limit);
    if (totalPages <= 1) return;

    const prev = $(`<li class="page-item ${currentHistorialPage === 1 ? 'disabled' : ''}"><a class="page-link" href="#">Anterior</a></li>`);
    prev.on('click', () => currentHistorialPage > 1 && cargarHistorial(currentHistorialPage - 1));
    container.append(prev);

    for (let i = 1; i <= totalPages; i++) {
        const page = $(`<li class="page-item ${i === currentHistorialPage ? 'active' : ''}"><a class="page-link" href="#">${i}</a></li>`);
        page.on('click', () => cargarHistorial(i));
        container.append(page);
    }

    const next = $(`<li class="page-item ${currentHistorialPage === totalPages ? 'disabled' : ''}"><a class="page-link" href="#">Siguiente</a></li>`);
    next.on('click', () => currentHistorialPage < totalPages && cargarHistorial(currentHistorialPage + 1));
    container.append(next);
}

function toggleFiltrosHistorial() {
    $('#filtrosHistorialRow').toggle();
}

// --- UTILS ---

async function apiCall(url, data) {
    const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    return await response.json();
}

function addDays(dateStr, days) {
    const date = new Date(dateStr + "T00:00:00");
    date.setDate(date.getDate() + days);
    return date.toISOString().split('T')[0];
}

function formatFechaLetras(dateStr) {
    const date = new Date(dateStr + "T00:00:00");
    const options = { day: '2-digit', month: 'short', year: '2-digit' };
    return date.toLocaleDateString('es-ES', options).replace(/ /g, '-').replace(/\./g, '');
}

function abrirModalEditar(p) {
    if (!PERMISO_CREAR) return;

    $('#editProyectoId').val(p.id);
    $('#editNombre').val(p.nombre);
    $('#editDescripcion').val(p.descripcion);
    $('#editFechaInicio').val(p.fecha_inicio);
    $('#editFechaFin').val(p.fecha_fin);

    $('#modalProyecto').modal('show');
}

$('#btnGuardarProyecto').on('click', async function () {
    const id = $('#editProyectoId').val();
    const data = {
        nombre: $('#editNombre').val(),
        descripcion: $('#editDescripcion').val(),
        fecha_inicio: $('#editFechaInicio').val(),
        fecha_fin: $('#editFechaFin').val()
    };

    // Actualizar cada campo serialmente por simplicidad del backend
    for (const campo in data) {
        await actualizarCampo(id, campo, data[campo]);
    }

    $('#modalProyecto').modal('hide');
    fetchGanttData();
});
