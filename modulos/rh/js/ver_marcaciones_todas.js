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

// Estado del modal de foto DVR
let fotoModalActual  = { id: null, tipo: null, codSucursal: null, fecha: null, hora: null };
let offsetSegundos   = 0;   // offset aplicado sobre la hora exacta de marcación

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

        // ── Foto DVR (SOLO SI TIENE PERMISO foto_marcacion) ──────────────
        if (PERMISOS_USUARIO.esFotoMarcacion) {
            let fotoHtml = '<span style="color:#484f58;">—</span>';

            if (row.tiene_marcacion && row.id) {
                const iconEntrada = crearIconoFoto(row, 'entrada');
                const iconSalida  = row.hora_salida
                    ? crearIconoFoto(row, 'salida')
                    : '<span style="color:#484f58;font-size:.7rem;" title="Sin salida"><i class="bi bi-box-arrow-right"></i></span>';

                fotoHtml = `<div style="display:flex;flex-direction:row;align-items:center;justify-content:center;gap:6px;">
                    ${iconEntrada}${iconSalida}
                </div>`;
            }
            tr.append(`<td class="text-center" style="padding:6px 8px;">${fotoHtml}</td>`);
        }

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
    if (PERMISOS_USUARIO.esFotoMarcacion) cols += 1; // Foto DVR
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
                    <select id="mesCalendario" onchange="actualizarCalendarioUnico('${columna}')"></select>
                    <select id="añoCalendario" onchange="actualizarCalendarioUnico('${columna}')"></select>
                </div>
                <div class="daterange-calendar" id="calendarioUnico"></div>
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

        actualizarCalendarioUnico(columna);
    }, 50);
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
function seleccionarFechaUnico(fecha, columna) {
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

    // EXCLUSIÓN MUTUA: Si se filtra por fecha, eliminar filtro de semana
    delete filtrosActivos['numero_semana'];

    // Actualizar el calendario visualmente
    actualizarCalendarioUnico(columna);

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

// Variable para cachear los últimos datos cargados (opcional para futuras mejoras)
let ultimoDatosCargados = [];


// ════════════════════════════════════════════════════════════════
// FOTO DVR — MARCACIONES
// ════════════════════════════════════════════════════════════════

/**
 * Crea el botón de cámara premium para la columna Foto.
 * Circular con gradiente cuando la foto existe; oscuro cuando está pendiente.
 * Badge E/S indica entrada o salida.
 */
function crearIconoFoto(row, tipo) {
    const existe    = tipo === 'entrada' ? !!row.foto_entrada_existe : !!row.foto_salida_existe;
    const path      = tipo === 'entrada' ? (row.foto_entrada_path || '') : (row.foto_salida_path || '');
    const hora      = tipo === 'entrada' ? (row.hora_ingreso || '') : (row.hora_salida || '');
    const nombre    = (row.nombre_completo || '').replace(/"/g, '&quot;');
    const tituloBtn = (tipo === 'entrada' ? 'Foto Entrada' : 'Foto Salida') + (hora ? ' ' + hora.substring(0, 5) : '');

    // Paleta según tipo y estado
    const gradiente = tipo === 'entrada'
        ? 'linear-gradient(135deg,#2ea043 0%,#1a7f37 100%)'   // verde entrada
        : 'linear-gradient(135deg,#388bfd 0%,#1158c7 100%)';  // azul salida

    const bgExiste  = existe ? gradiente : '#1c2128';
    const bgHover   = existe
        ? (tipo === 'entrada' ? 'linear-gradient(135deg,#3fb950,#2ea043)' : 'linear-gradient(135deg,#58a6ff,#388bfd)')
        : '#21262d';
    const iconColor = existe ? '#ffffff' : '#484f58';
    const badge     = tipo === 'entrada' ? 'E' : 'S';
    const badgeColor = existe ? 'rgba(255,255,255,.85)' : '#6e7681';
    const badgeBg    = existe ? 'rgba(0,0,0,.25)' : '#0d1117';
    const shadow     = existe
        ? (tipo === 'entrada' ? '0 2px 8px rgba(46,160,67,.45)' : '0 2px 8px rgba(56,139,253,.45)')
        : 'none';

    return `<button class="btn-foto-dvr btn-foto-${tipo}"
        onclick="abrirModalFoto(this)"
        data-id="${row.id}"
        data-tipo="${tipo}"
        data-cod-sucursal="${row.sucursal_codigo}"
        data-fecha="${row.fecha}"
        data-hora="${hora}"
        data-existe="${existe ? '1' : '0'}"
        data-path="${escHtml(path)}"
        data-nombre="${nombre}"
        data-titulo="${escHtml(tituloBtn)}"
        title="${escHtml(tituloBtn)}"
        style="position:relative;
               background:${bgExiste};
               border:none;
               border-radius:50%;
               width:32px; height:32px;
               padding:0;
               cursor:pointer;
               color:${iconColor};
               display:inline-flex;
               align-items:center;
               justify-content:center;
               box-shadow:${shadow};
               transition:background .18s, box-shadow .18s, transform .12s;
               flex-shrink:0;"
        onmouseenter="this.style.background='${bgHover}';this.style.transform='scale(1.12)';"
        onmouseleave="this.style.background='${bgExiste}';this.style.transform='scale(1)';">
        <i class="bi bi-camera-fill" style="font-size:.8rem;"></i>
        <span style="position:absolute;
                     bottom:-2px; right:-2px;
                     background:${badgeBg};
                     color:${badgeColor};
                     font-size:.48rem;
                     font-weight:800;
                     border-radius:50%;
                     width:11px; height:11px;
                     display:flex; align-items:center; justify-content:center;
                     line-height:1;
                     border:1px solid ${existe ? 'rgba(255,255,255,.2)' : '#21262d'};">${badge}</span>
    </button>`;
}


/**
 * Abre el modal de foto para una marcación.
 * Si ya existe foto la muestra; si no, la captura automáticamente.
 */
function abrirModalFoto(btn) {
    const $btn     = $(btn);
    const existe   = $btn.data('existe') === 1 || $btn.data('existe') === '1';
    const path     = $btn.data('path')     || '';
    const id       = $btn.data('id');
    const tipo     = $btn.data('tipo');
    const codSuc   = String($btn.data('cod-sucursal'));
    const fecha    = $btn.data('fecha');
    const hora     = $btn.data('hora')     || '';
    const nombre   = $btn.data('nombre')   || '';
    const titulo   = $btn.data('titulo')   || '';

    // Guardar estado global
    fotoModalActual = { id, tipo, codSucursal: codSuc, fecha, hora };
    offsetSegundos  = 0;   // resetear offset al abrir nuevo modal

    // Rellenar encabezado del modal
    const tipoLabel = tipo === 'entrada' ? 'Entrada' : 'Salida';
    $('#fotoModalTitulo').text(`Foto ${tipoLabel} DVR`);
    $('#fotoModalSubtitulo').text(`${nombre}  ·  ${fecha}  ·  ${titulo}`);
    $('#btnFotoAbrir').attr('href', path || '#').toggle(!!path);
    $('#fotoModalMeta').empty();
    actualizarLabelOffset();   // mostrar "Hora exacta"
    $('.btn-offset-foto').removeClass('offset-activo');
    $('.btn-offset-foto[data-delta="0"]').addClass('offset-activo');

    // Abrir con Bootstrap Modal API
    const modalEl = document.getElementById('modalFotoMarcacion');
    bootstrap.Modal.getOrCreateInstance(modalEl).show();

    if (existe && path) {
        mostrarImagenModal(path);
    } else {
        // Mostrar spinner tema claro mientras captura
        $('#fotoModalContenedor').html(
            `<div style="text-align:center;color:#6c757d;padding:32px;">
                <div style="width:32px;height:32px;border:3px solid rgba(14,84,76,.2);
                            border-top-color:#0E544C;border-radius:50%;
                            animation:spin .7s linear infinite;margin:0 auto 14px;"></div>
                <div style="font-size:.85rem;">Solicitando foto al DVR…</div>
             </div>`
        );
        capturarFotoModal();
    }
}

/** Muestra la imagen ya descargada en el contenedor del modal. */
function mostrarImagenModal(path) {
    const ts = Date.now();
    $('#fotoModalContenedor').html(
        `<img src="${escHtml(path)}?t=${ts}"
              alt="Foto DVR marcación"
              style="width:100%;max-height:460px;object-fit:contain;display:block;"
              onerror="this.parentElement.innerHTML='<span style=\'color:#dc3545;padding:16px;\'>Error al cargar imagen</span>'">`
    );
    $('#btnFotoAbrir').attr('href', path).show();
}

/** Botón Retomar foto: vuelve a capturar y sobreescribe. */
function retamarFotoModal() {
    $('#fotoModalContenedor').html(
        `<div style="text-align:center;color:#6c757d;padding:32px;">
            <div style="width:32px;height:32px;border:3px solid rgba(14,84,76,.2);
                        border-top-color:#0E544C;border-radius:50%;
                        animation:spin .7s linear infinite;margin:0 auto 14px;"></div>
            <div style="font-size:.85rem;">Retomando foto del DVR…</div>
         </div>`
    );
    capturarFotoModal();
}

/** Llama al endpoint AJAX para capturar la foto del DVR. */
function capturarFotoModal() {
    const { id, tipo, codSucursal, fecha, hora } = fotoModalActual;
    if (!id || !hora) {
        $('#fotoModalContenedor').html(
            '<span style="color:#f85149;padding:16px;">Sin datos suficientes para capturar.</span>'
        );
        return;
    }

    // Construir fecha_hora con el offset actual (default 0 = hora exacta).
    // Ej: offset -60 y marca 16:44:32 → captura en 16:43:32
    // hora viene como "HH:MM:SS" desde la BD
    let fechaHora;
    try {
        const dtBase  = new Date(`${fecha}T${hora}`);
        dtBase.setSeconds(dtBase.getSeconds() + offsetSegundos);
        const pad     = n => String(n).padStart(2, '0');
        const hh      = pad(dtBase.getHours());
        const mm      = pad(dtBase.getMinutes());
        const ss      = pad(dtBase.getSeconds());
        const yyyy    = dtBase.getFullYear();
        const mo      = pad(dtBase.getMonth() + 1);
        const dd      = pad(dtBase.getDate());
        fechaHora     = `${yyyy}-${mo}-${dd}T${hh}:${mm}:${ss}`;
    } catch (_) {
        fechaHora = fecha + 'T' + hora;
    }

    const $btnRetomar = $('#btnFotoRetomar');
    $btnRetomar.prop('disabled', true)
               .html('<i class="bi bi-arrow-repeat" style="display:inline-block;"></i> Capturando…');

    $.ajax({
        url: 'ajax/marcacion_capturar_foto_hora.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            id_marcacion: id,
            tipo:         tipo,
            cod_sucursal: codSucursal,
            fecha_hora:   fechaHora
        }),
        dataType: 'json',
        timeout: 40000,

        success: function (resp) {
            if (resp.success) {
                mostrarImagenModal(resp.path);
                // Actualizar meta info con chips estilo claro
                $('#fotoModalMeta').html(
                    `<span style="display:inline-flex;align-items:center;gap:5px;
                                  background:#fff;border:1px solid #dee2e6;
                                  border-radius:20px;padding:4px 12px;
                                  font-size:.74rem;color:#495057;">
                        <i class="bi bi-file-earmark-image" style="color:#0E544C;"></i>
                        ${resp.size_kb} KB
                     </span>
                     <span style="display:inline-flex;align-items:center;gap:5px;
                                  background:#fff;border:1px solid #dee2e6;
                                  border-radius:20px;padding:4px 12px;
                                  font-size:.74rem;color:#495057;">
                        <i class="bi bi-clock" style="color:#0E544C;"></i>
                        ${escHtml(resp.timestamp || '')}
                     </span>`
                );
                // Actualizar ícono en la tabla sin recargar
                actualizarIconoFotoEnTabla(id, tipo, resp.path);
            } else {
                $('#fotoModalContenedor').html(
                    `<div style="text-align:center;padding:32px;">
                        <i class="bi bi-exclamation-triangle-fill"
                           style="font-size:2.5rem;color:#dc3545;"></i>
                        <div style="margin-top:10px;color:#dc3545;font-weight:600;font-size:.95rem;">No se pudo capturar</div>
                        <div style="margin-top:6px;color:#6c757d;font-size:.82rem;">${escHtml(resp.message || 'Error desconocido')}</div>
                        ${resp.debug ? `<div style="margin-top:8px;font-family:monospace;font-size:.7rem;color:#6c757d;background:#f8f9fa;border:1px solid #dee2e6;border-radius:6px;padding:8px;white-space:pre-wrap;word-break:break-all;">${escHtml(resp.debug)}</div>` : ''}
                     </div>`
                );
            }
        },

        error: function (xhr, status) {
            const msg = status === 'timeout'
                ? 'Timeout: el DVR tardó más de 35 segundos en responder.'
                : `Error de red (${status}). Verifica conectividad.`;
            $('#fotoModalContenedor').html(
                `<div style="text-align:center;padding:32px;">
                    <i class="bi bi-wifi-off" style="font-size:2.5rem;color:#dc3545;"></i>
                    <div style="margin-top:12px;color:#dc3545;font-size:.9rem;font-weight:600;">${escHtml(msg)}</div>
                 </div>`
            );
        },

        complete: function () {
            $btnRetomar.prop('disabled', false)
                       .html('<i class="bi bi-arrow-repeat"></i> Retomar foto');
        }
    });
}

