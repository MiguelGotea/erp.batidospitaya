<?php
header('Content-Type: application/json');
session_start();
require_once '../models/Ticket.php';

if (!isset($_POST['ticket_id']) || !isset($_POST['colaboradores'])) {
    echo json_encode(['success' => false, 'message' => 'Parámetros incompletos']);
    exit;
}

try {
    $ticket = new Ticket();
    $ticket_id = $_POST['ticket_id'];
    $colaboradores = $_POST['colaboradores'];
    $asignado_por = $_SESSION['usuario_id'] ?? null;
    
    // Obtener colaboradores actuales
    $actuales = $ticket->getColaboradores($ticket_id);
    $actualesIds = array_column($actuales, 'cod_operario');
    
    // Determinar quién agregar y quién remover
    $nuevos = is_array($colaboradores) ? $colaboradores : [$colaboradores];
    $agregar = array_diff($nuevos, $actualesIds);
    $remover = array_diff($actualesIds, $nuevos);
    
    // Agregar nuevos colaboradores
    foreach ($agregar as $cod_operario) {
        if (!empty($cod_operario)) {
            $ticket->asignarColaborador($ticket_id, $cod_operario, $asignado_por);
        }
    }
    
    // Remover colaboradores
    foreach ($remover as $cod_operario) {
        $ticket->removerColaborador($ticket_id, $cod_operario);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Colaboradores actualizados',
        'agregados' => count($agregar),
        'removidos' => count($remover)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>