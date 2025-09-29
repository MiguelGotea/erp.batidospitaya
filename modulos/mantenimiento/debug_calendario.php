<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'models/Ticket.php';

echo "<h1>Debug del Calendario</h1>";
echo "<hr>";

try {
    $ticket = new Ticket();
    
    echo "<h3>1. Obtener Tickets con Fecha:</h3>";
    $tickets_con_fecha = $ticket->getTicketsForCalendar();
    echo "✅ Tickets con fecha: " . count($tickets_con_fecha) . "<br>";
    
    echo "<h3>2. Obtener Tickets sin Fecha:</h3>";
    $tickets_sin_fecha = $ticket->getTicketsWithoutDates();
    echo "✅ Tickets sin fecha: " . count($tickets_sin_fecha) . "<br>";
    
    echo "<h3>3. Eventos para el Calendario:</h3>";
    $calendar_events = [];
    foreach ($tickets_con_fecha as $t) {
        $calendar_events[] = [
            'id' => $t['id'],
            'title' => $t['titulo'],
            'start' => $t['fecha_inicio'],
            'end' => date('Y-m-d', strtotime($t['fecha_final'] . ' +1 day')),
            'backgroundColor' => '#51B8AC',
            'borderColor' => '#51B8AC'
        ];
    }
    echo "✅ Eventos generados: " . count($calendar_events) . "<br>";
    echo "<pre>" . json_encode($calendar_events, JSON_PRETTY_PRINT) . "</pre>";
    
    echo "<h3>4. Test de FullCalendar (HTML Simple):</h3>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Calendario</title>
    <!-- FullCalendar CSS -->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <style>
        #calendar {
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
        }
        .status {
            padding: 10px;
            margin: 20px auto;
            max-width: 900px;
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <h2 style="text-align: center;">Test de Calendario FullCalendar</h2>
    
    <div class="status" id="status">
        ⏳ Cargando librerías...
    </div>
    
    <div id='calendar'></div>

    <!-- FullCalendar JS -->
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/es.js'></script>
    
    <script>
        const statusDiv = document.getElementById('status');
        
        // Verificar que FullCalendar está disponible
        console.log('Verificando FullCalendar...');
        console.log('FullCalendar disponible:', typeof FullCalendar !== 'undefined');
        
        if (typeof FullCalendar === 'undefined') {
            statusDiv.innerHTML = '❌ Error: FullCalendar no se cargó correctamente';
            statusDiv.style.background = '#ffebee';
            statusDiv.style.borderColor = '#f44336';
            console.error('FullCalendar no está definido');
        } else {
            statusDiv.innerHTML = '✅ FullCalendar cargado correctamente';
            statusDiv.style.background = '#e8f5e9';
            statusDiv.style.borderColor = '#4caf50';
            console.log('✅ FullCalendar version:', FullCalendar.version);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM cargado');
            
            var calendarEl = document.getElementById('calendar');
            console.log('Elemento calendario:', calendarEl);
            
            if (!calendarEl) {
                console.error('No se encontró el elemento con id="calendar"');
                alert('Error: No se encontró el contenedor del calendario');
                return;
            }
            
            if (typeof FullCalendar === 'undefined') {
                alert('Error: La librería FullCalendar no se cargó. Verifica tu conexión a internet.');
                return;
            }
            
            try {
                var calendar = new FullCalendar.Calendar(calendarEl, {
                    locale: 'es',
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,listWeek'
                    },
                    height: 'auto',
                    events: <?php echo json_encode($calendar_events); ?>,
                    eventClick: function(info) {
                        console.log('Clic en evento:', info.event);
                        alert('Evento: ' + info.event.title);
                    },
                    eventDidMount: function(info) {
                        console.log('Evento renderizado:', info.event.title);
                    }
                });
                
                console.log('Calendario creado, renderizando...');
                calendar.render();
                console.log('✅ Calendario renderizado exitosamente');
                
                statusDiv.innerHTML = '✅ Calendario funcionando correctamente - Total eventos: <?= count($calendar_events) ?>';
                
            } catch (error) {
                console.error('❌ Error al crear calendario:', error);
                statusDiv.innerHTML = '❌ Error al crear calendario: ' + error.message;
                statusDiv.style.background = '#ffebee';
                statusDiv.style.borderColor = '#f44336';
            }
        });
    </script>
    
    <div style="text-align: center; margin-top: 20px; padding: 20px;">
        <p><strong>Debug Info:</strong></p>
        <p>Tickets con fecha: <?= count($tickets_con_fecha) ?></p>
        <p>Tickets sin fecha: <?= count($tickets_sin_fecha) ?></p>
        <p>Eventos generados: <?= count($calendar_events) ?></p>
        <hr>
        <a href="calendario.php" class="btn btn-primary" style="display: inline-block; padding: 10px 20px; background: #51B8AC; color: white; text-decoration: none; border-radius: 5px;">Ir a Calendario Principal</a>
    </div>
</body>
</html>