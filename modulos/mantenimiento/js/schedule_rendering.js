// schedule_rendering.js - Sistema de renderizado y drag & drop

function renderizarCronograma(data) {
    const grid = document.getElementById('schedule-grid');
    grid.innerHTML = '';
    
    // Headers
    const headerEquipo = document.createElement('div');
    headerEquipo.className = 'schedule-header team-label';
    headerEquipo.textContent = 'Equipo de Trabajo';
    grid.appendChild(headerEquipo);
    
    data.fechas.forEach(fecha => {
        const header = document.createElement('div');
        header.className = 'schedule-header';
        const d = new Date(fecha.fecha + 'T00:00:00');
        const dias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        header.innerHTML = `${dias[d.getDay()]}<br>${fecha.fecha_formato}`;
        grid.appendChild(header);
    });
    
    // Crear filas por equipo
    data.equipos.forEach(equipo => {
        // Celda de nombre de equipo
        const teamCell = document.createElement('div');
        teamCell.className = 'team-cell';
        teamCell.textContent = equipo.nombre;
        teamCell.dataset.equipo = equipo.id;
        grid.appendChild(teamCell);
        
        // Calcular posiciones de tickets con algoritmo de empaquetado
        const filasOcupacion = calcularPosiciones(equipo.tickets, data.fechas);
        
        // Crear celdas de días
        data.fechas.forEach((fecha, diaIdx) => {
            const dayCell = document.createElement('div');
            dayCell.className = 'day-cell';
            dayCell.dataset.fecha = fecha.fecha;
            dayCell.dataset.equipo = equipo.id;
            dayCell.dataset.tipoFormulario = equipo.tipo_formulario;
            
            // Altura dinámica basada en número de filas
            const alturaMinima = (filasOcupacion.length * 60) + 20;
            dayCell.style.minHeight = Math.max(80, alturaMinima) + 'px';
            
            // Eventos drag & drop
            dayCell.addEventListener('dragover', handleDragOver);
            dayCell.addEventListener('drop', handleDrop);
            dayCell.addEventListener('dragleave', handleDragLeave);
            
            grid.appendChild(dayCell);
        });
        
        // Renderizar tickets
        equipo.tickets.forEach(ticket => {
            renderizarTicket(ticket, data.fechas, equipo.id, filasOcupacion);
        });
    });
}

function calcularPosiciones(tickets, fechas) {
    const filas = [];
    const posiciones = new Map();
    
    tickets.forEach(ticket => {
        const fechaInicio = ticket.fecha_inicio;
        const fechaFinal = ticket.fecha_final;
        
        // Calcular índices de día
        const diaInicio = fechas.findIndex(f => f.fecha === fechaInicio);
        if (diaInicio === -1) return;
        
        const diaFinal = fechas.findIndex(f => f.fecha === fechaFinal);
        const numDias = Math.min(
            (diaFinal >= 0 ? diaFinal : fechas.length - 1) - diaInicio + 1,
            fechas.length - diaInicio
        );
        
        // Buscar primera fila disponible
        let filaAsignada = -1;
        for (let i = 0; i < filas.length; i++) {
            let cabe = true;
            for (let j = 0; j < numDias; j++) {
                if (filas[i][diaInicio + j]) {
                    cabe = false;
                    break;
                }
            }
            if (cabe) {
                filaAsignada = i;
                break;
            }
        }
        
        // Si no cabe en ninguna fila, crear nueva
        if (filaAsignada === -1) {
            filaAsignada = filas.length;
            filas.push(Array(fechas.length).fill(false));
        }
        
        // Marcar días como ocupados
        for (let j = 0; j < numDias; j++) {
            filas[filaAsignada][diaInicio + j] = true;
        }
        
        // Guardar posición
        posiciones.set(ticket.id, {
            fila: filaAsignada,
            diaInicio: diaInicio,
            numDias: numDias
        });
    });
    
    // Almacenar posiciones globalmente
    tickets.forEach(ticket => {
        ticket._posicion = posiciones.get(ticket.id);
    });
    
    return filas;
}

