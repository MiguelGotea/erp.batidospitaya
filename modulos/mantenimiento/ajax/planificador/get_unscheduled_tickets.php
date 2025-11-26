<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Ticket.php';

header('Content-Type: application/json');

try {
    $ticket = new Ticket();
    
    // Obtener tickets sin programar
    $tickets = $ticket->getTicketsWithoutDates();
    
    // Obtener sucursales
    $sucursales = $ticket->getSucursales();
    
    echo json_encode([
        'tickets' => $tickets,
        'sucursales' => $sucursales
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>