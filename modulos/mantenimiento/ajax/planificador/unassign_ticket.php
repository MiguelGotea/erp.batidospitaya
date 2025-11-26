<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Ticket.php';

header('Content-Type: application/json');

try {
    global $db;
    $ticket = new Ticket();
    
    $ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
    
    if (!$ticket_id) {
        throw new Exception('ID de ticket no válido');
    }
    
    // Iniciar transacción
    $db->getConnection()->beginTransaction();
    
    // Eliminar fechas del ticket
    $sql = "UPDATE mtto_tickets 
            SET fecha_inicio = NULL, 
                fecha_final = NULL, 
                status = 'clasificado' 
            WHERE id = ?";
    $db->query($sql, [$ticket_id]);
    
    // Eliminar colaboradores
    $sql = "DELETE FROM mtto_tickets_colaboradores WHERE ticket_id = ?";
    $db->query($sql, [$ticket_id]);
    
    $db->getConnection()->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Solicitud desprogramada correctamente'
    ]);
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->getConnection()->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>