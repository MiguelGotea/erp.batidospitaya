// js/historial_solicitudes.js

let paginaActual = 1;
let registrosPorPagina = 25;
let totalRegistros = 0;
let filtros = {};
let ordenActual = { campo: 'created_at', direccion: 'DESC' };
let campoFiltroActual = '';

// Cargar datos al iniciar
$(document).ready(function() {
    cargarDatos();
    
    $('#registrosPorPagina').change(function() {
        registrosPorPagina = parseInt($(this).val());
        paginaActual = 1;
        cargarDatos();
    });
});

function cargarDatos() {
    $.ajax({
        url: 'ajax/historial_get_solicitudes.php',
        method: 'POST',
        data: {
            pagina: paginaActual,
            registros: registrosPorPagina,
            filtros: JSON.stringify(filtros),
            orden: JSON.stringify(ordenActual),
            filtrar_sucursal: FILTRAR_SUCURSAL,
            codigo_sucursal: CODIGO_SUCURSAL_BUSQUEDA
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderizarTabla(response.datos);
                renderizarPaginacion(response.total);
                totalRegistros = response.total;
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function() {
            alert('Error al cargar los datos');
        }
    });
}

function renderizarTabla(datos) {
    const tbody = $('#tablaSolicitudesBody');
    tbody.empty();
    
    if (datos.length === 0) {
        tbody.append('<tr><td colspan="9" class="text-center">No se encontraron registros</td></tr>');
        return;
    }
    
    datos.forEach(ticket => {
        const fechaSolicitado = ticket.created_at ? formatearFecha(ticket.created_at) : '-';
        const fechaAgendado = ticket.fecha_inicio ? formatearFecha(ticket.fecha_inicio) : '-';
        const colorUrgencia = getColorUrgencia(ticket.nivel_urgencia);
        const textoUrgencia = getTextoUrgencia(ticket.nivel_urgencia);
        const colorStatus = getColorStatus(ticket.status);
        const textoTipo = ticket.tipo_formulario === 'cambio_equipos' ? 'Cambio Equipo' : 'Mantenimiento';
        const totalFotos = ticket.total_fotos || 0;
        
        const row = `
            <tr>
                <td>${fechaSolicitado}</td>
                <td>
                    <div class="texto-truncado" title="${escapeHtml(ticket.titulo)}">
                        ${escapeHtml(ticket.titulo)}
                    </div>
                </td>
                <td>
                    <div class="texto-expandible" onclick="expandirTexto(this)">
                        ${escapeHtml(ticket.descripcion || '')}
                    </div>
                </td>
                <td>${escapeHtml(ticket.nombre_sucursal)}</td>
                <td>
                    <span class="badge-tipo">${textoTipo}</span>
                </td>
                <td>
                    <div class="urgencia-selector">
                        ${[0, 1, 2, 3, 4].map(nivel => `
                            <button class="urgencia-btn ${ticket.nivel_urgencia == nivel ? 'active' : ''}" 
                                    style="background-color: ${getColorUrgencia(nivel)};"
                                    onclick="cambiarUrgencia(${ticket.id}, ${nivel})"
                                    title="${getTextoUrgencia(nivel)}">
                                ${nivel || 'X'}
                            </button>
                        `).join('')}
                    </div>
                </td>
                <td>
                    <span class="badge-status" style="background-color: ${colorStatus};">
                        ${ticket.status}
                    </span>
                </td>
                <td>${fechaAgendado}</td>
                <td>
                    <button class="btn-fotos" 
                            onclick="verFotos(${ticket.id})" 
                            ${totalFotos === 0 ? 'disabled' : ''}>
                        <i class="bi bi-images"></i> ${totalFotos}
                    </button>
                </td>
            </tr>
        `;
        
        tbody.append(row);
    });
    
    actualizarInfoPaginacion();
}

function renderizarPaginacion(total) {
    const totalPaginas = Math.ceil(total / registrosPorPagina);
    const paginacion = $('#paginacion');
    paginacion.empty();
    
    // Botón anterior
    paginacion.append(`
        <li class="page-item ${paginaActual === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="cambiarPagina(${paginaActual - 1}); return false;">
                <i class="bi bi-chevron-left"></i>
            </a>
        </li>
    `);
    
    // Números de página
    const rango = 2;
    for (let i = Math.max(1, paginaActual - rango); i <= Math.min(totalPaginas, paginaActual + rango); i++) {
        paginacion.append(`
            <li class="page-item ${i === paginaActual ? 'active' : ''}">
                <a class="page-link" href="#" onclick="cambiarPagina(${i}); return false;">${i}</a>
            </li>
        `);
    }
    
    // Botón siguiente
    paginacion.append(`
        <li class="page-item ${paginaActual === totalPaginas ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="cambiarPagina(${paginaActual + 1}); return false;">
                <i class="bi bi-chevron-right"></i>
            </a>
        </li>
    `);
}

function cambiarPagina(pagina) {
    paginaActual = pagina;
    cargarDatos();
}

function actualizarInfoPaginacion() {
    const inicio = (paginaActual - 1) * registrosPorPagina + 1;
    const fin = Math.min(paginaActual * registrosPorPagina, totalRegistros);
    $('#infoPaginacion').text(`Mostrando ${inicio} a ${fin} de ${totalRegistros} registros`);
}

// Filtros
function abrirFiltro(campo, titulo) {
    campoFiltroActual = campo;
    $('#modalFiltroTitulo').text('Filtro: ' + titulo);
    $('#inputBuscarFiltro').val('');
    
    cargarOpcionesFiltro(campo);
    
    const modal = new bootstrap.Modal(document.getElementById('modalFiltro'));
    modal.show();
}

function cargarOpcionesFiltro(campo) {
    $.ajax({
        url: 'ajax/historial_get_opciones_filtro.php',
        method: 'POST',
        data: { 
            campo: campo,
            filtrar_sucursal: FILTRAR_SUCURSAL,
            codigo_sucursal: CODIGO_SUCURSAL_BUSQUEDA
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderizarOpcionesFiltro(response.opciones, campo);
            }
        }
    });
}

