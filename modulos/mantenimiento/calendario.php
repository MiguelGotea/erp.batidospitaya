<?php
session_start();
require_once 'models/Ticket.php';

$ticket = new Ticket();
$tickets_con_fecha = $ticket->getTicketsForCalendar();
$tickets_sin_fecha = $ticket->getTicketsWithoutDates();

// Procesar tickets para el calendario
$calendar_events = [];
$sucursales_por_dia = [];

foreach ($tickets_con_fecha as $t) {
    $start_date = $t['fecha_inicio'];
    $end_date = $t['fecha_final'];
    
    // Crear evento para el calendario
    $calendar_events[] = [
        'id' => $t['id'],
        'title' => $t['titulo'],
        'start' => $start_date,
        'end' => date('Y-m-d', strtotime($end_date . ' +1 day')), // FullCalendar necesita +1 día para eventos de múltiples días
        'backgroundColor' => getColorByUrgency($t['nivel_urgencia']),
        'borderColor' => getColorByUrgency($t['nivel_urgencia']),
        'textColor' => '#000',
        'extendedProps' => [
            'codigo' => $t['codigo'],
            'sucursal' => $t['nombre_sucursal'],
            'urgencia' => $t['nivel_urgencia'],
            'status' => $t['status']
        ]
    ];
    
    // Agrupar por sucursal y día
    $current_date = $start_date;
    while ($current_date <= $end_date) {
        if (!isset($sucursales_por_dia[$current_date])) {
            $sucursales_por_dia[$current_date] = [];
        }
        if (!isset($sucursales_por_dia[$current_date][$t['nombre_sucursal']])) {
            $sucursales_por_dia[$current_date][$t['nombre_sucursal']] = [];
        }
        $sucursales_por_dia[$current_date][$t['nombre_sucursal']][] = $t;
        $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
    }
}

