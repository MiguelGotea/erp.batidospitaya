<?php
// ajax/historial_get_fotos.php
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
    $sql = "SELECT * FROM mtto_tickets_fotos WHERE ticket_id = ? ORDER BY orden ASC";
    $fotos = $db->fetchAll($sql, [$ticket_id]);
    
    echo json_encode([
        'success' => true,
        'fotos' => $fotos
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar fotos: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>