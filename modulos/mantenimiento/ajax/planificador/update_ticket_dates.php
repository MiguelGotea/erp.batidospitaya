<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Ticket.php';

header('Content-Type: application/json');

try {
    global $db;
    
    $ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
    $fecha_inicio = isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : '';
    $fecha_final = isset($_POST['fecha_final']) ? $_POST['fecha_final'] : '';
    
    if (!$ticket_id || !$fecha_inicio || !$fecha_final) {
        throw new Exception('Datos incompletos');
    }
    
    // Validar formato de fecha
    $fecha_inicio_obj = DateTime::createFromFormat('Y-m-d', $fecha_inicio);
    $fecha_final_obj = DateTime::createFromFormat('Y-m-d', $fecha_final);
    
    if (!$fecha_inicio_obj || !$fecha_final_obj) {
        throw new Exception('Formato de fecha inválido');
    }
    
    // Validar que fecha_final sea mayor o igual a fecha_inicio
    if ($fecha_final < $fecha_inicio) {
        throw new Exception('La fecha final no puede ser anterior a la fecha de inicio');
    }
    
    $fecha_inicio_formatted = $fecha_inicio_obj->format('Y-m-d');
    $fecha_final_formatted = $fecha_final_obj->format('Y-m-d');
    
    // Actualizar fechas del ticket
    $sql = "UPDATE mtto_tickets 
            SET fecha_inicio = ?, 
                fecha_final = ?
            WHERE id = ?";
    $db->query($sql, [$fecha_inicio_formatted, $fecha_final_formatted, $ticket_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Fechas actualizadas correctamente',
        'fecha_inicio' => $fecha_inicio_formatted,
        'fecha_final' => $fecha_final_formatted
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>