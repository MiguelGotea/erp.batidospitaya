<?php
// public_html/modulos/mantenimiento/ajax/equipos_movimiento_ejecutar.php
header('Content-Type: application/json');
require_once '../../../includes/auth.php';
require_once '../config/database.php';

try {
    $movimiento_id = $_POST['movimiento_id'] ?? 0;
    $fecha_ejecutada = $_POST['fecha_ejecutada'] ?? '';
    $observaciones = $_POST['observaciones_ejecucion'] ?? '';
    
    if (empty($movimiento_id) || empty($fecha_ejecutada)) {
        throw new Exception('Faltan datos requeridos');
    }
    
    $db->getConnection()->beginTransaction();
    
    // Actualizar movimiento
    $sqlUpdate = "
        UPDATE mtto_equipos_movimientos 
        SET fecha_ejecutada = :fecha_ejecutada,
            estado = 'Completado',
            observaciones = CONCAT(COALESCE(observaciones, ''), ' ', :observaciones)
        WHERE id = :id
    ";
    
    $stmt = $db->getConnection()->prepare($sqlUpdate);
    $stmt->execute([
        'fecha_ejecutada' => $fecha_ejecutada,
        'observaciones' => $observaciones,
        'id' => $movimiento_id
    ]);
    
    $db->getConnection()->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Movimiento ejecutado exitosamente'
    ]);
    
} catch (Exception $e) {
    if ($db->getConnection()->inTransaction()) {
        $db->getConnection()->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