function renderizarTicket(ticket, fechas, equipoId, filasOcupacion) {
    if (!ticket._posicion) return;
    
    const { fila, diaInicio, numDias } = ticket._posicion;
    
    // Obtener celda del día de inicio
    const selector = `.day-cell[data-fecha="${fechas[diaInicio].fecha}"][data-equipo="${equipoId}"]`;
    const celda = document.querySelector(selector);
    if (!celda) return;
    
    // Crear tarjeta
    const card = document.createElement('div');
    card.className = 'ticket-card';
    card.draggable = true;
    card.dataset.ticketId = ticket.id;
    card.dataset.tipoFormulario = ticket.tipo_formulario;
    
    // Calcular ancho para multi-día
    const celdaWidth = celda.offsetWidth || 150;
    const anchoCard = (celdaWidth * numDias) + (1 * (numDias - 1)) - 10;
    
    // Posicionar
    card.style.left = '5px';
    card.style.top = (fila * 60 + 5) + 'px';
    card.style.width = anchoCard + 'px';
    
    // Contenido
    card.innerHTML = `
        <div class="ticket-title">${escapeHtml(ticket.titulo)}</div>
        <div class="ticket-sucursal">${escapeHtml(ticket.nombre_sucursal)}</div>
        <div class="urgency-badge" style="background-color: ${getUrgencyColor(ticket.nivel_urgencia)}">
            ${ticket.nivel_urgencia || '?'}
        </div>
        <button class="btn-unschedule" onclick="desprogramarTicket(${ticket.id}, event)">
            <i class="fas fa-times"></i>
        </button>
        <div class="resize-handle"></div>
    `;
    
    // Eventos
    card.addEventListener('dragstart', handleDragStart);
    card.addEventListener('dragend', handleDragEnd);
    card.addEventListener('click', (e) => {
        if (!e.target.closest('.btn-unschedule') && !e.target.closest('.resize-handle')) {
            mostrarDetallesTicket(ticket.id);
        }
    });
    
    // Resize
    const resizeHandle = card.querySelector('.resize-handle');
    resizeHandle.addEventListener('mousedown', (e) => startResize(e, card, ticket, fechas, diaInicio));
    
    celda.appendChild(card);
}

function startResize(e, card, ticket, fechas, diaInicio) {
    e.stopPropagation();
    e.preventDefault();
    
    resizing = {
        card: card,
        ticket: ticket,
        fechas: fechas,
        diaInicio: diaInicio,
        startX: e.pageX,
        startWidth: card.offsetWidth,
        celdaWidth: card.parentElement.offsetWidth
    };
    
    card.draggable = false;
    document.body.classList.add('resizing');
    
    document.addEventListener('mousemove', doResize);
    document.addEventListener('mouseup', stopResize);
}

function doResize(e) {
    if (!resizing) return;
    
    const deltaX = e.pageX - resizing.startX;
    const nuevoAncho = resizing.startWidth + deltaX;
    
    // Calcular número de días
    const numDias = Math.max(1, Math.round((nuevoAncho + 10) / (resizing.celdaWidth + 1)));
    const diasDisponibles = resizing.fechas.length - resizing.diaInicio;
    const diasFinales = Math.min(numDias, diasDisponibles);
    
    // Aplicar ancho exacto
    const anchoFinal = (resizing.celdaWidth * diasFinales) + (1 * (diasFinales - 1)) - 10;
    resizing.card.style.width = anchoFinal + 'px';
    
    resizing.nuevoNumDias = diasFinales;
}

