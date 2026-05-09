// Variables para el manejo de la tabla y filtros
let paginaActual = 1;
let registrosPorPagina = 25;
let filtrosActivos = {};
let ordenActivo = { columna: null, direccion: 'asc' };
let panelFiltroAbierto = null;
let totalRegistros = 0;
let scrollTopInicial = 0;

// Variables para manejar el estado de edición de feriados
let editandoObservacionesFeriado = {};
let observacionesOriginalesFeriado = {};

/**
 * Inicializa los eventos del DOM cuando el contenido esté cargado
 */
document.addEventListener('DOMContentLoaded', function () {
    // Cargar datos iniciales si estamos en la página de feriados
    if (document.getElementById('tablaFeriadosBody')) {
        // Inicializar filtros base desde los select/input superiores
        const sucursal = document.getElementById('sucursal')?.value;
        const operario = document.getElementById('operario_id')?.value;
        const desde = document.getElementById('desde')?.value;
        const hasta = document.getElementById('hasta')?.value;

        filtrosActivos['fecha_base'] = { desde, hasta };
        if (sucursal) filtrosActivos['sucursal_id'] = sucursal;
        if (operario && operario != '0') filtrosActivos['operario_id'] = operario;

        cargarDatos();
    }

    // Cerrar filtros solo si se hace clic fuera del panel Y del icono
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.filter-panel, .filter-icon').length) {
            cerrarTodosFiltros();
        }
    });

    // Manejar el input de operario (Autocompletado)
    const operarioInput = document.getElementById('operario');
    const operarioIdInput = document.getElementById('operario_id');
    const sugerenciasDiv = document.getElementById('operarios-sugerencias');

    if (operarioInput && sugerenciasDiv) {
        operarioInput.addEventListener('input', function () {
            const texto = this.value.trim();
            if (texto === '') {
                if (operarioIdInput) operarioIdInput.value = '0';
                sugerenciasDiv.style.display = 'none';
                return;
            }
            const resultados = buscarOperarios(texto);
            sugerenciasDiv.innerHTML = '';
            if (resultados.length > 0) {
                resultados.forEach(op => {
                    const div = document.createElement('div');
                    div.textContent = op.nombre;
                    div.style.padding = '8px';
                    div.style.cursor = 'pointer';
                    div.addEventListener('click', function () {
                        operarioInput.value = op.nombre;
                        if (operarioIdInput) operarioIdInput.value = op.id;
                        sugerenciasDiv.style.display = 'none';
                        actualizarFiltros();
                    });
                    div.addEventListener('mouseover', function () { this.style.backgroundColor = '#f5f5f5'; });
                    div.addEventListener('mouseout', function () { this.style.backgroundColor = 'white'; });
                    sugerenciasDiv.appendChild(div);
                });
                sugerenciasDiv.style.display = 'block';
            } else {
                sugerenciasDiv.style.display = 'none';
            }
        });

        document.addEventListener('click', function (e) {
            if (e.target !== operarioInput) {
                sugerenciasDiv.style.display = 'none';
            }
        });
    }

    // Cerrar modal al hacer clic fuera
    window.addEventListener('click', function (event) {
        const modal = document.getElementById('modalAprobacion');
        if (event.target === modal) {
            cerrarModal();
        }
    });

    $(window).on('resize', function () {
        if (panelFiltroAbierto) {
            cerrarTodosFiltros();
        }
    });
});

/**
 * Cargar datos vía AJAX
 */
function cargarDatos() {
    // Actualizar filtros base antes de cargar
    const desde = document.getElementById('desde')?.value;
    const hasta = document.getElementById('hasta')?.value;
    filtrosActivos['fecha_base'] = { desde, hasta };

    $.ajax({
        url: 'ajax/feriados_get_datos.php',
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
                console.error('Error:', response.message);
            }
        },
        error: function () {
            console.error('Error al cargar los datos');
        }
    });
}

/**
 * Renderizar la tabla de feriados
 */
