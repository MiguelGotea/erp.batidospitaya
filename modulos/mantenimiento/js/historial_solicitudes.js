// Posicionar panel de filtro dinámicamente

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

    // Ajustar si se sale por la derecha
    if (left + panelWidth > windowWidth) {
        left = windowWidth - panelWidth - 10;
    }

    // Ajustar si se sale por la izquierda
    if (left < 10) {
        left = 10;
    }

    // Ajustar si se sale por abajo - mostrar arriba del icono
    if (top + panelHeight > windowHeight + scrollTop) {
        top = iconOffset.top - panelHeight - 5;
    }

    // Si aún así se sale por arriba, ajustar al top de la ventana
    if (top < scrollTop + 10) {
        top = scrollTop + 10;
        panel.css('max-height', (windowHeight - 60) + 'px');
    }

    panel.css({
        top: top + 'px',
        left: left + 'px'
    });
}

// Actualizar indicadores de filtros activos
function actualizarIndicadoresFiltros() {
    $('.filter-icon').removeClass('has-filter');

    Object.keys(filtrosActivos).forEach(columna => {
        const valor = filtrosActivos[columna];
        if ((Array.isArray(valor) && valor.length > 0) || (!Array.isArray(valor) && valor !== '')) {
            $(`th[data-column="${columna}"] .filter-icon`).addClass('has-filter');
        }
    });
}

// Limpiar filtro específico
function limpiarFiltro(columna) {
    // Si es sucursal y está bloqueado
    if (columna === 'nombre_sucursal' &&
        typeof filtroSucursalBloqueado !== 'undefined' &&
        filtroSucursalBloqueado) {
        if (typeof sucursalesLiderPropias !== 'undefined' && sucursalesLiderPropias.length > 1) {
            // Restablecer a todas sus sucursales permitidas
            filtrosActivos[columna] = [...sucursalesLiderPropias];
        } else {
            return;
        }
    } else {
        delete filtrosActivos[columna];
    }
    cerrarTodosFiltros();
    paginaActual = 1;
    cargarDatos();
    actualizarIndicadoresFiltros();
}// js/historial_solicitudes.js

let paginaActual = 1;
let registrosPorPagina = 25;
let filtrosActivos = {};
let ordenActivo = { columna: null, direccion: 'asc' };
let panelFiltroAbierto = null;
let totalRegistros = 0;

// Colores de urgencia
const coloresUrgencia = {
    0: '#8b8b8bff',
    1: '#28a745',
    2: '#ffc107',
    3: '#fd7e14',
    4: '#dc3545'
};

const textosUrgencia = {
    0: 'No Clasificado',
    1: 'No Urgente',
    2: 'Medio',
    3: 'Urgente',
    4: 'Crítico'
};

// Colores de estado
const coloresEstado = {
    'solicitado': '#6c757d',
    'clasificado': '#17a2b8',
    'agendado': '#ffc107',
    'finalizado': '#28a745',
    'cancelado': '#dc3545'
};

const textosEstado = {
    'solicitado': 'Solicitado',
    'clasificado': 'Clasificado',
    'agendado': 'Agendado',
    'finalizado': 'Finalizado',
    'cancelado': 'Cancelado'
};

const textosTipo = {
    'mantenimiento': 'Mantenimiento',
    'mantenimiento_general': 'Mantenimiento',
    'cambio_equipos': 'Equipos'
};

// Inicializar al cargar la página
$(document).ready(function () {
    cargarDatos();

    // Cerrar filtros al hacer click fuera
    $(document).on('click', function (e) {
        // No cerrar si el click es dentro del panel de filtro, el icono, o elementos del calendario
        if (!$(e.target).closest('.filter-panel, .filter-icon, .daterange-calendar-day, .daterange-month-selector').length) {
            cerrarTodosFiltros();
        }

        // Cerrar FAB si se hace clic fuera
        if (!$(e.target).closest('.fab-container').length) {
            $('.fab-container').removeClass('active');
            $('.btn-floating-pitaya').removeClass('active');
        }
    });

    // Manejar clic en el botón flotante (para móvil)
    $('.btn-floating-pitaya').on('click', function (e) {
        const container = $(this).closest('.fab-container');
        if (container.length) {
            container.toggleClass('active');
            $(this).toggleClass('active');
        }
    });

    // Cerrar filtros al hacer scroll
    $('.table-responsive').on('scroll', function () {
        cerrarTodosFiltros();
    });

    // Cerrar filtros al hacer scroll en la ventana
    $(window).on('scroll', function () {
        cerrarTodosFiltros();
    });

    // Recalcular posición al hacer resize
    $(window).on('resize', function () {
        if (panelFiltroAbierto) {
            cerrarTodosFiltros();
        }
    });
});

