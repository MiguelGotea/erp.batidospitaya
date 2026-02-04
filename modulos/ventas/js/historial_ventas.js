// historial_ventas.js
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

    // Cerrar filtros solo si se hace clic fuera del panel Y del icono
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
        url: 'ajax/ventas_get_datos.php',
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
                actualizarTotales(response.totales);
            } else {
                alert('Error: ' + response.message);
                mostrarMensajeVacio('No se encontraron datos');
            }
        },
        error: function () {
            alert('Error al cargar los datos');
            mostrarMensajeVacio('Error al cargar los datos');
        }
    });
}

// Actualizar totales
function actualizarTotales(totales) {
    if (totales) {
        if (puedeVerMontos) {
            $('#totalMonto').text(parseFloat(totales.monto || 0).toFixed(1));
        }
        $('#totalProductos').text(parseInt(totales.productos || 0));
    } else {
        if (puedeVerMontos) {
            $('#totalMonto').text('0.0');
        }
        $('#totalProductos').text('0');
    }
}

// Renderizar tabla
function renderizarTabla(datos) {
    const tbody = $('#tablaVentasBody');
    tbody.empty();

    if (datos.length === 0) {
        const colspan = puedeVerMontos ? 14 : 13;
        mostrarMensajeVacio('No se encontraron registros', colspan);
        return;
    }

    datos.forEach(row => {
        const esAnulado = parseInt(row.Anulado) !== 0;
        const tr = $('<tr>');

        if (esAnulado) {
            tr.addClass('fila-anulada');
        }

        tr.append(`<td>${row.Sucursal_Nombre || '-'}</td>`);
        tr.append(`<td>${row.CodPedido || '-'}</td>`);
        tr.append(`<td>${formatearFecha(row.Fecha)}</td>`);
        tr.append(`<td>${formatearHora(row.Hora)}</td>`);

        // Membresía (CodCliente)
        const membresia = row.CodCliente && parseInt(row.CodCliente) !== 0 ? row.CodCliente : '-';
        tr.append(`<td>${membresia}</td>`);

        // Nombre Cliente
        const nombreCliente = row.NombreCliente || '-';
        tr.append(`<td>${nombreCliente}</td>`);

        tr.append(`<td>${row.DBBatidos_Nombre || '-'}</td>`);
        tr.append(`<td>${row.Medida || '-'}</td>`);
        tr.append(`<td>${row.Cantidad || 0}</td>`);
        tr.append(`<td>${row.Puntos || 0}</td>`);
        tr.append(`<td>${row.Caja || '-'}</td>`);

        // Solo mostrar columna de Monto si tiene permiso
        if (puedeVerMontos) {
            tr.append(`<td>${parseFloat(row.Precio || 0).toFixed(1)}</td>`);
        }

        tr.append(`<td>${row.Modalidad || '-'}</td>`);

        const badgeAnulado = esAnulado
            ? '<span class="badge-anulado">SÍ</span>'
            : '<span class="badge-activo">NO</span>';
        tr.append(`<td>${badgeAnulado}</td>`);

        tbody.append(tr);
    });
}

// Mostrar mensaje vacío
function mostrarMensajeVacio(mensaje, colspan = null) {
    const tbody = $('#tablaVentasBody');
    if (!colspan) {
        colspan = puedeVerMontos ? 14 : 13;
    }
    tbody.html(`
        <tr>
            <td colspan="${colspan}" class="empty-state">
                <i class="bi bi-inbox"></i>
                <p>${mensaje}</p>
            </td>
        </tr>
    `);
    $('#paginacion').empty();
    actualizarTotales({ monto: 0, productos: 0 });
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
    scrollTopInicial = $(window).scrollTop(); // Guardar posición de scroll
    crearPanelFiltro(th, columna, tipo, icon);
    panelFiltroAbierto = columna;
    $(icon).addClass('active');
    actualizarIndicadoresFiltros();
}