function renderizarOpcionesFiltro(opciones, campo) {
    const lista = $('#listaOpciones');
    lista.empty();
    
    // Verificar si el filtro de sucursal debe estar bloqueado
    if (campo === 'nombre_sucursal' && FILTRAR_SUCURSAL) {
        lista.append('<div class="text-muted text-center p-3">Filtro bloqueado por permisos</div>');
        return;
    }
    
    opciones.forEach(opcion => {
        const selected = filtros[campo] && filtros[campo].includes(opcion.valor) ? 'selected' : '';
        lista.append(`
            <div class="opcion-filtro ${selected}" onclick="toggleOpcion('${campo}', '${escapeHtml(opcion.valor)}')">
                ${escapeHtml(opcion.texto)}
            </div>
        `);
    });
}

function filtrarOpciones() {
    const busqueda = $('#inputBuscarFiltro').val().toLowerCase();
    $('.opcion-filtro').each(function() {
        const texto = $(this).text().toLowerCase();
        $(this).toggle(texto.includes(busqueda));
    });
}

function toggleOpcion(campo, valor) {
    if (!filtros[campo]) {
        filtros[campo] = [];
    }
    
    const index = filtros[campo].indexOf(valor);
    const elemento = event.target;
    
    if (index > -1) {
        filtros[campo].splice(index, 1);
        if (filtros[campo].length === 0) {
            delete filtros[campo];
        }
        elemento.classList.remove('selected');
    } else {
        filtros[campo].push(valor);
        elemento.classList.add('selected');
    }
    
    // Aplicar filtro automáticamente
    paginaActual = 1;
    cargarDatos();
}

function aplicarOrden(direccion) {
    ordenActual = {
        campo: campoFiltroActual,
        direccion: direccion
    };
    paginaActual = 1;
    cargarDatos();
    
    const modal = bootstrap.Modal.getInstance(document.getElementById('modalFiltro'));
    modal.hide();
}

function limpiarFiltro() {
    if (campoFiltroActual && filtros[campoFiltroActual]) {
        delete filtros[campoFiltroActual];
        paginaActual = 1;
        cargarDatos();
    }
    
    const modal = bootstrap.Modal.getInstance(document.getElementById('modalFiltro'));
    modal.hide();
}

// Urgencia
function cambiarUrgencia(ticketId, nivel) {
    $.ajax({
        url: 'ajax/historial_cambiar_urgencia.php',
        method: 'POST',
        data: {
            ticket_id: ticketId,
            nivel_urgencia: nivel
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                cargarDatos();
            } else {
                alert('Error: ' + response.message);
            }
        }
    });
}

// Fotos
function verFotos(ticketId) {
    $.ajax({
        url: 'ajax/historial_get_fotos.php',
        method: 'GET',
        data: { ticket_id: ticketId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderizarCarruselFotos(response.fotos);
                const modal = new bootstrap.Modal(document.getElementById('modalFotos'));
                modal.show();
            }
        }
    });
}

function renderizarCarruselFotos(fotos) {
    const carousel = $('#carouselFotosInner');
    carousel.empty();
    
    if (fotos.length === 0) {
        carousel.append('<div class="carousel-item active"><p class="text-center">No hay fotos disponibles</p></div>');
        return;
    }
    
    fotos.forEach((foto, index) => {
        carousel.append(`
            <div class="carousel-item ${index === 0 ? 'active' : ''}">
                <img src="${foto.foto}" class="d-block w-100" alt="Foto ${index + 1}">
            </div>
        `);
    });
}

// Utilidades
function formatearFecha(fecha) {
    const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    const d = new Date(fecha);
    return `${String(d.getDate()).padStart(2, '0')}-${meses[d.getMonth()]}`;
}

function getColorUrgencia(nivel) {
    switch(parseInt(nivel)) {
        case 1: return '#28a745';
        case 2: return '#ffc107';
        case 3: return '#fd7e14';
        case 4: return '#dc3545';
        default: return '#8b8b8bff';
    }
}

function getTextoUrgencia(nivel) {
    switch(parseInt(nivel)) {
        case 1: return 'No Urgente';
        case 2: return 'Medio';
        case 3: return 'Urgente';
        case 4: return 'Crítico';
        default: return 'No Clasificado';
    }
}

function getColorStatus(status) {
    switch(status) {
        case 'solicitado': return '#17a2b8';
        case 'clasificado': return '#ffc107';
        case 'agendado': return '#28a745';
        case 'finalizado': return '#6c757d';
        default: return '#6c757d';
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

function expandirTexto(element) {
    if (element.style.webkitLineClamp) {
        element.style.webkitLineClamp = 'unset';
        element.style.maxWidth = 'none';
    } else {
        element.style.webkitLineClamp = '3';
        element.style.maxWidth = '400px';
    }
}