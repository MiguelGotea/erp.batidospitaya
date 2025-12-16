<?php
// public_html/modulos/mantenimiento/ajax/equipos_movimientos_listar.php
header('Content-Type: application/json');
require_once '../../../includes/auth.php';
require_once '../config/database.php';

try {
    $movimientos = $db->fetchAll("
        SELECT 
            m.id,
            m.equipo_id,
            e.codigo as equipo_codigo,
            e.nombre as equipo_nombre,
            m.tipo_movimiento,
            CASE 
                WHEN m.origen_tipo = 'Central' THEN 'Almacén Central'
                WHEN m.origen_tipo = 'Sucursal' THEN so.nombre
                WHEN m.origen_tipo = 'Proveedor' THEN CONCAT('Proveedor: ', m.proveedor_nombre)
            END as origen,
            CASE 
                WHEN m.destino_tipo = 'Central' THEN 'Almacén Central'
                WHEN m.destino_tipo = 'Sucursal' THEN sd.nombre
                WHEN m.destino_tipo = 'Proveedor' THEN CONCAT('Proveedor: ', m.proveedor_nombre)
            END as destino,
            DATE_FORMAT(m.fecha_planificada, '%d/%m/%Y') as fecha_planificada,
            m.estado,
            m.observaciones
        FROM mtto_equipos_movimientos m
        INNER JOIN mtto_equipos e ON m.equipo_id = e.id
        LEFT JOIN sucursales so ON m.origen_id = so.id AND m.origen_tipo = 'Sucursal'
        LEFT JOIN sucursales sd ON m.destino_id = sd.id AND m.destino_tipo = 'Sucursal'
        WHERE m.estado IN ('Planificado', 'En Tránsito')
        ORDER BY m.fecha_planificada ASC, m.id ASC
    ");
    
    echo json_encode([
        'success' => true,
        'movimientos' => $movimientos
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

