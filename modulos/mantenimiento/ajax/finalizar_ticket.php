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
    $fecha_final = $_POST['fecha_final'] ?? date('Y-m-d');
    $fecha_inicio = $_POST['fecha_inicio'] ?? $fecha_final;
    
    // Validar fechas
    if (!strtotime($fecha_inicio) || !strtotime($fecha_final)) {
        throw new Exception('Fechas inválidas');
    }
    
    if ($fecha_inicio > $fecha_final) {
        throw new Exception('La fecha de inicio no puede ser mayor a la fecha final');
    }
    
    $ticket = new Ticket();
    
    // Actualizar ticket con estado finalizado
    $data = [
        'status' => $status,
        'fecha_inicio' => $fecha_inicio,
        'fecha_final' => $fecha_final
    ];
    
    $ticket->update($ticket_id, $data);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Ticket finalizado exitosamente',
        'status' => $status,
        'fecha_final' => $fecha_final
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>