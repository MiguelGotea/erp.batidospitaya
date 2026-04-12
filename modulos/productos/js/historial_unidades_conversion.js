let paginaActual = 1;
let registrosPorPagina = 25;
let filtrosActivos = {};
let ordenActivo = { columna: null, direccion: 'asc' };
let panelFiltroAbierto = null;
let totalRegistros = 0;
let modalUnidad;
let modalConversion;
let modalHistorial;
let scrollTopInicial = 0;

// Inicializar
$(document).ready(function () {
    modalUnidad = new bootstrap.Modal(document.getElementById('modalUnidad'));
    modalConversion = new bootstrap.Modal(document.getElementById('modalConversion'));
    modalHistorial = new bootstrap.Modal(document.getElementById('modalHistorial'));

    cargarDatos();

    // Cerrar filtros solo si se hace clic fuera del panel Y del icono
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.filter-panel, .filter-icon').length) {
            cerrarTodosFiltros();
        }
    });

    // NO cerrar filtros al hacer scroll
    $('.table-responsive').on('scroll', function (e) {
        e.stopPropagation();
    });

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

    // Actualizar ejemplo en modal de conversión
    $('#cantidad, #unidadFinal').on('change', actualizarEjemplo);
});

// ==================== CARGA DE DATOS ====================
function cargarDatos() {
    $.ajax({
        url: 'ajax/unidades_get_datos.php',
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
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function () {
            Swal.fire('Error', 'Error al cargar los datos', 'error');
        }
    });
}

// ==================== RENDERIZAR TABLA ====================
function renderizarTabla(datos) {
    const tbody = $('#tablaUnidadesBody');
    tbody.empty();

    if (datos.length === 0) {
        const colspan = datos.puede_crear ? 3 : 2;
        tbody.append(`<tr><td colspan="${colspan}" class="text-center py-4">No se encontraron registros</td></tr>`);
        return;
    }

    datos.forEach(row => {
        const tr = $('<tr>');

        tr.append(`<td><strong>${row.nombre}</strong></td>`);
        tr.append(`<td>${row.observaciones || '-'}</td>`);

        // Botones de acciones - solo si tiene permiso
        if (row.puede_crear) {
            let btnAcciones = `
                <button class="btn-accion btn-conversion" onclick="abrirModalConversion(${row.id}, '${row.nombre}')" 
                        title="Nueva conversión desde ${row.nombre}">
                    <i class="bi bi-arrow-left-right"></i>
                </button>
                <button class="btn-accion btn-historial" onclick="abrirModalHistorial(${row.id}, '${row.nombre}')" 
                        title="Ver historial de conversiones">
                    <i class="bi bi-clock-history"></i>
                </button>
            `;
            tr.append(`<td>${btnAcciones}</td>`);
        }

        tbody.append(tr);
    });
}

// ==================== MODAL NUEVA UNIDAD ====================
function abrirModalNuevaUnidad() {
    $('#modalUnidadTitulo').text('Nueva Unidad');
    $('#formUnidad')[0].reset();
    $('#unidadId').val('');
    modalUnidad.show();
}

function guardarUnidad() {
    const formData = $('#formUnidad').serialize();
    const id = $('#unidadId').val();
    const accion = id ? 'editar' : 'crear';

    // Validación básica
    const nombre = $('#nombreUnidad').val().trim();
    if (!nombre) {
        Swal.fire('Advertencia', 'El nombre de la unidad es requerido', 'warning');
        return;
    }

    $.ajax({
        url: 'ajax/unidades_guardar.php',
        method: 'POST',
        data: formData + '&accion=' + accion,
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                modalUnidad.hide();
                cargarDatos();
                Swal.fire('¡Éxito!', response.message, 'success');
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function () {
            Swal.fire('Error', 'Error al guardar la unidad', 'error');
        }
    });
}

// ==================== MODAL NUEVA CONVERSIÓN ====================
function abrirModalConversion(idUnidad, nombreUnidad) {
    $('#formConversion')[0].reset();
    $('#conversionUnidadInicio').val(idUnidad);
    $('#nombreUnidadInicio').val(nombreUnidad);
    $('#ejemploInicio').text(nombreUnidad);

    // Cargar unidades disponibles (excluyendo la actual)
    $.ajax({
        url: 'ajax/unidades_get_lista.php',
        method: 'POST',
        data: { excluir_id: idUnidad },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                const select = $('#unidadFinal');
                select.empty();
                select.append('<option value="">Seleccionar unidad...</option>');

                response.unidades.forEach(function (unidad) {
                    select.append(`<option value="${unidad.id}">${unidad.nombre}</option>`);
                });
            }
        }
    });

    modalConversion.show();
}

function guardarConversion() {
    const formData = $('#formConversion').serialize();

    // Validaciones
    const idUnidadFinal = $('#unidadFinal').val();
    const cantidad = parseFloat($('#cantidad').val());

    if (!idUnidadFinal) {
        Swal.fire('Advertencia', 'Debe seleccionar la unidad de salida', 'warning');
        return;
    }

    if (!cantidad || cantidad <= 0) {
        Swal.fire('Advertencia', 'La cantidad de conversión debe ser mayor que 0', 'warning');
        return;
    }

    $.ajax({
        url: 'ajax/conversion_guardar.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                modalConversion.hide();
                Swal.fire('¡Éxito!', response.message, 'success');
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function () {
            Swal.fire('Error', 'Error al guardar la conversión', 'error');
        }
    });
}