/** Cierra el modal de foto y limpia el estado. */
function cerrarModalFoto() {
    const modalEl = document.getElementById('modalFotoMarcacion');
    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
    
    // Limpiar contenido tras animación de cierre
    setTimeout(() => {
        $('#fotoModalContenedor').html(
            `<span style="color:#adb5bd;font-size:.88rem;">
                <i class="bi bi-camera" style="font-size:2rem;display:block;text-align:center;margin-bottom:8px;opacity:.4;"></i>
                Iniciando captura&hellip;
             </span>`
        );
        $('#fotoModalMeta').empty();
    }, 300);
    
    fotoModalActual = { id: null, tipo: null, codSucursal: null, fecha: null, hora: null };
    offsetSegundos  = 0;
}

/**
 * Actualiza el ícono del botón en la tabla al capturar exitosamente,
 * sin necesidad de recargar todos los datos.
 */
function actualizarIconoFotoEnTabla(id, tipo, path) {
    const $btn = $(`.btn-foto-dvr[data-id="${id}"][data-tipo="${tipo}"]`);
    if (!$btn.length) return;

    // Aplicar gradiente premium según tipo
    const gradiente = tipo === 'entrada'
        ? 'linear-gradient(135deg,#2ea043 0%,#1a7f37 100%)'
        : 'linear-gradient(135deg,#388bfd 0%,#1158c7 100%)';
    const shadow = tipo === 'entrada'
        ? '0 2px 8px rgba(46,160,67,.45)'
        : '0 2px 8px rgba(56,139,253,.45)';

    $btn.css({ 'background': gradiente, 'box-shadow': shadow, 'color': '#ffffff', 'border': 'none' });
    $btn.find('i').attr('class', 'bi bi-camera-fill');
    $btn.find('span').css({ 'background': 'rgba(0,0,0,.25)', 'color': 'rgba(255,255,255,.85)', 'border-color': 'rgba(255,255,255,.2)' });
    $btn.data('existe', '1').data('path', path)
        .attr('data-existe', '1').attr('data-path', path);

    // Reasignar hover handlers con gradiente correcto
    const bgHover = tipo === 'entrada'
        ? 'linear-gradient(135deg,#3fb950,#2ea043)'
        : 'linear-gradient(135deg,#58a6ff,#388bfd)';
    $btn[0].onmouseenter = function() { this.style.background = bgHover; this.style.transform = 'scale(1.12)'; };
    $btn[0].onmouseleave = function() { this.style.background = gradiente; this.style.transform = 'scale(1)'; };
}