// Cargar datos de la tabla
function cargarDatos() {
    $.ajax({
        url: 'ajax/historial_get_solicitudes.php',
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
    const tbody = $('#tablaSolicitudesBody');
    tbody.empty();

    if (datos.length === 0) {
        tbody.append('<tr><td colspan="11" class="text-center py-4">No se encontraron registros</td></tr>');
        return;
    }

    datos.forEach(row => {
        const tr = $('<tr>');

        // Solicitado
        tr.append(`<td>${formatearFecha(row.created_at)}</td>`);

        // Título
        tr.append(`<td class="col-titulo" title="${row.titulo}">${renderizarTituloEditable(row.id, row.titulo)}</td>`);

        // Descripción
        tr.append(`<td class="col-descripcion" title="${row.descripcion}">${row.descripcion}</td>`);

        // Resolución
        const resText = row.resolucion || 'Sin resolución aún...';
        tr.append(`<td class="col-resolucion" title="${resText}">${renderizarResolucionEditable(row.id, row.resolucion)}</td>`);

        // Sucursal
        tr.append(`<td class="col-sucursal">${row.nombre_sucursal}</td>`);

        // Tipo
        tr.append(`<td>${renderizarTipoEditable(row.id, row.tipo_formulario)}</td>`);

        // Urgencia
        tr.append(`<td>${renderizarUrgencia(row.id, row.nivel_urgencia)}</td>`);

        // Tiempo Estimado
        tr.append(`<td>${renderizarTiempoEstimado(row.id, row.tiempo_estimado)}</td>`);

        // Estado
        tr.append(`<td>${renderizarEstadoEditable(row.id, row.status)}</td>`);

        // Agendado - con estilos según estado
        const fechaAgendadoHTML = renderizarFechaAgendado(row.fecha_inicio, row.status);
        tr.append(`<td class="col-agendado">${fechaAgendadoHTML}</td>`);

        // Foto
        const tieneFotos = parseInt(row.total_fotos) > 0;
        const btnFoto = tieneFotos
            ? `<button class="btn-foto-icon" onclick="mostrarFotos(${row.id})" title="Ver ${row.total_fotos} fotos"><i class="bi bi-camera-fill"></i><span class="foto-count-badge">${row.total_fotos}</span></button>`
            : `<button class="btn-foto-icon disabled" title="Sin fotos"><i class="bi bi-camera"></i></button>`;
        tr.append(`<td>${btnFoto}</td>`);

        // IA
        if (tienepermiso('consulta_ia')) {
            const esAnalizable = (
                ['solicitado', 'agendado'].includes(row.status) &&
                row.tipo_formulario === 'mantenimiento_general'
            );
            const btnIA = esAnalizable
                ? `<button class="btn-ia-icon" id="btn-ia-${row.id}" onclick="analizarConIA(${row.id})" title="Analizar con IA"><i class="bi bi-robot"></i></button>`
                : `<button class="btn-ia-icon disabled" disabled title="No disponible para este estado o tipo"><i class="bi bi-robot"></i></button>`;
            tr.append(`<td>${btnIA}</td>`);
        }

        tbody.append(tr);
    });
}

// Renderizar selector de urgencia
function renderizarUrgencia(ticketId, nivelActual) {
    const nivel = nivelActual || 0;
    const color = coloresUrgencia[nivel];
    const texto = textosUrgencia[nivel];

    // Solo permitir cambiar si tiene permiso cambiar_urgencia
    const permiteEditar = tienepermiso('cambiar_urgencia');
    const cursor = permiteEditar ? 'pointer' : 'default';
    const onClick = permiteEditar ? `onclick="cambiarUrgencia(${ticketId}, ${nivel})"` : '';

    return `
        <div class="urgency-selector" style="background-color: ${color}; cursor: ${cursor};" ${onClick}>
            <div class="urgency-text">${texto}</div>
        </div>
    `;
}

// Cambiar nivel de urgencia
function cambiarUrgencia(ticketId, nivelActual) {
    // Validar permiso
    if (!tienepermiso('cambiar_urgencia')) {
        return;
    }

    const opciones = `
        <div style="padding: 0.5rem;">
            <div style="font-weight: 600; margin-bottom: 0.5rem;">Seleccionar nivel:</div>
            ${[0, 1, 2, 3, 4].map(nivel => {
        const color = coloresUrgencia[nivel];
        const texto = textosUrgencia[nivel];
        const selected = nivel === nivelActual ? '✓ ' : '';
        return `
                    <div style="padding: 0.5rem 0.75rem; cursor: pointer; border-radius: 3px; margin-bottom: 0.25rem; background-color: ${color}; color: white; display: flex; align-items: center; justify-content: center; gap: 0.5rem; font-weight: 600;" 
                         onmouseover="this.style.opacity='0.8'" 
                         onmouseout="this.style.opacity='1'"
                         onclick="actualizarUrgencia(${ticketId}, ${nivel})">
                        <span>${selected}${texto}</span>
                    </div>
                `;
    }).join('')}
        </div>
    `;

    // Cerrar modal anterior si existe
    const modalAnterior = document.getElementById('modalUrgencia');
    if (modalAnterior) {
        $(modalAnterior).modal('hide');
        modalAnterior.remove();
    }

    const modalHtml = `
        <div class="modal fade" id="modalUrgencia" tabindex="-1">
            <div class="modal-dialog modal-sm">
                <div class="modal-content">
                    <div class="modal-header" style="background-color: #0E544C; color: white; padding: 0.75rem 1rem;">
                        <h6 class="modal-title mb-0">Nivel de Urgencia</h6>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-0">
                        ${opciones}
                    </div>
                </div>
            </div>
        </div>
    `;

    $('body').append(modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('modalUrgencia'));
    modal.show();

    $('#modalUrgencia').on('hidden.bs.modal', function () {
        $(this).remove();
    });
}

// Renderizar tiempo estimado con edición verdaderamente inline
function renderizarTiempoEstimado(ticketId, tiempoActual) {
    const tiempo = tiempoActual || 0;
    const texto = tiempo > 0 ? `${tiempo}` : '-';
    const permiteEditar = tienepermiso('cambiar_urgencia');

    if (!permiteEditar) {
        return `
            <div class="tiempo-estimado-container">
                <span class="tiempo-texto">${texto}</span>
            </div>
        `;
    }

    return `
        <div class="tiempo-estimado-container tiempo-estimado-editable" 
             onclick="habilitarEdicionInline(this, ${ticketId}, ${tiempo})">
            <span class="tiempo-texto">${texto}</span>
        </div>
    `;
}

// Habilitar edición inline (cambiar span por input)
function habilitarEdicionInline(container, ticketId, tiempoActual) {
    if ($(container).find('input').length > 0) return;

    const currentVal = tiempoActual > 0 ? tiempoActual : '';
    const input = $(`<input type="number" class="form-control form-control-sm input-inline-tiempo" 
                            value="${currentVal}" min="0" step="1" 
                            style="width: 60px; text-align: center; height: 26px; padding: 2px;">`);

    $(container).html(input);
    input.focus().select();

    // Eventos para guardar o cancelar
    input.on('blur', function () {
        const nuevoTiempo = $(this).val();
        if (nuevoTiempo === '' && tiempoActual === 0) {
            $(container).html(`<span class="tiempo-texto">-</span>`);
            return;
        }

        if (nuevoTiempo === '' || parseInt(nuevoTiempo) === tiempoActual) {
            $(container).html(`<span class="tiempo-texto">${tiempoActual > 0 ? tiempoActual : '-'}</span>`);
        } else {
            actualizarTiempoEstimado(ticketId, parseInt(nuevoTiempo));
        }
    });

    input.on('keyup', function (e) {
        if (e.key === 'Enter') {
            $(this).blur();
        } else if (e.key === 'Escape') {
            $(container).html(`<span class="tiempo-texto">${tiempoActual > 0 ? tiempoActual : '-'}</span>`);
        }
    });
}

// Actualizar tiempo estimado
function actualizarTiempoEstimado(ticketId, nuevoTiempo) {
    $.ajax({
        url: 'ajax/historial_actualizar_tiempo.php',
        method: 'POST',
        data: {
            ticket_id: ticketId,
            tiempo_estimado: nuevoTiempo
        },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                cargarDatos();
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function () {
            alert('Error al actualizar el tiempo estimado');
        }
    });
}

// Actualizar nivel de urgencia
function actualizarUrgencia(ticketId, nuevoNivel) {
    $('#modalUrgencia').modal('hide');

    $.ajax({
        url: 'ajax/historial_actualizar_urgencia.php',
        method: 'POST',
        data: {
            ticket_id: ticketId,
            nivel_urgencia: nuevoNivel
        },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                cargarDatos();
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function () {
            alert('Error al actualizar la urgencia');
        }
    });
}

// Mostrar fotos
function mostrarFotos(ticketId) {
    $.ajax({
        url: 'ajax/historial_get_fotos.php',
        method: 'GET',
        data: { ticket_id: ticketId },
        dataType: 'json',
        success: function (response) {
            if (response.success && response.fotos.length > 0) {
                const carouselInner = $('#carouselFotosInner');
                carouselInner.empty();

                const totalFotos = response.fotos.length;

                response.fotos.forEach((foto, index) => {
                    const activeClass = index === 0 ? 'active' : '';
                    const isHeic = foto.foto.toLowerCase().endsWith('.heic') || foto.foto.toLowerCase().endsWith('.heif');
                    const imgId = `img-ticket-photo-${index}`;
                    const loaderId = `loader-${imgId}`;

                    carouselInner.append(`
                        <div class="carousel-item ${activeClass}">
                            <div class="d-flex justify-content-center align-items-center position-relative" style="min-height: 250px; background: #f8f9fa;">
                                <div id="${loaderId}" class="carousel-foto-loader">
                                    <div class="spinner-border" role="status" style="color: #0E544C; width: 3rem; height: 3rem;">
                                        <span class="visually-hidden">Cargando...</span>
                                    </div>
                                    <div class="mt-2 text-muted" style="font-size:0.85rem;">Cargando foto...</div>
                                </div>
                                <img id="${imgId}"
                                     class="d-block carousel-foto-img"
                                     alt="Foto ${index + 1}"
                                     style="display:none;"
                                     onerror="this.style.display='block'; document.getElementById('${loaderId}').style.display='none'; this.src='/core/assets/img/broken-image.png';">
                            </div>
                        </div>
                    `);

                    if (isHeic) {
                        fetch(foto.foto)
                            .then(res => res.blob())
                            .then(blob => heic2any({
                                blob,
                                toType: "image/jpeg",
                                quality: 0.6
                            }))
                            .then(conversionResult => {
                                const url = URL.createObjectURL(Array.isArray(conversionResult) ? conversionResult[0] : conversionResult);
                                const imgEl = document.getElementById(imgId);
                                const loaderEl = document.getElementById(loaderId);
                                if (imgEl) {
                                    imgEl.onload = function () {
                                        if (loaderEl) loaderEl.style.display = 'none';
                                        imgEl.style.display = 'block';
                                    };
                                    imgEl.src = url;
                                }
                            })
                            .catch(e => {
                                console.error("Error converting HEIC:", e);
                                const loaderEl = document.getElementById(loaderId);
                                const imgEl = document.getElementById(imgId);
                                if (loaderEl) loaderEl.style.display = 'none';
                                if (imgEl) imgEl.style.display = 'block';
                            });
                    } else {
                        const imgEl = document.getElementById(imgId);
                        const loaderEl = document.getElementById(loaderId);
                        if (imgEl) {
                            imgEl.onload = function () {
                                if (loaderEl) loaderEl.style.display = 'none';
                                imgEl.style.display = 'block';
                            };
                            imgEl.onerror = function () {
                                if (loaderEl) loaderEl.style.display = 'none';
                                imgEl.style.display = 'block';
                            };
                            imgEl.src = foto.foto;
                        }
                    }
                });

                // Mostrar u ocultar flechas según cantidad de fotos
                const prevBtn = document.querySelector('#carouselFotos .carousel-control-prev');
                const nextBtn = document.querySelector('#carouselFotos .carousel-control-next');
                if (prevBtn) prevBtn.style.display = totalFotos > 1 ? '' : 'none';
                if (nextBtn) nextBtn.style.display = totalFotos > 1 ? '' : 'none';

                $('#modalFotos').modal('show');
            } else {
                alert('No se encontraron fotos');
            }
        },
        error: function () {
            alert('Error al cargar las fotos');
        }
    });
}

// Toggle filtro
function toggleFilter(icon) {
    const th = $(icon).closest('th');
    const columna = th.data('column');
    const tipo = th.data('type');

    // Si ya hay un panel abierto en esta columna, cerrarlo
    if (panelFiltroAbierto === columna) {
        cerrarTodosFiltros();
        return;
    }

    // Cerrar otros filtros
    cerrarTodosFiltros();

    // Crear y mostrar nuevo panel
    crearPanelFiltro(th, columna, tipo, icon);
    panelFiltroAbierto = columna;
    $(icon).addClass('active');

    // Actualizar indicador si hay filtro activo
    actualizarIndicadoresFiltros();
}

// Crear panel de filtro
function crearPanelFiltro(th, columna, tipo, icon) {
    const panel = $('<div class="filter-panel show"></div>');

    // Sección de ordenamiento
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

    // Botón de limpiar (inmediatamente debajo de los botones de ordenamiento)
    const botonLimpiarDeshabilitado = (columna === 'nombre_sucursal' &&
        typeof filtroSucursalBloqueado !== 'undefined' &&
        filtroSucursalBloqueado);

    const disabledAttr = botonLimpiarDeshabilitado ? 'disabled' : '';
    const disabledStyle = botonLimpiarDeshabilitado ? 'opacity: 0.5; cursor: not-allowed;' : '';

    panel.append(`
        <button class="filter-action-btn clear" 
                onclick="limpiarFiltro('${columna}')" 
                ${disabledAttr}
                style="${disabledStyle}">
            <i class="bi bi-x-circle"></i> Limpiar
        </button>
    `);


    // Sección de búsqueda y opciones según el tipo
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
    } else if (tipo === 'daterange') {
        // Agregar clase para scroll
        panel.addClass('has-daterange');

        // Crear calendario de rango de fechas
        const fechaActual = filtrosActivos[columna] || { desde: '', hasta: '' };
        panel.append(crearCalendarioRangoFechas(columna, fechaActual));
    } else if (tipo === 'list' || tipo === 'urgency') {
        // Cargar opciones únicas de la columna
        cargarOpcionesFiltro(panel, columna, tipo);
    }



    // Agregar al body en lugar del th
    $('body').append(panel);

    // Calcular y aplicar posición
    posicionarPanelFiltro(panel, icon);
}

// Cargar opciones de filtro
function cargarOpcionesFiltro(panel, columna, tipo) {
    $.ajax({
        url: 'ajax/historial_get_opciones_filtro.php',
        method: 'POST',
        data: { columna: columna, tipo: tipo },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                let html = '<div class="filter-section">';
                html += '<span class="filter-section-title">Filtrar por:</span>';
                html += '<input type="text" class="filter-search" placeholder="Buscar..." onkeyup="buscarEnOpciones(this)">';
                html += '<div class="filter-options">';

                response.opciones.forEach(opcion => {
                    const checked = filtrosActivos[columna] && filtrosActivos[columna].includes(opcion.valor) ? 'checked' : '';

                    // Validar si debe estar deshabilitado (solo para sucursales)
                    let disabled = '';
                    let disabledClass = '';

                    if (columna === 'nombre_sucursal' && filtroSucursalBloqueado) {
                        const allowed = (typeof sucursalesLiderPropias !== 'undefined' && Array.isArray(sucursalesLiderPropias))
                            ? sucursalesLiderPropias
                            : [codigoSucursalBusqueda];
                        if (!allowed.includes(opcion.texto)) {
                            disabled = 'disabled';
                            disabledClass = 'disabled';
                        }
                    }

                    html += `
                        <div class="filter-option ${disabledClass}">
                            <input type="checkbox" value="${opcion.valor}" ${checked} ${disabled}
                                   onchange="toggleOpcionFiltro('${columna}', '${opcion.valor}', this.checked)">
                            <span>${opcion.texto}</span>
                        </div>
                    `;
                });

                html += '</div></div>';
                panel.append(html);

                // Si el filtro está bloqueado, marcar automáticamente la sucursal o sucursales permitidas
                if (columna === 'nombre_sucursal' && filtroSucursalBloqueado) {
                    if (!filtrosActivos[columna] || filtrosActivos[columna].length === 0) {
                        filtrosActivos[columna] = (typeof sucursalesLiderPropias !== 'undefined' && sucursalesLiderPropias.length > 0)
                            ? [...sucursalesLiderPropias]
                            : [codigoSucursalBusqueda];
                        cargarDatos();
                    }
                }
            }
        }
    });
}