// ==================== MODAL HISTORIAL ====================
function abrirModalHistorial(idUnidad, nombreUnidad) {
    $('#historialNombreUnidad').text(nombreUnidad);

    // Mostrar loading
    $('#historialConversiones').html(`
        <div class="sin-conversiones">
            <i class="bi bi-hourglass-split"></i>
            <p>Cargando historial...</p>
        </div>
    `);

    $.ajax({
        url: 'ajax/conversion_get_historial.php',
        method: 'POST',
        data: { id_unidad: idUnidad },
        dataType: 'json',
        success: function (response) {
            console.log('Respuesta del servidor:', response); // Para depuración

            if (response.success) {
                if (response.conversiones && response.conversiones.length > 0) {
                    renderizarHistorial(response.conversiones);
                } else {
                    $('#historialConversiones').html(`
                        <div class="sin-conversiones">
                            <i class="bi bi-inbox"></i>
                            <p>No hay conversiones registradas para esta unidad</p>
                        </div>
                    `);
                }
            } else {
                $('#historialConversiones').html(`
                    <div class="sin-conversiones text-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                        <p>Error: ${response.message}</p>
                    </div>
                `);
            }
        },
        error: function (xhr, status, error) {
            console.error('Error en AJAX:', { xhr, status, error }); // Para depuración
            $('#historialConversiones').html(`
                <div class="sin-conversiones text-danger">
                    <i class="bi bi-exclamation-triangle"></i>
                    <p>Error al cargar el historial</p>
                    <small>Por favor, intente nuevamente</small>
                </div>
            `);
        }
    });

    modalHistorial.show();
}

function renderizarHistorial(conversiones) {
    const container = $('#historialConversiones');
    container.empty();

    if (conversiones.length === 0) {
        container.html(`
            <div class="sin-conversiones">
                <i class="bi bi-inbox"></i>
                <p>No hay conversiones registradas para esta unidad</p>
            </div>
        `);
        return;
    }

    conversiones.forEach(conv => {
        const item = $(`
            <div class="conversion-item">
                <div class="conversion-unidad">${conv.unidad_inicio}</div>
                <div class="conversion-arrow">
                    <div class="conversion-cantidad">${conv.cantidad}</div>
                    <i class="bi bi-arrow-right"></i>
                </div>
                <div class="conversion-unidad">${conv.unidad_final}</div>
            </div>
            <div class="conversion-fecha">
                Creado: ${formatearFecha(conv.fecha_creacion)} por ${conv.usuario_creacion}
            </div>
        `);
        container.append(item);
    });
}

// ==================== ACTUALIZAR EJEMPLO ====================
function actualizarEjemplo() {
    const cantidad = $('#cantidad').val() || $('#ejemploCantidad').val();
    const unidadFinal = $('#unidadFinal option:selected').text();

    $('#ejemploCantidad').val(cantidad);

    if (unidadFinal && unidadFinal !== 'Seleccionar unidad...') {
        $('#ejemploFinal').text(unidadFinal);
    }
}

// ==================== FILTROS ====================
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
    }

    posicionarPanelFiltro(panel, icon);
}

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
        left: left + 'px',
        maxHeight: Math.min(windowHeight - 100, panelHeight) + 'px'
    });
}

function actualizarIndicadoresFiltros() {
    $('.filter-icon').removeClass('has-filter');
    Object.keys(filtrosActivos).forEach(columna => {
        const valor = filtrosActivos[columna];
        if (valor !== '') {
            $(`th[data-column="${columna}"] .filter-icon`).addClass('has-filter');
        }
    });
}

function limpiarFiltro(columna) {
    delete filtrosActivos[columna];
    cerrarTodosFiltros();
    paginaActual = 1;
    cargarDatos();
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

function filtrarBusqueda(columna, valor) {
    if (valor.trim() === '') {
        delete filtrosActivos[columna];
    } else {
        filtrosActivos[columna] = valor;
    }
    paginaActual = 1;
    cargarDatos();
}

// ==================== PAGINACIÓN ====================
function cambiarRegistrosPorPagina() {
    registrosPorPagina = parseInt($('#registrosPorPagina').val());
    paginaActual = 1;
    cargarDatos();
}

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

function cambiarPagina(pagina) {
    if (pagina < 1 || pagina > Math.ceil(totalRegistros / registrosPorPagina)) return;
    paginaActual = pagina;
    cargarDatos();
}

// ==================== UTILIDADES ====================
function formatearFecha(fecha) {
    if (!fecha) return '-';
    const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    const d = new Date(fecha);
    const año = String(d.getFullYear()).slice(-2);
    return `${String(d.getDate()).padStart(2, '0')}-${meses[d.getMonth()]}-${año}`;
}