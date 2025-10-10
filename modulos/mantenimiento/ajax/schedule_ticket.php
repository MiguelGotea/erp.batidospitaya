<?php
header('Content-Type: application/json');
require_once '../models/Ticket.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    if (!isset($_POST['ticket_id']) || !isset($_POST['fecha_inicio']) || !isset($_POST['fecha_final'])) {
        throw new Exception('Datos requeridos faltantes');
    }
    
    $ticket_id = intval($_POST['ticket_id']);
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_final = $_POST['fecha_final'];
    
    // Validaciones
    if (!strtotime($fecha_inicio) || !strtotime($fecha_final)) {
        throw new Exception('Fechas inválidas');
    }
    
    if ($fecha_inicio > $fecha_final) {
        throw new Exception('La fecha de inicio no puede ser mayor a la fecha final');
    }
    
    // Verificar que el ticket existe
    $ticket = new Ticket();
    $ticket_data = $ticket->getById($ticket_id);
    
    if (!$ticket_data) {
        throw new Exception('Ticket no encontrado');
    }
    
    // Actualizar ticket con las fechas y cambiar status a 'agendado'
    $data = [
        'fecha_inicio' => $fecha_inicio,
        'fecha_final' => $fecha_final,
        'status' => 'agendado'
    ];
    
    $ticket->update($ticket_id, $data);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Ticket programado exitosamente',
        'ticket_codigo' => $ticket_data['codigo'],
        'fecha_inicio' => $fecha_inicio,
        'fecha_final' => $fecha_final
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>