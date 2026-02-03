/**
 * Sistema de Filtros para Historial de Marcaciones
 * Basado en el patrón de cupones.js
 */

let paginaActual = 1;
let registrosPorPagina = 25;
let filtrosActivos = {};
let ordenActivo = { columna: null, direccion: 'desc' };
let panelFiltroAbierto = null;
let totalRegistros = 0;
let scrollTopInicial = 0;
let filtroIncidencias = 'todos'; // 'todos', 'con_incidencia', 'sin_incidencia'

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
        url: 'ajax/marcaciones_get_datos.php',
        method: 'POST',
        data: {
            pagina: paginaActual,
            registros_por_pagina: registrosPorPagina,
            filtros: JSON.stringify(filtrosActivos),
            orden: JSON.stringify(ordenActivo),
            incidencias: filtroIncidencias // Parámetro para filtrado en servidor
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
                alert('Error al cargar datos: ' + response.message);
            }
        },
        error: function (xhr, status, error) {
            console.error('Error AJAX:', error);
            alert('Error al cargar los datos');
        }
    });
}

// Renderizar tabla
function renderizarTabla(datos) {
    const tbody = $('#tablaMarcacionesBody');
    tbody.empty();

    // El filtrado por incidencias ya se realiza en el servidor para mantener la paginación correcta.
    // Solo usamos los datos tal cual vienen del AJAX.
    let datosFiltrados = datos;

    if (datosFiltrados.length === 0) {
        const colspan = calcularColspan();
        tbody.append(`<tr><td colspan="${colspan}" class="text-center py-4">No se encontraron registros</td></tr>`);
        return;
    }

    datosFiltrados.forEach(row => {
        const tr = $('<tr>');

        // Semana
        tr.append(`<td class="text-center" style="font-weight: bold;">${row.numero_semana || 'N/A'}</td>`);

        // Sucursal
        tr.append(`<td>${row.nombre_sucursal || '-'}</td>`);

        // Colaborador
        tr.append(`<td>${row.nombre_completo || '-'}</td>`);

        // Cargo
        tr.append(`<td>${row.nombre_cargo || '-'}</td>`);

        // Fecha
        tr.append(`<td>${formatearFecha(row.fecha)}</td>`);

        // Turno Programado (Estado del Día) - CON TAGS
        let estadoHtml = '-';
        let tagSucursalExterna = '';

        if (row.estado_dia) {
            const estado = row.estado_dia;
            if (estado === 'Activo') {
                estadoHtml = '<span class="status-activo">Activo</span>';
            } else {
                estadoHtml = `<span class="inactive-hours">${estado}</span>`;
            }

            // Mostrar tag de sucursal externa si el estado es "Otra.Tienda"
            if (estado === 'Otra.Tienda' && row.sucursal_externa_nombre) {
                tagSucursalExterna = `<span class="external-branch-tag" title="Programado en: ${row.sucursal_externa_nombre}">
                    <i class="fas fa-map-marker-alt"></i> ${row.sucursal_externa_nombre}
                </span>`;
            }
        }
        tr.append(`<td class="text-center">${estadoHtml}${tagSucursalExterna}</td>`);

        // Horario Programado - FORMATO HH:MM
        let horarioProgramado = '-';
        if (row.hora_entrada_programada && row.hora_salida_programada) {
            const entrada = formatearHora(row.hora_entrada_programada);
            const salida = formatearHora(row.hora_salida_programada);
            horarioProgramado = `<span class="compact-time">${entrada} - ${salida}</span>`;
        }
        tr.append(`<td class="text-center">${horarioProgramado}</td>`);

        // Horas Programadas (SOLO SI ES LÍDER)
        if (PERMISOS_USUARIO.esLider) {
            const horasProgramadas = calcularHoras(row.hora_entrada_programada, row.hora_salida_programada);
            tr.append(`<td class="text-center" style="font-weight:bold;">${horasProgramadas}</td>`);
        }

        // Horario Marcado - FORMATO HH:MM
        let horarioMarcado = '-';
        let tagSucursal = '';

        // Verificar si marcó en una sucursal distinta a la programada
        if (row.tiene_marcacion && row.sucursal_marcacion_codigo && row.sucursal_marcacion_codigo !== row.sucursal_codigo) {
            tagSucursal = `<span class="external-branch-tag" title="Marcó en: ${row.sucursal_marcacion_nombre}">
                <i class="fas fa-map-marker-alt"></i> ${row.sucursal_marcacion_nombre}
            </span>`;
        }

        if (row.hora_ingreso && row.hora_salida) {
            const entrada = formatearHora(row.hora_ingreso);
            const salida = formatearHora(row.hora_salida);
            horarioMarcado = `<div class="d-flex flex-column align-items-center">
                <span class="compact-time">${entrada} - ${salida}</span>
                ${tagSucursal}
            </div>`;
        } else if (row.hora_ingreso) {
            horarioMarcado = `<div class="d-flex flex-column align-items-center">
                <span class="compact-time">${formatearHora(row.hora_ingreso)} - -</span>
                ${tagSucursal}
            </div>`;
        } else if (tagSucursal) {
            horarioMarcado = `<div class="d-flex flex-column align-items-center">${tagSucursal}</div>`;
        }
        tr.append(`<td class="text-center">${horarioMarcado}</td>`);

        // Horas Trabajadas (SOLO SI ES LÍDER)
        if (PERMISOS_USUARIO.esLider) {
            const horasTrabajadas = calcularHoras(row.hora_ingreso, row.hora_salida);
            tr.append(`<td class="text-center">${horasTrabajadas}</td>`);
        }

        // Diferencia Entrada (OCULTA - pero se renderiza para mantener estructura)
        // Estas columnas están ocultas con CSS en el HTML

        // Diferencia Salida (OCULTA - pero se renderiza para mantener estructura)
        // Estas columnas están ocultas con CSS en el HTML

        // Total Horas (SOLO SI ES OPERACIONES)
        if (PERMISOS_USUARIO.esOperaciones) {
            tr.append(`<td class="text-center" style="font-weight: bold; background-color: #e8f5e9;">${row.total_horas_periodo || '0.00'}</td>`);
        }

        // Acciones - STATUS TRACKER DE 3 PASOS (BARRA DE PROGRESO)
        let accionesHtml = '';
        const hoyStr = PERMISOS_USUARIO.fechaHoy;

        if (row.fecha < hoyStr && (row.hora_entrada_programada || row.tiene_horario)) {
            let esTardanza = false;
            let esFalta = false;

            // Determinar tipo de incidencia
            if (row.hora_entrada_programada && row.hora_ingreso) {
                const difMin = calcularMinutosDiferencia(row.hora_entrada_programada, row.hora_ingreso);
                if (difMin > 1) esTardanza = true;
            } else if (!row.tiene_marcacion) {
                // NUEVA LÓGICA: Considerar requiere_marcacion Y requiere_justificacion
                // Si requiere_marcacion=true (tipo='con_marcacion'), es falta normal
                // Si requiere_marcacion=false pero requiere_justificacion=1, es falta programada que requiere justificación
                if (row.requiere_marcacion || row.requiere_justificacion === 1) {
                    esFalta = true;
                }
            }

            if (esTardanza || esFalta) {
                const dataJust = esTardanza ? row.tardanza_data : row.falta_data;
                const solicitada = esTardanza ? row.tardanza_solicitada : row.falta_solicitada;

                // Definir estados de los 3 pasos
                // Paso 1: Incidencia Detectada
                let step1Class = esTardanza ? 'active-warning' : 'active-danger';
                let step1Icon = esTardanza ? 'fa-clock' : 'fa-user-slash';
                let step1Title = esTardanza ? 'Tardanza detectada por sistema' : 'Falta detectada por sistema';

                // NUEVA LÓGICA: Si es falta programada con justificación requerida, usar color verde
                if (esFalta && !row.requiere_marcacion && row.requiere_justificacion === 1) {
                    step1Class = 'active-success'; // Verde en lugar de rojo
                    step1Title = 'Inasistencia programada - Requiere justificación';
                }

                if (solicitada) {
                    step1Class = 'completed';
                    step1Icon = 'fa-check';
                }

                // Paso 2: Justificación (Líder)
                let step2Class = '';
                let step2Icon = 'fa-file-medical';
                let step2Title = 'Justificación pendiente de enviar';
                let step2Click = '';

                if (solicitada) {
                    step2Class = (dataJust && (dataJust.estado === 'Justificado' || dataJust.tipo !== 'Pendiente')) ? 'completed' : 'active-info';
                    step2Icon = (step2Class === 'completed') ? 'fa-check' : 'fa-file-medical';
                    step2Title = 'Justificación enviada por lider';
                } else if (PERMISOS_USUARIO.esLider) {
                    step2Class = 'interactive';
                    step2Title = 'Haga clic para reportar justificación';
                    if (esTardanza) {
                        step2Click = `onclick="mostrarModalTardanza(${row.CodOperario}, '${(row.nombre_completo || '').replace(/'/g, "\\'")}', '${row.sucursal_codigo}', '${(row.nombre_sucursal || '').replace(/'/g, "\\'")}', '${row.fecha}', '${row.hora_entrada_programada}', '${row.hora_ingreso}', null, true)"`;
                    } else {
                        step2Click = `onclick="mostrarModalFalta(${row.CodOperario}, '${(row.nombre_completo || '').replace(/'/g, "\\'")}', '${row.sucursal_codigo}', '${(row.nombre_sucursal || '').replace(/'/g, "\\'")}', '${row.fecha}')"`;
                    }
                }

                // Paso 3: Resolución (RRHH/Operas)
                let step3Class = '';
                let step3Icon = 'fa-gavel';
                let step3Title = dataJust ? 'Pendiente de revisión' : 'Esperando revisión final';

                if (dataJust) {
                    if (esTardanza) {
                        if (dataJust.estado === 'Justificado') {
                            step3Class = 'completed';
                            step3Icon = 'fa-check-double';
                            step3Title = 'Aceptado: ' + (dataJust.tipo || '');
                        } else if (dataJust.estado === 'No Válido') {
                            step3Class = 'failed';
                            step3Icon = 'fa-times';
                            step3Title = 'No Válido / Rechazado';
                        }
                    } else {
                        // Lógica basada en tipo_status de la tabla tipos_falta
                        const status = dataJust.tipo_status;
                        if (status === 'rechazado') {
                            step3Class = 'failed';
                            step3Icon = 'fa-times';
                            step3Title = 'Rechazado: ' + dataJust.tipo.replace(/_/g, ' ');
                        } else if (status === 'aprobado') {
                            step3Class = 'completed';
                            step3Icon = 'fa-check-double';
                            step3Title = 'Aprobado: ' + dataJust.tipo.replace(/_/g, ' ');
                        } else {
                            // Pendiente o cualquier otro
                            step3Title = 'Pendiente de revisión';
                        }
                    }
                }

                // Generar HTML del Tracker
                accionesHtml = `
                    <div class="status-tracker">
                        <div class="tracker-step ${step1Class}" data-bs-toggle="tooltip" title="${step1Title}">
                            <i class="fas ${step1Icon}"></i>
                        </div>
                        <div class="tracker-line ${solicitada ? 'completed' : ''}"></div>
                        <div class="tracker-step ${step2Class}" ${step2Click} data-bs-toggle="tooltip" title="${step2Title}">
                            <i class="fas ${step2Icon}"></i>
                        </div>
                        <div class="tracker-line ${step3Class !== '' ? 'completed' : ''}"></div>
                        <div class="tracker-step ${step3Class}" data-bs-toggle="tooltip" title="${step3Title}">
                            <i class="fas ${step3Icon}"></i>
                        </div>
                    </div>
                `;
            }
        }

        tr.append(`<td class="text-center"><div class="rh-actions-cell">${accionesHtml}</div></td>`);

        tbody.append(tr);
    });

    // Inicializar tooltips de Bootstrap para los nuevos iconos (si bootstrap está disponible)
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
}

