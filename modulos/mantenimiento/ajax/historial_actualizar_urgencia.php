<?php
// ajax/historial_actualizar_urgencia.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
$nivel_urgencia = isset($_POST['nivel_urgencia']) ? intval($_POST['nivel_urgencia']) : 0;

if ($ticket_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de ticket inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($nivel_urgencia < 0 || $nivel_urgencia > 4) {
    echo json_encode(['success' => false, 'message' => 'Nivel de urgencia inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $sql = "UPDATE mtto_tickets SET nivel_urgencia = ? WHERE id = ?";
    $db->query($sql, [$nivel_urgencia, $ticket_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Urgencia actualizada correctamente'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar urgencia: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>