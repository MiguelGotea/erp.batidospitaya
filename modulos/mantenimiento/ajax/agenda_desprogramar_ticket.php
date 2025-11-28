<?php
// ajax/agenda_desprogramar_ticket.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;

if ($ticket_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de ticket inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $conn = $db->getConnection();
    $conn->beginTransaction();
    
    // Eliminar todos los colaboradores asignados
    $stmt = $conn->prepare("DELETE FROM mtto_tickets_colaboradores WHERE ticket_id = ?");
    $stmt->execute([$ticket_id]);
    
    // Limpiar fechas y cambiar status a solicitado
    $stmt = $conn->prepare("UPDATE mtto_tickets 
                           SET fecha_inicio = NULL, 
                               fecha_final = NULL, 
                               status = 'solicitado' 
                           WHERE id = ?");
    $stmt->execute([$ticket_id]);
    
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Solicitud desprogramada correctamente'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false, 
        'message' => 'Error al desprogramar: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>