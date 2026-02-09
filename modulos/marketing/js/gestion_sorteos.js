// gestion_sorteos.js

let paginaActual = 1;
let registrosPorPagina = 50;
let filtrosActivos = {};
let ordenActivo = { columna: null, direccion: null };
let validoFilterState = 'all'; // 'all', 'valid', 'invalid'
// tienePermisoEdicion is set from PHP inline script

$(document).ready(function () {
    cargarRegistros();

    // Event delegation for Ver buttons
    $(document).on('click', '.btn-ver-foto', function (e) {
        e.preventDefault();
        const id = $(this).data('id');
        const buttonText = $(this).closest('tr').find('td:first').text();
        console.log('=== CLICK EN BOTÓN VER ===');
        console.log('ID del botón:', id);
        console.log('Tipo de ID:', typeof id);
        console.log('Primera celda de la fila:', buttonText);
        console.log('========================');
        verFoto(id);
    });
});


function cargarRegistros() {
    const params = new URLSearchParams({
        page: paginaActual,
        per_page: registrosPorPagina,
        ...(ordenActivo.columna && {
            orden_columna: ordenActivo.columna,
            orden_direccion: ordenActivo.direccion
        }),
        ...(validoFilterState !== 'all' && {
            valido: validoFilterState === 'valid' ? 1 : 0
        })
    });

    // Add filters with proper JSON serialization
    Object.keys(filtrosActivos).forEach(key => {
        const value = filtrosActivos[key];
        if (typeof value === 'object' && value !== null) {
            params.append(key, JSON.stringify(value));
        } else {
            params.append(key, value);
        }
    });

    console.log('Cargando registros con params:', params.toString());

    $.ajax({
        url: `ajax/get_registros_sorteos.php?${params}`,
        method: 'GET',
        dataType: 'json',
        success: function (response) {
            console.log('Respuesta AJAX:', response);
            if (response.success) {
                console.log('Datos recibidos:', response.data.length, 'registros');
                renderizarTabla(response.data);
                renderizarPaginacion(response.total_pages, response.page);
                actualizarIndicadoresFiltros(); // Update filter indicators
            } else {
                console.error('Error en respuesta:', response.message);
                mostrarError('Error al cargar registros: ' + (response.message || 'Error desconocido'));
            }
        },
        error: function (xhr, status, error) {
            console.error('Error AJAX:', {
                status: xhr.status,
                statusText: xhr.statusText,
                responseText: xhr.responseText,
                error: error
            });
            mostrarError('Error al cargar registros');
        }
    });
}

// Función para obtener badge de verificación IA
function getVerificacionBadge(registro) {
    // Verificar que existan valores de IA para comparar
    const tieneValoresIA = (registro.codigo_sorteo_ia !== null && registro.codigo_sorteo_ia !== '') ||
        (registro.puntos_ia !== null && registro.puntos_ia !== '');

    if (!tieneValoresIA) {
        return '<span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle"></i> Revisar</span>';
    }

    // Verificar si los valores de IA coinciden con los guardados
    const codigoCoincide = registro.numero_factura == registro.codigo_sorteo_ia;
    const puntosCoinciden = registro.puntos_factura == registro.puntos_ia;

    // Solo es "Verificado" si AMBOS coinciden exactamente
    if (codigoCoincide && puntosCoinciden) {
        return '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Verificado</span>';
    } else {
        return '<span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle"></i> Revisar</span>';
    }
}

