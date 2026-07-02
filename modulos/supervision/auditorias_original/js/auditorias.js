// auditorias.js
let paginaActual = 1;
let registrosPorPagina = 25;
let filtrosActivos = {};
let ordenActivo = { columna: null, direccion: 'asc' };
let panelFiltroAbierto = null;
let totalRegistros = 0;
let scrollTopInicial = 0;

// Inicializar
$(document).ready(function() {
    cargarDatos();
    
    // Cerrar filtros solo si se hace clic fuera del panel Y del icono
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.filter-panel, .filter-icon').length) {
            cerrarTodosFiltros();
        }
    });
    
    // Permitir scroll dentro del panel de filtro sin cerrarlo
    $(document).on('wheel', '.filter-panel', function(e) {
        e.stopPropagation();
        
        // Permitir scroll natural dentro del panel
        const panel = $(this);
        const scrollTop = panel.scrollTop();
        const scrollHeight = panel.prop('scrollHeight');
        const height = panel.outerHeight();
        const delta = e.originalEvent.deltaY;
        
        // Prevenir scroll de la página solo cuando el panel puede scrollear
        if ((delta > 0 && scrollTop + height < scrollHeight) || 
            (delta < 0 && scrollTop > 0)) {
            // No hacer nada, dejar que el scroll funcione naturalmente
        } else {
            // Prevenir scroll de la página cuando llegamos al límite
            e.preventDefault();
        }
    });
    
    // Cerrar filtros al hacer scroll en la página principal (no en el panel)
    $(window).on('scroll', function(e) {
        // Solo cerrar si hay un panel abierto y el scroll es significativo
        if (panelFiltroAbierto && Math.abs($(window).scrollTop() - scrollTopInicial) > 100) {
            cerrarTodosFiltros();
        }
    });
    
    $(window).on('resize', function() {
        if (panelFiltroAbierto) {
            cerrarTodosFiltros();
        }
    });
});

// Cargar datos
function cargarDatos() {
    $.ajax({
        url: 'ajax/auditorias_get_datos.php',
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
                totalRegistros = response.total_registros;
                renderizarTabla(response.datos);
                renderizarPaginacion(response.total_registros);
                actualizarIndicadoresFiltros();
            } else {
                mostrarError('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX:', error);
            mostrarError('Error al cargar los datos');
        }
    });
}

// Mostrar error en la tabla
function mostrarError(mensaje) {
    const tbody = $('#tablaAuditoriasBody');
    tbody.html('<tr><td colspan="6" class="sin-registros">' + mensaje + '</td></tr>');
}

// Renderizar tabla
function renderizarTabla(datos) {
    const tbody = $('#tablaAuditoriasBody');
    tbody.empty();
    
    if (datos.length === 0) {
        tbody.append('<tr><td colspan="6" class="sin-registros">No se encontraron registros</td></tr>');
        return;
    }
    
    datos.forEach(row => {
        const tr = $('<tr>');
        
        // Columna número (oculta)
        tr.append(`<td class="columna-numero">${row.id}</td>`);
        
        // Columna Fecha
        tr.append(`<td>${formatearFechaHora(row.fecha_hora)}</td>`);
        
        // Columna Sucursal
        tr.append(`<td>${row.sucursal || '-'}</td>`);
        
        // Columna Persona
        tr.append(`<td>${row.persona || '-'}</td>`);
        
        // Columna Tipo
        tr.append(`<td>${capitalizarPrimeraLetra(row.tipo_auditoria)}</td>`);
        
        // Columna Puntaje con enlace para ver detalle
        const urlDetalle = obtenerUrlDetalle(row.tipo_auditoria, row.id);
        tr.append(`
            <td class="columna-promedio">
                <div class="promedio-contenedor">
                    ${Number(row.promedio).toFixed(2)}
                    <a href="${urlDetalle}" title="Ver detalle">
                        <i class="fas fa-eye"></i>
                    </a>
                </div>
            </td>
        `);
        
        tbody.append(tr);
    });
}

