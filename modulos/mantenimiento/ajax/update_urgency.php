<?php

require_once '../models/Ticket.php';

header('Content-Type: application/json');


// Validar parámetros
if (!isset($_POST['ticket_id']) || !isset($_POST['nivel_urgencia'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'Parámetros requeridos faltantes'
    ]);
    exit;
}


$ticket_id = intval($_POST['ticket_id']);
$nivel_urgencia = intval($_POST['nivel_urgencia']);

// Validar nivel de urgencia (1-4)
if ($nivel_urgencia < 1 || $nivel_urgencia > 4) {
    echo json_encode([
        'success' => false, 
        'message' => 'Nivel de urgencia inválido. Debe ser entre 1 y 4'
    ]);
    exit;
}

try {
    $ticket_model = new Ticket();
    
    // Verificar que el ticket existe
    $ticket = $ticket_model->getById($ticket_id);
    if (!$ticket) {
        echo json_encode([
            'success' => false, 
            'message' => 'Ticket no encontrado'
        ]);
        exit;
    }
    
    // Actualizar nivel de urgencia
    $ticket_model->update($ticket_id, [
        'nivel_urgencia' => $nivel_urgencia
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Nivel de urgencia actualizado correctamente',
        'nivel_urgencia' => $nivel_urgencia
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error al actualizar: ' . $e->getMessage()
    ]);
}
?>