function renderizarTabla(registros) {
    console.log('Renderizando tabla con', registros.length, 'registros');
    const tbody = $('#tablaSorteosBody');
    tbody.empty();

    if (!registros || registros.length === 0) {
        tbody.append('<tr><td colspan="10" class="text-center py-4">No se encontraron registros</td></tr>');
        return;
    }

    registros.forEach(registro => {
        // Icono de válido/inválido
        const validoIcon = registro.valido == 1
            ? '<i class="bi bi-check-circle-fill valido-icon valid" title="Válido"></i>'
            : '<i class="bi bi-x-circle-fill valido-icon invalid" title="Inválido"></i>';

        // Convertir fecha a formato dd/MMM/yy
        const fechaUTC = new Date(registro.fecha_registro + ' UTC');
        const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        const dia = String(fechaUTC.getDate()).padStart(2, '0');
        const mes = meses[fechaUTC.getMonth()];
        const año = String(fechaUTC.getFullYear()).slice(-2);
        const fecha = `${dia}/${mes}/${año}`;

        tbody.append(`
            <tr>
                <td>${registro.nombre_completo}</td>
                <td>${registro.numero_cedula || '-'}</td>
                <td>${registro.numero_contacto}</td>
                <td>${registro.correo_electronico || '-'}</td>
                <td>${parseFloat(registro.monto_factura).toFixed(2)}</td>
                <td>${registro.numero_factura}</td>
                <td>${registro.puntos_factura}</td>
                <td>${fecha}</td>
                <td class="text-center">${getVerificacionBadge(registro)}</td>
                <td class="text-center">${validoIcon}</td>
                <td>
                    <button class="btn btn-sm btn-primary btn-ver-foto" data-id="${registro.id}" title="Ver Detalle">
                        <i class="bi bi-eye"></i> Ver
                    </button>
                </td>
            </tr>
        `);
    });
}

// Circle Filter Function for Valido
function setValidoFilter(state) {
    // Update global state
    validoFilterState = state;

    // Update circle appearances
    document.querySelectorAll('.filter-circle').forEach(circle => {
        circle.classList.remove('active');
    });
    document.querySelector(`.filter-circle[data-state="${state}"]`).classList.add('active');

    // Reload data with new filter
    paginaActual = 1;
    cargarRegistros();
}


// 3-State Toggle Filter Function
function toggleValidoFilter() {
    const button = document.querySelector('.valido-filter-toggle');
    const icon = button.querySelector('i');
    const text = button.querySelector('span');

    // Cycle through states: all -> valid -> invalid -> all
    if (validoFilterState === 'all') {
        validoFilterState = 'valid';
        button.classList.remove('state-all');
        button.classList.add('state-valid');
        icon.className = 'bi bi-check-circle';
        text.textContent = 'Válidos';
    } else if (validoFilterState === 'valid') {
        validoFilterState = 'invalid';
        button.classList.remove('state-valid');
        button.classList.add('state-invalid');
        icon.className = 'bi bi-x-circle';
        text.textContent = 'Inválidos';
    } else {
        validoFilterState = 'all';
        button.classList.remove('state-invalid');
        button.classList.add('state-all');
        icon.className = 'bi bi-circle';
        text.textContent = 'Todos';
    }

    // Reload data with new filter
    paginaActual = 1;
    cargarRegistros();
}