// Obtener URL de detalle según tipo de auditoría
function obtenerUrlDetalle(tipo, id) {
    switch(tipo) {
        case 'limpieza':
            return 'ver.php?id=' + id;
        case 'personal':
            return 'verpersonal.php?id=' + id;
        case 'servicio':
            return 'verservicios.php?id=' + id;
        case 'procesos':
            return 'verprocesos.php?id=' + id;
        case 'promociones':
            return 'auditinternas/ver_auditoria_promociones.php?id=' + id;
        default:
            return '#';
    }
}

// Formatear fecha y hora
function formatearFechaHora(fechaHora) {
    if (!fechaHora) return '-';
    
    // Separar fecha y hora usando guiones, espacios, dos puntos o 'T' (para evitar conversiones de zona horaria por el navegador)
    const partes = fechaHora.split(/[- :T]/);
    if (partes.length < 3) return fechaHora;
    
    const anioStr = partes[0];
    const mesIdx = parseInt(partes[1], 10) - 1;
    const diaStr = partes[2].padStart(2, '0');
    
    const mesesCortos = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    const mesStr = mesesCortos[mesIdx] || '???';
    const anioCorto = anioStr.slice(-2);
    
    if (partes.length >= 5 && partes[3] !== '') {
        let horas = parseInt(partes[3], 10);
        const minutos = partes[4].padStart(2, '0');
        const periodo = horas >= 12 ? 'pm' : 'am';
        
        if (horas === 0) {
            horas = 12;
        } else if (horas > 12) {
            horas = horas - 12;
        }
        
        return `${diaStr}-${mesStr}-${anioCorto} ${horas}:${minutos} ${periodo}`;
    } else {
        return `${diaStr}-${mesStr}-${anioCorto}`;
    }
}

// Capitalizar primera letra
function capitalizarPrimeraLetra(texto) {
    if (!texto) return '';
    return texto.charAt(0).toUpperCase() + texto.slice(1);
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
    
    // Sección de Ordenamiento
    let iconoAsc = '<i class="bi bi-sort-alpha-down"></i> A→Z';
    let iconoDesc = '<i class="bi bi-sort-alpha-up"></i> Z→A';
    
    if (tipo === 'daterange') {
        iconoAsc = '<i class="bi bi-sort-numeric-down"></i> Antigua→Reciente';
        iconoDesc = '<i class="bi bi-sort-numeric-up"></i> Reciente→Antigua';
    }
    
    panel.append(`
        <div class="filter-section">
            <span class="filter-section-title">Ordenar:</span>
            <div class="filter-sort-buttons">
                <button class="filter-sort-btn ${ordenActivo.columna === columna && ordenActivo.direccion === 'asc' ? 'active' : ''}" 
                        onclick="aplicarOrden('${columna}', 'asc')">
                    ${iconoAsc}
                </button>
                <button class="filter-sort-btn ${ordenActivo.columna === columna && ordenActivo.direccion === 'desc' ? 'active' : ''}" 
                        onclick="aplicarOrden('${columna}', 'desc')">
                    ${iconoDesc}
                </button>
            </div>
        </div>
    `);
    
    // Botón Limpiar (después del ordenamiento)
    panel.append(`
        <div class="filter-actions">
            <button class="filter-action-btn clear" onclick="limpiarFiltro('${columna}')">
                <i class="bi bi-x-circle"></i> Limpiar
            </button>
        </div>
    `);
    
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
    } else if (tipo === 'list') {
        cargarOpcionesFiltro(panel, columna);
    } else if (tipo === 'daterange') {
        crearCalendarioDoble(panel, columna);
    }
    
    $('body').append(panel);
    posicionarPanelFiltro(panel, icon);
}

