<?php
// ajax/detalles_crear_material_frecuente.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';

if (empty($nombre)) {
    echo json_encode(['success' => false, 'message' => 'Nombre de material requerido'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Verificar si ya existe
    $sql = "SELECT id FROM mtto_materiales_frecuentes WHERE nombre = ?";
    $existe = $db->fetchOne($sql, [$nombre]);
    
    if ($existe) {
        echo json_encode([
            'success' => true,
            'material' => $existe
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Crear nuevo material
    $sql = "INSERT INTO mtto_materiales_frecuentes (nombre) VALUES (?)";
    $db->query($sql, [$nombre]);
    
    $materialId = $db->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'material' => [
            'id' => $materialId,
            'nombre' => $nombre
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al crear material: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>