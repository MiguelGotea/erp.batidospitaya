<?php
header('Content-Type: application/json');
require_once '../models/Ticket.php';

try {
    
    $ticket = new Ticket();
    
    // Obtener tickets 

    $tickets_con_fecha = $ticket->getTicketsForCalendar();
    $tickets_sin_fecha = $ticket->getTicketsWithoutDates();

    
    // Función auxiliar para color por urgencia
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
    
    // Procesar eventos para el calendario
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
    
    echo json_encode([
        'success' => true,
        'eventos' => $calendar_events,
        'tickets_sin_fecha' => $tickets_sin_fecha
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>