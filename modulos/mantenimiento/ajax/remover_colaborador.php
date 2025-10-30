<?php
header('Content-Type: application/json');
require_once '../models/Ticket.php';

if (!isset($_POST['ticket_id']) || !isset($_POST['cod_operario'])) {
    echo json_encode(['success' => false, 'message' => 'Parámetros incompletos']);
    exit;
}

try {
    $ticket = new Ticket();
    $ticket->removerColaborador($_POST['ticket_id'], $_POST['cod_operario']);
    
    echo json_encode(['success' => true, 'message' => 'Colaborador removido']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>