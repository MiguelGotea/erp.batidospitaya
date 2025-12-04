<?php
// ajax/detalles_delete_colaborador.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$colaborador_id = isset($_POST['colaborador_id']) ? intval($_POST['colaborador_id']) : 0;

if ($colaborador_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de colaborador inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $sql = "DELETE FROM mtto_tickets_colaboradores WHERE id = ?";
    $db->query($sql, [$colaborador_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Colaborador eliminado correctamente'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al eliminar colaborador: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>