/** Escapa HTML para uso seguro en atributos y contenido. */
function escHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// Cerrar modal al hacer clic en el backdrop
$(document).on('click', '#modalFotoMarcacion', function (e) {
    if (e.target === this) cerrarModalFoto();
});

// Cerrar modal con Escape
$(document).on('keydown', function (e) {
    if (e.key === 'Escape' && $('#modalFotoMarcacion').is(':visible')) {
        cerrarModalFoto();
    }
});

/**
 * Ajusta el offset de tiempo, marca el botón activo y recaptura.
 * delta: número de segundos respecto a la hora original (absoluto, no acumulativo).
 */
function ajustarOffsetFoto(delta) {
    offsetSegundos = delta;
    actualizarLabelOffset();
    // Marcar botón activo
    $('.btn-offset-foto').removeClass('offset-activo');
    $(`.btn-offset-foto[data-delta="${delta}"]`).addClass('offset-activo');
    // Capturar con el nuevo offset
    capturarFotoModal();
}

/** Actualiza el label que muestra el offset aplicado. */
function actualizarLabelOffset() {
    let txt;
    if (offsetSegundos === 0) {
        txt = '<strong>Hora exacta</strong>';
    } else {
        const signo = offsetSegundos > 0 ? '+' : '';
        txt = `offset <strong>${signo}${offsetSegundos}s</strong> sobre la hora marcada`;
    }
    $('#fotoOffsetLabel').html(txt);
}
