<?php
header('Content-Type: application/json');
require_once '../models/Ticket.php';

try {
    $ticket = new Ticket();
    $colaboradores = $ticket->getColaboradoresDisponibles();
    
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