// gestion_sorteos.js

let paginaActual = 1;
let registrosPorPagina = 50;
let filtrosActivos = {};
let tienePermisoEdicion = false; // Will be set from PHP inline script

$(document).ready(function () {
    cargarRegistros();
});

function cargarRegistros() {
    const params = new URLSearchParams({
        page: paginaActual,
        per_page: registrosPorPagina,
        ...filtrosActivos
    });

    $.ajax({
        url: `ajax/get_registros_sorteos.php?${params}`,
        method: 'GET',
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                renderizarTabla(response.data);
                renderizarPaginacion(response.total_pages, response.page);
            } else {
                mostrarError('Error al cargar registros');
            }
        },
        error: function () {
            mostrarError('Error de conexión');
        }
    });
}

function renderizarTabla(registros) {
    const tbody = $('#tablaSorteosBody');
    tbody.empty();

    if (registros.length === 0) {
        tbody.append(`
            <tr>
                <td colspan="12" class="text-center text-muted py-4">
                    No se encontraron registros
                </td>
            </tr>
        `);
        return;
    }

    registros.forEach(registro => {
        const validadoBadge = registro.validado_ia == 1
            ? '<span class="validado-badge validado-si">✅ Validado</span>'
            : '<span class="validado-badge validado-no">❌ No validado</span>';

        const tipoBadge = registro.tipo_qr === 'online'
            ? '<span class="tipo-qr-badge tipo-online">Online</span>'
            : '<span class="tipo-qr-badge tipo-offline">Offline</span>';

        const fecha = new Date(registro.fecha_registro).toLocaleString('es-NI', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });

        const btnEliminar = tienePermisoEdicion
            ? `<button class="btn btn-sm btn-danger" onclick="eliminarRegistro(${registro.id})" title="Eliminar">
                   <i class="bi bi-trash"></i>
               </button>`
            : '';

        tbody.append(`
            <tr>
                <td>${registro.id}</td>
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

                // Mostrar datos
                const validado = registro.validado_ia == 1 ? '✅ Sí' : '❌ No';
                const fecha = new Date(registro.fecha_registro).toLocaleString('es-NI');

                $('#datosRegistro').html(`
                    <div class="mb-2"><strong>ID:</strong> ${registro.id}</div>
                    <div class="mb-2"><strong>Fecha:</strong> ${fecha}</div>
                    <div class="mb-2"><strong>Nombre:</strong> ${registro.nombre_completo}</div>
                    <div class="mb-2"><strong>Contacto:</strong> ${registro.numero_contacto}</div>
                    <div class="mb-2"><strong>Cédula:</strong> ${registro.numero_cedula || 'N/A'}</div>
                    <div class="mb-2"><strong>No. Factura:</strong> ${registro.numero_factura}</div>
                    <div class="mb-2"><strong>Correo:</strong> ${registro.correo_electronico || 'N/A'}</div>
                    <div class="mb-2"><strong>Monto:</strong> C$ ${parseFloat(registro.monto_factura).toFixed(2)}</div>
                    <div class="mb-2"><strong>Puntos:</strong> ${registro.puntos_factura}</div>
                    <div class="mb-2"><strong>Tipo QR:</strong> ${registro.tipo_qr}</div>
                    <div class="mb-2"><strong>Validado IA:</strong> ${validado}</div>
                `);

                // Mostrar modal
                new bootstrap.Modal(document.getElementById('modalVerFoto')).show();
            }
        }
    });
}

function eliminarRegistro(id) {
    if (!confirm('¿Está seguro de eliminar este registro? Esta acción no se puede deshacer.')) {
        return;
    }

    $.ajax({
        url: 'ajax/delete_registro_sorteo.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ id: id }),
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                mostrarExito(response.message);
                cargarRegistros();
            } else {
                mostrarError(response.message);
            }
        },
        error: function () {
            mostrarError('Error al eliminar registro');
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
        const fechaDesde = filtrosActivos[columna]?.desde || '';
        const fechaHasta = filtrosActivos[columna]?.hasta || '';
        panel.append(`
            <div class="filter-section" style="margin-top: 12px;">
                <span class="filter-section-title">Rango de fechas:</span>
                <div class="numeric-inputs">
                    <input type="date" class="filter-search" 
                           value="${fechaDesde}"
                           onchange="filtrarFecha('${columna}', 'desde', this.value)">
                    <input type="date" class="filter-search" 
                           value="${fechaHasta}"
                           onchange="filtrarFecha('${columna}', 'hasta', this.value)">
                </div>
            </div>
        `);
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
