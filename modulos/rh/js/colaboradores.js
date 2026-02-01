let paginaActual = 1;
let registrosPorPagina = 25;
let filtrosActivos = {};
let ordenActivo = { columna: null, direccion: 'asc' };
let panelFiltroAbierto = null;
let totalRegistros = 0;
let scrollTopInicial = 0;

// Inicializar
$(document).ready(function () {
    cargarDatos();

    // Cerrar filtros al hacer clic fuera
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.filter-panel, .filter-icon').length) {
            cerrarTodosFiltros();
        }
    });

    // NO cerrar filtros al hacer scroll en la tabla
    $('.table-responsive').on('scroll', function (e) {
        e.stopPropagation();
    });

    // NO cerrar filtros al hacer scroll en la página
    $(window).on('scroll', function (e) {
        // Solo cerrar si el scroll es significativo (más de 50px desde que se abrió)
        if (panelFiltroAbierto && Math.abs($(window).scrollTop() - scrollTopInicial) > 50) {
            cerrarTodosFiltros();
        }
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
        url: 'ajax/colaboradores_get_datos.php',
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
    const tbody = $('#tablaColaboradoresBody');
    tbody.empty();

    if (datos.length === 0) {
        tbody.append('<tr><td colspan="12" class="text-center py-4">No se encontraron registros</td></tr>');
        return;
    }

    datos.forEach(row => {
        const tr = $('<tr>');

        // Código
        tr.append(`<td>${row.CodOperario}</td>`);

        // Nombre completo
        tr.append(`<td>${row.nombre_completo}</td>`);

        // Cargo
        tr.append(`<td>${row.cargo_nombre}</td>`);

        // Teléfonos
        const telefonoPersonal = row.Celular || '-';
        const telefonoCorporativo = row.telefono_corporativo || '-';
        tr.append(`
            <td>
                <div style="font-size: 12px !important; text-align: center;">
                    <div style="margin-bottom: 5px;">
                        <i class="fas fa-mobile-alt" style="color: #0E544C; margin-right: 5px;" title="Teléfono Personal"></i>
                        ${telefonoPersonal !== '-' ? telefonoPersonal : '<span style="color: #999;">-</span>'}
                    </div>
                    <div>
                        <i class="fas fa-building" style="color: #51B8AC; margin-right: 5px;" title="Teléfono Corporativo"></i>
                        ${telefonoCorporativo !== '-' ? telefonoCorporativo : '<span style="color: #999;">-</span>'}
                    </div>
                </div>
            </td>
        `);

        // Estado
        const estadoClass = row.Operativo == 1 ? 'status-activo' : 'status-inactivo';
        const estadoTexto = row.Operativo == 1 ? 'Activo' : 'Inactivo';
        tr.append(`<td><span class="${estadoClass}">${estadoTexto}</span></td>`);

        // Tienda/Área
        tr.append(`<td>${row.nombre_sucursal || '-'}</td>`);

        // Inicio Contrato
        tr.append(`<td>${formatearFecha(row.fecha_inicio_ultimo_contrato)}</td>`);

        // Fin Contrato
        const fechaFinTexto = row.fecha_fin_display || formatearFecha(row.fecha_fin_ultimo_contrato);
        tr.append(`<td>${fechaFinTexto}</td>`);

        // Tiempo Trabajado
        tr.append(`<td>${row.tiempo_trabajado_texto || '-'}</td>`);

        // Última Día Laborado
        tr.append(`<td>${formatearFecha(row.ultima_fecha_laborada)}</td>`);

        // Tiempo Restante (con HTML de estado)
        tr.append(`<td>${row.tiempo_restante_html || '-'}</td>`);

        // Acciones
        tr.append(`
            <td>
                <button class="btn-editar" onclick="editarColaborador(${row.CodOperario})">
                    <i class="fas fa-edit"></i>
                </button>
            </td>
        `);

        tbody.append(tr);
    });
}

// Editar colaborador
function editarColaborador(codOperario) {
    window.location.href = 'editar_colaborador.php?id=' + codOperario;
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
    scrollTopInicial = $(window).scrollTop();
    crearPanelFiltro(th, columna, tipo, icon);
    panelFiltroAbierto = columna;
    $(icon).addClass('active');
    actualizarIndicadoresFiltros();
}

// Crear panel de filtro
function crearPanelFiltro(th, columna, tipo, icon) {
    const panel = $('<div class="filter-panel show"></div>');

    if (tipo === 'daterange') {
        panel.addClass('has-daterange');
    }

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

    // Botón limpiar
    panel.append(`
        <div class="filter-actions">
            <button class="filter-action-btn clear" onclick="limpiarFiltro('${columna}')">
                <i class="bi bi-x-circle"></i> Limpiar
            </button>
        </div>
    `);

    $('body').append(panel);

    // Agregar filtros según tipo
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
    } else if (tipo === 'list') {
        cargarOpcionesFiltro(panel, columna, icon);
    } else if (tipo === 'daterange') {
        crearCalendarioDoble(panel, columna);
        posicionarPanelFiltro(panel, icon);
    }
}

