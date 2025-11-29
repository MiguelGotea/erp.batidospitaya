// js/programacion_solicitudes_render.js - Lógica de renderizado y empaquetado

function renderizarCronograma() {
    // Procesar cada equipo de trabajo
    Object.keys(ticketsPorEquipo).forEach(equipo => {
        const tickets = ticketsPorEquipo[equipo];
        if (!tickets || tickets.length === 0) return;
        
        // Inicializar matriz de ocupación para este equipo
        const matrizOcupacion = [];
        const posiciones = [];
        
        // Procesar cada ticket
        tickets.forEach(ticket => {
            const posicion = calcularPosicion(ticket, matrizOcupacion);
            if (posicion) {
                posiciones.push({
                    ticket: ticket,
                    fila: posicion.fila,
                    diaInicio: posicion.diaInicio,
                    numDias: posicion.numDias
                });
            }
        });
        
        // Renderizar tickets en el DOM
        posiciones.forEach(pos => {
            renderizarTicket(pos.ticket, pos.fila, pos.diaInicio, pos.numDias, equipo);
        });
        
        // Ajustar altura de las celdas del equipo
        ajustarAlturaCeldas(equipo, matrizOcupacion.length);
    });
}

function calcularPosicion(ticket, matrizOcupacion) {
    // Calcular índice de día de inicio
    const diaInicio = fechasSemana.indexOf(ticket.fecha_inicio);
    if (diaInicio === -1) {
        // El ticket empieza antes de esta semana
        const fechaInicioSemana = new Date(fechasSemana[0]);
        const fechaInicioTicket = new Date(ticket.fecha_inicio);
        if (fechaInicioTicket < fechaInicioSemana) {
            // Empezar desde lunes
            return calcularPosicionDesdeInicio(ticket, 0, matrizOcupacion);
        }
        return null;
    }
    
    return calcularPosicionDesdeInicio(ticket, diaInicio, matrizOcupacion);
}

function calcularPosicionDesdeInicio(ticket, diaInicio, matrizOcupacion) {
    // Calcular cuántos días abarca el ticket en esta semana
    const fechaInicio = new Date(ticket.fecha_inicio);
    const fechaFinal = new Date(ticket.fecha_final);
    const fechaFinSemana = new Date(fechasSemana[fechasSemana.length - 1]);
    
    // Limitar al fin de semana si se extiende más allá
    const fechaFinalVisible = fechaFinal > fechaFinSemana ? fechaFinSemana : fechaFinal;
    
    // Calcular días desde el inicio visible
    const inicioVisible = diaInicio === 0 ? fechaInicio : new Date(fechasSemana[diaInicio]);
    const numDias = Math.ceil((fechaFinalVisible - inicioVisible) / (1000 * 60 * 60 * 24)) + 1;
    
    // Limitar a los días disponibles en la semana
    const diasDisponibles = fechasSemana.length - diaInicio;
    const diasReales = Math.min(numDias, diasDisponibles);
    
    // Buscar la primera fila donde cabe el ticket
    let filaEncontrada = -1;
    
    for (let fila = 0; fila < matrizOcupacion.length; fila++) {
        if (cabEnFila(matrizOcupacion[fila], diaInicio, diasReales)) {
            filaEncontrada = fila;
            break;
        }
    }
    
    // Si no cabe en ninguna fila, crear una nueva
    if (filaEncontrada === -1) {
        matrizOcupacion.push(new Array(fechasSemana.length).fill(false));
        filaEncontrada = matrizOcupacion.length - 1;
    }
    
    // Marcar los días como ocupados
    for (let i = 0; i < diasReales; i++) {
        matrizOcupacion[filaEncontrada][diaInicio + i] = true;
    }
    
    return {
        fila: filaEncontrada,
        diaInicio: diaInicio,
        numDias: diasReales
    };
}

function cabEnFila(fila, diaInicio, numDias) {
    for (let i = 0; i < numDias; i++) {
        if (fila[diaInicio + i]) {
            return false;
        }
    }
    return true;
}