// Calcular colspan para mensaje de "no hay registros"
function calcularColspan() {
    let cols = 7; // Semana, Sucursal, Colaborador, Cargo, Fecha, Turno Programado, Horario Programado
    if (PERMISOS_USUARIO.esLider) cols += 2; // Horas Programadas, Horas Trabajadas
    cols += 1; // Horario Marcado
    // Las columnas de Diferencia Entrada y Salida están ocultas por CSS, no se cuentan aquí.
    if (PERMISOS_USUARIO.esOperaciones) cols += 1; // Total Horas
    cols += 1; // Acciones
    return cols;
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
                    ASC ↑
                </button>
                <button class="filter-sort-btn ${ordenActivo.columna === columna && ordenActivo.direccion === 'desc' ? 'active' : ''}" 
                        onclick="aplicarOrden('${columna}', 'desc')">
                    DESC ↓
                </button>
            </div>
        </div>
    `);

    // Botones de acción
    panel.append(`
        <div class="filter-actions">
            <button class="filter-action-btn clear clear-filter-btn" onclick="limpiarFiltro('${columna}')">
                <i class="bi bi-x-circle"></i> Borrar Filtros
            </button>
        </div>
    `);

    // Agregar al body
    $('body').append(panel);

    // Filtros según tipo
    if (tipo === 'number') {
        crearFiltroNumerico(panel, columna);
        posicionarPanelFiltro(panel, icon);
    } else if (tipo === 'list') {
        cargarOpcionesFiltro(panel, columna, icon);
    } else if (tipo === 'daterange') {
        crearCalendarioDoble(panel, columna);
        posicionarPanelFiltro(panel, icon);
    }
}

// Crear filtro numérico
function crearFiltroNumerico(panel, columna) {
    const valorMin = filtrosActivos[columna]?.min || '';
    const valorMax = filtrosActivos[columna]?.max || '';

    panel.append(`
        <div class="filter-section" style="margin-top: 12px;">
            <span class="filter-section-title">Rango:</span>
            <div class="numeric-inputs">
                <input type="number" class="filter-search" placeholder="Mínimo" 
                       value="${valorMin}"
                       onchange="filtrarNumerico('${columna}', 'min', this.value)">
                <input type="number" class="filter-search" placeholder="Máximo" 
                       value="${valorMax}"
                       onchange="filtrarNumerico('${columna}', 'max', this.value)">
            </div>
        </div>
    `);
}

// Filtrar numérico
function filtrarNumerico(columna, tipo, valor) {
    if (!filtrosActivos[columna]) {
        filtrosActivos[columna] = {};
    }

    if (valor === '') {
        delete filtrosActivos[columna][tipo];
        if (Object.keys(filtrosActivos[columna]).length === 0) {
            delete filtrosActivos[columna];
        }
    } else {
        filtrosActivos[columna][tipo] = valor;
    }

    // EXCLUSIÓN MUTUA: Si se filtra por semana, eliminar filtro de fecha
    if (columna === 'numero_semana') {
        delete filtrosActivos['fecha'];
    }

    paginaActual = 1;
    cargarDatos();
}

// Crear calendario para rango de fechas
function crearCalendarioDoble(panel, columna) {
    const fechaDesde = filtrosActivos[columna]?.desde || '';
    const fechaHasta = filtrosActivos[columna]?.hasta || '';

    const hoy = new Date();
    const mesActual = hoy.getMonth();
    const añoActual = hoy.getFullYear();

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

// Inicializar selectores de fecha
function inicializarSelectoresFechaUnico(mesInicial, añoInicial) {
    const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    const selectMes = $('#mesCalendario');
    meses.forEach((mes, idx) => {
        selectMes.append(`<option value="${idx}" ${idx === mesInicial ? 'selected' : ''}>${mes}</option>`);
    });

    const selectAño = $('#añoCalendario');
    const añoActual = new Date().getFullYear();
    for (let año = añoActual - 5; año <= añoActual + 1; año++) {
        selectAño.append(`<option value="${año}" ${año === añoInicial ? 'selected' : ''}>${año}</option>`);
    }
}

// Actualizar calendario
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

// Seleccionar fecha en calendario
function seleccionarFechaUnico(fecha, columna) {
    if (window.event) {
        window.event.stopPropagation();
    }

    if (!filtrosActivos[columna]) {
        filtrosActivos[columna] = {};
    }

    const fechaDesde = filtrosActivos[columna].desde;
    const fechaHasta = filtrosActivos[columna].hasta;

    if (!fechaDesde && !fechaHasta) {
        filtrosActivos[columna].desde = fecha;
    } else if (fechaDesde && !fechaHasta) {
        if (fecha >= fechaDesde) {
            filtrosActivos[columna].hasta = fecha;
        } else {
            filtrosActivos[columna].desde = fecha;
        }
    } else if (fechaDesde && fechaHasta) {
        if (fecha < fechaDesde) {
            filtrosActivos[columna].desde = fecha;
        } else if (fecha > fechaHasta) {
            filtrosActivos[columna].hasta = fecha;
        } else {
            filtrosActivos[columna] = { desde: fecha };
        }
    }

    actualizarCalendarioUnico(columna);

    if (filtrosActivos[columna].desde && filtrosActivos[columna].hasta) {
        // EXCLUSIÓN MUTUA: Si se filtra por fecha, eliminar filtro de semana
        delete filtrosActivos['numero_semana'];

        paginaActual = 1;
        cargarDatos();
    }
}

// Cargar opciones de filtro
function cargarOpcionesFiltro(panel, columna, icon) {
    $.ajax({
        url: 'ajax/marcaciones_get_opciones_filtro.php',
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

// Utilidades de formateo
function formatearFecha(fecha) {
    if (!fecha) return '-';
    const meses = ['En', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    const d = new Date(fecha + 'T00:00:00'); // Agregar hora para evitar problemas de zona horaria
    const año = String(d.getFullYear()).slice(-2);
    return `${String(d.getDate()).padStart(2, '0')}/${meses[d.getMonth()]}/${año}`;
}

function formatearHora(hora) {
    if (!hora) return '-';
    return hora.substring(0, 5); // HH:MM
}

function determinarTurno(entrada, salida) {
    if (!entrada || !salida) return '-';
    const horaEntrada = parseInt(entrada.split(':')[0]);
    if (horaEntrada >= 5 && horaEntrada < 14) return 'Mañana';
    if (horaEntrada >= 14 && horaEntrada < 22) return 'Tarde';
    return 'Noche';
}

function calcularHoras(horaInicio, horaFin) {
    if (!horaInicio || !horaFin) return '-';

    const inicio = new Date(`2000-01-01 ${horaInicio}`);
    let fin = new Date(`2000-01-01 ${horaFin}`);

    if (fin < inicio) {
        fin = new Date(`2000-01-02 ${horaFin}`);
    }

    const diff = (fin - inicio) / (1000 * 60 * 60);
    return diff.toFixed(2) + ' hrs';
}

function calcularDiferencia(entradaProg, salidaProg, entradaMarcada, salidaMarcada) {
    if (!entradaProg || !salidaProg || !entradaMarcada || !salidaMarcada) return '-';

    const progInicio = new Date(`2000-01-01 ${entradaProg}`);
    let progFin = new Date(`2000-01-01 ${salidaProg}`);
    if (progFin < progInicio) progFin = new Date(`2000-01-02 ${salidaProg}`);

    const marcInicio = new Date(`2000-01-01 ${entradaMarcada}`);
    let marcFin = new Date(`2000-01-01 ${salidaMarcada}`);
    if (marcFin < marcInicio) marcFin = new Date(`2000-01-02 ${salidaMarcada}`);

    const horasProg = (progFin - progInicio) / (1000 * 60 * 60);
    const horasMarc = (marcFin - marcInicio) / (1000 * 60 * 60);

    const diff = horasMarc - horasProg;
    return (diff >= 0 ? '+' : '') + diff.toFixed(2) + ' hrs';
}

function calcularDiferenciaEntrada(programada, marcada) {
    if (!programada || !marcada) return '-';

    const prog = new Date(`2000-01-01 ${programada}`);
    const marc = new Date(`2000-01-01 ${marcada}`);

    const diff = (marc - prog) / (1000 * 60);
    return (diff >= 0 ? '+' : '') + diff.toFixed(0) + ' min';
}

function calcularDiferenciaSalida(programada, marcada) {
    if (!programada || !marcada) return '-';

    let prog = new Date(`2000-01-01 ${programada}`);
    let marc = new Date(`2000-01-01 ${marcada}`);

    // Ajustar si la salida es al día siguiente
    if (marc < prog) marc = new Date(`2000-01-02 ${marcada}`);
    if (prog.getHours() < 12 && marc.getHours() >= 12) prog = new Date(`2000-01-02 ${programada}`);

    const diff = (marc - prog) / (1000 * 60);
    return (diff >= 0 ? '+' : '') + diff.toFixed(0) + ' min';
}

function calcularMinutosDiferencia(programada, marcada) {
    if (!programada || !marcada) return 0;

    const prog = new Date(`2000-01-01 ${programada}`);
    const marc = new Date(`2000-01-01 ${marcada}`);

    const diff = (marc - prog) / (1000 * 60);
    return diff;
}

function verDetalle(codOperario, fecha) {
    // Placeholder para ver detalle de marcación
    alert(`Ver detalle de marcación:\nOperario: ${codOperario}\nFecha: ${fecha}`);
}

// Función para establecer el filtro de incidencias (Tri-state discriminado)
function setFiltroIncidencias(estado) {
    filtroIncidencias = estado;

    // Actualizar estados visuales en la botonera del encabezado
    $('.tri-btn').removeClass('active');

    if (estado === 'todos') {
        $('.tri-btn.neutral').addClass('active');
    } else if (estado === 'tardanzas') {
        $('.tri-btn.warning').addClass('active');
    } else if (estado === 'faltas') {
        $('.tri-btn.danger').addClass('active');
    }

    // Reiniciar a la primera página y recargar desde el servidor para que la paginación sea correcta
    paginaActual = 1;
    cargarDatos();
}

// Variable para cachear los últimos datos cargados y permitir filtrado local instantáneo
// Variable para cachear los últimos datos cargados (opcional para futuras mejoras)
let ultimoDatosCargados = [];

