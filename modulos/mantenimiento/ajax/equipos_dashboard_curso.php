<?php
// public_html/modulos/mantenimiento/ajax/equipos_dashboard_curso.php
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
            proveedor_servicio,
            estado,
            observaciones
        FROM mtto_equipos_mantenimientos
        WHERE equipo_id = :equipo_id 
            AND estado IN ('Programado', 'En Proceso')
        ORDER BY fecha_programada ASC
    ", ['equipo_id' => $equipo_id]);
    
    echo json_encode([
        'success' => true,
        'mantenimientos' => $mantenimientos
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
