<?php
header('Content-Type: application/json');

require_once '../models/Ticket.php';

if (!isset($_GET['ticket_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de ticket requerido']);
    exit;
}

try {
    $ticket_model = new Ticket();
    $fotos = $ticket_model->getFotos($_GET['ticket_id']);
    
    echo json_encode([
        'success' => true,
        'fotos' => $fotos
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener fotos: ' . $e->getMessage()
    ]);
}
?>