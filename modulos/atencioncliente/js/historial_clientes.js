//historial_clientes.js
let paginaActual = 1;
let registrosPorPagina = 25;
let filtrosActivos = {};
let ordenActivo = { columna: null, direccion: 'asc' };
let panelFiltroAbierto = null;
let totalRegistros = 0;
let calendarioActivo = null;

// Inicializar
$(document).ready(function () {
    cargarDatos();

    $(document).on('click', function (e) {
        if (!$(e.target).closest('.filter-panel, .filter-icon').length) {
            cerrarTodosFiltros();
        }
    });

    $('.table-responsive').on('scroll', function () {
        cerrarTodosFiltros();
    });

    $(window).on('scroll', function () {
        cerrarTodosFiltros();
    });

    $(window).on('resize', function () {
        if (panelFiltroAbierto) {
            cerrarTodosFiltros();
        }
    });
});

// Cargar datos
function cargarDatos() {
    $.ajax({
        url: 'ajax/clientes_get_datos.php',
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
                totalRegistros = response.total_registros;
                renderizarTabla(response.datos);
                renderizarPaginacion(response.total_registros);
                actualizarIndicadoresFiltros();
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function () {
            alert('Error al cargar los datos');
        }
    });
}

// Renderizar tabla
function renderizarTabla(datos) {
    const tbody = $('#tablaClientesBody');
    tbody.empty();

    if (datos.length === 0) {
        tbody.append('<tr><td colspan="10" class="text-center py-4">No se encontraron registros</td></tr>');
        return;
    }

    datos.forEach(row => {
        const tr = $('<tr>');

        tr.append(`<td>${row.membresia || '-'}</td>`);
        tr.append(`<td>${row.nombre || '-'}</td>`);
        tr.append(`<td>${row.apellido || '-'}</td>`);
        tr.append(`<td>${row.celular || '-'}</td>`);
        tr.append(`<td>${formatearFecha(row.fecha_nacimiento)}</td>`);
        tr.append(`<td>${row.correo || '-'}</td>`);
        tr.append(`<td>${formatearFecha(row.fecha_registro)}</td>`);
        tr.append(`<td>${formatearFecha(row.ultima_compra)}</td>`);
        tr.append(`<td>${row.nombre_sucursal || '-'}</td>`);

        // Botón de acciones
        const btnAcciones = `
            <button class="btn-accion" onclick="verHistorialProductos('${row.membresia}')">
                <i class="bi bi-eye"></i> VER
            </button>
        `;
        tr.append(`<td>${btnAcciones}</td>`);

        tbody.append(tr);
    });
}

// Ver historial de productos
function verHistorialProductos(membresia) {
    window.location.href = 'historial_productos.php?membresia=' + encodeURIComponent(membresia);
}

// Toggle filtro
function toggleFilter(icon) {
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
    actualizarIndicadoresFiltros();
}

// Crear panel de filtro
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

    // Botón Limpiar
    panel.append(`
        <div class="filter-actions">
            <button class="filter-action-btn clear" onclick="limpiarFiltro('${columna}')">
                <i class="bi bi-x-circle"></i> Limpiar Filtros
            </button>
        </div>
    `);

    // Filtros según tipo (después del botón limpiar)
    if (tipo === 'text') {
        const valorActual = filtrosActivos[columna] || '';
        panel.append(`
            <div class="filter-section">
                <span class="filter-section-title">Buscar:</span>
                <input type="text" class="filter-search" placeholder="Escribir..." 
                       value="${valorActual}"
                       oninput="filtrarBusqueda('${columna}', this.value)">
            </div>
        `);
    } else if (tipo === 'list') {
        cargarOpcionesFiltro(panel, columna);
    } else if (tipo === 'daterange') {
        crearCalendarioDoble(panel, columna);
    }

    $('body').append(panel);
    posicionarPanelFiltro(panel, icon);
}

// Crear calendario para rango de fechas
function crearCalendarioDoble(panel, columna) {
    const fechaDesdeValue = filtrosActivos[columna]?.desde || '';
    const fechaHastaValue = filtrosActivos[columna]?.hasta || '';

    const hoy = new Date();
    const mesActual = hoy.getMonth();
    const añoActual = hoy.getFullYear();

    panel.append(`
        <div class="filter-section">
            <span class="filter-section-title">Seleccionar Rango:</span>
            <div class="daterange-calendar-container">
                <div class="daterange-month-selector">
                    <select id="mesCalendario" onchange="actualizarCalendario('rango', '${columna}')"></select>
                    <select id="añoCalendario" onchange="actualizarCalendario('rango', '${columna}')"></select>
                </div>
                <div class="daterange-calendar" id="calendarioRango"></div>
            </div>
            <div class="daterange-info mt-2" style="font-size: 0.8rem; color: #666;">
                <i class="bi bi-info-circle"></i> Haz clic en dos fechas para definir el rango.
            </div>
        </div>
    `);

    setTimeout(() => {
        const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

        const selectMes = $('#mesCalendario');
        const selectAño = $('#añoCalendario');

        meses.forEach((mes, idx) => {
            selectMes.append(`<option value="${idx}" ${idx === mesActual ? 'selected' : ''}>${mes}</option>`);
        });

        for (let año = añoActual - 10; año <= añoActual + 1; año++) {
            selectAño.append(`<option value="${año}" ${año === añoActual ? 'selected' : ''}>${año}</option>`);
        }

        actualizarCalendario('rango', columna);
    }, 50);
}