// Crear calendario único para rango de fechas
function crearCalendarioDoble(panel, columna) {
    const fechaDesde = filtrosActivos[columna]?.desde || '';
    const fechaHasta = filtrosActivos[columna]?.hasta || '';

    const hoy = new Date();
    const mesActual = hoy.getMonth();
    const añoActual = hoy.getFullYear();

    // Determinar mes inicial basado en fechas seleccionadas
    let mesInicial = mesActual;
    let añoInicial = añoActual;

    if (fechaDesde) {
        const d = new Date(fechaDesde);
        mesInicial = d.getMonth();
        añoInicial = d.getFullYear();
    }

    panel.append(`
        <div class="filter-section" style="margin-top: 8px; margin-bottom: 6px;">
            <span class="filter-section-title">Seleccionar rango:</span>
            <div class="daterange-inputs">
                <div class="daterange-calendar-container">
                    <div class="daterange-month-selector">
                        <select id="mesCalendario" onchange="actualizarCalendarioUnico('${columna}')"></select>
                        <select id="añoCalendario" onchange="actualizarCalendarioUnico('${columna}')"></select>
                    </div>
                    <div class="daterange-calendar" id="calendarioUnico"></div>
                </div>
            </div>
        </div>
    `);

    setTimeout(() => {
        inicializarSelectoresFechaUnico(mesInicial, añoInicial);
        actualizarCalendarioUnico(columna);
    }, 50);
}

// Inicializar selectores de fecha para calendario único
function inicializarSelectoresFechaUnico(mesInicial, añoInicial) {
    const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    // Llenar selector de mes
    const selectMes = $('#mesCalendario');
    meses.forEach((mes, idx) => {
        selectMes.append(`<option value="${idx}" ${idx === mesInicial ? 'selected' : ''}>${mes}</option>`);
    });

    // Llenar selector de año
    const selectAño = $('#añoCalendario');
    const añoActual = new Date().getFullYear();
    for (let año = añoActual - 10; año <= añoActual + 1; año++) {
        selectAño.append(`<option value="${año}" ${año === añoInicial ? 'selected' : ''}>${año}</option>`);
    }
}

// Actualizar calendario único
function actualizarCalendarioUnico(columna) {
    const mes = parseInt($('#mesCalendario').val());
    const año = parseInt($('#añoCalendario').val());
    const calendarioId = '#calendarioUnico';


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
        html += `<div class="daterange-calendar-day ${clases}" onclick="event.stopPropagation(); seleccionarFechaUnico('${fechaStr}', '${columna}')">${dia}</div>`;
    }

    html += '</div>';
    $(calendarioId).html(html);
}

