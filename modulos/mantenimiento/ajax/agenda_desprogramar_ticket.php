<?php
// ajax/agenda_desprogramar_ticket.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$ticketId = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;

if (!$ticketId) {
    echo json_encode(['error' => 'ID de ticket no proporcionado'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db->getConnection()->beginTransaction();
    
    // Limpiar fechas y cambiar status a solicitado
    $stmt = $db->query("
        UPDATE mtto_tickets 
        SET fecha_inicio = NULL, 
            fecha_final = NULL,
            status = 'solicitado'
        WHERE id = ?
    ", [$ticketId]);
    
    // Eliminar todos los colaboradores asignados
    $db->query("DELETE FROM mtto_tickets_colaboradores WHERE ticket_id = ?", [$ticketId]);
    
    $db->getConnection()->commit();
    
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    $db->getConnection()->rollBack();
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>