// Actualizar calendario
function actualizarCalendario(tipo, columna) {
    const mes = parseInt($('#mesCalendario').val());
    const año = parseInt($('#añoCalendario').val());
    const calendarioId = '#calendarioRango';

    const primerDia = new Date(año, mes, 1).getDay();
    const diasEnMes = new Date(año, mes + 1, 0).getDate();

    const diasSemana = ['D', 'L', 'M', 'M', 'J', 'V', 'S'];
    let html = '<div class="daterange-calendar-header">';
    diasSemana.forEach(dia => {
        html += `<div class="daterange-calendar-day-name">${dia}</div>`;
    });
    html += '</div><div class="daterange-calendar-days">';

    // Días vacíos al inicio
    for (let i = 0; i < primerDia; i++) {
        html += '<div class="daterange-calendar-day empty"></div>';
    }

    // Días del mes
    for (let dia = 1; dia <= diasEnMes; dia++) {
        const fechaStr = `${año}-${String(mes + 1).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
        const clases = obtenerClasesCalendario(fechaStr, columna);
        html += `<div class="daterange-calendar-day ${clases}" onclick="seleccionarFecha('rango', '${fechaStr}', '${columna}')">${dia}</div>`;
    }

    html += '</div>';
    $(calendarioId).html(html);
}

// Obtener clases para el día del calendario
function obtenerClasesCalendario(fecha, columna) {
    const fDesde = filtrosActivos[columna]?.desde;
    const fHasta = filtrosActivos[columna]?.hasta;
    let clases = [];

    if (fDesde && fecha === fDesde) clases.push('selected');
    if (fHasta && fecha === fHasta) clases.push('selected');

    if (fDesde && fHasta) {
        if (fecha > fDesde && fecha < fHasta) {
            clases.push('in-range');
        }
    }
    return clases.join(' ');
}

// Seleccionar fecha con lógica de dos clics
function seleccionarFecha(tipo, fecha, columna) {
    if (!filtrosActivos[columna]) {
        filtrosActivos[columna] = { desde: null, hasta: null };
    }

    if (!filtrosActivos[columna].desde || (filtrosActivos[columna].desde && filtrosActivos[columna].hasta)) {
        // Primer clic (o reinicio si ya había un rango completo)
        filtrosActivos[columna].desde = fecha;
        filtrosActivos[columna].hasta = null;
    } else {
        // Segundo clic
        let f1 = filtrosActivos[columna].desde;
        let f2 = fecha;

        if (f2 < f1) {
            // Swap if second date is earlier
            filtrosActivos[columna].desde = f2;
            filtrosActivos[columna].hasta = f1;
        } else {
            filtrosActivos[columna].hasta = f2;
        }

        // Aplicar filtro
        paginaActual = 1;
        cargarDatos();
        cerrarTodosFiltros();
    }

    actualizarCalendario('rango', columna);
}

// Cargar opciones de filtro
function cargarOpcionesFiltro(panel, columna) {
    $.ajax({
        url: 'ajax/clientes_get_opciones_filtro.php',
        method: 'POST',
        data: { columna: columna },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                let html = '<div class="filter-section">';
                html += '<span class="filter-section-title">Filtrar por:</span>';
                html += '<input type="text" class="filter-search" placeholder="Buscar..." onkeyup="buscarEnOpciones(this)">';
                html += '<div class="filter-options">';

                response.opciones.forEach(opcion => {
                    const checked = filtrosActivos[columna] && filtrosActivos[columna].includes(opcion.valor) ? 'checked' : '';
                    html += `
                        <div class="filter-option">
                            <input type="checkbox" value="${opcion.valor}" ${checked}
                                   onchange="toggleOpcionFiltro('${columna}', '${opcion.valor}', this.checked)">
                            <span>${opcion.texto}</span>
                        </div>
                    `;
                });

                html += '</div></div>';
                panel.append(html);
            }
        }
    });
}

// Posicionar panel
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
    if (top + panelHeight > windowHeight + scrollTop) {
        top = iconOffset.top - panelHeight - 5;
    }
    if (top < scrollTop + 10) {
        top = scrollTop + 10;
        panel.css('max-height', (windowHeight - 60) + 'px');
    }

    panel.css({ top: top + 'px', left: left + 'px' });
}

// Actualizar indicadores
function actualizarIndicadoresFiltros() {
    $('.filter-icon').removeClass('has-filter');
    Object.keys(filtrosActivos).forEach(columna => {
        const valor = filtrosActivos[columna];
        if ((Array.isArray(valor) && valor.length > 0) ||
            (!Array.isArray(valor) && typeof valor === 'object' && (valor.desde || valor.hasta)) ||
            (!Array.isArray(valor) && typeof valor !== 'object' && valor !== '')) {
            $(`th[data-column="${columna}"] .filter-icon`).addClass('has-filter');
        }
    });
}

// Limpiar filtro
function limpiarFiltro(columna) {
    delete filtrosActivos[columna];
    cerrarTodosFiltros();
    paginaActual = 1;
    cargarDatos();
}

// Cerrar filtros
function cerrarTodosFiltros() {
    $('.filter-panel').remove();
    $('.filter-icon').removeClass('active');
    panelFiltroAbierto = null;
}

// Aplicar orden
function aplicarOrden(columna, direccion) {
    ordenActivo = { columna, direccion };
    cerrarTodosFiltros();
    paginaActual = 1;
    cargarDatos();
}

// Filtrar búsqueda
function filtrarBusqueda(columna, valor) {
    if (valor.trim() === '') {
        delete filtrosActivos[columna];
    } else {
        filtrosActivos[columna] = valor;
    }
    paginaActual = 1;
    cargarDatos();
}

// Toggle opción filtro
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

// Buscar en opciones
function buscarEnOpciones(input) {
    const busqueda = input.value.toLowerCase();
    const opciones = $(input).siblings('.filter-options').find('.filter-option');
    opciones.each(function () {
        const texto = $(this).text().toLowerCase();
        $(this).toggle(texto.includes(busqueda));
    });
}

// Cambiar registros por página
function cambiarRegistrosPorPagina() {
    registrosPorPagina = parseInt($('#registrosPorPagina').val());
    paginaActual = 1;
    cargarDatos();
}

// Renderizar paginación
function renderizarPaginacion(totalRegistros) {
    const totalPaginas = Math.ceil(totalRegistros / registrosPorPagina);
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
        paginacion.append(`<button class="pagination-btn" onclick="cambiarPagina(1)">1</button>`);
        if (inicio > 2) {
            paginacion.append(`<span class="pagination-btn" disabled>...</span>`);
        }
    }

    for (let i = inicio; i <= fin; i++) {
        const activeClass = i === paginaActual ? 'active' : '';
        paginacion.append(`<button class="pagination-btn ${activeClass}" onclick="cambiarPagina(${i})">${i}</button>`);
    }

    if (fin < totalPaginas) {
        if (fin < totalPaginas - 1) {
            paginacion.append(`<span class="pagination-btn" disabled>...</span>`);
        }
        paginacion.append(`<button class="pagination-btn" onclick="cambiarPagina(${totalPaginas})">${totalPaginas}</button>`);
    }

    paginacion.append(`
        <button class="pagination-btn" onclick="cambiarPagina(${paginaActual + 1})" ${paginaActual === totalPaginas ? 'disabled' : ''}>
            <i class="bi bi-chevron-right"></i>
        </button>
    `);
}

// Cambiar página
function cambiarPagina(pagina) {
    if (pagina < 1 || pagina > Math.ceil(totalRegistros / registrosPorPagina)) return;
    paginaActual = pagina;
    cargarDatos();
}

// Formatear fecha
function formatearFecha(fecha) {
    if (!fecha || fecha === '0000-00-00') return '-';
    const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    const d = new Date(fecha + 'T00:00:00'); // Forzar interpretación como fecha local
    if (isNaN(d.getTime())) return '-';
    const año = String(d.getFullYear()).slice(-2);
    return `${String(d.getDate()).padStart(2, '0')}-${meses[d.getMonth()]}-${año}`;
}

// Exportar a Excel
function exportarExcel() {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'ajax/clientes_exportar_excel.php';

    const inputFiltros = document.createElement('input');
    inputFiltros.type = 'hidden';
    inputFiltros.name = 'filtros';
    inputFiltros.value = JSON.stringify(filtrosActivos);
    form.appendChild(inputFiltros);

    const inputOrden = document.createElement('input');
    inputOrden.type = 'hidden';
    inputOrden.name = 'orden';
    inputOrden.value = JSON.stringify(ordenActivo);
    form.appendChild(inputOrden);

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}