// Cerrar todos los filtros
function cerrarTodosFiltros() {
    // Antes de cerrar, verificar si hay un filtro de fecha con solo una fecha seleccionada
    for (const columna in filtrosActivos) {
        if (filtrosActivos[columna] && filtrosActivos[columna].desde && !filtrosActivos[columna].hasta) {
            // Si solo hay fecha "desde", usar la misma fecha para "hasta"
            filtrosActivos[columna].hasta = filtrosActivos[columna].desde;
            paginaActual = 1;
            cargarDatos();
            actualizarIndicadoresFiltros();
            break; // Solo procesar el primero encontrado
        }
    }

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

// Filtrar por búsqueda de texto
function filtrarBusqueda(columna, valor) {
    if (valor.trim() === '') {
        delete filtrosActivos[columna];
    } else {
        filtrosActivos[columna] = valor;
    }
    paginaActual = 1;
    cargarDatos();
    actualizarIndicadoresFiltros();
}

// Toggle opción de filtro
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
    actualizarIndicadoresFiltros();
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

    // Botón anterior
    paginacion.append(`
        <button class="pagination-btn" onclick="cambiarPagina(${paginaActual - 1})" ${paginaActual === 1 ? 'disabled' : ''}>
            <i class="bi bi-chevron-left"></i>
        </button>
    `);

    // Números de página
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

    // Botón siguiente
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
    return `${String(d.getDate()).padStart(2, '0')}-${meses[d.getMonth()]}`;
}

