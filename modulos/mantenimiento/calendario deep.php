<?php
session_start();
require_once 'models/Ticket.php';

$ticket = new Ticket();
$tickets_con_fecha = $ticket->getTicketsForCalendar();
$tickets_sin_fecha = $ticket->getTicketsWithoutDates();

// DEBUG: Verificar datos
error_log("Tickets con fecha: " . count($tickets_con_fecha));
error_log("Tickets sin fecha: " . count($tickets_sin_fecha));

// Procesar tickets para el calendario
$calendar_events = [];
$sucursales_por_dia = [];

foreach ($tickets_con_fecha as $t) {
    // ... resto del código igual ...
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario de Mantenimiento</title>
    
    <!-- Bootstrap CSS con fallback -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
        // Verificar si Bootstrap se cargó
        if (typeof bootstrap === 'undefined') {
            document.write('<link href="css/bootstrap.min.css" rel="stylesheet">');
        }
    </script>
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- FullCalendar CSS con fallback -->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css' rel='stylesheet' />
    <script>
        // Verificar si FullCalendar CSS se cargó
        if (!document.querySelector('link[href*="fullcalendar"]')) {
            document.write('<link href="css/fullcalendar.min.css" rel="stylesheet">');
        }
    </script>
    
    <style>
        /* Tus estilos CSS actuales aquí */
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
            min-height: 600px;
        }
        
        #calendar {
            height: 100% !important;
            min-height: 500px;
        }
        
        .fc {
            height: 100% !important;
        }
        
        /* Resto de tus estilos... */
    </style>
</head>
<style>
    .calendar-container {
        height: calc(100vh - 150px); /* Aumenté un poco el espacio */
        display: flex;
    }
    
    .calendar-main {
        flex: 1;
        padding: 20px;
        background: white;
        margin-right: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        min-height: 600px; /* Altura mínima */
    }
    
    #calendar {
        height: 100% !important;
        min-height: 500px;
    }
    
    .fc {
        height: 100% !important;
    }
    
    /* Asegurar que el body tenga altura */
    html, body {
        height: 100%;
    }
    
    .bg-light {
        min-height: 100vh;
    }
</style>
function initializeCalendar() {
    const calendarEl = document.getElementById('calendar');
    
    // Verificar que el elemento existe
    if (!calendarEl) {
        console.error('No se encontró el elemento #calendar');
        return;
    }
    
    console.log('Inicializando calendario...');
    console.log('Eventos:', <?= json_encode($calendar_events) ?>);
    
    calendar = new FullCalendar.Calendar(calendarEl, {
        locale: 'es',
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listWeek'
        },
        height: 'auto',
        contentHeight: 'auto',
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
    console.log('Calendario renderizado');
}