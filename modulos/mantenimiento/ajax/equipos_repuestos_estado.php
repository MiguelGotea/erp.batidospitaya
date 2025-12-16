<?php
// public_html/modulos/mantenimiento/ajax/equipos_repuestos_estado.php
header('Content-Type: application/json');
require_once '../../../includes/auth.php';
require_once '../config/database.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $repuesto_id = $input['repuesto_id'] ?? 0;
    $activo = $input['activo'] ?? 1;
    
    if (empty($repuesto_id)) {
        throw new Exception('ID de repuesto requerido');
    }
    
    $sql = "
        UPDATE mtto_equipos_repuestos 
        SET activo = :activo
        WHERE id = :id
    ";
    
    $stmt = $db->getConnection()->prepare($sql);
    $stmt->execute([
        'activo' => $activo,
        'id' => $repuesto_id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => $activo ? 'Repuesto activado' : 'Repuesto desactivado'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>