// js/historial_solicitudes.js

let paginaActual = 1;
let registrosPorPagina = 25;
let filtrosActivos = {};
let ordenActivo = { columna: null, direccion: 'asc' };
let panelFiltroAbierto = null;

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
    'finalizado': '#28a745'
};

// Inicializar al cargar la página
$(document).ready(function() {
    cargarDatos();
    
    // Cerrar filtros al hacer click fuera
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.filter-panel, .filter-icon').length) {
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
        success: function(response) {
            if (response.success) {
                renderizarTabla(response.datos);
                renderizarPaginacion(response.total_registros);
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function() {
            alert('Error al cargar los datos');
        }
    });
}

// Renderizar tabla
function renderizarTabla(datos) {
    const tbody = $('#tablaSolicitudesBody');
    tbody.empty();
    
    if (datos.length === 0) {
        tbody.append('<tr><td colspan="9" class="text-center py-4">No se encontraron registros</td></tr>');
        return;
    }
    
    datos.forEach(row => {
        const tr = $('<tr>');
        
        // Solicitado
        tr.append(`<td>${formatearFecha(row.created_at)}</td>`);
        
        // Título
        tr.append(`<td class="col-titulo">${row.titulo}</td>`);
        
        // Descripción
        tr.append(`<td class="col-descripcion">${row.descripcion}</td>`);
        
        // Sucursal
        tr.append(`<td class="col-sucursal">${row.nombre_sucursal}</td>`);
        
        // Tipo
        const tipoClass = row.tipo_formulario === 'cambio_equipos' ? 'cambio-equipo' : 'mantenimiento';
        const tipoText = row.tipo_formulario === 'cambio_equipos' ? 'Cambio Equipo' : 'Mantenimiento';
        tr.append(`<td><span class="badge-tipo ${tipoClass}">${tipoText}</span></td>`);
        
        // Urgencia
        tr.append(`<td>${renderizarUrgencia(row.id, row.nivel_urgencia)}</td>`);
        
        // Estado
        const colorEstado = coloresEstado[row.status] || '#6c757d';
        tr.append(`<td><span class="badge-estado" style="background-color: ${colorEstado};">${row.status}</span></td>`);
        
        // Agendado
        tr.append(`<td>${row.fecha_inicio ? formatearFecha(row.fecha_inicio) : '-'}</td>`);
        
        // Foto
        const tieneFotos = parseInt(row.total_fotos) > 0;
        const btnFoto = tieneFotos 
            ? `<button class="btn-foto" onclick="mostrarFotos(${row.id})"><i class="bi bi-camera"></i> Ver (${row.total_fotos})</button>`
            : `<button class="btn-foto" disabled><i class="bi bi-camera"></i> Sin fotos</button>`;
        tr.append(`<td>${btnFoto}</td>`);
        
        tbody.append(tr);
    });
}

// Renderizar selector de urgencia
function renderizarUrgencia(ticketId, nivelActual) {
    const nivel = nivelActual || 0;
    const color = coloresUrgencia[nivel];
    const texto = textosUrgencia[nivel];
    
    return `
        <div class="urgency-selector" style="background-color: ${color};" onclick="cambiarUrgencia(${ticketId}, ${nivel})">
            <div class="urgency-number" style="background-color: rgba(0,0,0,0.2);">${nivel}</div>
            <div class="urgency-text">${texto}</div>
        </div>
    `;
}

// Cambiar nivel de urgencia
function cambiarUrgencia(ticketId, nivelActual) {
    const opciones = `
        <div style="padding: 0.5rem;">
            <div style="font-weight: 600; margin-bottom: 0.5rem;">Seleccionar nivel:</div>
            ${[0, 1, 2, 3, 4].map(nivel => {
                const color = coloresUrgencia[nivel];
                const texto = textosUrgencia[nivel];
                const selected = nivel === nivelActual ? '✓ ' : '';
                return `
                    <div style="padding: 0.35rem; cursor: pointer; border-radius: 3px; margin-bottom: 0.25rem; background-color: ${color}; color: white; display: flex; align-items: center; gap: 0.5rem;" 
                         onmouseover="this.style.opacity='0.8'" 
                         onmouseout="this.style.opacity='1'"
                         onclick="actualizarUrgencia(${ticketId}, ${nivel})">
                        <span style="font-weight: bold;">${selected}${nivel}</span>
                        <span>${texto}</span>
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
    
    $('#modalUrgencia').on('hidden.bs.modal', function() {
        $(this).remove();
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
        success: function(response) {
            if (response.success) {
                cargarDatos();
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function() {
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
        success: function(response) {
            if (response.success && response.fotos.length > 0) {
                const carouselInner = $('#carouselFotosInner');
                carouselInner.empty();
                
                response.fotos.forEach((foto, index) => {
                    const activeClass = index === 0 ? 'active' : '';
                    carouselInner.append(`
                        <div class="carousel-item ${activeClass}">
                            <img src="${foto.foto}" class="d-block w-100" alt="Foto ${index + 1}">
                        </div>
                    `);
                });
                
                $('#modalFotos').modal('show');
            } else {
                alert('No se encontraron fotos');
            }
        },
        error: function() {
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
    crearPanelFiltro(th, columna, tipo);
    panelFiltroAbierto = columna;
    $(icon).addClass('active');
}

// Crear panel de filtro
function crearPanelFiltro(th, columna, tipo) {
    const panel = $('<div class="filter-panel show"></div>');
    
    // Sección de ordenamiento
    panel.append(`
        <div class="filter-section">
            <label>Ordenar:</label>
            <button class="filter-sort-btn ${ordenActivo.columna === columna && ordenActivo.direccion === 'asc' ? 'active' : ''}" 
                    onclick="aplicarOrden('${columna}', 'asc')">
                <i class="bi bi-sort-alpha-down"></i> Ascendente
            </button>
            <button class="filter-sort-btn ${ordenActivo.columna === columna && ordenActivo.direccion === 'desc' ? 'active' : ''}" 
                    onclick="aplicarOrden('${columna}', 'desc')">
                <i class="bi bi-sort-alpha-up"></i> Descendente
            </button>
        </div>
    `);
    
    // Sección de búsqueda y opciones según el tipo
    if (tipo === 'text' || tipo === 'date') {
        panel.append(`
            <div class="filter-section">
                <label>Buscar:</label>
                <input type="text" class="filter-search" placeholder="Escribir..." 
                       oninput="filtrarBusqueda('${columna}', this.value)">
            </div>
        `);
    } else if (tipo === 'list' || tipo === 'urgency') {
        // Cargar opciones únicas de la columna
        cargarOpcionesFiltro(panel, columna, tipo);
    }
    
    th.css('position', 'relative');
    th.append(panel);
}

// Cargar opciones de filtro
function cargarOpcionesFiltro(panel, columna, tipo) {
    $.ajax({
        url: 'ajax/historial_get_opciones_filtro.php',
        method: 'POST',
        data: { columna: columna, tipo: tipo },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let html = '<div class="filter-section"><label>Filtrar por:</label>';
                html += '<input type="text" class="filter-search" placeholder="Buscar..." onkeyup="buscarEnOpciones(this)">';
                html += '<div class="filter-options">';
                
                response.opciones.forEach(opcion => {
                    const checked = filtrosActivos[columna] && filtrosActivos[columna].includes(opcion.valor) ? 'checked' : '';
                    const disabled = columna === 'nombre_sucursal' && filtroSucursalBloqueado && opcion.valor !== codigoSucursalBusqueda ? 'disabled' : '';
                    const disabledClass = disabled ? 'disabled' : '';
                    
                    html += `
                        <div class="filter-option ${disabledClass}">
                            <input type="checkbox" value="${opcion.valor}" ${checked} ${disabled}
                                   onchange="toggleOpcionFiltro('${columna}', this.value, this.checked)">
                            <span>${opcion.texto}</span>
                        </div>
                    `;
                });
                
                html += '</div></div>';
                panel.append(html);
                
                // Si el filtro está bloqueado, marcar automáticamente la sucursal
                if (columna === 'nombre_sucursal' && filtroSucursalBloqueado && codigoSucursalBusqueda) {
                    if (!filtrosActivos[columna]) {
                        filtrosActivos[columna] = [codigoSucursalBusqueda];
                        cargarDatos();
                    }
                }
            }
        }
    });
}

// Cerrar todos los filtros
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

// Filtrar por búsqueda de texto
function filtrarBusqueda(columna, valor) {
    if (valor.trim() === '') {
        delete filtrosActivos[columna];
    } else {
        filtrosActivos[columna] = valor;
    }
    paginaActual = 1;
    cargarDatos();
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
}

// Buscar en opciones
function buscarEnOpciones(input) {
    const busqueda = input.value.toLowerCase();
    const opciones = $(input).siblings('.filter-options').find('.filter-option');
    
    opciones.each(function() {
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