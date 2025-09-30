<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'models/Ticket.php';

$ticket = new Ticket();
$tickets_con_fecha = $ticket->getTicketsForCalendar();
$tickets_sin_fecha = $ticket->getTicketsWithoutDates();

// Procesar tickets para el calendario
$calendar_events = [];

foreach ($tickets_con_fecha as $t) {
    $calendar_events[] = [
        'id' => $t['id'],
        'title' => $t['codigo'] . ' - ' . $t['titulo'],
        'start' => $t['fecha_inicio'],
        'end' => date('Y-m-d', strtotime($t['fecha_final'] . ' +1 day')),
        'backgroundColor' => getColorByUrgency($t['nivel_urgencia']),
        'borderColor' => getColorByUrgency($t['nivel_urgencia']),
        'extendedProps' => [
            'codigo' => $t['codigo'],
            'sucursal' => $t['nombre_sucursal'],
            'urgencia' => $t['nivel_urgencia'],
            'status' => $t['status']
        ]
    ];
}

function getColorByUrgency($urgencia) {
    switch ($urgencia) {
        case 1: return '#28a745';
        case 2: return '#ffc107';
        case 3: return '#fd7e14';
        case 4: return '#dc3545';
        default: return '#6c757d';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario de Mantenimiento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <style>
        :root {
            --pitaya-primary: #51B8AC;
            --pitaya-secondary: #0E544C;
            --pitaya-light: #F6F6F6;
        }
        
        body {
            font-family: 'Calibri', sans-serif;
            background-color: var(--pitaya-light);
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--pitaya-primary) 0%, var(--pitaya-secondary) 100%) !important;
        }
        
        .calendar-container {
            display: flex;
            gap: 20px;
            padding: 20px;
            min-height: calc(100vh - 100px);
        }
        
        .calendar-main {
            flex: 1;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            min-height: 600px;
        }
        
        #calendar {
            height: 100%;
        }
        
        .fc {
            font-family: 'Calibri', sans-serif;
        }
        
        .fc-button-primary {
            background-color: var(--pitaya-primary) !important;
            border-color: var(--pitaya-primary) !important;
        }
        
        .fc-button-primary:hover {
            background-color: var(--pitaya-secondary) !important;
            border-color: var(--pitaya-secondary) !important;
        }
        
        .fc-button-primary:not(:disabled):active,
        .fc-button-primary:not(:disabled).fc-button-active {
            background-color: var(--pitaya-secondary) !important;
            border-color: var(--pitaya-secondary) !important;
        }
        
        .sidebar {
            width: 350px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 140px);
        }
        
        .sidebar-header {
            background: linear-gradient(135deg, var(--pitaya-primary) 0%, var(--pitaya-secondary) 100%);
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
        }
        
        .unscheduled-tickets {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }
        
        .ticket-item {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: move;
            transition: all 0.3s ease;
        }
        
        .ticket-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .urgency-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        
        .legend {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 20px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .legend-color {
            width: 15px;
            height: 15px;
            border-radius: 3px;
        }
        
        .sucursales-dia {
            font-size: 0.7em;
            color: var(--pitaya-secondary);
            background: rgba(81, 184, 172, 0.15);
            padding: 3px 5px;
            border-radius: 4px;
            margin-top: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: help;
        }
        
        .sucursales-dia:hover {
            background: rgba(81, 184, 172, 0.3);
        }
        
        .fc-event {
            margin-bottom: 2px;
            border-left: 3px solid;
            font-size: 0.85em;
        }
        
        .fc-daygrid-day-top {
            flex-direction: column;
            align-items: stretch;
        }
        
        .calendar-main.dragging {
            background: rgba(81, 184, 172, 0.1) !important;
            border: 2px dashed var(--pitaya-primary);
        }
        
        .fc-day:hover {
            background: rgba(81, 184, 172, 0.05);
        }
        
        #loadingOverlay .spinner-border {
            width: 3rem;
            height: 3rem;
            border-width: 0.3rem;
        }
        
        .modal-content {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .alert-success.position-fixed {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border-radius: 8px;
            animation: slideInRight 0.3s ease-out;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .fc-daygrid-day-number {
            font-weight: 600;
            color: #333;
        }
        
        .fc-day-today .fc-daygrid-day-number {
            background: var(--pitaya-primary);
            color: white;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 2px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="fas fa-calendar-alt me-2"></i>
                Calendario de Mantenimiento
            </span>
            <div class="navbar-nav flex-row">
                <a class="nav-link me-3" href="dashboard_mantenimiento.php">
                    <i class="fas fa-arrow-left me-2"></i>Volver al Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <!-- Leyenda -->
        <div class="legend mt-3">
            <div class="legend-item">
                <div class="legend-color" style="background: #28a745;"></div>
                <span>Urgencia 1 - Baja</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #ffc107;"></div>
                <span>Urgencia 2 - Media</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #fd7e14;"></div>
                <span>Urgencia 3 - Alta</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #dc3545;"></div>
                <span>Urgencia 4 - Crítica</span>
            </div>
        </div>

        <div class="calendar-container">
            <!-- Calendario principal -->
            <div class="calendar-main">
                <div id='calendar'></div>
            </div>

            <!-- Sidebar con tickets sin programar -->
            <div class="sidebar">
                <div class="sidebar-header">
                    <h5 class="mb-1">
                        <i class="fas fa-clock me-2"></i>
                        Tickets Sin Programar
                    </h5>
                    <small>Arrastra los tickets al calendario</small>
                </div>
                
                <div class="unscheduled-tickets" id="unscheduledTickets">
                    <?php if (empty($tickets_sin_fecha)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-check-circle fa-3x mb-3"></i>
                            <p>¡Todos los tickets están programados!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($tickets_sin_fecha as $ticket): ?>
                            <div class="ticket-item" 
                                 draggable="true" 
                                 data-ticket-id="<?= $ticket['id'] ?>"
                                 data-ticket-title="<?= htmlspecialchars($ticket['titulo']) ?>"
                                 data-ticket-codigo="<?= htmlspecialchars($ticket['codigo']) ?>">
                                
                                <span class="urgency-indicator" 
                                      style="background: <?= getColorByUrgency($ticket['nivel_urgencia']) ?>"></span>
                                
                                <div class="mb-2">
                                    <strong><?= htmlspecialchars($ticket['codigo']) ?></strong>
                                </div>
                                
                                <div class="mb-2">
                                    <?= htmlspecialchars($ticket['titulo']) ?>
                                </div>
                                
                                <div class="text-muted small">
                                    <i class="fas fa-building me-1"></i>
                                    <?= htmlspecialchars($ticket['nombre_sucursal']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/es.js'></script>
    
    <script>
        console.log('=== Iniciando Calendario con Drag & Drop ===');
        
        let calendar;
        let draggedTicket = null;
        const sucursalesPorDia = {};
        
        // Procesar tickets para agrupar por día y sucursal
        const eventos = <?= json_encode($calendar_events) ?>;
        eventos.forEach(evento => {
            const fecha = evento.start;
            if (!sucursalesPorDia[fecha]) {
                sucursalesPorDia[fecha] = new Set();
            }
            if (evento.extendedProps && evento.extendedProps.sucursal) {
                sucursalesPorDia[fecha].add(evento.extendedProps.sucursal);
            }
        });
        
        console.log('Sucursales por día:', sucursalesPorDia);
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM cargado, inicializando calendario...');
            
            const calendarEl = document.getElementById('calendar');
            
            if (!calendarEl) {
                console.error('❌ No se encontró el elemento #calendar');
                return;
            }
            
            try {
                calendar = new FullCalendar.Calendar(calendarEl, {
                    locale: 'es',
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,listWeek'
                    },
                    height: 'auto',
                    editable: true,
                    droppable: true,
                    events: eventos,
                    
                    // Click en celda vacía del día
                    dateClick: function(info) {
                        console.log('Click en día:', info.dateStr);
                        
                        // Si hay un ticket siendo arrastrado, no hacer nada (se maneja en drop)
                        if (!draggedTicket) {
                            mostrarTicketsDelDia(info.dateStr);
                        }
                    },
                    
                    // Click en evento existente
                    eventClick: function(info) {
                        console.log('Click en evento:', info.event);
                        info.jsEvent.stopPropagation();
                        mostrarDetallesTicket(info.event.id);
                    },
                    
                    // Personalizar renderizado de eventos
                    eventContent: function(arg) {
                        const urgencia = arg.event.extendedProps.urgencia || 0;
                        const urgenciaIcon = urgencia >= 3 ? '⚠️ ' : '';
                        return {
                            html: '<div style="padding: 2px 4px; font-size: 0.8em; overflow: hidden; text-overflow: ellipsis;">' + 
                                  urgenciaIcon + arg.event.title + '</div>'
                        };
                    },
                    
                    // Agregar sucursales al día
                    dayCellDidMount: function(info) {
                        const fecha = info.date.toISOString().split('T')[0];
                        
                        if (sucursalesPorDia[fecha]) {
                            const sucursales = Array.from(sucursalesPorDia[fecha]);
                            if (sucursales.length > 0) {
                                const sucursalesDiv = document.createElement('div');
                                sucursalesDiv.className = 'sucursales-dia';
                                sucursalesDiv.innerHTML = '<i class="fas fa-building" style="font-size: 0.8em;"></i> ' + 
                                                          sucursales.slice(0, 2).join(', ') + 
                                                          (sucursales.length > 2 ? '...' : '');
                                sucursalesDiv.title = 'Sucursales: ' + sucursales.join(', ');
                                
                                const dayTop = info.el.querySelector('.fc-daygrid-day-top');
                                if (dayTop) {
                                    dayTop.appendChild(sucursalesDiv);
                                }
                            }
                        }
                    }
                });
                
                console.log('📅 Calendario creado, renderizando...');
                calendar.render();
                console.log('✅ Calendario renderizado exitosamente');
                
                // Inicializar drag de tickets
                inicializarDragTickets();
                
            } catch (error) {
                console.error('❌ Error al crear calendario:', error);
                alert('Error al inicializar el calendario: ' + error.message);
            }
        });
        
        // Inicializar drag & drop de tickets
        function inicializarDragTickets() {
            console.log('Inicializando drag & drop de tickets...');
            
            const ticketItems = document.querySelectorAll('.ticket-item');
            const calendarMain = document.querySelector('.calendar-main');
            
            console.log('Tickets encontrados:', ticketItems.length);
            
            ticketItems.forEach(item => {
                item.addEventListener('dragstart', function(e) {
                    console.log('Drag start:', this.dataset.ticketId);
                    
                    draggedTicket = {
                        id: this.dataset.ticketId,
                        title: this.dataset.ticketTitle,
                        codigo: this.dataset.ticketCodigo
                    };
                    
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/html', this.innerHTML);
                    
                    this.style.opacity = '0.5';
                    this.style.transform = 'scale(0.95)';
                    
                    calendarMain.classList.add('dragging');
                });
                
                item.addEventListener('dragend', function(e) {
                    console.log('Drag end');
                    this.style.opacity = '1';
                    this.style.transform = 'scale(1)';
                    
                    calendarMain.classList.remove('dragging');
                });
            });
            
            // Prevenir comportamiento por defecto en el calendario
            calendarMain.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
            });
            
            calendarMain.addEventListener('drop', function(e) {
                e.preventDefault();
                console.log('Drop en calendario detectado');
                
                if (!draggedTicket) {
                    console.log('No hay ticket siendo arrastrado');
                    return;
                }
                
                // Obtener la celda del día donde se soltó
                const target = e.target.closest('.fc-daygrid-day');
                if (target) {
                    const dateStr = target.getAttribute('data-date');
                    console.log('Fecha destino:', dateStr);
                    
                    if (dateStr) {
                        programarTicket(draggedTicket, dateStr, dateStr);
                        draggedTicket = null;
                    }
                } else {
                    console.log('No se detectó una celda válida del calendario');
                }
            });
        }
        
        // Programar ticket
        function programarTicket(ticket, fechaInicio, fechaFinal) {
            console.log('Programando ticket:', ticket, fechaInicio, fechaFinal);
            
            // Crear modal de confirmación
            const modalHtml = `
                <div class="modal fade" id="modalProgramar" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header" style="background: var(--pitaya-primary); color: white;">
                                <h5 class="modal-title">
                                    <i class="fas fa-calendar-plus me-2"></i>
                                    Programar Ticket
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-info">
                                    <strong>Ticket:</strong> ${ticket.codigo}<br>
                                    <strong>Título:</strong> ${ticket.title}
                                </div>
                                
                                <div class="mb-3">
                                    <label for="fecha_inicio_modal" class="form-label">
                                        <i class="fas fa-calendar-day me-2"></i>Fecha de Inicio:
                                    </label>
                                    <input type="date" class="form-control" id="fecha_inicio_modal" value="${fechaInicio}" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="fecha_final_modal" class="form-label">
                                        <i class="fas fa-calendar-check me-2"></i>Fecha Final:
                                    </label>
                                    <input type="date" class="form-control" id="fecha_final_modal" value="${fechaFinal}" required>
                                </div>
                                
                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    El ticket se programará para estas fechas y cambiará a estado "Agendado"
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-2"></i>Cancelar
                                </button>
                                <button type="button" class="btn btn-primary" onclick="confirmarProgramacion()" style="background: var(--pitaya-primary); border: none;">
                                    <i class="fas fa-check me-2"></i>Programar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            const existingModal = document.getElementById('modalProgramar');
            if (existingModal) {
                existingModal.remove();
            }
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            const modal = new bootstrap.Modal(document.getElementById('modalProgramar'));
            modal.show();
            
            window.currentTicketToProgramar = ticket;
        }
        
        // Confirmar programación
        function confirmarProgramacion() {
            const ticket = window.currentTicketToProgramar;
            const fechaInicio = document.getElementById('fecha_inicio_modal').value;
            const fechaFinal = document.getElementById('fecha_final_modal').value;
            
            if (!fechaInicio || !fechaFinal) {
                alert('❌ Debes seleccionar ambas fechas');
                return;
            }
            
            if (fechaInicio > fechaFinal) {
                alert('❌ La fecha de inicio no puede ser mayor a la fecha final');
                return;
            }
            
            bootstrap.Modal.getInstance(document.getElementById('modalProgramar')).hide();
            
            const loading = document.createElement('div');
            loading.id = 'loadingOverlay';
            loading.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999;';
            loading.innerHTML = '<div style="background: white; padding: 30px; border-radius: 10px; text-align: center;"><div class="spinner-border text-primary mb-3" role="status"></div><div>Programando ticket...</div></div>';
            document.body.appendChild(loading);
            
            console.log('Enviando petición AJAX:', ticket.id, fechaInicio, fechaFinal);
            
            $.ajax({
                url: 'ajax/schedule_ticket.php',
                method: 'POST',
                data: {
                    ticket_id: ticket.id,
                    fecha_inicio: fechaInicio,
                    fecha_final: fechaFinal
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Respuesta:', response);
                    loading.remove();
                    
                    if (response.success) {
                        const successAlert = document.createElement('div');
                        successAlert.className = 'alert alert-success position-fixed';
                        successAlert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
                        successAlert.innerHTML = `
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>¡Éxito!</strong><br>
                            Ticket ${ticket.codigo} programado
                        `;
                        document.body.appendChild(successAlert);
                        
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        alert('❌ Error: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    loading.remove();
                    console.error('Error AJAX:', xhr.responseText);
                    alert('❌ Error: ' + error);
                }
            });
        }
        
        // Mostrar detalles del ticket
        function mostrarDetallesTicket(ticketId) {
            console.log('Mostrar detalles:', ticketId);
            $.ajax({
                url: 'ajax/get_ticket_details.php',
                method: 'GET',
                data: { id: ticketId },
                success: function(response) {
                    const modal = $('<div class="modal fade"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5>Detalles</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">' + response + '</div></div></div></div>');
                    $('body').append(modal);
                    modal.modal('show');
                    modal.on('hidden.bs.modal', function() { modal.remove(); });
                }
            });
        }
        
        // Mostrar tickets del día
        function mostrarTicketsDelDia(fecha) {
            const ticketsDelDia = eventos.filter(e => e.start === fecha);
            
            if (ticketsDelDia.length === 0) return;
            
            let html = '<h5>Tickets del ' + fecha + '</h5>';
            
            const porSucursal = {};
            ticketsDelDia.forEach(t => {
                const suc = t.extendedProps.sucursal || 'Sin sucursal';
                if (!porSucursal[suc]) porSucursal[suc] = [];
                porSucursal[suc].push(t);
            });
            
            for (const suc in porSucursal) {
                html += '<div class="card mb-3"><div class="card-header" style="background: var(--pitaya-primary); color: white;"><i class="fas fa-building me-2"></i>' + suc + '</div><div class="card-body"><ul class="list-unstyled">';
                porSucursal[suc].forEach(t => {
                    html += '<li class="mb-2"><strong>' + t.extendedProps.codigo + '</strong>: ' + t.title + '</li>';
                });
                html += '</ul></div></div>';
            }
            
            const modal = $('<div class="modal fade"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5>Tickets del Día</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">' + html + '</div></div></div></div>');
            $('body').append(modal);
            modal.modal('show');
            modal.on('hidden.bs.modal', function() { modal.remove(); });
        }
    </script>
</body>
</html>