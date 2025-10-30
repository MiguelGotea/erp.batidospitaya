<?php
header('Content-Type: application/json');
session_start();
require_once '../models/Ticket.php';

if (!isset($_POST['ticket_id']) || !isset($_POST['cod_operario'])) {
    echo json_encode(['success' => false, 'message' => 'Parámetros incompletos']);
    exit;
}

try {
    $ticket = new Ticket();
    $asignado_por = $_SESSION['usuario_id'] ?? null;
    
    $ticket->asignarColaborador($_POST['ticket_id'], $_POST['cod_operario'], $asignado_por);
    
    echo json_encode(['success' => true, 'message' => 'Colaborador agregado']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>