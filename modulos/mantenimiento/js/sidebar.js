// sidebar.js - Gestión del sidebar de solicitudes pendientes

function cargarTicketsSinProgramar() {
    const filtroSucursal = document.getElementById('filtro-sucursal').value;
    
    $.ajax({
        url: 'ajax/agenda_get_tickets_pendientes.php',
        method: 'GET',
        data: { sucursal: filtroSucursal },
        success: function(response) {
            const tickets = JSON.parse(response);
            renderizarTicketsPendientes(tickets);
        },
        error: function() {
            alert('Error al cargar solicitudes pendientes');
        }
    });
}

function renderizarTicketsPendientes(tickets) {
    const container = document.getElementById('tickets-sin-programar');
    container.innerHTML = '';
    
    if (tickets.length === 0) {
        container.innerHTML = '<p class="text-muted text-center">No hay solicitudes pendientes</p>';
        return;
    }
    
    tickets.forEach(ticket => {
        const card = document.createElement('div');
        card.className = 'sidebar-card';
        card.draggable = true;
        card.dataset.ticketId = ticket.id;
        card.dataset.tipoFormulario = ticket.tipo_formulario;
        
        card.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.3rem;">
                <div style="font-weight: 600; font-size: 0.85rem; flex: 1; padding-right: 0.5rem;">
                    ${escapeHtml(ticket.titulo)}
                </div>
                <div class="urgency-badge" style="background-color: ${getUrgencyColor(ticket.nivel_urgencia)}; position: static; margin-left: 0.5rem;">
                    ${ticket.nivel_urgencia || '?'}
                </div>
            </div>
            <div style="font-size: 0.75rem; color: #6c757d;">
                ${escapeHtml(ticket.nombre_sucursal)}
            </div>
            <div style="font-size: 0.7rem; color: #999; margin-top: 0.2rem;">
                ${ticket.tipo_formulario === 'cambio_equipos' ? 'Cambio de Equipos' : 'Mantenimiento General'}
            </div>
        `;
        
        // Eventos
        card.addEventListener('dragstart', handleSidebarDragStart);
        card.addEventListener('dragend', handleSidebarDragEnd);
        card.addEventListener('click', () => mostrarDetallesTicket(ticket.id));
        
        container.appendChild(card);
    });
}

function handleSidebarDragStart(e) {
    draggedTicket = {
        id: this.dataset.ticketId,
        tipoFormulario: this.dataset.tipoFormulario,
        fromSidebar: true
    };
    
    this.style.opacity = '0.5';
    e.dataTransfer.effectAllowed = 'move';
}

function handleSidebarDragEnd(e) {
    this.style.opacity = '1';
}