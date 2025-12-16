<?php
// public_html/modulos/mantenimiento/ajax/equipos_dashboard_historial_mov.php
header('Content-Type: application/json');
require_once '../../../includes/auth.php';
require_once '../config/database.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $equipo_id = $input['equipo_id'] ?? 0;
    
    $movimientos = $db->fetchAll("
        SELECT 
            m.tipo_movimiento,
            DATE_FORMAT(m.fecha_planificada, '%d/%m/%Y') as fecha_planificada,
            DATE_FORMAT(m.fecha_ejecutada, '%d/%m/%Y') as fecha_ejecutada,
            m.estado,
            m.observaciones,
            CASE 
                WHEN m.origen_tipo = 'Central' THEN 'Almacén Central'
                WHEN m.origen_tipo = 'Sucursal' THEN so.nombre
                WHEN m.origen_tipo = 'Proveedor' THEN CONCAT('Proveedor: ', m.proveedor_nombre)
            END as origen,
            CASE 
                WHEN m.destino_tipo = 'Central' THEN 'Almacén Central'
                WHEN m.destino_tipo = 'Sucursal' THEN sd.nombre
                WHEN m.destino_tipo = 'Proveedor' THEN CONCAT('Proveedor: ', m.proveedor_nombre)
            END as destino
        FROM mtto_equipos_movimientos m
        LEFT JOIN sucursales so ON m.origen_id = so.codigo AND m.origen_tipo = 'Sucursal'
        LEFT JOIN sucursales sd ON m.destino_id = sd.codigo AND m.destino_tipo = 'Sucursal'
        WHERE m.equipo_id = :equipo_id
        ORDER BY 
            COALESCE(m.fecha_ejecutada, m.fecha_planificada) DESC,
            m.id DESC
        LIMIT 50
    ", ['equipo_id' => $equipo_id]);
    
    echo json_encode([
        'success' => true,
        'movimientos' => $movimientos
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>