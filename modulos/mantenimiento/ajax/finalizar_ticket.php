<?php
header('Content-Type: application/json');
require_once '../models/Ticket.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    if (!isset($_POST['id'])) {
        throw new Exception('ID de ticket requerido');
    }
    
    $ticket_id = intval($_POST['id']);
    $status = 'finalizado';
    
    $ticket = new Ticket();
    
    // Actualizar ticket con estado finalizado
    $data = [
        'status' => $status,
    ];
    
    $ticket->update($ticket_id, $data);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Ticket finalizado exitosamente',
        'status' => $status,
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>