// Renderizar fecha agendado con estilos según estado
function renderizarFechaAgendado(fechaInicio, status) {
    if (!fechaInicio) {
        return '<span class="fecha-sin-programar">Pendiente</span>';
    }

    // Si está finalizado, mostrar con menos realce
    if (status === 'finalizado') {
        return `<span class="fecha-finalizado">${formatearFecha(fechaInicio)}</span>`;
    }

    // Calcular semana actual
    const hoy = new Date();
    const fechaTicket = new Date(fechaInicio);

    // Obtener lunes de esta semana
    const lunesActual = new Date(hoy);
    lunesActual.setDate(hoy.getDate() - (hoy.getDay() === 0 ? 6 : hoy.getDay() - 1));
    lunesActual.setHours(0, 0, 0, 0);

    // Obtener domingo de esta semana
    const domingoActual = new Date(lunesActual);
    domingoActual.setDate(lunesActual.getDate() + 6);
    domingoActual.setHours(23, 59, 59, 999);

    // Verificar si está en la semana actual
    if (fechaTicket >= lunesActual && fechaTicket <= domingoActual) {
        return `<span class="fecha-semana-actual"><i class="bi bi-exclamation-circle"></i> ${formatearFecha(fechaInicio)}</span>`;
    }

    // Si es semana siguiente en adelante
    if (fechaTicket > domingoActual) {
        return `<span class="fecha-proxima"><i class="bi bi-calendar-check"></i> ${formatearFecha(fechaInicio)}</span>`;
    }

    // Si ya pasó la fecha (atrasado)
    return `<span class="fecha-sin-programar"><i class="bi bi-exclamation-triangle"></i> ${formatearFecha(fechaInicio)}</span>`;
}

// ========== FUNCIONES PARA CALENDARIO DE RANGO DE FECHAS (UN SOLO CALENDARIO) ==========

// Crear calendario de rango de fechas con UN SOLO calendario
function crearCalendarioRangoFechas(columna, fechaActual) {
    const hoy = new Date();
    const mesActual = hoy.getMonth();
    const anioActual = hoy.getFullYear();

    const fechaDesde = fechaActual?.desde || '';
    const fechaHasta = fechaActual?.hasta || '';

    const html = `
        <div class="filter-section" style="margin-top: 4px; margin-bottom: 6px;">
            <span class="filter-section-title">Seleccionar rango:</span>
            <div class="daterange-inputs">
                <div class="daterange-calendar-container">
                    <div class="daterange-month-selector">
                        <select id="mes-${columna}" onchange="actualizarCalendario('${columna}')"></select>
                        <select id="año-${columna}" onchange="actualizarCalendario('${columna}')"></select>
                    </div>
                    <div class="daterange-calendar" id="calendario-${columna}"></div>
                </div>
            </div>
        </div>
    `;

    setTimeout(() => {
        inicializarSelectoresFecha(columna, mesActual, anioActual, fechaDesde, fechaHasta);
        actualizarCalendario(columna);
    }, 50);

    return html;
}

// Inicializar selectores de fecha
function inicializarSelectoresFecha(columna, mesActual, anioActual, fechaDesde, fechaHasta) {
    const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    let mesSeleccionado = mesActual;
    let anioSeleccionado = anioActual;

    // Si hay una fecha desde, usar ese mes/año
    if (fechaDesde) {
        const d = new Date(fechaDesde);
        mesSeleccionado = d.getMonth();
        anioSeleccionado = d.getFullYear();
    }

    // Llenar selector de mes
    const selectMes = $(`#mes-${columna}`);
    meses.forEach((mes, idx) => {
        selectMes.append(`<option value="${idx}" ${idx === mesSeleccionado ? 'selected' : ''}>${mes}</option>`);
    });

    // Llenar selector de año
    const selectAnio = $(`#año-${columna}`);
    for (let anio = anioActual - 2; anio <= anioActual + 1; anio++) {
        selectAnio.append(`<option value="${anio}" ${anio === anioSeleccionado ? 'selected' : ''}>${anio}</option>`);
    }
}

