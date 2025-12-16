<?php
// public_html/modulos/mantenimiento/ajax/equipos_dashboard_estadisticas.php
header('Content-Type: application/json');
require_once '../../../includes/auth.php';
require_once '../config/database.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $equipo_id = $input['equipo_id'] ?? 0;
    
    // Total mantenimientos
    $totalMtto = $db->fetchOne("
        SELECT COUNT(*) as total FROM mtto_equipos_mantenimientos 
        WHERE equipo_id = :equipo_id
    ", ['equipo_id' => $equipo_id]);
    
    // Mantenimientos por tipo
    $preventivos = $db->fetchOne("
        SELECT COUNT(*) as total FROM mtto_equipos_mantenimientos 
        WHERE equipo_id = :equipo_id AND tipo_mantenimiento = 'Preventivo'
    ", ['equipo_id' => $equipo_id]);
    
    $correctivos = $db->fetchOne("
        SELECT COUNT(*) as total FROM mtto_equipos_mantenimientos 
        WHERE equipo_id = :equipo_id AND tipo_mantenimiento = 'Correctivo'
    ", ['equipo_id' => $equipo_id]);
    
    // Total movimientos
    $movimientos = $db->fetchOne("
        SELECT COUNT(*) as total FROM mtto_equipos_movimientos 
        WHERE equipo_id = :equipo_id
    ", ['equipo_id' => $equipo_id]);
    
    // Costo total
    $costo = $db->fetchOne("
        SELECT COALESCE(SUM(costo_total), 0) as total 
        FROM mtto_equipos_mantenimientos 
        WHERE equipo_id = :equipo_id AND estado = 'Completado'
    ", ['equipo_id' => $equipo_id]);
    
    // Ubicación actual
    $ubicacion = $db->fetchOne("
        SELECT CASE 
            WHEN m.destino_tipo = 'Central' THEN 'Almacén Central'
            WHEN m.destino_tipo = 'Sucursal' THEN s.nombre
            WHEN m.destino_tipo = 'Proveedor' THEN CONCAT('Proveedor: ', m.proveedor_nombre)
            ELSE 'Sin ubicación'
        END as ubicacion
        FROM mtto_equipos_movimientos m
        LEFT JOIN sucursales s ON m.destino_id = s.codigo AND m.destino_tipo = 'Sucursal'
        WHERE m.equipo_id = :equipo_id 
            AND m.estado = 'Completado'
        ORDER BY m.fecha_ejecutada DESC, m.id DESC
        LIMIT 1
    ", ['equipo_id' => $equipo_id]);
    
    echo json_encode([
        'success' => true,
        'estadisticas' => [
            'total_mantenimientos' => $totalMtto['total'],
            'mantenimientos_preventivos' => $preventivos['total'],
            'mantenimientos_correctivos' => $correctivos['total'],
            'total_movimientos' => $movimientos['total'],
            'costo_total' => $costo['total'],
            'ubicacion_actual' => $ubicacion['ubicacion'] ?? 'Sin ubicación'
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

