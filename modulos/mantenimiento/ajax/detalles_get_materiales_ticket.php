<?php
// ajax/detalles_get_materiales_ticket.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ticket_id = isset($_GET['ticket_id']) ? intval($_GET['ticket_id']) : 0;

if ($ticket_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de ticket inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $sql = "SELECT tm.*, mf.nombre as material_nombre_ref
            FROM mtto_tickets_materiales tm
            LEFT JOIN mtto_materiales_frecuentes mf ON tm.material_id = mf.id
            WHERE tm.ticket_id = ?
            ORDER BY tm.created_at ASC";
    
    $materiales = $db->fetchAll($sql, [$ticket_id]);
    
    echo json_encode([
        'success' => true,
        'materiales' => $materiales
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar materiales: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>