function stopResize(e) {
    if (!resizing) return;
    
    document.removeEventListener('mousemove', doResize);
    document.removeEventListener('mouseup', stopResize);
    
    const card = resizing.card;
    const ticket = resizing.ticket;
    const nuevoNumDias = resizing.nuevoNumDias || 1;
    
    card.draggable = true;
    document.body.classList.remove('resizing');
    
    // Calcular nueva fecha final
    const fechaInicio = ticket.fecha_inicio;
    const diaInicioIdx = resizing.fechas.findIndex(f => f.fecha === fechaInicio);
    const diaFinalIdx = Math.min(diaInicioIdx + nuevoNumDias - 1, resizing.fechas.length - 1);
    const fechaFinal = resizing.fechas[diaFinalIdx].fecha;
    
    resizing = null;
    
    // Actualizar en servidor
    if (fechaFinal !== ticket.fecha_final) {
        $.ajax({
            url: 'ajax/agenda_actualizar_fechas.php',
            method: 'POST',
            data: {
                ticket_id: ticket.id,
                fecha_inicio: fechaInicio,
                fecha_final: fechaFinal
            },
            success: function() {
                cargarCronograma();
            },
            error: function() {
                alert('Error al actualizar fechas');
                cargarCronograma();
            }
        });
    }
}

// Drag & Drop handlers
let draggedTicket = null;

function handleDragStart(e) {
    if (resizing) {
        e.preventDefault();
        return;
    }
    
    draggedTicket = {
        id: this.dataset.ticketId,
        tipoFormulario: this.dataset.tipoFormulario,
        fromSidebar: false
    };
    
    this.style.opacity = '0.5';
    e.dataTransfer.effectAllowed = 'move';
}

function handleDragEnd(e) {
    this.style.opacity = '1';
}

function handleDragOver(e) {
    if (!draggedTicket) return;
    
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    
    // Validar compatibilidad de tipo
    const equipoTipo = this.dataset.tipoFormulario;
    const ticketTipo = draggedTicket.tipoFormulario;
    
    if ((equipoTipo === 'cambio_equipos' && ticketTipo !== 'cambio_equipos') ||
        (equipoTipo === 'mantenimiento_general' && ticketTipo === 'cambio_equipos')) {
        e.dataTransfer.dropEffect = 'none';
        return;
    }
    
    this.classList.add('drag-over');
}

function handleDragLeave(e) {
    this.classList.remove('drag-over');
}

function handleDrop(e) {
    e.preventDefault();
    this.classList.remove('drag-over');
    
    if (!draggedTicket) return;
    
    const equipoTipo = this.dataset.tipoFormulario;
    const ticketTipo = draggedTicket.tipoFormulario;
    
    // Validar compatibilidad
    if ((equipoTipo === 'cambio_equipos' && ticketTipo !== 'cambio_equipos') ||
        (equipoTipo === 'mantenimiento_general' && ticketTipo === 'cambio_equipos')) {
        alert('No se puede programar este tipo de solicitud en este equipo');
        draggedTicket = null;
        return;
    }
    
    const fecha = this.dataset.fecha;
    const equipoId = this.dataset.equipo;
    const ticketId = draggedTicket.id;
    const fromSidebar = draggedTicket.fromSidebar;
    
    const url = fromSidebar ? 'ajax/agenda_asignar_ticket.php' : 'ajax/agenda_mover_ticket.php';
    
    $.ajax({
        url: url,
        method: 'POST',
        data: {
            ticket_id: ticketId,
            equipo_id: equipoId,
            fecha_inicio: fecha,
            fecha_final: fecha
        },
        success: function() {
            cargarCronograma();
            if (fromSidebar) {
                cargarTicketsSinProgramar();
            }
        },
        error: function() {
            alert('Error al programar la solicitud');
        }
    });
    
    draggedTicket = null;
}

// Utilidades
function getUrgencyColor(nivel) {
    switch(parseInt(nivel)) {
        case 1: return '#28a745';
        case 2: return '#ffc107';
        case 3: return '#fd7e14';
        case 4: return '#dc3545';
        default: return '#8b8b8b';
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function desprogramarTicket(ticketId, event) {
    event.stopPropagation();
    
    if (!confirm('¿Desea desprogramar esta solicitud?')) return;
    
    $.ajax({
        url: 'ajax/agenda_desprogramar_ticket.php',
        method: 'POST',
        data: { ticket_id: ticketId },
        success: function() {
            cargarCronograma();
        },
        error: function() {
            alert('Error al desprogramar');
        }
    });
}

// Cargar al inicio
$(document).ready(function() {
    cargarCronograma();
});