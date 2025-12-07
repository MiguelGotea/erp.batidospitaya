<?php
// ajax/detalles_get_materiales_frecuentes.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $sql = "SELECT * FROM mtto_materiales_frecuentes ORDER BY nombre ASC";
    $materiales = $db->fetchAll($sql);
    
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