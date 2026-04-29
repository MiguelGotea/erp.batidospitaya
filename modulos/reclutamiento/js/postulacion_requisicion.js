// postulacion_requisicion.js

// Variables globales para filtros y paginación
let filtrosActivos = {};
let ordenActual = { columna: '', direccion: 'ASC' };
let paginaActual = 1;
let registrosPorPagina = 25;
let panelFiltroAbierto = null;
let scrollTopInicial = 0;

document.addEventListener('DOMContentLoaded', function () {
    cargarDatos();

    // Cerrar filtros al hacer clic fuera
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.filter-panel, .filter-icon')) {
            cerrarTodosFiltros();
        }
    });

    // NO cerrar filtros al hacer scroll en la página si es pequeño
    window.addEventListener('scroll', function (e) {
        if (panelFiltroAbierto && Math.abs(window.scrollY - scrollTopInicial) > 100) {
            cerrarTodosFiltros();
        }
    });
});

// ========================================
// CARGAR Y RENDERIZAR DATOS
// ========================================
async function cargarDatos() {
    try {
        const response = await fetch('ajax/postulacion_requisicion_get_datos.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                filtros: filtrosActivos,
                orden: ordenActual,
                pagina: paginaActual,
                registros_por_pagina: registrosPorPagina
            })
        });

        const data = await response.json();

        if (data.success) {
            renderizarTabla(data.datos);
            renderizarPaginacion(data.total_registros);
            actualizarIndicadoresFiltros();
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error al cargar datos:', error);
        Swal.fire('Error', 'No se pudieron cargar los datos', 'error');
    }
}