// Comparison Modal Function
function verFoto(id) {
    // Cargar datos del registro incluyendo campos de IA
    $.ajax({
        url: `ajax/get_registros_sorteos.php?id=${id}`,
        method: 'GET',
        dataType: 'json',
        success: function (response) {
            if (response.success && response.data.length > 0) {
                const registro = response.data[0];

                // Convertir fecha a zona horaria de Nicaragua
                const fechaUTC = new Date(registro.fecha_registro + ' UTC');
                const fecha = fechaUTC.toLocaleString('es-NI', {
                    timeZone: 'America/Managua',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true
                });

                // Helper function to check if values differ
                const isDifferent = (val1, val2) => {
                    return val1 != val2 && val2 != null && val2 !== '';
                };

                // Build 3-column comparison layout
                const modalBody = $('.modal-body', '#modalVerFoto');
                modalBody.html(`
                    <div class="modal-comparison-container">
                        <!-- Column 1: Photo -->
                        <div class="comparison-column">
                            <h6><i class="bi bi-image"></i> Foto de Factura</h6>
                            <img src="https://pitayalove.batidospitaya.com/uploads/${registro.foto_factura}" 
                                 alt="Factura" 
                                 class="comparison-photo"
                                 onclick="window.open(this.src, '_blank')">
                        </div>

                        <!-- Column 2: Stored Data -->
                        <div class="comparison-column">
                            <h6><i class="bi bi-database"></i> Datos Guardados</h6>
                            <div class="comparison-data">
                                <div class="comparison-row">
                                    <div class="comparison-label">ID</div>
                                    <div class="comparison-value">${registro.id}</div>
                                </div>
                                <div class="comparison-row">
                                    <div class="comparison-label">Fecha Registro</div>
                                    <div class="comparison-value">${fecha}</div>
                                </div>
                                <div class="comparison-row">
                                    <div class="comparison-label">Nombre Completo</div>
                                    <div class="comparison-value">${registro.nombre_completo}</div>
                                </div>
                                <div class="comparison-row">
                                    <div class="comparison-label">No. Contacto</div>
                                    <div class="comparison-value">${registro.numero_contacto}</div>
                                </div>
                                <div class="comparison-row">
                                    <div class="comparison-label">No. Cédula</div>
                                    <div class="comparison-value">${registro.numero_cedula || 'N/A'}</div>
                                </div>
                                <div class="comparison-row ${isDifferent(registro.numero_factura, registro.codigo_sorteo_ia) ? 'highlight-diff' : ''}">
                                    <div class="comparison-label">No. Factura / Código Sorteo</div>
                                    <div class="comparison-value">${registro.numero_factura}</div>
                                </div>
                                <div class="comparison-row">
                                    <div class="comparison-label">Correo Electrónico</div>
                                    <div class="comparison-value">${registro.correo_electronico || 'N/A'}</div>
                                </div>
                                <div class="comparison-row">
                                    <div class="comparison-label">Monto Factura</div>
                                    <div class="comparison-value">C$ ${parseFloat(registro.monto_factura).toFixed(2)}</div>
                                </div>
                                <div class="comparison-row ${isDifferent(registro.puntos_factura, registro.puntos_ia) ? 'highlight-diff' : ''}">
                                    <div class="comparison-label">Puntos</div>
                                    <div class="comparison-value">${registro.puntos_factura}</div>
                                </div>
                                <div class="comparison-row">
                                    <div class="comparison-label">Tipo QR</div>
                                    <div class="comparison-value">${registro.tipo_qr === 'online' ? 'Online' : 'Offline'}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Column 3: AI Detected Data -->
                        <div class="comparison-column">
                            <h6><i class="bi bi-robot"></i> Datos Detectados por IA</h6>
                            <div class="comparison-data">
                                <div class="comparison-row">
                                    <div class="comparison-label">Estado Validación IA</div>
                                    <div class="comparison-value">
                                        ${registro.validado_ia == 1
                        ? '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Validado</span>'
                        : '<span class="badge bg-danger"><i class="bi bi-x-circle"></i> No Validado</span>'}
                                    </div>
                                </div>
                                <div class="comparison-row ${isDifferent(registro.numero_factura, registro.codigo_sorteo_ia) ? 'highlight-diff' : ''}">
                                    <div class="comparison-label">Código Sorteo (IA)</div>
                                    <div class="comparison-value">${registro.codigo_sorteo_ia || 'No detectado'}</div>
                                </div>
                                <div class="comparison-row ${isDifferent(registro.puntos_factura, registro.puntos_ia) ? 'highlight-diff' : ''}">
                                    <div class="comparison-label">Puntos (IA)</div>
                                    <div class="comparison-value">${registro.puntos_ia || 'No detectado'}</div>
                                </div>
                                ${registro.validado_ia == 0 ? `
                                    <div class="alert alert-warning mt-3 small">
                                        <i class="bi bi-exclamation-triangle"></i>
                                        <strong>Nota:</strong> La IA no pudo validar esta factura automáticamente.
                                    </div>
                                ` : ''}
                                ${(isDifferent(registro.numero_factura, registro.codigo_sorteo_ia) || isDifferent(registro.puntos_factura, registro.puntos_ia)) ? `
                                    <div class="alert alert-info mt-3 small">
                                        <i class="bi bi-info-circle"></i>
                                        <strong>Diferencias detectadas:</strong> Los campos resaltados muestran discrepancias entre los datos guardados y los detectados por la IA.
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>

                    <!-- Toggle Switch for Valido Status -->
                    ${tienePermisoEdicion ? `
                        <div class="valido-toggle-container">
                            <span class="valido-toggle-label">Estado del Registro:</span>
                            <label class="toggle-switch">
                                <input type="checkbox" 
                                       id="toggleValido" 
                                       ${registro.valido == 1 ? 'checked' : ''}
                                       onchange="toggleValidoRegistro(${registro.id}, this.checked)">
                                <span class="toggle-slider"></span>
                            </label>
                            <span class="toggle-status-text ${registro.valido == 1 ? 'valid' : 'invalid'}" id="toggleStatusText">
                                ${registro.valido == 1 ? '✓ Válido' : '✗ Inválido'}
                            </span>
                        </div>
                    ` : `
                        <div class="alert alert-secondary text-center mt-3">
                            <strong>Estado:</strong> 
                            ${registro.valido == 1
                        ? '<span class="text-success">✓ Válido</span>'
                        : '<span class="text-danger">✗ Inválido</span>'}
                        </div>
                    `}
                `);

                // Show modal
                new bootstrap.Modal(document.getElementById('modalVerFoto')).show();
            }
        },
        error: function () {
            mostrarError('Error al cargar los detalles del registro');
        }
    });
}

// Toggle Valido Status Function
function toggleValidoRegistro(id, isValid) {
    const nuevoEstado = isValid ? 1 : 0;

    $.ajax({
        url: 'ajax/toggle_valido_registro.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            id: id,
            valido: nuevoEstado
        }),
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                // Update status text
                const statusText = document.getElementById('toggleStatusText');
                if (statusText) {
                    statusText.textContent = isValid ? '✓ Válido' : '✗ Inválido';
                    statusText.className = 'toggle-status-text ' + (isValid ? 'valid' : 'invalid');
                }

                // Reload table to reflect changes
                cargarRegistros();
            } else {
                // Revert toggle if failed
                document.getElementById('toggleValido').checked = !isValid;
                mostrarError(response.message || 'Error al actualizar el estado');
            }
        },
        error: function () {
            // Revert toggle if failed
            document.getElementById('toggleValido').checked = !isValid;
            mostrarError('Error al actualizar el estado del registro');
        }
    });
}

function cambiarRegistrosPorPagina() {
    registrosPorPagina = parseInt($('#registrosPorPagina').val());
    paginaActual = 1;
    cargarRegistros();
}

function renderizarPaginacion(totalPaginas, paginaActual) {
    const paginacion = $('#paginacion');
    paginacion.empty();

    if (totalPaginas <= 1) return;

    let html = '<nav><ul class="pagination pagination-sm mb-0">';

    // Botón anterior
    html += `<li class="page-item ${paginaActual === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="cambiarPagina(${paginaActual - 1}); return false;">Anterior</a>
    </li>`;

    // Números de página
    for (let i = 1; i <= totalPaginas; i++) {
        if (i === 1 || i === totalPaginas || (i >= paginaActual - 2 && i <= paginaActual + 2)) {
            html += `<li class="page-item ${i === paginaActual ? 'active' : ''}">
                <a class="page-link" href="#" onclick="cambiarPagina(${i}); return false;">${i}</a>
            </li>`;
        } else if (i === paginaActual - 3 || i === paginaActual + 3) {
            html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    // Botón siguiente
    html += `<li class="page-item ${paginaActual === totalPaginas ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="cambiarPagina(${paginaActual + 1}); return false;">Siguiente</a>
    </li>`;

    html += '</ul></nav>';
    paginacion.html(html);
}

function cambiarPagina(pagina) {
    paginaActual = pagina;
    cargarRegistros();
}

function mostrarExito(mensaje) {
    alert(mensaje); // TODO: Implementar toast notifications
}

function mostrarError(mensaje) {
    alert(mensaje); // TODO: Implementar toast notifications
}

// ========== SISTEMA DE FILTROS ==========
let panelFiltroAbierto = null;
let scrollTopInicial = 0;

// Cerrar filtros al hacer clic fuera
$(document).on('click', function (e) {
    if (!$(e.target).closest('.filter-panel, .filter-icon').length) {
        cerrarTodosFiltros();
    }
});

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

    // Ordenamiento
    panel.append(`
        <div class="filter-section">
            <span class="filter-section-title">Ordenar:</span>
            <div class="filter-sort-buttons">
                <button class="filter-sort-btn ${ordenActivo.columna === columna && ordenActivo.direccion === 'asc' ? 'active' : ''}" 
                        onclick="aplicarOrden('${columna}', 'asc')">
                    <i class="bi bi-sort-alpha-down"></i> ASC
                </button>
                <button class="filter-sort-btn ${ordenActivo.columna === columna && ordenActivo.direccion === 'desc' ? 'active' : ''}" 
                        onclick="aplicarOrden('${columna}', 'desc')">
                    <i class="bi bi-sort-alpha-up"></i> DESC
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
        const valorActual = filtrosActivos[columna] || '';
        panel.append(`
            <div class="filter-section" style="margin-top: 12px;">
                <span class="filter-section-title">Buscar:</span>
                <input type="text" class="filter-search" placeholder="Escribir..." 
                       value="${valorActual}"
                       oninput="filtrarBusqueda('${columna}', this.value)">
            </div>
        `);
    } else if (tipo === 'number') {
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
    } else if (tipo === 'list') {
        panel.append(`
            <div class="filter-section" style="margin-top: 12px;">
                <span class="filter-section-title">Filtrar por:</span>
                <div class="filter-options">
                    ${columna === 'tipo_qr' ? `
                        <div class="filter-option">
                            <input type="checkbox" value="online" 
                                   ${filtrosActivos[columna]?.includes('online') ? 'checked' : ''}
                                   onchange="toggleOpcionFiltro('${columna}', 'online', this.checked)">
                            <span>Online</span>
                        </div>
                        <div class="filter-option">
                            <input type="checkbox" value="offline" 
                                   ${filtrosActivos[columna]?.includes('offline') ? 'checked' : ''}
                                   onchange="toggleOpcionFiltro('${columna}', 'offline', this.checked)">
                            <span>Offline</span>
                        </div>
                    ` : columna === 'verificacion_ia' ? `
                        <div class="filter-option">
                            <input type="checkbox" value="verificado" 
                                   ${filtrosActivos[columna]?.includes('verificado') ? 'checked' : ''}
                                   onchange="toggleOpcionFiltro('${columna}', 'verificado', this.checked)">
                            <span>Verificado</span>
                        </div>
                        <div class="filter-option">
                            <input type="checkbox" value="revisar" 
                                   ${filtrosActivos[columna]?.includes('revisar') ? 'checked' : ''}
                                   onchange="toggleOpcionFiltro('${columna}', 'revisar', this.checked)">
                            <span>Revisar</span>
                        </div>
                    ` : ''}
                </div>
            </div>
        `);
    } else if (tipo === 'daterange') {
        crearCalendarioDoble(panel, columna);
    }

    posicionarPanelFiltro(panel, icon);
}

// Posicionar panel
function posicionarPanelFiltro(panel, icon) {
    const iconOffset = $(icon).offset();
    const iconWidth = $(icon).outerWidth();
    const iconHeight = $(icon).outerHeight();
    const panelWidth = panel.outerWidth();
    const windowWidth = $(window).width();

    let top = iconOffset.top + iconHeight + 5;
    let left = iconOffset.left - panelWidth + iconWidth;

    if (left + panelWidth > windowWidth) {
        left = windowWidth - panelWidth - 10;
    }
    if (left < 10) {
        left = 10;
    }

    panel.css({
        top: top + 'px',
        left: left + 'px'
    });
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

    // Actualizar el calendario visualmente
    actualizarCalendarioUnico(columna);

    // Aplicar filtro si ya tenemos un rango completo
    if (filtrosActivos[columna].desde && filtrosActivos[columna].hasta) {
        paginaActual = 1;
        cargarRegistros();
    }
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
    cargarRegistros();
}

// Cerrar filtros
function cerrarTodosFiltros() {
    $('.filter-panel').remove();
    $('.filter-icon').removeClass('active');
    panelFiltroAbierto = null;
}

// Filtrar búsqueda
function filtrarBusqueda(columna, valor) {
    if (valor.trim() === '') {
        delete filtrosActivos[columna];
    } else {
        filtrosActivos[columna] = valor;
    }
    paginaActual = 1;
    cargarRegistros();
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

    paginaActual = 1;
    cargarRegistros();
}

// Filtrar fecha
function filtrarFecha(columna, tipo, valor) {
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

    paginaActual = 1;
    cargarRegistros();
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
    cargarRegistros();
}

// Aplicar orden
function aplicarOrden(columna, direccion) {
    ordenActivo = { columna, direccion };
    paginaActual = 1;
    cargarRegistros();
}
