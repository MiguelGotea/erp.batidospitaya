<?php
header('Content-Type: application/json');
require_once '../models/Ticket.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    if (!isset($_POST['ticket_ids']) || !isset($_POST['fecha_inicio']) || !isset($_POST['fecha_final'])) {
        throw new Exception('Datos requeridos faltantes');
    }
    
    $ticket_ids = $_POST['ticket_ids'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_final = $_POST['fecha_final'];
    
    // Validaciones
    if (!is_array($ticket_ids) || empty($ticket_ids)) {
        throw new Exception('Debe seleccionar al menos un ticket');
    }
    
    if (!strtotime($fecha_inicio) || !strtotime($fecha_final)) {
        throw new Exception('Fechas inválidas');
    }
    
    if ($fecha_inicio > $fecha_final) {
        throw new Exception('La fecha de inicio no puede ser mayor a la fecha final');
    }
    
    // Validar que todos los IDs sean numéricos
    foreach ($ticket_ids as $id) {
        if (!is_numeric($id)) {
            throw new Exception('IDs de ticket inválidos');
        }
    }
    
    $ticket = new Ticket();
    $ticket->updateBulkDates($ticket_ids, $fecha_inicio, $fecha_final);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Fechas asignadas exitosamente a ' . count($ticket_ids) . ' tickets'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>