// Actualizar calendario
function actualizarCalendario(columna) {
    const mes = parseInt($(`#mes-${columna}`).val());
    const anio = parseInt($(`#año-${columna}`).val());
    const calendarioId = `#calendario-${columna}`;

    const primerDia = new Date(anio, mes, 1).getDay();
    const diasEnMes = new Date(anio, mes + 1, 0).getDate();

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
        const fechaStr = `${anio}-${String(mes + 1).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
        const clases = obtenerClasesCalendario(fechaStr, columna);
        html += `<div class="daterange-calendar-day ${clases}" onclick="event.stopPropagation(); seleccionarFecha('${fechaStr}', '${columna}')">${dia}</div>`;
    }

    html += '</div>';
    $(calendarioId).html(html);
}

// Obtener clases para días del calendario
function obtenerClasesCalendario(fecha, columna) {
    const fechaDesde = filtrosActivos[columna]?.desde;
    const fechaHasta = filtrosActivos[columna]?.hasta;

    let clases = [];

    if (fechaDesde && fecha === fechaDesde) {
        clases.push('selected');
    }

    if (fechaHasta && fecha === fechaHasta) {
        clases.push('selected');
    }

    if (fechaDesde && fechaHasta && fecha > fechaDesde && fecha < fechaHasta) {
        clases.push('in-range');
    }

    return clases.join(' ');
}

// Seleccionar fecha en calendario (lógica de 2 clics)
function seleccionarFecha(fecha, columna) {
    if (!filtrosActivos[columna]) {
        filtrosActivos[columna] = {};
    }

    // Si no hay fecha desde, esta es la primera selección
    if (!filtrosActivos[columna].desde) {
        filtrosActivos[columna].desde = fecha;
        filtrosActivos[columna].hasta = ''; // Limpiar hasta
        actualizarCalendario(columna);
    }
    // Si ya hay fecha desde pero no hasta, esta es la segunda selección
    else if (!filtrosActivos[columna].hasta) {
        filtrosActivos[columna].hasta = fecha;

        // Validar que 'desde' no sea mayor que 'hasta'
        if (filtrosActivos[columna].desde > filtrosActivos[columna].hasta) {
            // Intercambiar fechas
            const temp = filtrosActivos[columna].desde;
            filtrosActivos[columna].desde = filtrosActivos[columna].hasta;
            filtrosActivos[columna].hasta = temp;
        }

        // Aplicar filtro y cerrar panel
        paginaActual = 1;
        cargarDatos();
        actualizarIndicadoresFiltros();
        cerrarTodosFiltros();
    }
    // Si ya hay ambas fechas, reiniciar la selección
    else {
        filtrosActivos[columna].desde = fecha;
        filtrosActivos[columna].hasta = '';
        actualizarCalendario(columna);
    }
}

// ========== FUNCIONES ACCESO RÁPIDO SUCURSAL ==========

function aplicarAccesoRapido(sucursal, element) {
    // 1. Limpiar filtros actuales
    filtrosActivos = {};

    // 2. Aplicar filtros solicitados
    filtrosActivos['nombre_sucursal'] = [sucursal];
    filtrosActivos['status'] = ['solicitado', 'agendado'];
    filtrosActivos['tipo_formulario'] = ['mantenimiento_general'];

    // 3. UI Update: Marcar chip activo
    $('.branch-chip').removeClass('active');
    $(element).addClass('active');

    // 4. Recargar datos
    paginaActual = 1;
    cargarDatos();
    actualizarIndicadoresFiltros();

    // 5. Scroll suave a la tabla si es necesario
    $('html, body').animate({
        scrollTop: $("#tablaSolicitudes").offset().top - 100
    }, 500);
}

function limpiarFiltrosAccesoRapido() {
    // Limpiar objeto de filtros
    filtrosActivos = {};

    // Si el filtro de sucursal está bloqueado, restaurar el filtro por defecto
    if (typeof filtroSucursalBloqueado !== 'undefined' && filtroSucursalBloqueado && typeof codigoSucursalBusqueda !== 'undefined') {
        filtrosActivos['nombre_sucursal'] = [codigoSucursalBusqueda];
    }

    // UI Update: Quitar activos de chips
    $('.branch-chip').removeClass('active');

    // Recargar datos
    paginaActual = 1;
    cargarDatos();
    actualizarIndicadoresFiltros();
}
// ========== FUNCIONES DE SUPER EDICIÓN ==========

// Renderizar título editable
function renderizarTituloEditable(ticketId, titulo) {
    const permiteEditar = tienepermiso('super_edicion');
    
    if (!permiteEditar) {
        return titulo;
    }

    return `
        <div class="titulo-editable" onclick="habilitarEdicionTitulo(this, ${ticketId}, '${titulo.replace(/'/g, "\\'")}')">
            ${titulo}
        </div>
    `;
}

// Habilitar edición de título
function habilitarEdicionTitulo(container, ticketId, tituloActual) {
    if ($(container).find('input').length > 0) return;

    const input = $(`<input type="text" class="form-control form-control-sm input-inline-titulo" 
                            value="${tituloActual}" 
                            style="width: 100%; height: 26px; padding: 2px; font-size: 0.85rem;">`);

    $(container).html(input);
    input.focus().select();

    input.on('blur', function () {
        const nuevoTitulo = $(this).val().trim();
        if (nuevoTitulo === '' || nuevoTitulo === tituloActual) {
            $(container).html(tituloActual);
        } else {
            actualizarTitulo(ticketId, nuevoTitulo);
        }
    });

    input.on('keyup', function (e) {
        if (e.key === 'Enter') {
            $(this).blur();
        } else if (e.key === 'Escape') {
            $(container).html(tituloActual);
        }
    });
}

// Actualizar título
function actualizarTitulo(ticketId, nuevoTitulo) {
    $.ajax({
        url: 'ajax/historial_actualizar_titulo.php',
        method: 'POST',
        data: {
            ticket_id: ticketId,
            titulo: nuevoTitulo
        },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                cargarDatos();
            } else {
                alert('Error: ' + response.message);
                cargarDatos(); // Revertir UI
            }
        },
        error: function () {
            alert('Error al actualizar el título');
            cargarDatos();
        }
    });
}

// Renderizar tipo editable
function renderizarTipoEditable(ticketId, tipoActual) {
    const tipoClass = tipoActual === 'cambio_equipos' ? 'cambio-equipo' : 'mantenimiento';
    const tipoText = textosTipo[tipoActual] || tipoActual;
    const permiteEditar = tienepermiso('super_edicion');
    
    const cursor = permiteEditar ? 'pointer' : 'default';
    const onClick = permiteEditar ? `onclick="cambiarTipo(${ticketId}, '${tipoActual}')"` : '';

    return `<span class="badge-tipo ${tipoClass} editable-badge" style="cursor: ${cursor};" ${onClick}>${tipoText}</span>`;
}

// Cambiar tipo
function cambiarTipo(ticketId, tipoActual) {
    const opciones = `
        <div style="padding: 0.5rem;">
            <div style="font-weight: 600; margin-bottom: 0.5rem;">Seleccionar tipo:</div>
            ${Object.keys(textosTipo).map(tipo => {
        const selected = tipo === tipoActual ? '✓ ' : '';
        const texto = textosTipo[tipo];
        return `
                    <div class="opcion-modal-pitaya" 
                         onclick="actualizarTipo(${ticketId}, '${tipo}')">
                        <span>${selected}${texto}</span>
                    </div>
                `;
    }).join('')}
        </div>
    `;

    abrirModalPitaya('modalTipoSolicitud', 'Tipo de Solicitud', opciones);
}

// Actualizar tipo
function actualizarTipo(ticketId, nuevoTipo) {
    $('#modalTipoSolicitud').modal('hide');

    $.ajax({
        url: 'ajax/historial_actualizar_tipo.php',
        method: 'POST',
        data: {
            ticket_id: ticketId,
            tipo: nuevoTipo
        },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                cargarDatos();
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function () {
            alert('Error al actualizar el tipo');
        }
    });
}

// Renderizar estado editable
function renderizarEstadoEditable(ticketId, statusActual) {
    const colorEstado = coloresEstado[statusActual] || '#6c757d';
    const permiteEditar = tienepermiso('super_edicion');
    
    const cursor = permiteEditar ? 'pointer' : 'default';
    const onClick = permiteEditar ? `onclick="cambiarEstado(${ticketId}, '${statusActual}')"` : '';

    return `<span class="badge-estado editable-badge" style="background-color: ${colorEstado}; cursor: ${cursor};" ${onClick}>${textosEstado[statusActual] || statusActual}</span>`;
}

// Cambiar estado
function cambiarEstado(ticketId, statusActual) {
    const opciones = `
        <div style="padding: 0.5rem;">
            <div style="font-weight: 600; margin-bottom: 0.5rem;">Seleccionar estado:</div>
            ${['solicitado', 'finalizado', 'cancelado'].map(status => {
        const color = coloresEstado[status];
        const selected = status === statusActual ? '✓ ' : '';
        const texto = textosEstado[status];
        return `
                    <div class="opcion-modal-pitaya" 
                         style="background-color: ${color}; color: white;"
                         onclick="seleccionarNuevoEstado(${ticketId}, '${status}')">
                        <span>${selected}${texto}</span>
                    </div>
                `;
    }).join('')}
        </div>
    `;

    abrirModalPitaya('modalEstadoSolicitud', 'Estado de la Solicitud', opciones);
}

function seleccionarNuevoEstado(ticketId, nuevoStatus) {
    $('#modalEstadoSolicitud').modal('hide');
    if (nuevoStatus === 'finalizado') {
        abrirModalFinalizarTicket(ticketId);
    } else {
        actualizarEstado(ticketId, nuevoStatus);
    }
}

// Actualizar estado
function actualizarEstado(ticketId, nuevoStatus) {
    $('#modalEstadoSolicitud').modal('hide');

    $.ajax({
        url: 'ajax/historial_actualizar_status.php',
        method: 'POST',
        data: {
            ticket_id: ticketId,
            status: nuevoStatus
        },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                cargarDatos();
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function () {
            alert('Error al actualizar el estado');
        }
    });
}

// Función genérica para abrir modales estilo pitaya
function abrirModalPitaya(id, titulo, contenido) {
    // Cerrar modal anterior si existe
    const modalAnterior = document.getElementById(id);
    if (modalAnterior) {
        $(modalAnterior).modal('hide');
        modalAnterior.remove();
    }

    const modalHtml = `
        <div class="modal fade" id="${id}" tabindex="-1">
            <div class="modal-dialog modal-sm">
                <div class="modal-content">
                    <div class="modal-header" style="background-color: #0E544C; color: white; padding: 0.75rem 1rem;">
                        <h6 class="modal-title mb-0">${titulo}</h6>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-0">
                        ${contenido}
                    </div>
                </div>
            </div>
        </div>
    `;

    $('body').append(modalHtml);
    const modal = new bootstrap.Modal(document.getElementById(id));
    modal.show();

    $(`#${id}`).on('hidden.bs.modal', function () {
        $(this).remove();
    });
}

