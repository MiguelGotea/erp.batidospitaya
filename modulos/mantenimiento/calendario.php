<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'models/Ticket.php';
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';
// Incluir el menú lateral
require_once '../../includes/menu_lateral.php';
// Incluir el header universal
require_once '../../includes/header_universal.php';
//******************************Estándar para header******************************
verificarAutenticacion();

$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';
// Obtener cargo del operario para el menú
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso al módulo Mantenimiento (Código 14)
verificarAccesoCargo([5, 11, 14, 16, 35]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([5, 11, 14, 16, 35]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);

//******************************Estándar para header, termina******************************

$ticket = new Ticket();

// Filtrar tickets según el cargo del usuario
if ($esAdmin || verificarAccesoCargo([11, 14, 16, 35])) {
    // Admin y cargos enlistados ven todos los tickets
    $tickets_con_fecha = $ticket->getTicketsForCalendar();
    $tickets_sin_fecha = $ticket->getTicketsWithoutDates();
} elseif (verificarAccesoCargo([5])) {
    // Cargo 5 (Líder) solo ve tickets de sus sucursales
    $sucursalesLider = obtenerSucursalesLider($_SESSION['usuario_id']);
    $codigosSucursales = array_column($sucursalesLider, 'codigo');
    
    $tickets_con_fecha = $ticket->getTicketsForCalendarBySucursales($codigosSucursales);
    $tickets_sin_fecha = []; // Los líderes no ven tickets sin programar
} else {
    // Otros usuarios no tienen acceso
    header('Location: ../index.php');
    exit();
}

// Procesar tickets para el calendario
$calendar_events = [];

foreach ($tickets_con_fecha as $t) {
    $calendar_events[] = [
        'id' => $t['id'],
        'title' => $t['titulo'],
        'start' => $t['fecha_inicio'],
        'end' => date('Y-m-d', strtotime($t['fecha_final'] . ' +1 day')),
        'backgroundColor' => getColorByUrgency($t['nivel_urgencia'], $t['tipo_formulario']),
        'borderColor' => getColorByUrgency($t['nivel_urgencia'], $t['tipo_formulario']),
        'extendedProps' => [
            'codigo' => $t['codigo'],
            'sucursal' => $t['nombre_sucursal'],
            'urgencia' => $t['nivel_urgencia'],
            'status' => $t['status'],
            'descripcion' => $t['descripcion'],
            'tipo_formulario' => $t['tipo_formulario'],
        ]
    ];
}

function getColorByUrgency($urgencia, $tipo_formulario) {
    if ($tipo_formulario === 'cambio_equipos') {
        return '#dc3545';
    } else {
        switch ($urgencia) {
            case 1: return '#28a745';
            case 2: return '#ffc107';
            case 3: return '#fd7e14';
            case 4: return '#dc3545';
            default: return '#8b8b8bff';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario de Mantenimiento</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Calibri', sans-serif;
            font-size: clamp(11px, 2vw, 16px) !important;
        }
        
        body {
            background-color: #F6F6F6;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 100%;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 10px;
        }
              
        .title {
            color: #0E544C;
            font-size: 1.5rem !important;
            margin-bottom: 20px;
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
            transition: all 0.3s ease;
        }
        
        #calendar {
            height: 100%;
        }
        
        .fc {
            font-family: 'Calibri', sans-serif;
        }
        
        .fc-button-primary {
            background-color: #51B8AC !important;
            border-color: #51B8AC !important;
        }
        
        .fc-button-primary:hover {
            background-color: #0E544C !important;
            border-color: #0E544C !important;
        }
        
        .fc-button-primary:not(:disabled):active,
        .fc-button-primary:not(:disabled).fc-button-active {
            background-color: #0E544C !important;
            border-color: #0E544C !important;
        }
        
        .sidebarsolicitudes {
            width: 350px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 140px);
            transition: all 0.3s ease;
        }
        
        .sidebar.hidden {
            display: none;
        }
        
        .calendar-main.full-width {
            flex: 1 0 100%;
        }
        
        .sidebarsolicitudes-header {
            background: #0E544C;
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
            color: #0E544C;
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
            border: 2px dashed #51B8AC;
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
            background: #51B8AC;
            color: white;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 2px;
        }

        /* ✅ NUEVO: Estilos para drag & drop bidireccional */
        .sidebar.accepting-drop {
            background: rgba(81, 184, 172, 0.1);
            border: 3px dashed #51B8AC;
            box-shadow: 0 4px 20px rgba(81, 184, 172, 0.3);
        }
        
        .sidebar.drag-over {
            background: rgba(81, 184, 172, 0.2);
            border: 3px solid #51B8AC;
        }
        
        .fc-event.fc-draggable {
            cursor: move;
        }
        
        .fc-event[data-status="finalizado"] {
            cursor: not-allowed !important;
            opacity: 0.7;
        }
        
        .btn-primary {
            background-color: #51B8AC;
            border-color: #51B8AC;
        }
        
        .btn-primary:hover {
            background-color: #0E544C;
            border-color: #0E544C;
        }
        
        /* Aumentar altura de las celdas del calendario */
        .fc .fc-daygrid-day {
            min-height: 80px !important;
        }
        
        .fc .fc-daygrid-day-frame {
            min-height: 80px !important;
        }
        
        .fc .fc-scrollgrid-sync-table {
            height: auto !important;
        }

        /* Reducir padding de eventos para que quepan más */
        .fc-event {
            margin-bottom: 1px !important;
            padding: 1px 2px !important;
        }
        
        /* Scrollbar personalizado para los eventos */
        .fc-daygrid-day-events::-webkit-scrollbar {
            width: 4px;
        }
        
        .fc-daygrid-day-events::-webkit-scrollbar-track {
            background: #f1f1f1;
        }


        
        .fc-daygrid-day-events::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 2px;
        }
        
        .fc-daygrid-day-events::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        @media (max-width: 768px) {
            .header-container {
                flex-direction: row;
                align-items: center;
                gap: 10px;
            }
            
            .buttons-container {
                position: static;
                transform: none;
                order: 3;
                width: 100%;
                justify-content: center;
                margin-top: 10px;
            }
            
            .logo-container {
                order: 1;
                margin-right: 0;
            }
            
            .user-info {
                order: 2;
                margin-left: auto;
            }
            
            .btn-agregar {
                padding: 6px 10px;
                font-size: 13px;
            }
            
            .calendar-container {
                flex-direction: column;
                padding: 10px;
            }
            
            .sidebarsolicitudes {
                width: 100%;
                max-height: 400px;
            }
            
            .legend {
                flex-wrap: wrap;
                gap: 10px;
            }
        }
        
        @media (max-width: 480px) {
            .btn-agregar {
                flex-grow: 1;
                justify-content: center;
                white-space: normal;
                text-align: center;
                padding: 8px 5px;
            }
            
            .user-info {
                flex-direction: column;
                align-items: flex-end;
            }
            
            .legend {
                flex-direction: column;
                align-items: center;
                gap: 10px;
            }
        }
        
        a.btn{
            text-decoration: none;
        }

        /* Lista de sucursales en vista mes */
        .sucursales-lista-dia {
            font-size: 0.7em;
            color: #0E544C;
            margin-top: 4px;
            padding: 4px;
            max-height: 140px;
            overflow-y: auto;
            scrollbar-width: thin;
        }

        .sucursales-lista-dia::-webkit-scrollbar {
            width: 4px;
        }

        .sucursales-lista-dia::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .sucursales-lista-dia::-webkit-scrollbar-thumb {
            background: #51B8AC;
            border-radius: 2px;
        }

        .sucursales-lista-dia::-webkit-scrollbar-thumb:hover {
            background: #0E544C;
        }

        /* Ocultar eventos en vista mes */
        .fc-dayGridMonth-view .fc-event,
        .fc-dayGridMonth-view .fc-daygrid-day-events,
        .fc-dayGridMonth-view .fc-daygrid-event-harness {
            display: none !important;
        }

        /* Vista semana: Mantener estructura pero ocultar horas si es necesario */
        .fc-timeGridWeek-view .fc-timegrid-slot-label {
            display: none !important;
        }

        .fc-timeGridWeek-view .fc-timegrid-slot,
        .fc-timeGridWeek-view .fc-timegrid-axis {
            /* En lugar de ocultar, hacerlos mínimos */
            min-width: 0 !important;
            width: 0 !important;
        }

        .fc-timeGridWeek-view .fc-timegrid-axis-frame {
            display: none !important;
        }

        .fc-timeGridWeek-view .fc-col-header {
            width: 100% !important;
        }

        .fc-timeGridWeek-view .fc-col-header-cell {
            padding: 10px 0 !important;
        }


        .fc-timeGridWeek-view .fc-timegrid-cols {
            display: table-row !important;
        }

        .fc-timeGridWeek-view .fc-timegrid-col {
            display: table-cell !important;
            width: 14.28% !important; /* Distribución equitativa para 7 días */
        }

        /* Mostrar eventos de múltiples días en vista semana */
        .fc-timeGridWeek-view .fc-daygrid-event,
        .fc-timeGridWeek-view .fc-daygrid-event-harness {
            display: block !important;
        }

        .fc-timeGridWeek-view .fc-daygrid-body {
            display: table-row-group !important;
        }

        .fc-timeGridWeek-view .fc-scrollgrid-section-header {
            display: table-row-group !important;
        }

        .fc-timeGridWeek-view .fc-timegrid-body {
            height: auto !important;
            display: table-row-group !important;
        }
        /* Eventos que abarcan múltiples días */
        .fc-timeGridWeek-view .fc-h-event {
            border-radius: 4px;
            padding: 4px 6px;
            font-size: 0.85em;
        }

        /* Permitir que los eventos se expandan según su contenido */
        .fc-timeGridWeek-view .fc-event {
            position: relative !important;
            min-height: auto !important;
            height: auto !important;
            white-space: normal !important;
            line-height: 1.3 !important;
            padding: 6px 8px !important;
        }
        
    </style>