function renderizarTabla(datos) {
    const tbody = $('#tablaFeriadosBody');
    tbody.empty();

    if (datos.length === 0) {
        tbody.append('<tr><td colspan="10" class="text-center py-4">No se encontraron registros</td></tr>');
        return;
    }

    datos.forEach(row => {
        const id_fila = row.id_aprobacion || `temp_${row.cod_operario}_${row.fecha}`;
        const tr = $('<tr>').attr('id', `feriado-row-${id_fila}`);

        tr.append(`<td>${row.nombre_operario}</td>`);
        tr.append(`<td>${row.sucursal_nombre}</td>`);
        tr.append(`<td class="text-nowrap">${formatearFecha(row.inicio_contrato)}</td>`);
        tr.append(`<td>${formatearFecha(row.fecha)}</td>`);
        tr.append(`<td>${row.feriado_nombre}</td>`);
        
        let tipoDept = row.feriado_tipo;
        if (row.feriado_tipo === 'Departamental') {
            tipoDept += ` (${row.departamento_nombre})`;
        }
        tr.append(`<td>${tipoDept}</td>`);
        
        // Status column
        let statusHtml = '';
        if (row.estado === 'Con Marcación') {
            const hIngreso = row.hora_ingreso ? row.hora_ingreso.substring(0, 5) : '--:--';
            const hSalida = row.hora_salida ? row.hora_salida.substring(0, 5) : '--:--';
            statusHtml = `
                <span class="status-badge status-con-marcacion" id="status-badge-${id_fila}">
                    <i class="bi bi-clock"></i> ${hIngreso} - ${hSalida}
                </span>
            `;
        } else {
            const statusClass = row.estado.toLowerCase().replace(/ /g, '-');
            statusHtml = `
                <span class="status-badge status-${statusClass}" id="status-badge-${id_fila}">
                    ${row.estado}
                </span>
            `;
        }
        tr.append(`<td>${statusHtml}</td>`);

        // Observaciones column
        const yaTieneDecision = !!row.id_aprobacion;
        const puedeAprobar = window.puedeAprobarPermiso || false; // Asumimos que esta variable se setea en el PHP
        const puedeEditarObs = puedeAprobar && yaTieneDecision;
        const obsText = row.observaciones || '';
        
        tr.append(`
            <td>
                <div class="observaciones-cell ${puedeEditarObs ? 'editable' : ''}" 
                     id="obs-display-${id_fila}" 
                     ${puedeEditarObs ? `onclick="toggleEditObservacionesFeriado('${id_fila}')"` : ''}
                     title="${puedeEditarObs ? 'Click para editar' : ''}">
                    ${obsText ? obsText.replace(/\n/g, '<br>') : '<span class="text-muted">Sin observaciones</span>'}
                </div>
                <textarea id="obs-edit-${id_fila}" class="observaciones-edit" style="display: none;" 
                          onblur="guardarObservacionesFeriado('${id_fila}', '${row.cod_operario}', '${row.fecha}')"
                          onkeyup="manejarTeclasObservaciones(event, '${id_fila}', '${row.cod_operario}', '${row.fecha}')"
                          rows="3">${obsText}</textarea>
            </td>
        `);

        // Acciones column
        if (puedeAprobar) {
            let actionsHtml = `<div class="action-buttons-inline" id="actions-${id_fila}">`;
            if (row.estado === 'Pendiente' || row.estado === 'Sin marcación' || row.estado === 'Con Marcación') {
                actionsHtml += `
                    <button type="button" class="btn-action btn-approve" 
                            onclick="actualizarEstadoFeriado('${id_fila}', 'Pagado', '${row.cod_operario}', '${row.fecha}')" title="Marcar como Pagado">
                        <i class="fas fa-dollar-sign"></i>
                    </button>
                    <button type="button" class="btn-action btn-compensado" 
                            onclick="actualizarEstadoFeriado('${id_fila}', 'Descansado', '${row.cod_operario}', '${row.fecha}')" title="Marcar como Compensado/Descansado">
                        <i class="fas fa-bed"></i>
                    </button>
                `;
            } else {
                actionsHtml += `
                    <button type="button" class="btn-action btn-change" 
                            onclick="cambiarEstadoFeriado('${id_fila}', '${row.estado}', '${row.cod_operario}', '${row.fecha}')" title="Cambiar estado">
                        <i class="fas fa-exchange-alt"></i>
                    </button>
                `;
            }
            actionsHtml += `</div>`;
            tr.append(`<td style="text-align: center;">${actionsHtml}</td>`);
        }

        tbody.append(tr);
    });
}

/**
 * Toggle filtro (Header filters)
 */
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