// ========== FUNCIONES PARA RESOLUCIÓN ==========

// Renderizar resolución editable
function renderizarResolucionEditable(ticketId, resolucion) {
    const res = resolucion || '';
    const permiteEditar = tienepermiso('editar_resolucion');
    const displayRes = res.replace(/\n/g, '<br>');
    const emptyClass = !res ? 'text-muted italic' : '';
    const texto = res || 'Sin resolución aún...';
    
    if (!permiteEditar) {
        return `<div class="resolucion-text ${emptyClass}">${displayRes || texto}</div>`;
    }

    return `
        <div class="resolucion-editable ${emptyClass}" 
             onclick="habilitarEdicionResolucion(this, ${ticketId}, '${res.replace(/'/g, "\\'").replace(/\n/g, "\\n")}')">
            ${displayRes || texto}
        </div>
    `;
}

// Habilitar edición de resolución
function habilitarEdicionResolucion(container, ticketId, resolucionActual) {
    if ($(container).find('textarea').length > 0) return;

    const textarea = $(`<textarea class="form-control form-control-sm input-inline-resolucion" 
                                style="width: 100%; min-height: 80px; font-size: 0.85rem; padding: 4px;">${resolucionActual}</textarea>`);

    $(container).html(textarea);
    textarea.focus();
    
    // Mover cursor al final
    const len = textarea.val().length;
    textarea[0].setSelectionRange(len, len);

    textarea.on('blur', function () {
        const nuevaResolucion = $(this).val().trim();
        if (nuevaResolucion === resolucionActual) {
            const displayRes = resolucionActual.replace(/\n/g, '<br>');
            const texto = resolucionActual || 'Sin resolución aún...';
            const emptyClass = !resolucionActual ? 'text-muted italic' : '';
            $(container).html(displayRes || texto).addClass(emptyClass);
        } else {
            actualizarResolucion(ticketId, nuevaResolucion);
        }
    });

    textarea.on('keyup', function (e) {
        if (e.key === 'Escape') {
            const displayRes = resolucionActual.replace(/\n/g, '<br>');
            const texto = resolucionActual || 'Sin resolución aún...';
            const emptyClass = !resolucionActual ? 'text-muted italic' : '';
            $(container).html(displayRes || texto).addClass(emptyClass);
        }
    });
}

// Actualizar resolución
function actualizarResolucion(ticketId, nuevaResolucion) {
    $.ajax({
        url: 'ajax/historial_actualizar_resolucion.php',
        method: 'POST',
        data: {
            ticket_id: ticketId,
            resolucion: nuevaResolucion
        },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                cargarDatos();
            } else {
                alert('Error: ' + response.message);
                cargarDatos(); // Revertir UI
            }
        },
        error: function () {
            alert('Error al actualizar la resolución');
            cargarDatos();
        }
    });
}

// ========== INFORME GLOBAL EXCEL ==========

/**
 * Descarga un Excel con los registros que cumplen los mismos filtros
 * que aplican los botones de "Acceso Rápido por Sucursal":
 *   - status:         ['solicitado', 'agendado']    (NO finalizados/cancelados)
 *   - tipo_formulario: ['mantenimiento_general']
 *   - nombre_sucursal: si hay un chip activo se respeta; si no, todas las sucursales
 *
 * Usa formulario POST oculto para que el navegador active el attachment.
 */
