<?php
// ajax/detalles_eliminar_material_ticket.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$material_id = isset($_POST['material_id']) ? intval($_POST['material_id']) : 0;

if ($material_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de material inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $sql = "DELETE FROM mtto_tickets_materiales WHERE id = ?";
    $db->query($sql, [$material_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Material eliminado correctamente'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al eliminar material: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>