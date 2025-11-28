<?php
// ajax/agenda_update_colaborador.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$colaborador_id = isset($_POST['colaborador_id']) ? intval($_POST['colaborador_id']) : 0;
$cod_operario = isset($_POST['cod_operario']) ? intval($_POST['cod_operario']) : null;

if ($colaborador_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de colaborador inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $sql = "UPDATE mtto_tickets_colaboradores SET cod_operario = ? WHERE id = ?";
    $db->query($sql, [$cod_operario, $colaborador_id]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Colaborador actualizado correctamente'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error al actualizar colaborador: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>