function getColorByUrgency($urgencia) {
    switch ($urgencia) {
        case 1: return '#28a745'; // Verde
        case 2: return '#ffc107'; // Amarillo
        case 3: return '#fd7e14'; // Naranja
        case 4: return '#dc3545'; // Rojo
        default: return '#6c757d'; // Gris
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
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css' rel='stylesheet' />
    <style>
        .calendar-container {
            height: calc(100vh - 100px);
            display: flex;
        }
        
        .calendar-main {
            flex: 1;
            padding: 20px;
            background: white;
            margin-right: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .sidebar {
            width: 350px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            position: relative;
        }
        
        .ticket-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .ticket-item.dragging {
            opacity: 0.7;
            transform: rotate(5deg);
        }
        
        .urgency-indicator {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
        }
        
        .fc-event {
            cursor: pointer;
            border-radius: 4px !important;
            border: none !important;
        }
        
        .fc-daygrid-event {
            margin: 1px 0;
            border-radius: 3px;
        }
        
        .day-summary {
            position: absolute;
            bottom: 5px;
            left: 5px;
            right: 5px;
            font-size: 0.7em;
            background: rgba(255,255,255,0.9);
            padding: 2px 4px;
            border-radius: 3px;
            display: none;
        }
        
        .fc-day-today .day-summary {
            display: block;
        }
        
        .legend {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
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
        
        .stats-summary {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .drop-zone {
            border: 2px dashed #007bff;
            border-radius: 8px;
            background: rgba(0, 123, 255, 0.1);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { background-color: rgba(0, 123, 255, 0.1); }
            50% { background-color: rgba(0, 123, 255, 0.2); }
            100% { background-color: rgba(0, 123, 255, 0.1); }
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="fas fa-calendar-alt me-2"></i>
                Calendario de Mantenimiento
            </span>
            <div class="navbar-nav flex-row">
                <a class="nav-link me-3" href="dashboard_mantenimiento.php">
                    <i class="fas fa-arrow-left me-2"></i>Volver al Dashboard
                </a>
                <a class="nav-link" href="#" onclick="location.reload()">
                    <i class="fas fa-sync-alt me-2"></i>Actualizar
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Leyenda -->
        <div class="legend">
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
                    <h5 class="mb-0">
                        <i class="fas fa-clock me-2"></i>
                        Tickets Sin Programar
                    </h5>
                    <small>Arrastra los tickets al calendario para programarlos</small>
                </div>
                
                <div class="stats-summary">
                    <div class="row text-center">
                        <div class="col-6">
                            <h4><?= count($tickets_sin_fecha) ?></h4>
                            <small>Sin Programar</small>
                        </div>
                        <div class="col-6">
                            <h4><?= count($tickets_con_fecha) ?></h4>
                            <small>Programados</small>
                        </div>
                    </div>
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
                                
                                <div class="urgency-indicator" 
                                     style="background: <?= getColorByUrgency($ticket['nivel_urgencia']) ?>"></div>
                                
                                <div class="mb-2">
                                    <strong><?= htmlspecialchars($ticket['codigo']) ?></strong>
                                    <span class="badge bg-secondary ms-2">
                                        <?= $ticket['tipo_formulario'] === 'mantenimiento_general' ? 'Mant.' : 'Equipo' ?>
                                    </span>
                                </div>
                                
                                <div class="mb-2">
                                    <?= htmlspecialchars($ticket['titulo']) ?>
                                </div>
                                
                                <div class="text-muted small">
                                    <i class="fas fa-building me-1"></i>
                                    <?= htmlspecialchars($ticket['nombre_sucursal']) ?>
                                </div>
                                
                                <?php if ($ticket['nivel_urgencia']): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">Urgencia: <?= $ticket['nivel_urgencia'] ?>/4</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para mostrar tickets del día -->
    <div class="modal fade" id="dayTicketsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="dayTicketsTitle">Tickets del Día</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="dayTicketsBody">
                    <!-- Contenido cargado dinámicamente -->
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para confirmar programación -->
    <div class="modal fade" id="scheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Programar Ticket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <strong>Ticket:</strong> <span id="scheduleTicketInfo"></span>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <label class="form-label">Fecha Inicio:</label>
                            <input type="date" class="form-control" id="scheduleFechaInicio">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Fecha Final:</label>
                            <input type="date" class="form-control" id="scheduleFechaFinal">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="confirmSchedule()">
                        <i class="fas fa-calendar-check me-2"></i>Programar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/locales/es.global.min.js'></script>
    
    <script>
        let calendar;
        let draggedTicket = null;
        let sucursalesPorDia = <?= json_encode($sucursales_por_dia) ?>;
        
        document.addEventListener('DOMContentLoaded', function() {
            initializeCalendar();
            initializeDragDrop();
        });
        
        function initializeCalendar() {
            const calendarEl = document.getElementById('calendar');
            
            calendar = new FullCalendar.Calendar(calendarEl, {
                locale: 'es',
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,listWeek'
                },
                height: 'auto',
                events: <?= json_encode($calendar_events) ?>,
                eventClick: function(info) {
                    showTicketDetails(info.event);
                },
                dateClick: function(info) {
                    showDayTickets(info.dateStr);
                },
                eventDidMount: function(info) {
                    // Agregar tooltip
                    info.el.title = `${info.event.extendedProps.codigo} - ${info.event.title} (${info.event.extendedProps.sucursal})`;
                },
                dayCellDidMount: function(info) {
                    // Agregar resumen de sucursales por día
                    const dateStr = info.date.toISOString().split('T')[0];
                    if (sucursalesPorDia[dateStr]) {
                        const sucursales = Object.keys(sucursalesPorDia[dateStr]);
                        if (sucursales.length > 0) {
                            const summary = document.createElement('div');
                            summary.className = 'day-summary';
                            summary.innerHTML = `<i class="fas fa-building"></i> ${sucursales.length} sucursal(es)`;
                            info.el.appendChild(summary);
                        }
                    }
                },
                // Habilitar drop para programar tickets
                droppable: true,
                drop: function(info) {
                    if (draggedTicket) {
                        scheduleTicket(draggedTicket, info.dateStr);
                    }
                }
            });
            
            calendar.render();
        }
        
        function initializeDragDrop() {
            const ticketItems = document.querySelectorAll('.ticket-item');
            
            ticketItems.forEach(item => {
                item.addEventListener('dragstart', function(e) {
                    draggedTicket = {
                        id: this.dataset.ticketId,
                        title: this.dataset.ticketTitle,
                        codigo: this.dataset.ticketCodigo
                    };
                    this.classList.add('dragging');
                    
                    // Resaltar zonas de drop en el calendario
                    document.querySelectorAll('.fc-daygrid-day').forEach(day => {
                        day.classList.add('drop-zone');
                    });
                });
                
                item.addEventListener('dragend', function(e) {
                    this.classList.remove('dragging');
                    draggedTicket = null;
                    
                    // Quitar resaltado
                    document.querySelectorAll('.fc-daygrid-day').forEach(day => {
                        day.classList.remove('drop-zone');
                    });
                });
            });
        }
        
        function scheduleTicket(ticket, dateStr) {
            // Mostrar modal de confirmación
            document.getElementById('scheduleTicketInfo').textContent = `${ticket.codigo} - ${ticket.title}`;
            document.getElementById('scheduleFechaInicio').value = dateStr;
            document.getElementById('scheduleFechaFinal').value = dateStr;
            
            // Guardar datos temporalmente
            window.tempScheduleData = {
                ticketId: ticket.id,
                codigo: ticket.codigo
            };
            
            new bootstrap.Modal(document.getElementById('scheduleModal')).show();
        }
        
        function confirmSchedule() {
            const fechaInicio = document.getElementById('scheduleFechaInicio').value;
            const fechaFinal = document.getElementById('scheduleFechaFinal').value;
            
            if (!fechaInicio || !fechaFinal) {
                alert('Debe seleccionar ambas fechas');
                return;
            }
            
            if (fechaInicio > fechaFinal) {
                alert('La fecha de inicio no puede ser mayor a la fecha final');
                return;
            }
            
            const data = window.tempScheduleData;
            
            // Enviar solicitud AJAX
            $.ajax({
                url: 'ajax/schedule_ticket.php',
                method: 'POST',
                data: {
                    ticket_id: data.ticketId,
                    fecha_inicio: fechaInicio,
                    fecha_final: fechaFinal
                },
                success: function(response) {
                    if (response.success) {
                        bootstrap.Modal.getInstance(document.getElementById('scheduleModal')).hide();
                        
                        // Mostrar mensaje de éxito
                        showSuccessMessage(`Ticket ${data.codigo} programado exitosamente`);
                        
                        // Actualizar página después de un momento
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error en la comunicación con el servidor');
                }
            });
        }
        
        function showTicketDetails(event) {
            // Abrir detalles del ticket
            const ticketId = event.id;
            
            $.ajax({
                url: 'ajax/get_ticket_details.php',
                method: 'GET',
                data: { id: ticketId },
                success: function(response) {
                    // Crear modal temporal para mostrar detalles
                    const modal = document.createElement('div');
                    modal.className = 'modal fade';
                    modal.innerHTML = `
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Detalles del Ticket</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    ${response}
                                </div>
                            </div>
                        </div>
                    `;
                    
                    document.body.appendChild(modal);
                    const modalInstance = new bootstrap.Modal(modal);
                    modalInstance.show();
                    
                    // Limpiar modal al cerrar
                    modal.addEventListener('hidden.bs.modal', function() {
                        document.body.removeChild(modal);
                    });
                },
                error: function() {
                    alert('Error al cargar los detalles del ticket');
                }
            });
        }
        
        function showDayTickets(dateStr) {
            if (sucursalesPorDia[dateStr]) {
                const sucursales = sucursalesPorDia[dateStr];
                const sucursalNames = Object.keys(sucursales);
                
                if (sucursalNames.length > 0) {
                    let content = `<h6>Sucursales programadas para ${dateStr}:</h6>`;
                    
                    sucursalNames.forEach(sucursal => {
                        content += `
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-building me-2"></i>
                                        ${sucursal}
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                        `;
                        
                        sucursales[sucursal].forEach(ticket => {
                            const urgencyColor = getUrgencyColor(ticket.nivel_urgencia);
                            content += `
                                <div class="col-md-6 mb-2">
                                    <div class="d-flex align-items-center">
                                        <div class="me-2" style="width: 10px; height: 10px; background: ${urgencyColor}; border-radius: 50%;"></div>
                                        <div>
                                            <strong>${ticket.codigo}</strong><br>
                                            <small>${ticket.titulo}</small>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        
                        content += `
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    document.getElementById('dayTicketsTitle').textContent = `Tickets del ${dateStr}`;
                    document.getElementById('dayTicketsBody').innerHTML = content;
                    new bootstrap.Modal(document.getElementById('dayTicketsModal')).show();
                }
            }
        }
        
        function getUrgencyColor(urgencia) {
            switch (urgencia) {
                case 1: return '#28a745';
                case 2: return '#ffc107';
                case 3: return '#fd7e14';
                case 4: return '#dc3545';
                default: return '#6c757d';
            }
        }
        
        function showSuccessMessage(message) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-success alert-dismissible fade show position-fixed';
            alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alert.innerHTML = `
                <i class="fas fa-check-circle me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(alert);
            
            // Auto-remover después de 3 segundos
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 3000);
        }
        
        // Actualizar calendario cada 2 minutos
        setInterval(function() {
            // Solo actualizar si no hay modales abiertos
            if (!document.querySelector('.modal.show')) {
                location.reload();
            }
        }, 120000);
    </script>
</body>
</html>