function descargarInformeGlobal() {
    const btn = document.getElementById('btnInformeGlobal');

    // Estado de carga
    if (btn) {
        btn.style.pointerEvents = 'none';
        btn.classList.add('loading');
        
        const label = btn.querySelector('.fab-label');
        const icon = btn.querySelector('.fab-icon-holder i');
        
        if (label && icon) {
            label.innerText = 'Generando...';
            icon.className = 'fas fa-spinner fa-spin';
        } else {
            btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> <span>Generando...</span>';
        }
    }

    // ── Filtros del informe (SIEMPRE todas las sucursales) ───────────────────
    const filtrosExport = {};

    // Sin filtro de sucursal: el informe es global (todas las sucursales)
    filtrosExport['status']          = ['solicitado', 'agendado'];
    filtrosExport['tipo_formulario'] = ['mantenimiento_general'];
    // ─────────────────────────────────────────────────────────────────────────

    // Construir formulario temporal oculto
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'ajax/historial_export_excel.php';
    form.style.display = 'none';

    const inputFiltros = document.createElement('input');
    inputFiltros.type  = 'hidden';
    inputFiltros.name  = 'filtros';
    inputFiltros.value = JSON.stringify(filtrosExport);

    const inputOrden = document.createElement('input');
    inputOrden.type  = 'hidden';
    inputOrden.name  = 'orden';
    inputOrden.value = JSON.stringify(ordenActivo);

    form.appendChild(inputFiltros);
    form.appendChild(inputOrden);
    document.body.appendChild(form);
    form.submit();

    // Limpiar formulario del DOM tras el submit
    setTimeout(function () {
        if (document.body.contains(form)) {
            document.body.removeChild(form);
        }
    }, 1000);

    // Restaurar botón (no existe evento "descarga completada" en submit nativo)
    setTimeout(function () {
        if (btn) {
            btn.style.pointerEvents = 'auto';
            btn.classList.remove('loading');
            
            const label = btn.querySelector('.fab-label');
            const icon = btn.querySelector('.fab-icon-holder i');
            
            if (label && icon) {
                label.innerText = 'Informe Global';
                icon.className = 'fas fa-file-excel';
            } else {
                btn.innerHTML = '<i class="bi bi-file-earmark-excel-fill"></i> <span>Informe Global</span>';
            }
        }
    }, 4000);
}

// ========== ANÁLISIS IA POR TICKET ==========

/**
 * Llama al endpoint de IA para analizar el ticket y actualizar urgencia, tiempo y resolución.
 * El botón se deshabilita durante el proceso y se restaura al terminar.
 */
async function analizarConIA(ticketId) {
    const btn = document.getElementById(`btn-ia-${ticketId}`);
    if (!btn) return;

    // Estado de carga
    btn.disabled = true;
    btn.innerHTML = '<span class="btn-ia-spinner"></span>';
    btn.classList.add('loading');

    try {
        const response = await fetch('ajax/historial_consulta_ia.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ticket_id: ticketId })
        });

        if (!response.ok) {
            throw new Error('Error de red al conectar con el servidor.');
        }

        const data = await response.json();

        if (!data.success) {
            mostrarToastIA(false, data.message || 'Error desconocido al analizar el ticket.');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-robot"></i> IA';
            btn.classList.remove('loading');
            return;
        }

        // Éxito: recargar datos y mostrar toast
        const proveedorNombre = (data.proveedor || 'IA').toUpperCase();
        mostrarToastIA(true, `✓ Analizado · ${proveedorNombre}`);
        cargarDatos();

    } catch (error) {
        console.error('analizarConIA error:', error);
        mostrarToastIA(false, 'Error de conexión. Intenta nuevamente.');
        const btnActual = document.getElementById(`btn-ia-${ticketId}`);
        if (btnActual) {
            btnActual.disabled = false;
            btnActual.innerHTML = '<i class="bi bi-robot"></i> IA';
            btnActual.classList.remove('loading');
        }
    }
}

/**
 * Muestra un toast flotante de éxito o error en la esquina inferior derecha.
 * Se destruye automáticamente después de 4 segundos.
 */
function mostrarToastIA(exito, mensaje) {
    const toastAnterior = document.getElementById('toast-ia-pitaya');
    if (toastAnterior) toastAnterior.remove();

    const color   = exito ? '#0E544C' : '#dc3545';
    const iconoBI = exito ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';

    const toast = document.createElement('div');
    toast.id = 'toast-ia-pitaya';
    toast.innerHTML = `<i class="bi ${iconoBI}" style="font-size:1.1rem;"></i><span>${mensaje}</span>`;
    Object.assign(toast.style, {
        position:     'fixed',
        bottom:       '30px',
        right:        '30px',
        zIndex:       '99999',
        background:   color,
        color:        'white',
        padding:      '12px 20px',
        borderRadius: '8px',
        boxShadow:    '0 4px 16px rgba(0,0,0,0.25)',
        display:      'flex',
        alignItems:   'center',
        gap:          '10px',
        fontSize:     '0.9rem',
        fontWeight:   '600',
        maxWidth:     '320px',
        fontFamily:   'inherit'
    });

    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.4s ease';
        setTimeout(() => toast.remove(), 400);
    }, 4000);
}

/**
 * GESTIÓN DE CÁMARA Y FINALIZACIÓN DIRECTA — Módulo de Mantenimiento
 */
let activeStream        = null;
let activeVideoTrack    = null;
let torchActivo         = false;
let currentCameraTarget = null;
let focusToastTimers    = {};
let fotosEvidenciaTmp   = []; // Almacena fotos (tipo: 'file' o 'cam') para la finalización directa

function previewEvidencia(input) {
    if (input.files) {
        Array.from(input.files).forEach(file => {
            const reader = new FileReader();
            reader.onload = function (e) {
                fotosEvidenciaTmp.push({ tipo: 'file', data: e.target.result, file: file });
                renderEvidenciaPreviews();
            };
            reader.readAsDataURL(file);
        });
    }
}

function renderEvidenciaPreviews() {
    const container = $('#finalizar_evidencia_previews');
    container.empty();
    fotosEvidenciaTmp.forEach((foto, index) => {
        container.append(`
            <div class="col-4 col-md-3 position-relative">
                <img src="${foto.data}" class="img-thumbnail rounded-3 w-100" style="height: 80px; object-fit: cover;">
                <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1 rounded-circle" 
                        onclick="removerFotoTmp(${index})"><i class="fas fa-times"></i></button>
            </div>
        `);
    });
}

function removerFotoTmp(index) {
    fotosEvidenciaTmp.splice(index, 1);
    renderEvidenciaPreviews();
}

async function startCamera(target) {
    stopCamera();

    currentCameraTarget = target;
    torchActivo         = false;
    const container = document.getElementById(`${target}_container`);
    const video     = document.getElementById(`${target}_video`);
    const btnTorch  = document.getElementById(`${target}_torch`);

    const constraints = {
        audio: false,
        video: {
            facingMode: { ideal: 'environment' },
            width:      { ideal: 3840 },
            height:     { ideal: 2160 },
            focusMode:  { ideal: 'continuous' }
        }
    };

    try {
        activeStream     = await navigator.mediaDevices.getUserMedia(constraints);
        activeVideoTrack = activeStream.getVideoTracks()[0];
        video.srcObject  = activeStream;
        container.classList.remove('d-none');

        video.onloadedmetadata = () => initCameraControls(target);
        container.addEventListener('click', (e) => onCameraViewportClick(e, target));

    } catch (e) {
        Swal.fire('Cámara', 'No se pudo acceder a la cámara: ' + e.message, 'warning');
    }
}

