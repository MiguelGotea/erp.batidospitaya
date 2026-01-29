// gestion_proyectos.js
// Lógica principal del Diagrama de Gantt


let fechaInicioGantt = new Date();
fechaInicioGantt.setDate(fechaInicioGantt.getDate() - 1); // Hoy es la segunda columna
let proyectosData = [];
let lastCargosList = [];
let cargandoGantt = false;

// Configuración Historial
let currentHistorialPage = 1;
let currentFilters = {};
let filterOptions = { cargo: [] };
let panelFiltroAbierto = null;
let ordenActivo = { columna: null, direccion: null };

$(document).ready(function () {
    initGantt();
    cargarHistorial();
    cargarOpcionesFiltro();

    $('#btnGuardarProyecto').on('click', guardarProyecto);

    // Cerrar paneles de filtro al hacer clic fuera
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.filter-panel, .filter-icon').length) {
            cerrarTodosFiltros();
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
            proyectosData = res.proyectos || [];
            // Reset all projects to collapsed state on page load
            proyectosData.forEach(p => p.esta_expandido = 0);

            // Calculate start date based on earliest project
            if (proyectosData.length > 0) {
                const fechas = proyectosData.map(p => new Date(p.fecha_inicio));
                const fechaMasAntigua = new Date(Math.min(...fechas));
                const hoy = new Date();

                // Use the earlier of: earliest project or today-1
                if (fechaMasAntigua < hoy) {
                    fechaInicioGantt = new Date(fechaMasAntigua);
                    fechaInicioGantt.setDate(fechaInicioGantt.getDate() - 7); // Add 1 week buffer before
                } else {
                    fechaInicioGantt = new Date();
                    fechaInicioGantt.setDate(fechaInicioGantt.getDate() - 1);
                }
            }

            renderGantt(res.cargos || []);
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

function renderGantt(cargosList = []) {
    lastCargosList = cargosList;
    const container = $('#ganttContainer');
    container.empty();

    const endDate = new Date(fechaInicioGantt);
    endDate.setDate(endDate.getDate() + 90); // 3 meses vista

    const wrapper = $('<div class="gantt-grid"></div>');

    // 1. Render Headers and Sticky Corners
    const headerRow = $('<div class="gantt-header"></div>');
    headerRow.append('<div class="gantt-header-corner"></div>');
    const headerTop = $('<div class="gantt-header-top"></div>');

    const headerDaysRow = $('<div class="gantt-header"></div>');
    headerDaysRow.append('<div class="gantt-header-corner-days"></div>');
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
    headerRow.append(headerTop);
    headerDaysRow.append(headerDays);
    wrapper.append(headerRow).append(headerDaysRow);

    // 2. Render Rows per Cargo - SHOW ALL CARGOS
    cargosList.forEach(cargoObj => {
        const cargo = cargoObj.Nombre;
        const cargoId = cargoObj.CodNivelesCargos;

        const row = $('<div class="gantt-row"></div>');
        row.append(`<div class="gantt-cargo-name" onclick="nuevoProyecto(${cargoId}, '${cargo}')">${cargo}</div>`);

        const content = $('<div class="gantt-content"></div>');
        const proyectosCargo = proyectosData.filter(p => p.CodNivelesCargos == cargoId);

        // Hierarchical Stacking Logic
        let levels = [[]];
        const padres = proyectosCargo.filter(p => p.es_subproyecto == 0).sort((a, b) => new Date(a.fecha_inicio) - new Date(b.fecha_inicio));

        padres.forEach(padre => {
            let padreLevel = findBestLevel(padre, levels);
            levels[padreLevel].push(padre);
            content.append(renderProyectoBar(padre, padreLevel));
            // Si está expandido, procesar sus hijos inmediatamente debajo
            if (parseInt(padre.esta_expandido) !== 0) {
                const hijos = proyectosCargo.filter(p => p.proyecto_padre_id == padre.id).sort((a, b) => new Date(a.fecha_inicio) - new Date(b.fecha_inicio));

                let nextChildLevel = padreLevel + 1;
                hijos.forEach(hijo => {
                    // Cada hijo del mismo padre DEBE estar en su propia fila debajo del anterior
                    let hijoLevel = nextChildLevel;
                    // Aseguramos que el array de niveles tenga espacio
                    while (levels.length <= hijoLevel) levels.push([]);

                    levels[hijoLevel].push(hijo);
                    content.append(renderProyectoBar(hijo, hijoLevel));
                    nextChildLevel++; // El siguiente hijo irá una fila más abajo
                });
            }
        });

        const rowHeight = Math.max(60, (levels.length * 45) + 10);
        content.css('height', rowHeight + 'px');
        row.find('.gantt-cargo-name').css('height', rowHeight + 'px');

        row.append(content);
        wrapper.append(row);
    });

    // 3. Today Line
    const hoy = new Date();
    const difHoy = (hoy - fechaInicioGantt) / (1000 * 60 * 60 * 24);
    if (difHoy >= 0 && difHoy <= 90) {
        const left = 180 + (difHoy * 40);
        wrapper.append(`<div class="gantt-today-line-full" style="left: ${left}px"></div>`);
    }

    container.append(wrapper);
    initInteractions();

    // Scroll to today's date on initial load
    scrollToToday();`n    setTimeout(() => initDragToScroll(), 100);
}

function scrollToToday() {
    const hoy = new Date();
    const difHoy = (hoy - fechaInicioGantt) / (1000 * 60 * 60 * 24);
    const scrollLeft = Math.max(0, (difHoy * 40) - 200); // Center today with 200px offset

    const ganttWrapper = $('#ganttContainer');
    if (ganttWrapper.length) {
        ganttWrapper.scrollLeft(scrollLeft);
    }
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
    const isExpandido = parseInt(p.esta_expandido) !== 0;
    const tieneHijos = isPadre && proyectosData.some(hijo => hijo.proyecto_padre_id == p.id);

    // Formatear fecha de fin para mostrar al lado derecho
    const fechaFin = new Date(p.fecha_fin);
    const dia = fechaFin.getDate();
    const mesAbrev = fechaFin.toLocaleString('es-ES', { month: 'short' }).replace('.', '').substring(0, 2);
    const fechaFinFormateada = `${dia}/${mesAbrev.charAt(0).toUpperCase() + mesAbrev.slice(1)}`;

    const bar = $(`
        <div class="gantt-bar ${p.es_subproyecto == 1 ? 'subproject' : ''}" 
             data-id="${p.id}" 
             style="left: ${left}px; width: ${width}px; top: ${top}px;"
             title="${p.nombre}: ${p.fecha_inicio} al ${p.fecha_fin}">
            <div class="gantt-bar-actions-top">
                ${tieneHijos ? `
                    <div class="gantt-btn-sm" onclick="toggleExpandir(${p.id}, event)" title="${isExpandido ? 'Contraer' : 'Expandir'}">
                        <i class="fas ${isExpandido ? 'fa-chevron-up' : 'fa-chevron-down'}"></i>
                    </div>
                ` : ''}
                ${isPadre ? `
                    <div class="gantt-btn-sm" onclick="nuevoSubproyecto(${p.id}, ${p.CodNivelesCargos}, event)" title="Añadir Subproyecto">
                        <i class="fas fa-plus"></i>
                    </div>
                ` : ''}
                <div class="gantt-btn-sm bg-primary" onclick="editarProyecto(${p.id}, event)" title="Editar">
                    <i class="fas fa-pencil-alt"></i>
                </div>
                <div class="gantt-btn-sm bg-danger" onclick="confirmarEliminar(${p.id}, event)" title="Eliminar">
                    <i class="fas fa-times"></i>
                </div>
            </div>
            <div class="gantt-bar-title text-truncate">${p.nombre}</div>
            <div class="gantt-bar-end-date">${fechaFinFormateada}</div>
            ${typeof PERMISO_CREAR !== 'undefined' && PERMISO_CREAR ? '<div class="gantt-resize-handle"></div>' : ''}
        </div>
    `);
    return bar;
}

/** --- ACTIONS & MODALS --- **/

function nuevoProyecto(cargoId, cargoNombre) {
    $('#formProyecto')[0].reset();
    $('#editProyectoId').val('');
    $('#editProyectoPadreId').val('');
    $('#editCargoId').val(cargoId);
    $('#editEsSubproyecto').val(0);
    $('#editFechaInicio').prop('disabled', false);
    $('#editFechaFin').prop('disabled', false);
    $('#modalTitulo').text(`Nuevo Proyecto para ${cargoNombre}`);
    $('#modalProyecto').modal('show');
}

function nuevoSubproyecto(padreId, cargoId, event) {
    event.stopPropagation();
    $('#formProyecto')[0].reset();
    $('#editProyectoId').val('');
    $('#editProyectoPadreId').val(padreId);
    $('#editCargoId').val(cargoId);
    $('#editEsSubproyecto').val(1);
    $('#editFechaInicio').prop('disabled', false);
    $('#editFechaFin').prop('disabled', false);

    const padre = proyectosData.find(p => p.id == padreId);
    $('#editNombre').val(`Sub: ${padre.nombre}`);
    $('#editFechaInicio').val(padre.fecha_inicio);
    $('#editFechaFin').val(padre.fecha_fin);

    $('#modalTitulo').text(`Añadir Subproyecto`);
    $('#modalProyecto').modal('show');
}

window.editarProyecto = function (id, event) {
    if (event) event.stopPropagation();
    const p = proyectosData.find(item => item.id == id);
    if (!p) return;

    $('#editProyectoId').val(p.id);
    $('#editProyectoPadreId').val(p.proyecto_padre_id);
    $('#editCargoId').val(p.CodNivelesCargos);
    $('#editEsSubproyecto').val(p.es_subproyecto);
    $('#editNombre').val(p.nombre);
    $('#editDescripcion').val(p.descripcion);
    $('#editFechaInicio').val(p.fecha_inicio).prop('disabled', true);
    $('#editFechaFin').val(p.fecha_fin).prop('disabled', true);
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

    const nuevoEstado = parseInt(p.esta_expandido) === 0 ? 1 : 0;
    p.esta_expandido = nuevoEstado;

    fetch('ajax/gestion_proyectos_toggle_expandir.php', {
        method: 'POST',
        body: JSON.stringify({ id, expandido: nuevoEstado })
    });

    renderGantt(lastCargosList);
}

/** --- INTERACTIONS (DRAG & RESIZE) --- **/

function initInteractions() {
    const bars = document.querySelectorAll('.gantt-bar');
    bars.forEach(bar => {
        bar.addEventListener('mousedown', iniciarDrag);
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
    const daysOffset = Math.round((newLeft - originalLeft) / 40);

    if (daysOffset === 0) {
        elementRef = null;
        return;
    }

    const p = proyectosData.find(item => item.id == id);
    if (!p) { elementRef = null; return; }

    const newStart = new Date(p.fecha_inicio);
    newStart.setDate(newStart.getDate() + daysOffset);
    const newEnd = new Date(p.fecha_fin);
    newEnd.setDate(newEnd.getDate() + daysOffset);

    // Cascading Move
    if (p.es_subproyecto == 0) {
        actualizarFechasFull({
            id: id,
            fecha_inicio: formatDate(newStart),
            fecha_fin: formatDate(newEnd),
            movimiento_cascada: daysOffset
        });
    } else {
        actualizarFechasFull({
            id: id,
            fecha_inicio: formatDate(newStart),
            fecha_fin: formatDate(newEnd)
        });
    }
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

    const id = elementRef.dataset.id;
    const p = proyectosData.find(item => item.id == id);

    if (p && p.es_subproyecto == 0) {
        const hijos = proyectosData.filter(h => h.proyecto_padre_id == p.id);
        if (hijos.length > 0) {
            const maxFechaFinHijos = new Date(Math.max(...hijos.map(h => new Date(h.fecha_fin))));
            const startParent = new Date(p.fecha_inicio);
            const minDurationDays = Math.round((maxFechaFinHijos - startParent) / (1000 * 60 * 60 * 24)) + 1;
            const minWidth = minDurationDays * 40;
            if (newWidth < minWidth) newWidth = minWidth;
        }
    }

    newWidth = Math.max(40, Math.round(newWidth / 40) * 40);
    elementRef.style.width = newWidth + 'px';
}

async function finalizarResize(e) {
    if (!elementRef) return;
    document.removeEventListener('mousemove', redimensionar);
    document.removeEventListener('mouseup', finalizarResize);

    const id = elementRef.dataset.id;
    const newWidth = parseFloat(elementRef.style.width);
    const durationDays = Math.round(newWidth / 40);

    const p = proyectosData.find(item => item.id == id);
    const newEnd = new Date(p.fecha_inicio);
    newEnd.setDate(newEnd.getDate() + durationDays - 1);

    actualizarFechasFull({ id, fecha_inicio: p.fecha_inicio, fecha_fin: formatDate(newEnd) });
    elementRef = null;
}

async function actualizarFechasFull(data) {
    try {
        const response = await fetch('ajax/gestion_proyectos_actualizar.php', {
            method: 'POST',
            body: JSON.stringify(data)
        });
        const res = await response.json();
        if (res.success) {
            cargarDatosGantt();
        } else {
            Swal.fire('Error', res.message, 'error');
            renderGantt(lastCargosList);
        }
    } catch (e) {
        renderGantt(lastCargosList);
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
            filtros: JSON.stringify(currentFilters),
            orden_columna: ordenActivo.columna || '',
            orden_direccion: ordenActivo.direccion || ''
        });
        const response = await fetch(`ajax/gestion_proyectos_get_historial.php?${params}`);
        const res = await response.json();
        if (res.success) {
            renderTablaHistorial(res.datos);
            renderPaginacion(res.total_registros, limit, pagina);
        }
    } catch (e) { console.error("Error historial:", e); }
}

function renderTablaHistorial(datos) {
    const body = $('#historialBody');
    body.empty();
    if (datos.length === 0) {
        body.append('<tr><td colspan="5" class="text-center text-muted py-4">No hay resultados</td></tr>');
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
    container.append(`<button class="pagination-btn" ${actual === 1 ? 'disabled' : ''} onclick="cargarHistorial(${actual - 1})"><i class="fas fa-chevron-left"></i></button>`);
    for (let i = 1; i <= paginas; i++) {
        if (i === 1 || i === paginas || (i >= actual - 1 && i <= actual + 1)) {
            container.append(`<button class="pagination-btn ${i === actual ? 'active' : ''}" onclick="cargarHistorial(${i})">${i}</button>`);
        } else if (i === actual - 2 || i === actual + 2) {
            container.append('<span class="px-2">...</span>');
        }
    }
    container.append(`<button class="pagination-btn" ${actual === paginas ? 'disabled' : ''} onclick="cargarHistorial(${actual + 1})"><i class="fas fa-chevron-right"></i></button>`);
}

/** --- FILTROS MEJORADOS (BASADO EN DOCS) --- **/

window.toggleFilter = function (icon) {
    const th = $(icon).closest('th');
    const columna = th.data('column');
    const tipo = th.data('type');

    if (panelFiltroAbierto === columna) {
        cerrarTodosFiltros();
        return;
    }

    cerrarTodosFiltros();
    crearPanelFiltro(th, columna, tipo, icon);
    panelFiltroAbierto = columna;
    $(icon).addClass('active');
}

function crearPanelFiltro(th, columna, tipo, icon) {
    const panel = $('<div class="filter-panel show"></div>');

    // Ordenamiento
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

    // Botones de acción
    panel.append(`
        <div class="filter-actions">
            <button class="filter-action-btn clear" onclick="limpiarFiltro('${columna}')">
                <i class="bi bi-x-circle"></i> Limpiar
            </button>
        </div>
    `);

    $('body').append(panel);

    // Filtros según tipo
    if (tipo === 'text') {
        const valorActual = currentFilters[columna] || '';
        panel.append(`
            <div class="filter-section" style="margin-top: 12px;">
                <span class="filter-section-title">Buscar:</span>
                <input type="text" class="filter-search" placeholder="Escribir..." 
                       value="${valorActual}"
                       oninput="filtrarBusqueda('${columna}', this.value)">
            </div>
        `);
    } else if (tipo === 'list') {
        const options = filterOptions[columna] || [];
        const selected = currentFilters[columna] || [];
        panel.append(`
            <div class="filter-section" style="margin-top: 12px;">
                <span class="filter-section-title">Seleccionar:</span>
                <div class="filter-options">
                    ${options.map(opt => `
                        <label class="filter-option">
                            <input type="checkbox" value="${opt}" ${selected.includes(opt) ? 'checked' : ''} onchange="updateListFilter('${columna}')">
                            ${opt}
                        </label>
                    `).join('')}
                </div>
            </div>
        `);
    } else if (tipo === 'daterange') {
        const desde = currentFilters[columna + '_desde'] || '';
        const hasta = currentFilters[columna + '_hasta'] || '';
        panel.append(`
            <div class="filter-section" style="margin-top: 12px;">
                <span class="filter-section-title">Desde:</span>
                <input type="date" class="filter-search" id="f_desde_${columna}" value="${desde}">
                <span class="filter-section-title" style="margin-top: 8px;">Hasta:</span>
                <input type="date" class="filter-search" id="f_hasta_${columna}" value="${hasta}">
                <button class="btn btn-sm btn-block btn-primary mt-2" onclick="aplicarFiltroFecha('${columna}')">Aplicar</button>
            </div>
        `);
    }

    posicionarPanelFiltro(panel, icon);
}

function posicionarPanelFiltro(panel, icon) {
    const offset = $(icon).offset();
    panel.css({
        top: offset.top + 25,
        left: Math.min(offset.left, $(window).width() - 280)
    });
}

function cerrarTodosFiltros() {
    $('.filter-panel').remove();
    $('.filter-icon').removeClass('active');
    panelFiltroAbierto = null;
}

window.aplicarOrden = function (columna, direccion) {
    ordenActivo = { columna, direccion };
    cargarHistorial(1);
    cerrarTodosFiltros();
}

window.filtrarBusqueda = function (columna, valor) {
    currentFilters[columna] = valor;
    cargarHistorial(1);
}

window.updateListFilter = function (columna) {
    const selected = [];
    $(`.filter-panel input:checked`).each(function () { selected.push($(this).val()); });
    currentFilters[columna] = selected.length > 0 ? selected : undefined;
    if (!currentFilters[columna]) delete currentFilters[columna];
    cargarHistorial(1);
}

window.aplicarFiltroFecha = function (columna) {
    const desde = $(`#f_desde_${columna}`).val();
    const hasta = $(`#f_hasta_${columna}`).val();
    if (desde) currentFilters[columna + '_desde'] = desde;
    else delete currentFilters[columna + '_desde'];
    if (hasta) currentFilters[columna + '_hasta'] = hasta;
    else delete currentFilters[columna + '_hasta'];
    cargarHistorial(1);
    cerrarTodosFiltros();
}

window.limpiarFiltro = function (columna) {
    delete currentFilters[columna];
    delete currentFilters[columna + '_desde'];
    delete currentFilters[columna + '_hasta'];
    if (ordenActivo.columna === columna) {
        ordenActivo = { columna: null, direccion: null };
    }
    cargarHistorial(1);
    cerrarTodosFiltros();
}

async function cargarOpcionesFiltro() {
    try {
        const response = await fetch('ajax/gestion_proyectos_get_opciones_filtro.php');
        const res = await response.json();
        if (res.success) {
            filterOptions.cargo = res.cargos || [];
        }
    } catch (e) { }
}

/** --- UTILS --- **/

function navegarGantt(d) {
    fechaInicioGantt.setDate(fechaInicioGantt.getDate() + (d === 'anterior' ? -7 : 7));
    renderGantt(lastCargosList);
}
function irAHoy() { fechaInicioGantt = new Date(); fechaInicioGantt.setDate(fechaInicioGantt.getDate() - 1); renderGantt(lastCargosList); }
function cambiarRegistrosPorPagina() { cargarHistorial(1); }
function formatDate(d) { if (!d) return ''; const dt = new Date(d); return dt.toISOString().split('T')[0]; }

function findBestLevel(p, levels, minLevel = 0) {
    for (let i = minLevel; i < levels.length; i++) {
        const collides = levels[i].some(item => {
            return (new Date(p.fecha_inicio) <= new Date(item.fecha_fin)) && (new Date(p.fecha_fin) >= new Date(item.fecha_inicio));
        });
        if (!collides) return i;
    }
    while (levels.length <= minLevel) levels.push([]);
    levels.push([]);
    return levels.length - 1;
}


function initDragToScroll() {
    const ganttWrapper = document.getElementById('ganttContainer');
    if (!ganttWrapper) return;
    
    let isDown = false;
    let startX;
    let scrollLeft;
    
    ganttWrapper.addEventListener('mousedown', (e) => {
        if (!e.target.closest('.gantt-bar, .gantt-cargo-name, .gantt-btn-sm')) {
            isDown = true;
            ganttWrapper.style.cursor = 'grabbing';
            startX = e.pageX - ganttWrapper.offsetLeft;
            scrollLeft = ganttWrapper.scrollLeft;
            e.preventDefault();
        }
    });
    
    ganttWrapper.addEventListener('mouseleave', () => {
        isDown = false;
        ganttWrapper.style.cursor = 'grab';
    });
    
    ganttWrapper.addEventListener('mouseup', () => {
        isDown = false;
        ganttWrapper.style.cursor = 'grab';
    });
    
    ganttWrapper.addEventListener('mousemove', (e) => {
        if (!isDown) return;
        e.preventDefault();
        const x = e.pageX - ganttWrapper.offsetLeft;
        const walk = (x - startX) * 1.5;
        ganttWrapper.scrollLeft = scrollLeft - walk;
    });
}