function renderizarTicket(ticket, fila, diaInicio, numDias, equipo) {
    // Obtener la celda correspondiente
    const row = document.querySelector(`tr[data-equipo="${equipo}"]`);
    if (!row) return;
    
    const celdas = row.querySelectorAll('.calendar-cell');
    const celdaInicio = celdas[diaInicio];
    if (!celdaInicio) return;
    
    // Calcular ancho
    const anchoCelda = celdaInicio.offsetWidth;
    const anchoCard = (anchoCelda * numDias) + (1 * (numDias - 1)) - 10;
    
    // Calcular posición vertical
    const top = (fila * 60) + 5;
    
    // Color de urgencia
    const coloresUrgencia = {
        1: '#28a745',
        2: '#ffc107',
        3: '#fd7e14',
        4: '#dc3545'
    };
    const colorUrgencia = coloresUrgencia[ticket.nivel_urgencia] || '#8b8b8bff';
    
    // Crear elemento
    const card = document.createElement('div');
    card.className = 'ticket-card';
    card.draggable = true;
    card.dataset.ticketId = ticket.id;
    card.dataset.fechaInicio = ticket.fecha_inicio;
    card.dataset.fechaFinal = ticket.fecha_final;
    card.dataset.tipoFormulario = ticket.tipo_formulario;
    
    card.style.position = 'absolute';
    card.style.top = top + 'px';
    card.style.left = '5px';
    card.style.width = anchoCard + 'px';
    card.style.height = '55px';
    card.style.backgroundColor = 'white';
    card.style.border = '1px solid #ddd';
    card.style.borderRadius = '4px';
    card.style.padding = '0.4rem 0.5rem';
    card.style.cursor = 'move';
    card.style.boxSizing = 'border-box';
    card.style.overflow = 'hidden';
    
    card.innerHTML = `
        <div style="position: relative; height: 100%;">
            <div style="font-size: 0.8rem; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding-right: 25px;">
                ${ticket.titulo}
            </div>
            <div style="font-size: 0.7rem; color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding-right: 25px;">
                ${ticket.nombre_sucursal}
            </div>
            
            <button class="btn-desprogramar" onclick="desprogramarTicket(${ticket.id}, event)" title="Desprogramar">
                <i class="bi bi-x"></i>
            </button>
            
            <button class="btn-colaboradores" onclick="mostrarColaboradores(${ticket.id}, event)" title="Asignar colaboradores">
                <i class="bi bi-plus"></i>
            </button>
            
            ${ticket.nivel_urgencia ? `
                <span class="badge-urgencia-card" style="background-color: ${colorUrgencia};">
                    ${ticket.nivel_urgencia}
                </span>
            ` : ''}
            
            <div class="resize-handle" 
                 onmousedown="startResize(event, ${ticket.id}, '${ticket.fecha_inicio}', '${ticket.fecha_final}')">
            </div>
        </div>
    `;
    
    // Event listeners
    card.addEventListener('dragstart', handleDragStart);
    card.addEventListener('click', (e) => {
        if (!e.target.closest('.btn-desprogramar') && 
            !e.target.closest('.btn-colaboradores') &&
            !e.target.closest('.resize-handle')) {
            mostrarDetallesTicket(ticket.id);
        }
    });
    
    celdaInicio.appendChild(card);
}

function ajustarAlturaCeldas(equipo, numFilas) {
    const row = document.querySelector(`tr[data-equipo="${equipo}"]`);
    if (!row) return;
    
    const alturaMinima = Math.max(80, (numFilas * 60) + 20);
    const celdas = row.querySelectorAll('.calendar-cell, .equipo-label');
    
    celdas.forEach(celda => {
        celda.style.minHeight = alturaMinima + 'px';
        celda.style.position = 'relative';
    });
}

// Sidebar functions
function toggleSidebar() {
    const sidebar = document.getElementById('sidebarPendientes');
    sidebar.classList.toggle('open');
}

function filtrarPendientes() {
    const sucursal = document.getElementById('filtroSucursal').value;
    const tickets = document.querySelectorAll('.ticket-pendiente');
    
    tickets.forEach(ticket => {
        const ticketSucursal = ticket.dataset.sucursal;
        if (!sucursal || ticketSucursal === sucursal) {
            ticket.style.display = 'flex';
        } else {
            ticket.style.display = 'none';
        }
    });
}