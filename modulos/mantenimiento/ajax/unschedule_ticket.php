<?php
header('Content-Type: application/json');
require_once '../models/Ticket.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    if (!isset($_POST['ticket_id'])) {
        throw new Exception('ID de ticket requerido');
    }
    
    $ticket_id = intval($_POST['ticket_id']);
    
    if ($ticket_id <= 0) {
        throw new Exception('ID de ticket inválido');
    }
    
    // Verificar que el ticket existe
    $ticket = new Ticket();
    $ticket_data = $ticket->getById($ticket_id);
    
    if (!$ticket_data) {
        throw new Exception('Ticket no encontrado');
    }
    
    // No permitir desprogramar tickets finalizados
    if ($ticket_data['status'] === 'finalizado') {
        throw new Exception('No se pueden desprogramar tickets finalizados');
    }
    
    // Actualizar ticket: limpiar fechas y cambiar status a 'solicitado'
    $data = [
        'fecha_inicio' => null,
        'fecha_final' => null,
        'status' => 'solicitado'
    ];
    
    $ticket->update($ticket_id, $data);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Ticket desprogramado exitosamente',
        'ticket_codigo' => $ticket_data['codigo']
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>