// Seleccionar fecha en calendario único con lógica inteligente
function seleccionarFechaUnico(fecha, columna) {
    // Detener propagación del evento
    if (window.event) {
        window.event.stopPropagation();
    }

    if (!filtrosActivos[columna]) {
        filtrosActivos[columna] = {};
    }

    const fechaDesde = filtrosActivos[columna].desde;
    const fechaHasta = filtrosActivos[columna].hasta;

    // Lógica inteligente de selección:
    // 1. Si no hay fechas seleccionadas, esta es la fecha "desde"
    if (!fechaDesde && !fechaHasta) {
        filtrosActivos[columna].desde = fecha;
    }
    // 2. Si solo hay "desde", determinar si esta es "hasta" o nueva "desde"
    else if (fechaDesde && !fechaHasta) {
        if (fecha >= fechaDesde) {
            // La fecha es mayor o igual, se convierte en "hasta"
            filtrosActivos[columna].hasta = fecha;
        } else {
            // La fecha es menor, se convierte en nueva "desde"
            filtrosActivos[columna].desde = fecha;
        }
    }
    // 3. Si ambas fechas están seleccionadas
    else if (fechaDesde && fechaHasta) {
        if (fecha < fechaDesde) {
            // La fecha es menor que "desde", se convierte en nueva "desde"
            filtrosActivos[columna].desde = fecha;
        } else if (fecha > fechaHasta) {
            // La fecha es mayor que "hasta", se convierte en nueva "hasta"
            filtrosActivos[columna].hasta = fecha;
        } else {
            // La fecha está dentro del rango, reiniciar selección
            filtrosActivos[columna] = { desde: fecha };
        }
    }

    // Actualizar el calendario para mostrar el rango
    actualizarCalendarioUnico(columna);

    // Aplicar filtro cuando ambas fechas están seleccionadas
    if (filtrosActivos[columna].desde && filtrosActivos[columna].hasta) {
        paginaActual = 1;
        cargarDatos();
    }
}

// Obtener clases para días del calendario
function obtenerClasesCalendario(fecha, columna) {
    const fechaDesde = filtrosActivos[columna]?.desde;
    const fechaHasta = filtrosActivos[columna]?.hasta;

    let clases = [];

    if (fecha === fechaDesde || fecha === fechaHasta) {
        clases.push('selected');
    } else if (fechaDesde && fechaHasta) {
        if (fecha > fechaDesde && fecha < fechaHasta) {
            clases.push('in-range');
        }
    }

    return clases.join(' ');
}
function cargarOpcionesFiltro(panel, columna, icon) {
    $.ajax({
        url: 'ajax/colaboradores_get_opciones_filtro.php',
        method: 'POST',
        data: { columna: columna },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                let html = '<div class="filter-section" style="margin-top: 12px;">';
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

                posicionarPanelFiltro(panel, icon);
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

    panel.css({
        top: top + 'px',
        left: left + 'px'
    });
}

// Actualizar indicadores
function actualizarIndicadoresFiltros() {
    $('.filter-icon').removeClass('has-filter');
    Object.keys(filtrosActivos).forEach(columna => {
        const valor = filtrosActivos[columna];
        if ((Array.isArray(valor) && valor.length > 0) ||
            (!Array.isArray(valor) && typeof valor === 'object' && Object.keys(valor).length > 0) ||
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

// Buscar en opciones
function buscarEnOpciones(input) {
    const busqueda = input.value.toLowerCase();
    const opciones = $(input).siblings('.filter-options').find('.filter-option');
    opciones.each(function () {
        const texto = $(this).text().toLowerCase();
        $(this).toggle(texto.includes(busqueda));
    });
}

// Formatear fecha
function formatearFecha(fecha) {
    if (!fecha || fecha === '0000-000-00') return '-';
    const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    const d = new Date(fecha);
    const año = String(d.getFullYear()).slice(-2);
    return `${String(d.getDate()).padStart(2, '0')}-${meses[d.getMonth()]}-${año}`;
}

// Formatear fecha larga para mostrar en el rango seleccionado
function formatearFechaLarga(fecha) {
    if (!fecha) return '-';
    const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    const d = new Date(fecha);
    return `${String(d.getDate()).padStart(2, '0')} ${meses[d.getMonth()]} ${d.getFullYear()}`;
}