/**
 * Crear panel de filtro
 */
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

    panel.append(`
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

function filtrarBusqueda(columna, valor) {
    if (valor.trim() === '') {
        delete filtrosActivos[columna];
    } else {
        filtrosActivos[columna] = valor;
    }
    paginaActual = 1;
    cargarDatos();
}

function cargarOpcionesFiltro(panel, columna, icon) {
    $.ajax({
        url: 'ajax/feriados_get_opciones_filtro.php',
        method: 'POST',
        data: { columna: columna },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                let html = '<div class="filter-section" style="margin-top: 12px;">';
                html += '<span class="filter-section-title">Filtrar por:</span>';
                html += '<input type="text" class="filter-search" placeholder="Buscar..." onkeyup="buscarEnOpciones(this)">';
                html += '<div class="filter-options">';

                const columnaList = columna + '_list';
                response.opciones.forEach(opcion => {
                    const checked = filtrosActivos[columnaList] && filtrosActivos[columnaList].includes(opcion.valor) ? 'checked' : '';
                    // Especial para 'estado' que no lleva _list
                    const realCol = (columna === 'estado' || columna === 'feriado_tipo') ? columna : columnaList;
                    const isChecked = filtrosActivos[realCol] && filtrosActivos[realCol].includes(opcion.valor) ? 'checked' : '';
                    
                    html += `
                        <div class="filter-option">
                            <input type="checkbox" value="${opcion.valor}" ${isChecked}
                                   onchange="toggleOpcionFiltro('${realCol}', '${opcion.valor}', this.checked)">
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

/**
 * Calendario para rango de fechas (Copiado de cupones.js)
 */
function crearCalendarioDoble(panel, columna) {
    const hoy = new Date();
    const mesActual = hoy.getMonth();
    const añoActual = hoy.getFullYear();

    panel.append(`
        <div class="filter-section" style="margin-top: 8px;">
            <span class="filter-section-title">Seleccionar Rango:</span>
            <div class="daterange-calendar-container">
                <div class="daterange-month-selector">
                    <select id="mesCalendario" onchange="actualizarCalendarioUnico('${columna}')"></select>
                    <select id="añoCalendario" onchange="actualizarCalendarioUnico('${columna}')"></select>
                </div>
                <div class="daterange-calendar" id="calendarioUnico"></div>
            </div>
        </div>
    `);

    setTimeout(() => {
        const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        const selectMes = $('#mesCalendario');
        const selectAño = $('#añoCalendario');
        meses.forEach((mes, idx) => {
            selectMes.append(`<option value="${idx}" ${idx === mesActual ? 'selected' : ''}>${mes}</option>`);
        });
        for (let año = añoActual - 10; año <= añoActual + 1; año++) {
            selectAño.append(`<option value="${año}" ${año === añoActual ? 'selected' : ''}>${año}</option>`);
        }
        actualizarCalendarioUnico(columna);
    }, 50);
}

function actualizarCalendarioUnico(columna) {
    const mes = parseInt($('#mesCalendario').val());
    const año = parseInt($('#añoCalendario').val());
    const calendarioId = '#calendarioUnico';
    const primerDia = new Date(año, mes, 1).getDay();
    const diasEnMes = new Date(año, mes + 1, 0).getDate();
    const diasSemana = ['D', 'L', 'M', 'M', 'J', 'V', 'S'];
    
    let html = '<div class="daterange-calendar-header">';
    diasSemana.forEach(dia => { html += `<div class="daterange-calendar-day-name">${dia}</div>`; });
    html += '</div><div class="daterange-calendar-days">';

    for (let i = 0; i < primerDia; i++) {
        html += '<div class="daterange-calendar-day empty"></div>';
    }

    for (let dia = 1; dia <= diasEnMes; dia++) {
        const fechaStr = `${año}-${String(mes + 1).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
        const clases = obtenerClasesCalendario(fechaStr, columna);
        html += `<div class="daterange-calendar-day ${clases}" onclick="event.stopPropagation(); seleccionarFechaUnico('${fechaStr}', '${columna}')">${dia}</div>`;
    }
    html += '</div>';
    $(calendarioId).html(html);
}

function obtenerClasesCalendario(fecha, columna) {
    const fDesde = filtrosActivos[columna]?.desde;
    const fHasta = filtrosActivos[columna]?.hasta;
    let clases = [];
    if (fDesde && fecha === fDesde) clases.push('selected');
    if (fHasta && fecha === fHasta) clases.push('selected');
    if (fDesde && fHasta) {
        if (fecha > fDesde && fecha < fHasta) { clases.push('in-range'); }
    }
    return clases.join(' ');
}

function seleccionarFechaUnico(fecha, columna) {
    if (!filtrosActivos[columna]) {
        filtrosActivos[columna] = { desde: null, hasta: null };
    }
    let fDesde = filtrosActivos[columna].desde;
    let fHasta = filtrosActivos[columna].hasta;

    if (!fDesde) {
        filtrosActivos[columna].desde = fecha;
    } else if (!fHasta) {
        if (fecha < fDesde) {
            filtrosActivos[columna].desde = fecha;
            filtrosActivos[columna].hasta = fDesde;
        } else {
            filtrosActivos[columna].hasta = fecha;
        }
    } else {
        if (fecha < fDesde) { filtrosActivos[columna].desde = fecha; }
        else if (fecha > fHasta) { filtrosActivos[columna].hasta = fecha; }
        else { filtrosActivos[columna].hasta = fecha; }
    }
    actualizarCalendarioUnico(columna);
    if (filtrosActivos[columna].desde && filtrosActivos[columna].hasta) {
        paginaActual = 1;
        cargarDatos();
    }
}

/**
 * Posicionar el panel de filtro
 */
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

    if (left + panelWidth > windowWidth) { left = windowWidth - panelWidth - 10; }
    if (left < 10) { left = 10; }

    const espacioAbajo = windowHeight + scrollTop - top;
    const espacioArriba = iconOffset.top - scrollTop;

    if (espacioAbajo < panelHeight && espacioArriba > panelHeight) {
        top = iconOffset.top - panelHeight - 5;
    }
    if (top + panelHeight > windowHeight + scrollTop) {
        top = Math.max(scrollTop + 10, windowHeight + scrollTop - panelHeight - 10);
    }
    if (top < scrollTop + 10) { top = scrollTop + 10; }

    panel.css({ top: top + 'px', left: left + 'px' });
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

function limpiarFiltro(columna) {
    if (columna === 'sucursal_nombre') {
        delete filtrosActivos['sucursal_nombre'];
        delete filtrosActivos['sucursal_nombre_list'];
    } else {
        delete filtrosActivos[columna];
    }
    cerrarTodosFiltros();
    paginaActual = 1;
    cargarDatos();
}

function actualizarIndicadoresFiltros() {
    $('.filter-icon').removeClass('has-filter');
    Object.keys(filtrosActivos).forEach(key => {
        let col = key;
        if (key.endsWith('_list')) col = key.replace('_list', '');
        if (key === 'fecha_base') return; // Filtro base de fechas no marca indicador
        
        const valor = filtrosActivos[key];
        if ((Array.isArray(valor) && valor.length > 0) ||
            (!Array.isArray(valor) && typeof valor === 'object' && Object.keys(valor).length > 0) ||
            (!Array.isArray(valor) && typeof valor !== 'object' && valor !== '')) {
            $(`th[data-column="${col}"] .filter-icon`).addClass('has-filter');
        }
    });
}

function buscarEnOpciones(input) {
    const busqueda = input.value.toLowerCase();
    const opciones = $(input).siblings('.filter-options').find('.filter-option');
    opciones.each(function () {
        const texto = $(this).text().toLowerCase();
        $(this).toggle(texto.includes(busqueda));
    });
}

/**
 * Paginación
 */
function renderizarPaginacion(total) {
    const totalPaginas = Math.ceil(total / registrosPorPagina);
    const paginacion = $('#paginacion');
    paginacion.empty();

    if (totalPaginas <= 1) return;

    paginacion.append(`
        <button class="pagination-btn" onclick="cambiarPagina(${paginaActual - 1})" ${paginaActual === 1 ? 'disabled' : ''}>
            <i class="bi bi-chevron-left"></i>
        </button>
    `);

    let inicio = Math.max(1, paginaActual - 2);
    let fin = Math.min(totalPaginas, paginaActual + 2);

    if (inicio > 1) {
        paginacion.append(`<button class="pagination-btn" onclick="cambiarPagina(1)">1</button>`);
        if (inicio > 2) paginacion.append(`<span class="pagination-btn" disabled>...</span>`);
    }

    for (let i = inicio; i <= fin; i++) {
        paginacion.append(`<button class="pagination-btn ${i === paginaActual ? 'active' : ''}" onclick="cambiarPagina(${i})">${i}</button>`);
    }

    if (fin < totalPaginas) {
        if (fin < totalPaginas - 1) paginacion.append(`<span class="pagination-btn" disabled>...</span>`);
        paginacion.append(`<button class="pagination-btn" onclick="cambiarPagina(${totalPaginas})">${totalPaginas}</button>`);
    }

    paginacion.append(`
        <button class="pagination-btn" onclick="cambiarPagina(${paginaActual + 1})" ${paginaActual === totalPaginas ? 'disabled' : ''}>
            <i class="bi bi-chevron-right"></i>
        </button>
    `);
}

function cambiarPagina(pagina) {
    if (pagina < 1 || pagina > Math.ceil(totalRegistros / registrosPorPagina)) return;
    paginaActual = pagina;
    cargarDatos();
}

function cambiarRegistrosPorPagina() {
    registrosPorPagina = parseInt($('#registrosPorPagina').val());
    paginaActual = 1;
    cargarDatos();
}

/**
 * Funciones de Negocio (Existentes pero adaptadas)
 */
function actualizarEstadoFeriado(elementId, nuevoEstado, codOperario, fecha) {
    const confirmMessage = nuevoEstado === 'Pagado'
        ? '¿Está seguro de marcar este feriado como PAGADO? (8 horas a pagar)'
        : '¿Está seguro de marcar este feriado como DESCANSADO/COMPENSADO?';

    if (!confirm(confirmMessage)) return;

    if (elementId.startsWith('temp_')) {
        crearRegistroFeriado(elementId, nuevoEstado, codOperario, fecha);
    } else {
        actualizarRegistroFeriado(elementId, nuevoEstado);
    }
}

function crearRegistroFeriado(elementId, estado, codOperario, fecha) {
    const observaciones = document.getElementById(`obs-edit-${elementId}`)?.value || '';
    const actionsDiv = document.getElementById(`actions-${elementId}`);
    if (!actionsDiv) return;
    const originalHTML = actionsDiv.innerHTML;
    actionsDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    $.ajax({
        url: 'ajax/crear_feriado_ajax.php',
        method: 'POST',
        data: { cod_operario: codOperario, fecha_feriado: fecha, estado: estado, observaciones: observaciones },
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                mostrarNotificacion('success', data.message);
                setTimeout(() => { cargarDatos(); }, 1000);
            } else {
                actionsDiv.innerHTML = originalHTML;
                mostrarNotificacion('error', data.message);
            }
        },
        error: function() {
            actionsDiv.innerHTML = originalHTML;
            mostrarNotificacion('error', 'Error al crear el registro');
        }
    });
}

function actualizarRegistroFeriado(id, nuevoEstado) {
    const observaciones = document.getElementById(`obs-edit-${id}`)?.value || '';
    const actionsDiv = document.getElementById(`actions-${id}`);
    if (!actionsDiv) return;
    const originalHTML = actionsDiv.innerHTML;
    actionsDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    $.ajax({
        url: 'ajax/actualizar_feriado_ajax.php',
        method: 'POST',
        data: { id: id, estado: nuevoEstado, observaciones: observaciones },
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                mostrarNotificacion('success', data.message);
                cargarDatos();
            } else {
                actionsDiv.innerHTML = originalHTML;
                mostrarNotificacion('error', data.message);
            }
        },
        error: function() {
            actionsDiv.innerHTML = originalHTML;
            mostrarNotificacion('error', 'Error al actualizar');
        }
    });
}

function cambiarEstadoFeriado(id, estadoActual, codOperario, fecha) {
    const nuevoEstado = estadoActual === 'Pagado' ? 'Descansado' : 'Pagado';
    actualizarEstadoFeriado(id, nuevoEstado, codOperario, fecha);
}

function toggleEditObservacionesFeriado(id) {
    const displayDiv = document.getElementById(`obs-display-${id}`);
    const editTextarea = document.getElementById(`obs-edit-${id}`);
    if (editandoObservacionesFeriado[id]) return;
    observacionesOriginalesFeriado[id] = editTextarea ? editTextarea.value : '';
    if (displayDiv) displayDiv.style.display = 'none';
    if (editTextarea) {
        editTextarea.style.display = 'block';
        editTextarea.focus();
        const length = editTextarea.value.length;
        editTextarea.setSelectionRange(length, length);
    }
    editandoObservacionesFeriado[id] = true;
}

function manejarTeclasObservaciones(event, id, codOperario, fecha) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        event.target.blur();
    } else if (event.key === 'Escape') {
        cancelarEditObservacionesFeriado(id);
    }
}

function guardarObservacionesFeriado(id, codOperario, fecha) {
    if (!editandoObservacionesFeriado[id] || editandoObservacionesFeriado[id] === 'guardando') return;
    const editTextarea = document.getElementById(`obs-edit-${id}`);
    const nuevasObservaciones = editTextarea ? editTextarea.value.trim() : '';
    if (nuevasObservaciones === observacionesOriginalesFeriado[id]) {
        finalizarEdicionObservacionesFeriado(id);
        return;
    }
    editandoObservacionesFeriado[id] = 'guardando';
    const badge = document.getElementById(`status-badge-${id}`);
    const estadoActual = badge ? badge.textContent.trim() : 'Pendiente';
    const displayDiv = document.getElementById(`obs-display-${id}`);
    if (displayDiv) {
        displayDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        displayDiv.style.display = 'block';
    }
    if (editTextarea) editTextarea.style.display = 'none';

    $.ajax({
        url: 'ajax/actualizar_feriado_ajax.php',
        method: 'POST',
        data: { id: id, estado: estadoActual, observaciones: nuevasObservaciones },
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                if (editTextarea) editTextarea.value = nuevasObservaciones;
                if (displayDiv) displayDiv.innerHTML = nuevasObservaciones ? nuevasObservaciones.replace(/\n/g, '<br>') : '<span class="text-muted">Sin observaciones</span>';
                finalizarEdicionObservacionesFeriado(id);
                mostrarNotificacion('success', 'Observaciones actualizadas');
            } else {
                mostrarNotificacion('error', data.message);
                cancelarEditObservacionesFeriado(id);
            }
        },
        error: function() {
            mostrarNotificacion('error', 'Error al guardar');
            cancelarEditObservacionesFeriado(id);
        }
    });
}

function cancelarEditObservacionesFeriado(id) {
    const editTextarea = document.getElementById(`obs-edit-${id}`);
    if (observacionesOriginalesFeriado[id] !== undefined && editTextarea) {
        editTextarea.value = observacionesOriginalesFeriado[id];
    }
    finalizarEdicionObservacionesFeriado(id);
}

function finalizarEdicionObservacionesFeriado(id) {
    const displayDiv = document.getElementById(`obs-display-${id}`);
    const editTextarea = document.getElementById(`obs-edit-${id}`);
    if (displayDiv) displayDiv.style.display = 'block';
    if (editTextarea) editTextarea.style.display = 'none';
    delete editandoObservacionesFeriado[id];
    delete observacionesOriginalesFeriado[id];
}

function mostrarNotificacion(tipo, mensaje) {
    const notification = $('<div class="notification notification-' + tipo + '"><i class="fas fa-' + (tipo === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i><span>' + mensaje + '</span></div>');
    notification.css({
        position: 'fixed', top: '20px', right: '20px', padding: '15px 20px', borderRadius: '8px',
        color: 'white', fontWeight: 'bold', display: 'flex', alignItems: 'center', gap: '10px',
        zIndex: '10000', boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
        background: tipo === 'success' ? 'linear-gradient(135deg, #28a745 0%, #20c997 100%)' : 'linear-gradient(135deg, #dc3545 0%, #e83e8c 100%)'
    });
    $('body').append(notification);
    setTimeout(() => { notification.fadeOut(300, function() { $(this).remove(); }); }, 3000);
}

function actualizarFiltros() {
    paginaActual = 1;
    // Los filtros se obtienen en cargarDatos() desde los inputs
    cargarDatos();
}

function buscarOperarios(texto) {
    if (!texto) return typeof operariosData !== 'undefined' ? operariosData : [];
    return (typeof operariosData !== 'undefined' ? operariosData : []).filter(op => op.nombre.toLowerCase().includes(texto.toLowerCase()));
}

function formatearFecha(fecha) {
    if (!fecha || fecha === '0000-00-00') return '-';
    const meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    const partes = fecha.split('-');
    if (partes.length !== 3) return fecha;
    const año = partes[0].slice(-2);
    const mes = parseInt(partes[1]) - 1;
    const dia = partes[2];
    return `${dia}-${meses[mes]}-${año}`;
}

function cerrarModal() {
    const modal = document.getElementById('modalAprobacion');
    if (modal) modal.style.display = 'none';
}
