<?php
// public_html/modulos/mantenimiento/ajax/equipos_dashboard_historial_mtto.php
header('Content-Type: application/json');
require_once '../../../includes/auth.php';
require_once '../config/database.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $equipo_id = $input['equipo_id'] ?? 0;
    
    $mantenimientos = $db->fetchAll("
        SELECT 
            tipo_mantenimiento,
            DATE_FORMAT(fecha_programada, '%d/%m/%Y') as fecha_programada,
            DATE_FORMAT(fecha_realizada, '%d/%m/%Y') as fecha_realizada,
            proveedor_servicio,
            trabajo_realizado,
            costo_total,
            estado
        FROM mtto_equipos_mantenimientos
        WHERE equipo_id = :equipo_id
        ORDER BY 
            COALESCE(fecha_realizada, fecha_programada) DESC,
            id DESC
        LIMIT 50
    ", ['equipo_id' => $equipo_id]);
    
    echo json_encode([
        'success' => true,
        'mantenimientos' => $mantenimientos
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>