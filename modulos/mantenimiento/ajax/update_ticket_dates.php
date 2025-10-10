<?php
header('Content-Type: application/json');
require_once '../models/Ticket.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    if (!isset($_POST['id']) || !isset($_POST['fecha_inicio']) || !isset($_POST['fecha_final'])) {
        throw new Exception('Datos requeridos faltantes');
    }
    
    $ticket_id = intval($_POST['id']);
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_final = $_POST['fecha_final'];
    
    // Validaciones
    if (!strtotime($fecha_inicio) || !strtotime($fecha_final)) {
        throw new Exception('Fechas inválidas');
    }
    
    if ($fecha_inicio > $fecha_final) {
        throw new Exception('La fecha de inicio no puede ser mayor a la fecha final');
    }
    
    $ticket = new Ticket();
    
    // Actualizar solo las fechas, mantener status si ya está agendado
    $data = [
        'fecha_inicio' => $fecha_inicio,
        'fecha_final' => $fecha_final
    ];
    
    $ticket->update($ticket_id, $data);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Fechas actualizadas exitosamente',
        'fecha_inicio' => $fecha_inicio,
        'fecha_final' => $fecha_final
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>