function renderizarTabla(datos) {
    const tbody = document.getElementById('tablaRequisicionesBody');
    tbody.innerHTML = '';

    if (datos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No hay requisiciones registradas</td></tr>';
        return;
    }

    datos.forEach(req => {
        const urgenciaTexto = ['', 'No urgente', 'Medio', 'Urgente', 'Crítico'][req.nivel_urgencia];
        const urgenciaClase = `urgencia-badge-${req.nivel_urgencia}`;

        const statusClase = `badge-${req.status.toLowerCase()}`;

        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="text-center">${req.id}</td>
            <td>${req.nombre_cargo}</td>
            <td class="text-center">${req.cantidad}</td>
            <td>${req.sucursal_nombre}</td>
            <td class="text-center">
                <span class="badge ${urgenciaClase}">${urgenciaTexto}</span>
            </td>
            <td class="text-center">${formatearFecha(req.fecha_creacion)}</td>
            <td class="text-center">
                <span class="badge ${statusClase}">${req.status}</span>
            </td>
            <td class="text-center">
                <button class="btn btn-sm btn-info btn-action" onclick="verDetalle(${req.id})" title="Ver detalle">
                    <i class="bi bi-eye"></i>
                </button>
                ${req.status === 'Solicitado' ? `
                <button class="btn btn-sm btn-warning btn-action" onclick="editarRequisicion(${req.id})" title="Editar requisición">
                    <i class="bi bi-pencil"></i>
                </button>
                ` : ''}
            </td>
        `;
        tbody.appendChild(row);
    });
}

// ========================================
// SISTEMA DE FILTROS
// ========================================

function toggleFilter(icon) {
    const th = icon.closest('th');
    const columna = th.dataset.column;
    const tipo = th.dataset.type;

    if (panelFiltroAbierto === columna) {
        cerrarTodosFiltros();
        return;
    }

    cerrarTodosFiltros();
    scrollTopInicial = window.scrollY;
    crearPanelFiltro(th, columna, tipo, icon);
    panelFiltroAbierto = columna;
    icon.classList.add('active');
}

function cerrarTodosFiltros() {
    const paneles = document.querySelectorAll('.filter-panel');
    paneles.forEach(p => p.remove());
    const iconos = document.querySelectorAll('.filter-icon');
    iconos.forEach(i => i.classList.remove('active'));
    panelFiltroAbierto = null;
}

function crearPanelFiltro(th, columna, tipo, icon) {
    const panel = document.createElement('div');
    panel.className = 'filter-panel show';
    if (tipo === 'daterange') panel.classList.add('has-daterange');

    let html = `
        <div class="filter-section">
            <span class="filter-section-title">Ordenar:</span>
            <div class="filter-sort-buttons">
                <button class="filter-sort-btn ${ordenActual.columna === columna && ordenActual.direccion === 'ASC' ? 'active' : ''}" 
                        onclick="aplicarOrden('${columna}', 'ASC')">
                    <i class="bi bi-sort-alpha-down"></i> A→Z
                </button>
                <button class="filter-sort-btn ${ordenActual.columna === columna && ordenActual.direccion === 'DESC' ? 'active' : ''}" 
                        onclick="aplicarOrden('${columna}', 'DESC')">
                    <i class="bi bi-sort-alpha-up"></i> Z→A
                </button>
            </div>
        </div>
    `;

    if (tipo === 'text' || tipo === 'number') {
        const valorActual = filtrosActivos[columna] || '';
        html += `
            <div class="filter-section">
                <span class="filter-section-title">Buscar:</span>
                <input type="text" class="filter-search" placeholder="Escribir..." 
                       value="${valorActual}"
                       oninput="filtrarBusqueda('${columna}', this.value)">
            </div>
        `;
    } else if (tipo === 'list') {
        html += `<div id="filter-options-container" class="filter-section">
                    <span class="filter-section-title">Filtrar por:</span>
                    <div class="text-center py-2 spinner-border spinner-border-sm text-secondary" role="status"></div>
                 </div>`;
        cargarOpcionesFiltro(columna);
    } else if (tipo === 'daterange') {
        html += crearHtmlCalendario(columna);
    }

    html += `
        <div class="filter-actions">
            <button class="filter-action-btn clear" onclick="limpiarFiltro('${columna}')">
                <i class="bi bi-x-circle me-1"></i>Limpiar
            </button>
        </div>
    `;

    panel.innerHTML = html;
    document.body.appendChild(panel);
    posicionarPanelFiltro(panel, icon);

    if (tipo === 'daterange') {
        inicializarCalendario(columna);
    }
}

function posicionarPanelFiltro(panel, icon) {
    const rect = icon.getBoundingClientRect();
    const panelWidth = panel.offsetWidth;
    const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

    let left = rect.left + scrollLeft - panelWidth + rect.width;
    let top = rect.bottom + scrollTop + 5;

    if (left < 10) left = 10;
    if (left + panelWidth > window.innerWidth) left = window.innerWidth - panelWidth - 10;

    panel.style.left = left + 'px';
    panel.style.top = top + 'px';
}

function aplicarOrden(columna, direccion) {
    ordenActual = { columna, direccion };
    cerrarTodosFiltros();
    paginaActual = 1;
    cargarDatos();
}

function filtrarBusqueda(columna, valor) {
    if (valor.trim() === '') {
        delete filtrosActivos[columna];
    } else {
        filtrosActivos[columna] = valor;
    }
    paginaActual = 1;
    // Debounce para búsqueda de texto
    clearTimeout(window.searchTimeout);
    window.searchTimeout = setTimeout(() => {
        cargarDatos();
    }, 400);
}

function limpiarFiltro(columna) {
    delete filtrosActivos[columna];
    cerrarTodosFiltros();
    paginaActual = 1;
    cargarDatos();
}

async function cargarOpcionesFiltro(columna) {
    try {
        const response = await fetch('ajax/postulacion_requisicion_get_opciones_filtro.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `columna=${columna}`
        });
        const data = await response.json();

        if (data.success) {
            const container = document.getElementById('filter-options-container');
            if (!container) return;

            let html = '<span class="filter-section-title">Filtrar por:</span>';
            html += '<div class="filter-options">';
            data.opciones.forEach(op => {
                const checked = (filtrosActivos[columna] && filtrosActivos[columna].includes(op.valor)) ? 'checked' : '';
                html += `
                    <label class="filter-option">
                        <input type="checkbox" value="${op.valor}" ${checked} 
                               onchange="toggleOpcionFiltro('${columna}', '${op.valor}', this.checked)">
                        <span>${op.texto}</span>
                    </label>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
        }
    } catch (error) {
        console.error('Error al cargar opciones:', error);
    }
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

function actualizarIndicadoresFiltros() {
    const iconos = document.querySelectorAll('.filter-icon');
    iconos.forEach(icon => {
        const columna = icon.closest('th').dataset.column;
        if (filtrosActivos[columna]) {
            icon.classList.add('has-filter');
        } else {
            icon.classList.remove('has-filter');
        }
    });
}

// ========================================
// LÓGICA DE CALENDARIO
// ========================================

function crearHtmlCalendario(columna) {
    return `
        <div class="filter-section">
            <span class="filter-section-title">Rango de Fechas:</span>
            <div class="daterange-calendar-container">
                <div class="daterange-month-selector">
                    <select id="mesCalendario" onchange="actualizarCalendario('${columna}')"></select>
                    <select id="añoCalendario" onchange="actualizarCalendario('${columna}')"></select>
                </div>
                <div class="daterange-calendar" id="calendarioDiv"></div>
            </div>
        </div>
    `;
}

function inicializarCalendario(columna) {
    const hoy = new Date();
    const mesActual = hoy.getMonth();
    const añoActual = hoy.getFullYear();

    const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    const selectMes = document.getElementById('mesCalendario');
    const selectAño = document.getElementById('añoCalendario');

    meses.forEach((mes, i) => {
        const opt = document.createElement('option');
        opt.value = i;
        opt.textContent = mes;
        if (i === mesActual) opt.selected = true;
        selectMes.appendChild(opt);
    });

    for (let a = añoActual - 2; a <= añoActual + 1; a++) {
        const opt = document.createElement('option');
        opt.value = a;
        opt.textContent = a;
        if (a === añoActual) opt.selected = true;
        selectAño.appendChild(opt);
    }

    actualizarCalendario(columna);
}

function actualizarCalendario(columna) {
    const mes = parseInt(document.getElementById('mesCalendario').value);
    const año = parseInt(document.getElementById('añoCalendario').value);
    const div = document.getElementById('calendarioDiv');

    const primerDia = new Date(año, mes, 1).getDay();
    const diasEnMes = new Date(año, mes + 1, 0).getDate();

    let html = '<div class="daterange-calendar-header">';
    ['D', 'L', 'M', 'M', 'J', 'V', 'S'].forEach(d => html += `<div>${d}</div>`);
    html += '</div><div class="daterange-calendar-days">';

    for (let i = 0; i < primerDia; i++) html += '<div class="daterange-calendar-day empty"></div>';

    for (let d = 1; d <= diasEnMes; d++) {
        const fechaStr = `${año}-${String(mes + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        const clases = obtenerClasesFecha(fechaStr, columna);
        html += `<div class="daterange-calendar-day ${clases}" onclick="seleccionarFecha('${fechaStr}', '${columna}')">${d}</div>`;
    }
    html += '</div>';
    div.innerHTML = html;
}

function obtenerClasesFecha(fecha, columna) {
    if (!filtrosActivos[columna]) return '';
    const { desde, hasta } = filtrosActivos[columna];
    if (fecha === desde || fecha === hasta) return 'selected';
    if (desde && hasta && fecha > desde && fecha < hasta) return 'in-range';
    return '';
}

function seleccionarFecha(fecha, columna) {
    if (!filtrosActivos[columna]) filtrosActivos[columna] = { desde: null, hasta: null };

    const f = filtrosActivos[columna];
    if (!f.desde || (f.desde && f.hasta)) {
        f.desde = fecha;
        f.hasta = null;
    } else {
        if (fecha < f.desde) {
            f.hasta = f.desde;
            f.desde = fecha;
        } else {
            f.hasta = fecha;
        }
        paginaActual = 1;
        cargarDatos();
    }
    actualizarCalendario(columna);
}

// ========================================
// OTROS
// ========================================

function formatearFecha(fecha) {
    if (!fecha) return '-';
    const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    const d = new Date(fecha);
    return `${d.getDate().toString().padStart(2, '0')}/${meses[d.getMonth()]}/${d.getFullYear().toString().slice(-2)}`;
}

function verDetalle(id) {
    window.location.href = `postulacion_detalle_requisicion.php?id=${id}`;
}

function editarRequisicion(id) {
    window.location.href = `postulacion_requisicion_nueva.php?id=${id}`;
}

function renderizarPaginacion(totalRegistros) {
    const totalPaginas = Math.ceil(totalRegistros / registrosPorPagina);
    const paginacionDiv = document.getElementById('paginacion');

    if (totalPaginas <= 1) {
        paginacionDiv.innerHTML = '';
        return;
    }

    let html = '<nav><ul class="pagination pagination-sm mb-0">';

    // Anterior
    html += `<li class="page-item ${paginaActual === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="cambiarPagina(${paginaActual - 1}); return false;">Anterior</a>
             </li>`;

    // Páginas
    for (let i = 1; i <= totalPaginas; i++) {
        if (i === 1 || i === totalPaginas || (i >= paginaActual - 1 && i <= paginaActual + 1)) {
            html += `<li class="page-item ${i === paginaActual ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="cambiarPagina(${i}); return false;">${i}</a>
                     </li>`;
        } else if (i === paginaActual - 2 || i === paginaActual + 2) {
            html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    // Siguiente
    html += `<li class="page-item ${paginaActual === totalPaginas ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="cambiarPagina(${paginaActual + 1}); return false;">Siguiente</a>
             </li>`;

    html += '</ul></nav>';
    paginacionDiv.innerHTML = html;
}

function cambiarPagina(nuevaPagina) {
    paginaActual = nuevaPagina;
    cargarDatos();
}

function cambiarRegistrosPorPagina() {
    registrosPorPagina = parseInt(document.getElementById('registrosPorPagina').value);
    paginaActual = 1;
    cargarDatos();
}
