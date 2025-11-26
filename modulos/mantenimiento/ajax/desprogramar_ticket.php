<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    global $db;
    
    $ticketId = $_POST['ticket_id'] ?? null;
    
    if (!$ticketId) {
        throw new Exception('Falta el ID del ticket');
    }
    
    // Limpiar fechas y cambiar status a solicitado
    $db->query(
        "UPDATE mtto_tickets 
         SET fecha_inicio = NULL, 
             fecha_final = NULL,
             status = 'solicitado'
         WHERE id = ?",
        [$ticketId]
    );
    
    // Eliminar todos los colaboradores asignados
    $db->query(
        "DELETE FROM mtto_tickets_colaboradores WHERE ticket_id = ?",
        [$ticketId]
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Ticket desprogramado correctamente'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}