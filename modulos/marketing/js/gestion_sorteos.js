// gestion_sorteos.js

let paginaActual = 1;
let registrosPorPagina = 50;
let filtrosActivos = {};
// tienePermisoEdicion is set from PHP inline script

$(document).ready(function () {
    cargarRegistros();
});

function cargarRegistros() {
    const params = new URLSearchParams({
        page: paginaActual,
        per_page: registrosPorPagina,
        ...filtrosActivos
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

function renderizarTabla(registros) {
    console.log('Renderizando tabla con', registros.length, 'registros');
    const tbody = $('#tablaSorteosBody');
    tbody.empty();

    if (!registros || registros.length === 0) {
        tbody.append('<tr><td colspan="11" class="text-center py-4">No se encontraron registros</td></tr>');
        return;
    }

    registros.forEach(registro => {
        const validadoBadge = registro.validado_ia == 1
            ? '<span class="validado-badge validado-si">✅ Validado</span>'
            : '<span class="validado-badge validado-no">❌ No validado</span>';

        const tipoBadge = registro.tipo_qr === 'online'
            ? '<span class="tipo-qr-badge tipo-online">Online</span>'
            : '<span class="tipo-qr-badge tipo-offline">Offline</span>';

        // Convertir fecha a zona horaria de Nicaragua (UTC-6)
        const fechaUTC = new Date(registro.fecha_registro + ' UTC');
        const fecha = fechaUTC.toLocaleString('es-NI', {
            timeZone: 'America/Managua',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });

        const btnEliminar = tienePermisoEdicion
            ? `<button class="btn btn-sm btn-danger" onclick="eliminarRegistro(${registro.id})" title="Eliminar">
                   <i class="bi bi-trash"></i>
               </button>`
            : '';

        tbody.append(`
            <tr>
                <td>${fecha}</td>
                <td>${registro.nombre_completo}</td>
                <td>${registro.numero_contacto}</td>
                <td>${registro.numero_cedula || '-'}</td>
                <td>${registro.numero_factura}</td>
                <td>${registro.correo_electronico || '-'}</td>
                <td>C$ ${parseFloat(registro.monto_factura).toFixed(2)}</td>
                <td>${registro.puntos_factura}</td>
                <td>${tipoBadge}</td>
                <td>${validadoBadge}</td>
                <td>
                    <button class="btn btn-sm btn-primary btn-ver-foto" onclick="verFoto(${registro.id}, '${registro.foto_factura}')" title="Ver Foto">
                        <i class="bi bi-eye"></i> Ver
                    </button>
                    ${btnEliminar}
                </td>
            </tr>
        `);
    });
}

function verFoto(id, fotoNombre) {
    // Cargar datos del registro
    $.ajax({
        url: `ajax/get_registros_sorteos.php?id=${id}`,
        method: 'GET',
        dataType: 'json',
        success: function (response) {
            if (response.success && response.data.length > 0) {
                const registro = response.data[0];

                // Mostrar foto
                $('#fotoFactura').attr('src', `../PitayaLove/uploads/${fotoNombre}`);

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

                // Badges estilizados
                const validadoBadge = registro.validado_ia == 1
                    ? '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Validado por IA</span>'
                    : '<span class="badge bg-danger"><i class="bi bi-x-circle"></i> No validado</span>';

                const tipoBadge = registro.tipo_qr === 'online'
                    ? '<span class="badge bg-primary"><i class="bi bi-wifi"></i> Online</span>'
                    : '<span class="badge bg-warning text-dark"><i class="bi bi-qr-code"></i> Offline</span>';

                $('#datosRegistro').html(`
    < div class="info-row" >
                        <span class="info-label"><i class="bi bi-hash"></i> ID:</span>
                        <span class="info-value">${registro.id}</span>
                    </div >
                    <div class="info-row">
                        <span class="info-label"><i class="bi bi-calendar-event"></i> Fecha:</span>
                        <span class="info-value">${fecha}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="bi bi-person"></i> Nombre:</span>
                        <span class="info-value">${registro.nombre_completo}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="bi bi-phone"></i> Contacto:</span>
                        <span class="info-value">${registro.numero_contacto}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="bi bi-card-text"></i> Cédula:</span>
                        <span class="info-value">${registro.numero_cedula || 'N/A'}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="bi bi-receipt"></i> No. Factura:</span>
                        <span class="info-value">${registro.numero_factura}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="bi bi-envelope"></i> Correo:</span>
                        <span class="info-value">${registro.correo_electronico || 'N/A'}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="bi bi-currency-dollar"></i> Monto:</span>
                        <span class="info-value">C$ ${parseFloat(registro.monto_factura).toFixed(2)}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="bi bi-star"></i> Puntos:</span>
                        <span class="info-value">${registro.puntos_factura}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="bi bi-qr-code-scan"></i> Tipo QR:</span>
                        <span class="info-value">${tipoBadge}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="bi bi-robot"></i> Validado IA:</span>
                        <span class="info-value">${validadoBadge}</span>
                    </div>
`);

                // Mostrar modal
                new bootstrap.Modal(document.getElementById('modalVerFoto')).show();
            }
        }
    });
}

let registroAEliminar = null;

function eliminarRegistro(id) {
    // Guardar el ID y mostrar modal de confirmación
    registroAEliminar = id;
    const modal = new bootstrap.Modal(document.getElementById('modalConfirmarEliminar'));
    modal.show();
}

// Manejar confirmación de eliminación
$(document).on('click', '#btnConfirmarEliminar', function () {
    if (!registroAEliminar) return;

    $.ajax({
        url: 'ajax/delete_registro_sorteo.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ id: registroAEliminar }),
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                mostrarExito(response.message);
                cargarRegistros();
                // Cerrar modal
                bootstrap.Modal.getInstance(document.getElementById('modalConfirmarEliminar')).hide();
            } else {
                mostrarError(response.message);
            }
            registroAEliminar = null;
        },
        error: function () {
            mostrarError('Error al eliminar registro');
            registroAEliminar = null;
        }
    });
});

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
    html += `< li class="page-item ${paginaActual === 1 ? 'disabled' : ''}" >
    <a class="page-link" href="#" onclick="cambiarPagina(${paginaActual - 1}); return false;">Anterior</a>
    </li > `;

    // Números de página
    for (let i = 1; i <= totalPaginas; i++) {
        if (i === 1 || i === totalPaginas || (i >= paginaActual - 2 && i <= paginaActual + 2)) {
            html += `< li class="page-item ${i === paginaActual ? 'active' : ''}" >
    <a class="page-link" href="#" onclick="cambiarPagina(${i}); return false;">${i}</a>
            </li > `;
        } else if (i === paginaActual - 3 || i === paginaActual + 3) {
            html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    // Botón siguiente
    html += `< li class="page-item ${paginaActual === totalPaginas ? 'disabled' : ''}" >
    <a class="page-link" href="#" onclick="cambiarPagina(${paginaActual + 1}); return false;">Siguiente</a>
    </li > `;

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

    // Botones de acción
    panel.append(`
    < div class="filter-actions" >
        <button class="filter-action-btn clear" onclick="limpiarFiltro('${columna}')">
            <i class="bi bi-x-circle"></i> Limpiar
        </button>
        </div >
    `);

    $('body').append(panel);

    // Filtros según tipo
    if (tipo === 'text') {
        const valorActual = filtrosActivos[columna] || '';
        panel.append(`
    < div class="filter-section" style = "margin-top: 12px;" >
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
    < div class="filter-section" style = "margin-top: 12px;" >
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
    < div class="filter-section" style = "margin-top: 12px;" >
                <span class="filter-section-title">Filtrar por:</span>
                <div class="filter-options">
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
            $(`th[data - column= "${columna}"] .filter - icon`).addClass('has-filter');
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