// Crear calendario doble para rango de fechas
function crearCalendarioDoble(panel, columna) {
    const fechaDesde = filtrosActivos[columna]?.desde || '';
    const fechaHasta = filtrosActivos[columna]?.hasta || '';
    
    const hoy = new Date();
    const mesActual = hoy.getMonth();
    const añoActual = hoy.getFullYear();
    
    panel.append(`
        <div class="filter-section" style="margin-top: 12px;">
            <span class="filter-section-title">Desde:</span>
            <div class="daterange-inputs">
                <div class="daterange-calendar-container">
                    <div class="daterange-month-selector">
                        <select id="mesDesde" onchange="actualizarCalendario('desde', '${columna}')"></select>
                        <select id="añoDesde" onchange="actualizarCalendario('desde', '${columna}')"></select>
                    </div>
                    <div class="daterange-calendar" id="calendarioDesde"></div>
                </div>
            </div>
        </div>
        <div class="filter-section">
            <span class="filter-section-title">Hasta:</span>
            <div class="daterange-inputs">
                <div class="daterange-calendar-container">
                    <div class="daterange-month-selector">
                        <select id="mesHasta" onchange="actualizarCalendario('hasta', '${columna}')"></select>
                        <select id="añoHasta" onchange="actualizarCalendario('hasta', '${columna}')"></select>
                    </div>
                    <div class="daterange-calendar" id="calendarioHasta"></div>
                </div>
            </div>
        </div>
    `);
    
    setTimeout(() => {
        inicializarSelectoresFecha(mesActual, añoActual, fechaDesde, fechaHasta);
        actualizarCalendario('desde', columna);
        actualizarCalendario('hasta', columna);
    }, 50);
}

// Inicializar selectores de fecha
function inicializarSelectoresFecha(mesActual, añoActual, fechaDesde, fechaHasta) {
    const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                   'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    
    let mesDesdeSeleccionado = mesActual;
    let añoDesdeSeleccionado = añoActual;
    let mesHastaSeleccionado = mesActual;
    let añoHastaSeleccionado = añoActual;
    
    if (fechaDesde) {
        const d = new Date(fechaDesde);
        mesDesdeSeleccionado = d.getMonth();
        añoDesdeSeleccionado = d.getFullYear();
    }
    
    if (fechaHasta) {
        const d = new Date(fechaHasta);
        mesHastaSeleccionado = d.getMonth();
        añoHastaSeleccionado = d.getFullYear();
    }
    
    // Llenar selectores de mes
    const selectMesDesde = $('#mesDesde');
    const selectMesHasta = $('#mesHasta');
    meses.forEach((mes, idx) => {
        selectMesDesde.append(`<option value="${idx}" ${idx === mesDesdeSeleccionado ? 'selected' : ''}>${mes}</option>`);
        selectMesHasta.append(`<option value="${idx}" ${idx === mesHastaSeleccionado ? 'selected' : ''}>${mes}</option>`);
    });
    
    // Llenar selectores de año (5 años atrás, 1 adelante)
    const selectAñoDesde = $('#añoDesde');
    const selectAñoHasta = $('#añoHasta');
    for (let año = añoActual - 5; año <= añoActual + 1; año++) {
        selectAñoDesde.append(`<option value="${año}" ${año === añoDesdeSeleccionado ? 'selected' : ''}>${año}</option>`);
        selectAñoHasta.append(`<option value="${año}" ${año === añoHastaSeleccionado ? 'selected' : ''}>${año}</option>`);
    }
}

// Actualizar calendario
function actualizarCalendario(tipo, columna) {
    const mes = parseInt($(`#mes${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`).val());
    const año = parseInt($(`#año${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`).val());
    const calendarioId = tipo === 'desde' ? '#calendarioDesde' : '#calendarioHasta';
    
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
        html += `<div class="daterange-calendar-day ${clases}" onclick="event.stopPropagation(); seleccionarFecha('${tipo}', '${fechaStr}', '${columna}')">${dia}</div>`;
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
function seleccionarFecha(tipo, fecha, columna) {
    if (window.event) {
        window.event.stopPropagation();
    }
    
    if (!filtrosActivos[columna]) {
        filtrosActivos[columna] = {};
    }
    
    filtrosActivos[columna][tipo] = fecha;
    
    // Actualizar ambos calendarios para mostrar el rango
    actualizarCalendario('desde', columna);
    actualizarCalendario('hasta', columna);
    
    // Aplicar filtro si ambas fechas están seleccionadas
    if (filtrosActivos[columna].desde && filtrosActivos[columna].hasta) {
        paginaActual = 1;
        cargarDatos();
    }
}

// Cargar opciones de filtro (para listas)
function cargarOpcionesFiltro(panel, columna) {
    $.ajax({
        url: 'ajax/auditorias_get_opciones_filtro.php',
        method: 'POST',
        data: { columna: columna },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let html = '<div class="filter-section" style="margin-top: 12px;">';
                html += '<span class="filter-section-title">Filtrar por:</span>';
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
            }
        },
        error: function() {
            console.error('Error al cargar opciones de filtro');
        }
    });
}

