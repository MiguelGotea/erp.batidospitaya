<?php
header('Content-Type: application/json');
require_once '../models/Ticket.php';

if (!isset($_GET['ticket_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID requerido']);
    exit;
}

try {
    $ticket = new Ticket();
    $colaboradores = $ticket->getColaboradores($_GET['ticket_id']);
    
    echo json_encode([
        'success' => true,
        'colaboradores' => $colaboradores
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>