</head>
<body>
    <!-- Renderizar menú lateral -->
    <?php echo renderMenuLateral($cargoOperario, 'index_avisos_publico.php'); ?>
    
    <!-- Contenido principal -->
    <div class="main-container">   <!-- ya existe en el css de menu lateral -->
        <div class="contenedor-principal"> <!-- ya existe en el css de menu lateral -->
            <!-- todo el contenido existente -->
            <div class="container">
                <!-- Renderizar header universal -->
                <?php echo renderHeader($usuario, $esAdmin, 'Calendario'); ?>
                <div class="calendar-container">
                    <!-- Calendario principal -->
                    <div class="calendar-main" id="calendarMain">
                        <div id='calendar'></div>
                    </div>

                    <!-- Sidebar con tickets sin programar -->
                    <div class="sidebarsolicitudes" id="ticketsSidebar">
                        <div class="sidebarsolicitudes-header">
                            <h5 class="mb-1">
                                <i class="fas fa-clock me-2"></i>
                                Solicitudes pendientes por programar
                            </h5>
                        </div>
                        
                        <!-- Filtro por sucursal -->
                        <div class="sidebar-filter p-3 border-bottom">
                            <select class="form-select form-select-sm" id="filterSucursal">
                                <option value="">Todas las sucursales</option>
                                <?php 
                                // Obtener sucursales únicas
                                $sucursales = [];
                                foreach ($tickets_sin_fecha as $ticket) {
                                    if (!empty($ticket['nombre_sucursal'])) {
                                        $sucursales[$ticket['nombre_sucursal']] = $ticket['nombre_sucursal'];
                                    }
                                }
                                sort($sucursales);
                                ?>
                                <?php foreach ($sucursales as $sucursal): ?>
                                    <option value="<?= htmlspecialchars($sucursal) ?>"><?= htmlspecialchars($sucursal) ?></option>
                                <?php endforeach; ?>
                            </select>
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
                                        data-ticket-codigo="<?= htmlspecialchars($ticket['codigo']) ?>"
                                        data-sucursal="<?= htmlspecialchars($ticket['nombre_sucursal']) ?>"
                                        style="background: <?= getColorByUrgency($ticket['nivel_urgencia'], $ticket['tipo_formulario']) ?>; color: white;">
                                        
                                        <div class="small" style="opacity: 0.9;">
                                            <span style="background: #51B8AC; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.8em;">
                                                <?php if ($ticket['tipo_formulario'] === 'cambio_equipos'): ?>
                                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                                <?php endif; ?>
                                                <?= htmlspecialchars($ticket['nombre_sucursal']) ?>
                                            </span>
                                        </div>

                                        <div class="mb-2">
                                            <?= htmlspecialchars($ticket['titulo']) ?>
                                        </div>
                                        
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <script>
                        // Variable global para controlar el drag
                        let isDraggingTicket = false;
                        
                        document.addEventListener('DOMContentLoaded', function() {
                            
                            const filterSucursal = document.getElementById('filterSucursal');
                            const ticketItems = document.querySelectorAll('.ticket-item');
                            
                            filterSucursal.addEventListener('change', function() {
                                const selectedSucursal = this.value;
                                
                                ticketItems.forEach(function(item) {
                                    const itemSucursal = item.getAttribute('data-sucursal');
                                    
                                    if (selectedSucursal === '' || itemSucursal === selectedSucursal) {
                                        item.style.display = 'block';
                                    } else {
                                        item.style.display = 'none';
                                    }
                                });
                            });
                        

                            // Eventos para los tickets
                            ticketItems.forEach(function(item) {
                                // Evento click
                                item.addEventListener('click', function(e) {
                                    // Solo abrir modal si no hay un drag en curso
                                    if (!isDraggingTicket) {
                                        const ticketId = this.getAttribute('data-ticket-id');
                                        mostrarDetallesTicket(ticketId);
                                    }
                                });
                            });
                        });
                    
                    </script>


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
            const sidebar = document.getElementById('ticketsSidebar');
            const calendarMain = document.getElementById('calendarMain');
            
            if (!calendarEl) {
                console.error('❌ No se encontró el elemento #calendar');
                return;
            }
            
            try {
                calendar = new FullCalendar.Calendar(calendarEl, {
                    locale: 'es',
                    initialView: 'timeGridWeek',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek' //,listWeek' se elimna
                    },
                    
                    height: 'auto',
                    editable: <?= verificarAccesoCargo([14, 16, 35]) || $esAdmin ? 'true' : 'false' ?>, // Solo editables para mantenimiento
                    droppable: <?= verificarAccesoCargo([14, 16, 35]) || $esAdmin ? 'true' : 'false' ?>, // Solo arrastrables para mantenimiento
                    eventStartEditable: <?= verificarAccesoCargo([14, 16, 35]) || $esAdmin ? 'true' : 'false' ?>,
                    eventDurationEditable: <?= verificarAccesoCargo([14, 16, 35]) || $esAdmin ? 'true' : 'false' ?>,
                    events: eventos,

                    views: {
                        dayGridMonth: {
                            // Vista mes: Ocultar eventos
                            eventDisplay: 'none'
                        },
                        timeGridWeek: {
                            // Vista semana: Configuración sin horas
                            allDaySlot: true,
                            slotMinTime: '00:00:00',
                            slotMaxTime: '24:00:00',
                            slotLabelInterval: '24:00', // Mostrar solo un slot para todo el día
                            slotDuration: '24:00'
                        }
                    },
                    displayEventTime: false,  // ✅ No mostrar hora
                    displayEventEnd: false,   // ✅ No mostrar hora final

                    // ✅ NUEVO: Configuración para bloquear eventos finalizados
                    eventAllow: function(dropInfo, draggedEvent) {
                        const status = draggedEvent.extendedProps.status || '';
                        if (status === 'finalizado') {
                            console.log('❌ EventAllow: Ticket finalizado bloqueado');
                            return false;
                        }
                        return true;
                    },

                    // ✅ NUEVO: Configurar drag desde eventos del calendario
                    eventDragStart: function(info) {
                        console.log('Evento drag start desde calendario:', info.event);
                        
                        const status = info.event.extendedProps.status || '';
                        
                        if (status === 'finalizado') {
                            console.log('❌ Ticket finalizado, no se puede mover');
                            return false;
                        }
                        
                        draggedTicket = {
                            id: info.event.id,
                            title: info.event.title,
                            codigo: info.event.extendedProps.codigo,
                            fromCalendar: true,
                            status: status
                        };
                        
                        console.log('✅ draggedTicket configurado:', draggedTicket);
                        
                        const sidebar = document.getElementById('ticketsSidebar');
                        sidebar.classList.add('accepting-drop');
                    },
                                                            

                    eventDragStop: function(info) {
                        console.log('Evento drag stop');
                        
                        const sidebar = document.getElementById('ticketsSidebar');
                        sidebar.classList.remove('accepting-drop');
                        
                        // ✅ NUEVO: Obtener posición del mouse del evento original
                        const mouseEvent = info.jsEvent;
                        const mouseX = mouseEvent.clientX;
                        const mouseY = mouseEvent.clientY;
                        
                        console.log('🖱️ Posición del mouse al soltar:', mouseX, mouseY);
                        
                        // Verificar si el mouse está sobre el sidebar
                        const sidebarRect = sidebar.getBoundingClientRect();
                        console.log('📦 Rectángulo del sidebar:', sidebarRect);
                        
                        const dentroDeSidebar = (
                            mouseX >= sidebarRect.left &&
                            mouseX <= sidebarRect.right &&
                            mouseY >= sidebarRect.top &&
                            mouseY <= sidebarRect.bottom
                        );
                        
                        console.log('✅ ¿Dentro del sidebar?', dentroDeSidebar);
                        
                        if (dentroDeSidebar && draggedTicket && draggedTicket.fromCalendar) {
                            console.log('🎯 Evento soltado en sidebar, desprogramando...');
                            // Remover el evento del calendario visualmente
                            info.event.remove();
                            // Desprogramar en backend
                            desprogramarTicket(draggedTicket);
                            draggedTicket = null;
                        }
                    },
                    
                    // Cuando se mueve un evento existente
                    eventDrop: function(info) {
                        console.log('📍 Evento movido dentro del calendario - eventDrop');

                        const ticketId = info.event.id;
                        const nuevaFechaInicio = info.event.start.toISOString().split('T')[0];
                        const nuevaFechaFinal = info.event.end ? 
                            new Date(info.event.end.getTime() - 86400000).toISOString().split('T')[0] : 
                            nuevaFechaInicio;
                        
                        console.log('Nueva fecha inicio:', nuevaFechaInicio);
                        console.log('Nueva fecha final:', nuevaFechaFinal);
                        
                        actualizarFechasTicket(ticketId, nuevaFechaInicio, nuevaFechaFinal, info);
                    },
                    
                    // Cuando se cambia la duración de un evento
                    eventResize: function(info) {
                        console.log('Evento redimensionado:', info.event);
                        
                        const ticketId = info.event.id;
                        const fechaInicio = info.event.start.toISOString().split('T')[0];
                        const fechaFinal = info.event.end ? 
                            new Date(info.event.end.getTime() - 86400000).toISOString().split('T')[0] : 
                            fechaInicio;
                        
                        actualizarFechasTicket(ticketId, fechaInicio, fechaFinal, info);
                    },
                    
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
                        const view = calendar.view.type;
                        const urgencia = arg.event.extendedProps.urgencia || 0;
                        const titulo = arg.event.title;
                        const sucursal = arg.event.extendedProps.sucursal || '';
                        const descripcion = arg.event.extendedProps.descripcion;
                        const status = arg.event.extendedProps.status || '';
                        const tipo_formulario = arg.event.extendedProps.tipo_formulario || '';
                        const id = arg.event.id;
                        
                        // Vista Mes: Ocultar (se maneja con CSS)
                        if (view === 'dayGridMonth') {
                            return { html: '<div style="display: none;"></div>' };
                        }
                        
                        // Vista Semana: Con nombres de colaboradores y botón +
                        if (view === 'timeGridWeek') {
                            let icono = tipo_formulario === 'cambio_equipos' ? 'fas fa-exclamation-triangle' : '';
                            
                            return {
                                html: `<div style="font-size: 0.75em; line-height: 1.2; padding: 2px;" onclick="event.stopPropagation();">
                                            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 3px; margin-bottom: 2px;" onclick="mostrarDetallesTicket(${id})">
                                                <div style="background: #51B8AC; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.5em; white-space: normal; word-wrap: break-word; border: 0.5px solid rgba(255,255,255,0.3);">
                                                    <i class="${icono}" style="font-size: 0.6em;"></i> ${sucursal}
                                                </div>
                                            </div>
                                            
                                            <div style="font-size: 0.7em; line-height: 1.1; white-space: normal; word-wrap: break-word; padding: 1px 0px; border-radius: 2px; cursor: pointer;" onclick="mostrarDetallesTicket(${id})">
                                                ${titulo}
                                            </div>
                                            
                                            <div style="margin-top: 4px; border-top: 1px solid rgba(255,255,255,0.3); padding-top: 4px; font-size: 0.65em; display: flex; justify-content: space-between; align-items: center;" onclick="mostrarDetallesTicket(${id})">
                                                <div id="colaboradores-list-${id}" style="display: flex; flex-wrap: wrap; gap: 2px;" >
                                                    <span class="badge" style="background: rgba(255,255,255,0.3); color: inherit; font-size: 0.85em; padding: 1px 4px;">Cargando...</span>
                                                </div>
                                                <button class="btn btn-sm" 
                                                        onclick="event.stopPropagation(); abrirModalColaboradores(${id})"
                                                        style="font-size: 0.7em; padding: 1px 6px; background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.4); color: inherit; border-radius: 3px;">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </div>`
                            };
                        }
                        
                        // Default
                        return {
                            html: '<div style="padding: 2px 4px; font-size: 0.8em; overflow: hidden; text-overflow: ellipsis;">' + 
                                arg.event.title + '</div>'
                        };
                    },
                    
                    //cambio de vista
                    viewDidMount: function(viewInfo) {
                        console.log('Vista cambiada:', viewInfo.view.type);
                        
                        // Mostrar sidebar solo en vista timeGridWeek
                        if (viewInfo.view.type === 'timeGridWeek') {
                            sidebar.classList.remove('hidden');
                            calendarMain.classList.remove('full-width');

                            // ✅ FORZAR REFLOW después de un pequeño delay
                            setTimeout(() => {
                                if (calendar) {
                                    calendar.updateSize();
                                }
                            }, 100);
                        } else {
                            sidebar.classList.add('hidden');
                            calendarMain.classList.add('full-width');
                        }
                    },

                    // Agregar sucursales al día
                    dayCellDidMount: function(info) {
                        const view = calendar.view.type;
                        const fecha = info.date.toISOString().split('T')[0];
                        
                        // Solo en vista mes
                        if (view === 'dayGridMonth' && sucursalesPorDia[fecha]) {
                            const sucursales = Array.from(sucursalesPorDia[fecha]);
                            if (sucursales.length > 0) {
                                const sucursalesDiv = document.createElement('div');
                                sucursalesDiv.className = 'sucursales-lista-dia';
                                
                                // Crear lista de sucursales (una por línea)
                                let listaHtml = '<div style="display: flex; flex-direction: column; gap: 2px;">';
                                sucursales.forEach(suc => {
                                    listaHtml += '<div style="background: rgba(81, 184, 172, 0.15); padding: 2px 6px; border-radius: 3px; display: flex; align-items: center; gap: 4px;">' +
                                                '<span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' + suc + '</span>' +
                                                '</div>';
                                });
                                listaHtml += '</div>';
                                
                                sucursalesDiv.innerHTML = listaHtml;
                                sucursalesDiv.title = 'Sucursales programadas: ' + sucursales.join(', ');
                                
                                // Agregar después del número del día
                                const dayFrame = info.el.querySelector('.fc-daygrid-day-frame');
                                if (dayFrame) {
                                    dayFrame.appendChild(sucursalesDiv);
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
                inicializarDropZoneSidebar();
                // INICIALIZAR FILTRO AL CARGAR LA PÁGINA
                inicializarFiltroSucursal();
                console.log('✅ Inicialización completa');
                
                // Cargar colaboradores en los eventos del calendario
                cargarColaboradoresEnEventos();
                
            } catch (error) {
                console.error('❌ Error al crear calendario:', error);
                alert('Error al inicializar el calendario: ' + error.message);
            }
        });
        

        // ✅ FUNCIÓN: Escapar HTML
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }

        // ✅ FUNCIÓN: Obtener color por urgencia (versión JS)
        function getColorByJS(urgencia) {
            switch (parseInt(urgencia)) {
                case 1: return '#28a745';
                case 2: return '#ffc107';
                case 3: return '#fd7e14';
                case 4: return '#dc3545';
                default: return '#8b8b8bff';
            }
        }
        

        // ✅ FUNCIÓN: Actualizar filtro de sucursales
        function actualizarFiltroSucursales(ticketsSinFecha) {
            const filterSelect = document.getElementById('filterSucursal');
            const valorActual = filterSelect.value;
            
            // Obtener sucursales únicas
            const sucursales = [...new Set(ticketsSinFecha.map(t => t.nombre_sucursal).filter(Boolean))].sort();
            
            let options = '<option value="">Todas las sucursales</option>';
            sucursales.forEach(suc => {
                options += `<option value="${escapeHtml(suc)}">${escapeHtml(suc)}</option>`;
            });
            
            filterSelect.innerHTML = options;
            filterSelect.value = valorActual;
        }

        // ✅ FUNCIÓN: Actualizar sidebar con nuevos tickets (MEJORADA)
        function actualizarSidebar(ticketsSinFecha) {
            const unscheduledContainer = document.getElementById('unscheduledTickets');
            const filtroActual = obtenerFiltroActual();
            
            if (ticketsSinFecha.length === 0) {
                unscheduledContainer.innerHTML = `
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-check-circle fa-3x mb-3"></i>
                        <p>¡Todos los tickets están programados!</p>
                    </div>
                `;
            } else {
                let html = '';
                ticketsSinFecha.forEach(ticket => {
                    const color = ticket.tipo_formulario === 'cambio_equipos' ? '#dc3545' : getColorByJS(ticket.nivel_urgencia);
                    const icono = ticket.tipo_formulario === 'cambio_equipos' ? '<i class="fas fa-exclamation-triangle me-1"></i>' : '';
                    
                    html += `
                        <div class="ticket-item" 
                            draggable="true" 
                            data-ticket-id="${ticket.id}"
                            data-ticket-title="${escapeHtml(ticket.titulo)}"
                            data-ticket-codigo="${escapeHtml(ticket.codigo)}"
                            data-sucursal="${escapeHtml(ticket.nombre_sucursal)}"
                            style="background: ${color}; color: white;">
                            
                            <div class="small" style="opacity: 0.9;">
                                <span style="background: #51B8AC; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.8em;">
                                    ${icono}
                                    ${escapeHtml(ticket.nombre_sucursal)}
                                </span>
                            </div>

                            <div class="mb-2">
                                ${escapeHtml(ticket.titulo)}
                            </div>
                        </div>
                    `;
                });
                
                unscheduledContainer.innerHTML = html;
                
                // Reinicializar eventos de drag
                inicializarDragTickets();
            }
            
            // Actualizar opciones del filtro
            actualizarOpcionesFiltro(ticketsSinFecha, filtroActual);
            
            // Re-inicializar el event listener del filtro
            inicializarFiltroSucursal();
            
            // Aplicar el filtro que estaba activo
            aplicarFiltroDespuesDeRefresh(filtroActual, ticketsSinFecha);
        }

        // ✅ FUNCIÓN: Actualizar opciones del filtro (MEJORADA)
        function actualizarOpcionesFiltro(ticketsSinFecha, filtroActual) {
            const filterSelect = document.getElementById('filterSucursal');
            if (!filterSelect) return;
            
            // Obtener sucursales únicas
            const sucursales = [...new Set(ticketsSinFecha.map(t => t.nombre_sucursal).filter(Boolean))].sort();
            
            let options = '<option value="">Todas las sucursales</option>';
            sucursales.forEach(suc => {
                const selected = suc === filtroActual ? 'selected' : '';
                options += `<option value="${escapeHtml(suc)}" ${selected}>${escapeHtml(suc)}</option>`;
            });
            
            filterSelect.innerHTML = options;
        }

        // ✅ FUNCIÓN: Aplicar filtro después del refresh (NUEVA)
        function aplicarFiltroDespuesDeRefresh(filtroAnterior, ticketsSinFecha) {
            const filterSelect = document.getElementById('filterSucursal');
            if (!filterSelect) return;
            
            // Si no hay tickets, forzar "Todas las sucursales"
            if (ticketsSinFecha.length === 0) {
                filterSelect.value = '';
                aplicarFiltro('');
                return;
            }
            
            // Verificar si la sucursal del filtro anterior existe en los nuevos tickets
            const sucursalesDisponibles = [...new Set(ticketsSinFecha.map(t => t.nombre_sucursal))];
            const filtroValido = filtroAnterior && sucursalesDisponibles.includes(filtroAnterior);
            
            if (filtroValido) {
                // Mantener el filtro anterior si es válido
                filterSelect.value = filtroAnterior;
                aplicarFiltro(filtroAnterior);
            } else {
                // Si no hay tickets para el filtro anterior, cambiar a "Todas las sucursales"
                filterSelect.value = '';
                aplicarFiltro('');
                console.log('🔄 Filtro cambiado a "Todas las sucursales" - No hay tickets para:', filtroAnterior);
            }
        }

        // ✅ FUNCIÓN: Actualizar filtro sin resetear selección (MEJORADA)
        function actualizarFiltroSucursales(ticketsSinFecha) {
            const filterSelect = document.getElementById('filterSucursal');
            if (!filterSelect) return;
            
            const valorActual = filterSelect.value;
            
            // Obtener sucursales únicas
            const sucursales = [...new Set(ticketsSinFecha.map(t => t.nombre_sucursal).filter(Boolean))].sort();
            
            let options = '<option value="">Todas las sucursales</option>';
            sucursales.forEach(suc => {
                options += `<option value="${escapeHtml(suc)}">${escapeHtml(suc)}</option>`;
            });
            
            filterSelect.innerHTML = options;
            
            // ✅ PRESERVAR la selección anterior si existe en las nuevas opciones
            if (valorActual && sucursales.includes(valorActual)) {
                filterSelect.value = valorActual;
            } else {
                filterSelect.value = ''; // Resetear si la sucursal anterior ya no existe
            }
        }

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
                        codigo: this.dataset.ticketCodigo,
                        fromSidebar: true
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
        
        // ✅ FUNCIÓN: Obtener filtro actual (MEJORADA)
        function obtenerFiltroActual() {
            const filterSelect = document.getElementById('filterSucursal');
            return filterSelect ? filterSelect.value : '';
        }

        // ✅ FUNCIÓN: Inicializar event listener del filtro (NUEVA)
        function inicializarFiltroSucursal() {
            const filterSucursal = document.getElementById('filterSucursal');
            if (!filterSucursal) return;
            
            // Remover event listeners anteriores para evitar duplicados
            const newFilter = filterSucursal.cloneNode(true);
            filterSucursal.parentNode.replaceChild(newFilter, filterSucursal);
            
            // Agregar nuevo event listener
            newFilter.addEventListener('change', function() {
                const selectedSucursal = this.value;
                aplicarFiltro(selectedSucursal);
            });
            
            console.log('✅ Filtro de sucursal inicializado');
        }

        // ✅ FUNCIÓN: Aplicar filtro (MEJORADA)
        function aplicarFiltro(sucursal) {
            const ticketItems = document.querySelectorAll('.ticket-item');
            let ticketsVisibles = 0;
            
            ticketItems.forEach(function(item) {
                const itemSucursal = item.getAttribute('data-sucursal');
                
                if (!sucursal || sucursal === '' || itemSucursal === sucursal) {
                    item.style.display = 'block';
                    ticketsVisibles++;
                } else {
                    item.style.display = 'none';
                }
            });
            
            console.log(`✅ Filtro aplicado: "${sucursal}" - Tickets visibles: ${ticketsVisibles}`);
        }

        // ✅ FUNCIÓN: Refrescar calendario y sidebar (MEJORADA)
        function refrescarCalendarioYSidebar() {
            console.log('Refrescando calendario y sidebar...');
            
            if (!calendar) {
                console.error('Calendar no está inicializado');
                location.reload();
                return;
            }
            
            // Guardar estado actual
            const vistaActual = calendar.view.type;
            const fechaActual = calendar.getDate();
            const filtroActual = obtenerFiltroActual();
            
            console.log('Filtro actual a preservar:', filtroActual);
            
            $.ajax({
                url: 'ajax/get_calendar_data.php',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    console.log('Datos actualizados recibidos:', data);
                    
                    if (!data.success) {
                        console.error('Error en respuesta:', data.message);
                        alert('Error al actualizar: ' + data.message);
                        return;
                    }
                    
                    // Actualizar eventos del calendario
                    calendar.removeAllEvents();
                    calendar.addEventSource(data.eventos);
                    
                    // Restaurar vista y fecha
                    calendar.changeView(vistaActual, fechaActual);
                    
                    // Actualizar sidebar con los nuevos datos
                    actualizarSidebar(data.tickets_sin_fecha);

                    // ✅ AGREGAR: Recargar colaboradores después de refrescar
                    setTimeout(() => {
                        cargarColaboradoresEnEventos();
                    }, 500);
                    
                    console.log('✅ Calendario y sidebar actualizados con filtro preservado');
                },
                error: function(xhr, status, error) {
                    console.error('Error al obtener datos:', error);
                    console.error('Respuesta:', xhr.responseText);
                    alert('Error al actualizar el calendario. Se recargará la página.');
                    location.reload();
                }
            });
        }
        
        function inicializarDropZoneSidebar() {
            console.log('Inicializando drop zone en sidebar...');
            
            const sidebar = document.getElementById('ticketsSidebar');
            const unscheduledArea = document.getElementById('unscheduledTickets');
            
            if (!sidebar || !unscheduledArea) {
                console.error('No se encontró el sidebar o el área de tickets');
                return;
            }
            
            sidebar.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                sidebar.classList.add('drag-over');
            });
            
            sidebar.addEventListener('dragleave', function(e) {
                if (e.target === sidebar) {
                    sidebar.classList.remove('drag-over');
                }
            });
            
            sidebar.addEventListener('drop', function(e) {
                e.preventDefault();
                sidebar.classList.remove('drag-over');
                
                console.log('Drop en sidebar detectado');
                
                if (draggedTicket && draggedTicket.fromSidebar) {
                    console.log('Ticket viene del sidebar, ignorando');
                    draggedTicket = null;
                    return;
                }
                
                if (draggedTicket && draggedTicket.fromCalendar) {
                    desprogramarTicket(draggedTicket);
                    draggedTicket = null;
                }
            });
        }

        // Actualizar fechas de ticket movido
        function actualizarFechasTicket(ticketId, fechaInicio, fechaFinal, eventInfo) {
            console.log('Actualizando fechas del ticket:', ticketId, fechaInicio, fechaFinal);
            
            const loading = document.createElement('div');
            loading.id = 'loadingOverlay';
            loading.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; background: white; padding: 15px 25px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
            loading.innerHTML = '<div class="spinner-border spinner-border-sm me-2" role="status"></div>Actualizando...';
            document.body.appendChild(loading);
            
            $.ajax({
                url: 'ajax/update_ticket_dates.php',
                method: 'POST',
                data: {
                    id: ticketId,
                    fecha_inicio: fechaInicio,
                    fecha_final: fechaFinal
                },
                dataType: 'json',
                success: function(response) {
                    loading.remove();
                    
                    if (response.success) {
                        const successAlert = document.createElement('div');
                        successAlert.className = 'alert alert-success position-fixed';
                        successAlert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 250px;';
                        successAlert.innerHTML = `
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Fechas actualizadas</strong>
                        `;
                        document.body.appendChild(successAlert);
                        
                        // ✅ AQUÍ ESTÁ EL CAMBIO: Recargar después de éxito
                        setTimeout(() => {
                            successAlert.remove();
                            
                            //location.reload(); // Recargar la página
                        }, 1500);
                        
                    } else {
                        alert('❌ Error: ' + response.message);
                        if (eventInfo) {
                            eventInfo.revert(); // Revertir cambio si hay error
                        }
                    }
                },
                error: function(xhr, status, error) {
                    loading.remove();
                    console.error('Error AJAX:', xhr.responseText);
                    alert('❌ Error: ' + error);
                    if (eventInfo) {
                        eventInfo.revert(); // Revertir cambio si hay error
                    }
                }
            });
        }
        
        function programarTicket(ticket, fechaInicio, fechaFinal) {
            console.log('Programando ticket automáticamente:', ticket, fechaInicio, fechaFinal);
            
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
                            successAlert.remove();
                            refrescarCalendarioYSidebar();
                        }, 1500);
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
                    const modal = $('<div class="modal fade"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">' + response + '</div></div></div></div>');
                    $('body').append(modal);
                    
                    // ✅ RESETEAR y luego INICIALIZAR
                    setTimeout(function() {
                        // Leer el valor correcto del input hidden que viene del servidor
                        const hiddenInput = document.getElementById('edit_nivel_urgencia');
                        if (hiddenInput && typeof window.currentUrgency !== 'undefined') {
                            const newUrgency = hiddenInput.value ? parseInt(hiddenInput.value) : null;
                            console.log('🔄 Reseteando urgencia de', window.currentUrgency, 'a', newUrgency);
                            window.currentUrgency = newUrgency;
                        }
                        
                        if (typeof initUrgencyControls === 'function') {
                            initUrgencyControls();
                        }
                    }, 0);
                    
                    modal.modal('show');
                    
                    modal.on('hidden.bs.modal', function() { 
                        modal.remove(); 
                    });
                }
            });
        }
        
        // Mostrar tickets del día
        function mostrarTicketsDelDia(fecha) {
            const ticketsDelDia = eventos.filter(e => e.start === fecha);
            
            if (ticketsDelDia.length === 0) return;
            
            let html = '';
            
            const porSucursal = {};
            ticketsDelDia.forEach(t => {
                const suc = t.extendedProps.sucursal || 'Sin sucursal';
                if (!porSucursal[suc]) porSucursal[suc] = [];
                porSucursal[suc].push(t);
            });
            
            for (const suc in porSucursal) {
                html += '<div class="card mb-3"><div class="card-header" style="background: #51B8AC; color: white;">' + suc + '</div><div class="card-body"><ul class="list-unstyled">';
                porSucursal[suc].forEach(t => {
                    html += '<li class="mb-2">- ' + t.title + '</li>';
                });
                html += '</ul></div></div>';
            }
            
            const modal = $('<div class="modal fade"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">' + html + '</div></div></div></div>');
            $('body').append(modal);
            modal.modal('show');
            modal.on('hidden.bs.modal', function() { modal.remove(); });
        }
        
        function refreshData() {
            location.reload();
        }
        
        function desprogramarTicket(ticket) {
            console.log('Desprogramando ticket:', ticket);
            
            const loading = document.createElement('div');
            loading.id = 'loadingOverlay';
            loading.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; background: white; padding: 15px 25px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
            loading.innerHTML = '<div class="spinner-border spinner-border-sm me-2" role="status"></div>Desprogramando...';
            document.body.appendChild(loading);
            
            $.ajax({
                url: 'ajax/unschedule_ticket.php',
                method: 'POST',
                data: {
                    ticket_id: ticket.id
                },
                dataType: 'json',
                success: function(response) {
                    loading.remove();
                    
                    if (response.success) {
                        const successAlert = document.createElement('div');
                        successAlert.className = 'alert alert-success position-fixed';
                        successAlert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
                        successAlert.innerHTML = `
                            <i class="fas fa-undo me-2"></i>
                            <strong>¡Ticket desprogramado!</strong><br>
                            Regresó a la lista de pendientes
                        `;
                        document.body.appendChild(successAlert);
                        
                        setTimeout(() => {
                            successAlert.remove();
                            refrescarCalendarioYSidebar(); // ✅ Usar la función corregida
                        }, 500);
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

        function finalizarTicketRapido(ticketId, buttonElement) {
            // Detener la propagación del evento inmediatamente
            event.stopPropagation();
            event.preventDefault();
            
            if (!confirm('¿Estás seguro de que deseas finalizar este ticket?\n\nSe establecerá:\n• Estado: Finalizado')) {
                return;
            }
            
            $.ajax({
                url: 'ajax/finalizar_ticket.php',
                method: 'POST',
                data: {
                    id: ticketId,
                    status: 'finalizado',
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Ocultar solo el botón que se clickeó
                        if (buttonElement) {
                            buttonElement.style.display = 'none';
                        } else {
                            // Fallback: buscar el botón por el ticketId
                            const buttons = document.querySelectorAll(`button[onclick*="${ticketId}"]`);
                            buttons.forEach(btn => btn.style.display = 'none');
                        }
                        
                    } else {
                        alert('❌ Error: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error AJAX:', xhr.responseText);
                    alert('❌ Error en la comunicación con el servidor');
                }
            });
        }

        //Funcion de colaboradores asignados a ticket
        function cargarColaboradoresEnEventos() {
            setTimeout(() => {
                $('.fc-event').each(function() {
                    const ticketId = $(this).find('[id^="colaboradores-list-"]').attr('id');
                    if (ticketId) {
                        const id = ticketId.replace('colaboradores-list-', '');
                        cargarColaboradoresTicket(id);
                    }
                });
            }, 500);
        }

        function abrirModalColaboradores(ticketId) {
            $.ajax({
                url: 'ajax/get_modal_colaboradores.php',
                method: 'GET',
                data: { ticket_id: ticketId },
                success: function(response) {
                    const modal = $('<div class="modal fade" id="modalColaboradores"><div class="modal-dialog"><div class="modal-content">' + response + '</div></div></div>');
                    $('body').append(modal);
                    const bsModal = new bootstrap.Modal(document.getElementById('modalColaboradores'));
                    bsModal.show();
                    modal.on('hidden.bs.modal', function() {
                        modal.remove();
                    });
                }
            });
        }

        function cargarColaboradoresTicket(ticketId, container) {
            $.ajax({
                url: 'ajax/get_ticket_colaboradores.php',
                method: 'GET',
                data: { ticket_id: ticketId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const listContainer = $(`#colaboradores-list-${ticketId}`);
                        if (listContainer.length) {
                            if (response.colaboradores.length === 0) {
                                listContainer.html('<span class="badge" style="background: rgba(255,255,255,0.3); color: inherit; font-size: 0.85em; padding: 1px 4px;">Sin asignar</span>');
                            } else {
                                let html = '';
                                response.colaboradores.forEach(col => {
                                    const primerNombre = col.Nombre.split(' ')[0];
                                    html += `<span class="badge" style="background: rgba(255,255,255,0.3); color: inherit; font-size: 0.85em; padding: 1px 4px;">${primerNombre}</span>`;
                                });
                                listContainer.html(html);
                            }
                        }
                    }
                }
            });
        }

    </script>

</body>
</html>