function initCameraControls(target) {
    if (!activeVideoTrack) return;
    const caps     = activeVideoTrack.getCapabilities ? activeVideoTrack.getCapabilities() : {};
    const btnTorch = document.getElementById(`${target}_torch`);

    if (btnTorch) {
        btnTorch.style.display = caps.torch ? 'flex' : 'none';
        btnTorch.classList.remove('on');
    }

    if (caps.focusMode && caps.focusMode.includes('continuous')) {
        activeVideoTrack.applyConstraints({ advanced: [{ focusMode: 'continuous' }] }).catch(() => {});
    }

    showFocusToast(target, 'Toca para enfocar', 2000);
}

function onCameraViewportClick(e, target) {
    if (e.target.closest('button')) return;

    const container = document.getElementById(`${target}_container`);
    const ring      = document.getElementById(`${target}_ring`);
    if (!ring || !container) return;

    const rect = container.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;

    ring.style.left = x + 'px';
    ring.style.top  = y + 'px';
    ring.classList.remove('focus-active', 'focus-locked');
    void ring.offsetWidth;
    ring.classList.add('focus-active');

    if (activeVideoTrack) {
        const xR = x / rect.width;
        const yR = y / rect.height;
        activeVideoTrack.applyConstraints({
            advanced: [{ pointsOfInterest: [{ x: xR, y: yR }], focusMode: 'single-shot' }]
        }).then(() => {
            ring.classList.add('focus-locked');
            showFocusToast(target, '✓ Enfocado', 1500);
            setTimeout(() => {
                activeVideoTrack && activeVideoTrack.applyConstraints({ advanced: [{ focusMode: 'continuous' }] }).catch(() => {});
            }, 2000);
        }).catch(() => {
            ring.classList.add('focus-locked');
            showFocusToast(target, 'Enfoque ajustado', 1200);
        });
    }

    setTimeout(() => ring.classList.remove('focus-active', 'focus-locked'), 2500);
}

function showFocusToast(target, msg, duration) {
    const toast = document.getElementById(`${target}_toast`);
    if (!toast) return;
    if (focusToastTimers[target]) clearTimeout(focusToastTimers[target]);
    toast.textContent = msg;
    toast.style.opacity = '1';
    focusToastTimers[target] = setTimeout(() => { toast.style.opacity = '0'; }, duration || 1500);
}

function toggleCameraTorch(target) {
    if (!activeVideoTrack || currentCameraTarget !== target) return;
    torchActivo = !torchActivo;
    activeVideoTrack.applyConstraints({ advanced: [{ torch: torchActivo }] })
        .then(() => {
            const btn = document.getElementById(`${target}_torch`);
            if (btn) btn.classList.toggle('on', torchActivo);
        })
        .catch(() => {
            torchActivo = false;
            Swal.fire('Linterna', 'Este dispositivo no soporta linterna.', 'info');
        });
}

function captureSnapshot(target) {
    const video     = document.getElementById(`${target}_video`);
    const container = document.getElementById(`${target}_container`);
    const canvas    = document.createElement('canvas');

    canvas.width  = video.videoWidth  || video.clientWidth;
    canvas.height = video.videoHeight || video.clientHeight;
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

    if (container) {
        const flash = document.createElement('div');
        flash.style.cssText = 'position:absolute;inset:0;background:#fff;opacity:0.85;pointer-events:none;z-index:10;transition:opacity 0.35s';
        container.appendChild(flash);
        requestAnimationFrame(() => { flash.style.opacity = '0'; });
        setTimeout(() => flash.remove(), 400);
    }

    canvas.toBlob(blob => {
        const dataURL = canvas.toDataURL('image/jpeg', 0.92);

        if (target === 'finalizar_evidencia' || target === 'cam_evidencia') {
            fotosEvidenciaTmp.push({ tipo: 'cam', data: dataURL });
            renderEvidenciaPreviews();
        } else {
            $(`#${target}_data`).val(dataURL);
            const preview = target.replace('cam_', 'preview_');
            $(`#${preview}`).removeClass('d-none').find('img').attr('src', dataURL);
        }

        stopCamera();
    }, 'image/jpeg', 0.92);
}

function stopCamera() {
    if (torchActivo && activeVideoTrack) {
        activeVideoTrack.applyConstraints({ advanced: [{ torch: false }] }).catch(() => {});
        torchActivo = false;
    }
    if (activeStream) {
        activeStream.getTracks().forEach(track => track.stop());
        activeStream     = null;
        activeVideoTrack = null;
    }
    if (currentCameraTarget) {
        const container = document.getElementById(`${currentCameraTarget}_container`);
        if (container) {
            const clone = container.cloneNode(true);
            container.parentNode.replaceChild(clone, container);
            clone.classList.add('d-none');
        }
        const btnTorch = document.getElementById(`${currentCameraTarget}_torch`);
        if (btnTorch) { btnTorch.classList.remove('on'); btnTorch.style.display = 'none'; }
        currentCameraTarget = null;
    }
}

function abrirModalFinalizarTicket(ticketId) {
    $('#finalizar_ticket_id').val(ticketId);
    $('#formFinalizarTicket')[0].reset();
    $('#finalizar_evidencia_previews').empty();
    fotosEvidenciaTmp = [];
    stopCamera();
    
    // Abrir modal usando Bootstrap 5
    const modalEl = document.getElementById('finalizarTicketModal');
    let modal = bootstrap.Modal.getInstance(modalEl);
    if (!modal) {
        modal = new bootstrap.Modal(modalEl);
    }
    modal.show();
}

async function guardarFinalizacionDirecta() {
    const form = document.getElementById('formFinalizarTicket');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    if (fotosEvidenciaTmp.length === 0) {
        Swal.fire('Atención', 'Debe incluir al menos una foto de evidencia del trabajo', 'warning');
        return;
    }

    const formData = new FormData(form);
    const dt = new DataTransfer();
    const fotosCam = [];

    fotosEvidenciaTmp.forEach(f => {
        if (f.tipo === 'file') {
            dt.items.add(f.file);
        } else {
            fotosCam.push(f.data);
        }
    });

    document.getElementById('finalizar_evidencia_input').files = dt.files;
    
    const finalFormData = new FormData(form);
    Array.from(dt.files).forEach(f => finalFormData.append('fotos_evidencia[]', f));
    finalFormData.append('fotos_camera_json', JSON.stringify(fotosCam));

    Swal.fire({ 
        title: 'Guardando registro de trabajo...', 
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading() 
    });

    try {
        const response = await fetch('ajax/historial_finalizar_ticket.php', {
            method: 'POST',
            body: finalFormData
        });
        const res = await response.json();
        if (res.success) {
            Swal.fire('Guardado', 'Ticket finalizado correctamente', 'success').then(() => {
                const modalEl = document.getElementById('finalizarTicketModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
                cargarDatos(); // Recargar la tabla
            });
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    } catch (e) {
        Swal.fire('Error', e.message, 'error');
    }
}