// Crear panel de filtro
function crearPanelFiltro(th, columna, tipo, icon) {
    const panel = $('<div class="filter-panel show"></div>');

    // Agregar clase especial si es filtro de fecha
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
                    <i class="bi bi-sort-alpha-down"></i> A↑Z
                </button>
                <button class="filter-sort-btn ${ordenActivo.columna === columna && ordenActivo.direccion === 'desc' ? 'active' : ''}" 
                        onclick="aplicarOrden('${columna}', 'desc')">
                    <i class="bi bi-sort-alpha-up"></i> Z↑A
                </button>
            </div>
        </div>
    `);

    // Botones de acción después del ordenamiento
    panel.append(`
        <div class="filter-actions">
            <button class="filter-action-btn clear" onclick="limpiarFiltro('${columna}')">
                <i class="bi bi-x-circle"></i> Limpiar
            </button>
        </div>
    `);

    // Agregar al body primero
    $('body').append(panel);

    // Filtros según tipo (después de agregar al DOM)
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

// Crear calendario para rango de fechas
function crearCalendarioDoble(panel, columna) {
    const fechaDesdeValue = filtrosActivos[columna]?.desde || '';
    const fechaHastaValue = filtrosActivos[columna]?.hasta || '';

    const hoy = new Date();
    const mesActual = hoy.getMonth();
    const añoActual = hoy.getFullYear();

    panel.append(`
        <div class="filter-section" style="margin-top: 8px;">
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
        html += `<div class="daterange-calendar-day ${clases}" onclick="event.stopPropagation(); seleccionarFecha('rango', '${fechaStr}', '${columna}')">${dia}</div>`;
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

// Seleccionar fecha con lógica inteligente de actualización de rango
function seleccionarFecha(tipo, fecha, columna) {
    if (window.event) window.event.stopPropagation();

    if (!filtrosActivos[columna]) {
        filtrosActivos[columna] = { desde: null, hasta: null };
    }

    let fDesde = filtrosActivos[columna].desde;
    let fHasta = filtrosActivos[columna].hasta;

    if (!fDesde) {
        // Primer clic absoluto
        filtrosActivos[columna].desde = fecha;
    } else if (!fHasta) {
        // Segundo clic: definir el rango inicial
        if (fecha < fDesde) {
            filtrosActivos[columna].desde = fecha;
            filtrosActivos[columna].hasta = fDesde;
        } else {
            filtrosActivos[columna].hasta = fecha;
        }
    } else {
        // Tercer clic en adelante: actualizar el límite más cercano o el final si está dentro
        if (fecha < fDesde) {
            filtrosActivos[columna].desde = fecha;
        } else if (fecha > fHasta) {
            filtrosActivos[columna].hasta = fecha;
        } else {
            // Si está dentro (o es igual a uno de los límites), actualizamos el "hasta"
            filtrosActivos[columna].hasta = fecha;
        }
    }

    // Actualizar el calendario visualmente
    actualizarCalendario('rango', columna);

    // Aplicar filtro si ya tenemos un rango completo
    if (filtrosActivos[columna].desde && filtrosActivos[columna].hasta) {
        paginaActual = 1;
        cargarDatos();
        // NO cerramos el modal, como pidió el usuario
    }
}

// Cargar opciones de filtro
function cargarOpcionesFiltro(panel, columna, icon) {
    $.ajax({
        url: 'ajax/ventas_get_opciones_filtro.php',
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

                // Posicionar después de agregar el contenido
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

    // Intentar posicionar debajo del icono
    let top = iconOffset.top + iconHeight + 5;
    let left = iconOffset.left - panelWidth + iconWidth;

    // Ajustar horizontalmente si se sale de la pantalla
    if (left + panelWidth > windowWidth) {
        left = windowWidth - panelWidth - 10;
    }
    if (left < 10) {
        left = 10;
    }

    // Verificar si cabe debajo del icono
    const espacioAbajo = windowHeight + scrollTop - top;
    const espacioArriba = iconOffset.top - scrollTop;

    // Si no cabe abajo pero sí arriba, posicionar arriba
    if (espacioAbajo < panelHeight && espacioArriba > panelHeight) {
        top = iconOffset.top - panelHeight - 5;
    }

    // Si no cabe en ningún lado, ajustar al espacio disponible
    if (top + panelHeight > windowHeight + scrollTop) {
        top = Math.max(scrollTop + 10, windowHeight + scrollTop - panelHeight - 10);
    }

    if (top < scrollTop + 10) {
        top = scrollTop + 10;
    }

    panel.css({
        top: top + 'px',
        left: left + 'px',
        maxHeight: Math.min(windowHeight - 100, panelHeight) + 'px'
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
    // NO cerrar el filtro automáticamente
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
    // NO cerrar el filtro automáticamente
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
    if (!fecha) return '-';
    const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    const d = new Date(fecha);
    const año = String(d.getFullYear()).slice(-2);
    return `${String(d.getDate()).padStart(2, '0')}-${meses[d.getMonth()]}-${año}`;
}
// Formatear hora
function formatearHora(hora) {
    if (!hora) return '-';
    return hora.substring(0, 5);
}