// Posicionar panel de filtro
function posicionarPanelFiltro(panel, icon) {
    const iconRect = icon.getBoundingClientRect();
    const panelWidth = panel.outerWidth();
    const windowWidth = $(window).width();
    const windowHeight = $(window).height();
    
    // Posición inicial: justo debajo del ícono
    let top = iconRect.bottom + 5;
    let left = iconRect.right - panelWidth;
    
    // Ajustar si se sale por la derecha
    if (left + panelWidth > windowWidth - 10) {
        left = windowWidth - panelWidth - 10;
    }
    
    // Ajustar si se sale por la izquierda
    if (left < 10) {
        left = 10;
    }
    
    // Calcular altura máxima disponible
    const espacioAbajo = windowHeight - iconRect.bottom - 20;
    const espacioArriba = iconRect.top - 20;
    
    // Si no hay suficiente espacio abajo, mostrar arriba
    if (espacioAbajo < 300 && espacioArriba > espacioAbajo) {
        // Mostrar arriba del ícono
        panel.css('max-height', Math.min(espacioArriba, 500) + 'px');
        // Necesitamos recalcular después de aplicar max-height
        setTimeout(() => {
            const newPanelHeight = panel.outerHeight();
            panel.css({ 
                top: (iconRect.top - newPanelHeight - 5) + 'px', 
                left: left + 'px',
                position: 'fixed'
            });
        }, 10);
    } else {
        // Mostrar abajo del ícono
        panel.css({ 
            top: top + 'px', 
            left: left + 'px',
            position: 'fixed',
            'max-height': Math.min(espacioAbajo, 500) + 'px'
        });
    }
}

// Actualizar indicadores de filtros activos
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

// Limpiar filtro de una columna
function limpiarFiltro(columna) {
    delete filtrosActivos[columna];
    cerrarTodosFiltros();
    paginaActual = 1;
    cargarDatos();
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

// Toggle opción de filtro (para listas con checkboxes)
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
    
    if (totalPaginas <= 1) {
        return;
    }
    
    // Botón anterior
    paginacion.append(`
        <button class="pagination-btn" onclick="cambiarPagina(${paginaActual - 1})" ${paginaActual === 1 ? 'disabled' : ''}>
            <i class="bi bi-chevron-left"></i>
        </button>
    `);
    
    // Páginas
    let inicio = Math.max(1, paginaActual - 2);
    let fin = Math.min(totalPaginas, paginaActual + 2);
    
    if (inicio > 1) {
        paginacion.append(`<button class="pagination-btn" onclick="cambiarPagina(1)">1</button>`);
        if (inicio > 2) {
            paginacion.append(`<span class="pagination-btn" style="cursor: default;">...</span>`);
        }
    }
    
    for (let i = inicio; i <= fin; i++) {
        const activeClass = i === paginaActual ? 'active' : '';
        paginacion.append(`<button class="pagination-btn ${activeClass}" onclick="cambiarPagina(${i})">${i}</button>`);
    }
    
    if (fin < totalPaginas) {
        if (fin < totalPaginas - 1) {
            paginacion.append(`<span class="pagination-btn" style="cursor: default;">...</span>`);
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
    const totalPaginas = Math.ceil(totalRegistros / registrosPorPagina);
    if (pagina < 1 || pagina > totalPaginas) return;
    paginaActual = pagina;
    cargarDatos();
}