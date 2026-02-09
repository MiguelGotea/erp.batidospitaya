<?php
// ajax/historial_actualizar_tiempo.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
$tiempo_estimado = isset($_POST['tiempo_estimado']) ? intval($_POST['tiempo_estimado']) : 0;

if ($ticket_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de ticket inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($tiempo_estimado < 0) {
    echo json_encode(['success' => false, 'message' => 'El tiempo estimado no puede ser negativo'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $sql = "UPDATE mtto_tickets SET tiempo_estimado = ? WHERE id = ?";
    $db->query($sql, [$tiempo_estimado, $ticket_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Tiempo estimado actualizado correctamente'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar tiempo estimado: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>