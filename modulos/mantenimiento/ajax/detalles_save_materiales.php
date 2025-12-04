<?php
// ajax/detalles_save_materiales.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
$materiales = isset($_POST['materiales']) ? json_decode($_POST['materiales'], true) : [];

if ($ticket_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de ticket inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $conn = $db->getConnection();
    $conn->beginTransaction();
    
    // Eliminar materiales anteriores
    $sql_delete = "DELETE FROM mtto_tickets_materiales WHERE ticket_id = ?";
    $db->query($sql_delete, [$ticket_id]);
    
    // Insertar nuevos materiales
    $sql_insert = "INSERT INTO mtto_tickets_materiales 
                   (ticket_id, material_id, material_nombre, detalle, procedencia) 
                   VALUES (?, ?, ?, ?, ?)";
    
    foreach ($materiales as $material) {
        $material_id = isset($material['material_id']) ? intval($material['material_id']) : null;
        $material_nombre = $material['nombre'];
        $detalle = $material['detalle'] ?? null;
        $procedencia = $material['procedencia'] ?? null;
        
        // Si es material nuevo (no frecuente), agregarlo a frecuentes
        if (!$material_id && !empty($material_nombre)) {
            $sql_check = "SELECT id FROM mtto_materiales_frecuentes WHERE nombre = ?";
            $existe = $db->fetchOne($sql_check, [$material_nombre]);
            
            if (!$existe) {
                $sql_nuevo = "INSERT INTO mtto_materiales_frecuentes (nombre) VALUES (?)";
                $db->query($sql_nuevo, [$material_nombre]);
                $material_id = $db->lastInsertId();
            } else {
                $material_id = $existe['id'];
            }
        }
        
        $db->query($sql_insert, [
            $ticket_id,
            $material_id,
            $material_nombre,
            $detalle,
            $procedencia
        ]);
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Materiales guardados correctamente'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar materiales: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>