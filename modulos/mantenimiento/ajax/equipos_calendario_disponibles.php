<?php
// public_html/modulos/mantenimiento/ajax/equipos_calendario_disponibles.php
header('Content-Type: application/json');
require_once '../../../includes/auth.php';
require_once '../config/database.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $mes = $input['mes'] ?? date('n');
    $anio = $input['anio'] ?? date('Y');
    
    $primerDia = "$anio-" . str_pad($mes, 2, '0', STR_PAD_LEFT) . "-01";
    $ultimoDia = date("Y-m-t", strtotime($primerDia));
    
    // Equipos con mantenimiento preventivo sugerido este mes
    $preventivos = $db->fetchAll("
        SELECT DISTINCT
            e.id as equipo_id,
            e.codigo,
            e.nombre,
            e.frecuencia_mantenimiento_meses,
            'Preventivo' as tipo,
            0 as retrasado,
            CASE 
                WHEN m.destino_tipo = 'Central' THEN 'Almacén Central'
                WHEN m.destino_tipo = 'Sucursal' THEN s.nombre
                ELSE 'Sin ubicación'
            END as ubicacion,
            CASE WHEN m.destino_tipo = 'Sucursal' THEN 1 ELSE 0 END as en_sucursal,
            m.destino_id as sucursal_id,
            NULL as solicitud_id
        FROM mtto_equipos e
        LEFT JOIN (
            SELECT m1.*
            FROM mtto_equipos_movimientos m1
            INNER JOIN (
                SELECT equipo_id, MAX(id) as max_id
                FROM mtto_equipos_movimientos
                WHERE estado = 'Completado'
                GROUP BY equipo_id
            ) m2 ON m1.id = m2.max_id
        ) m ON e.id = m.equipo_id
        LEFT JOIN sucursales s ON m.destino_id = s.id AND m.destino_tipo = 'Sucursal'
        WHERE e.activo = 1
            AND NOT EXISTS (
                SELECT 1 FROM mtto_equipos_mantenimientos mt
                WHERE mt.equipo_id = e.id 
                    AND mt.estado IN ('Programado', 'En Proceso')
            )
            AND (
                SELECT DATE_ADD(MAX(mt2.fecha_realizada), 
                    INTERVAL e.frecuencia_mantenimiento_meses MONTH)
                FROM mtto_equipos_mantenimientos mt2
                WHERE mt2.equipo_id = e.id 
                    AND mt2.estado = 'Completado'
                    AND mt2.fecha_realizada IS NOT NULL
            ) BETWEEN :primer_dia AND :ultimo_dia
    ", ['primer_dia' => $primerDia, 'ultimo_dia' => $ultimoDia]);
    
    // Equipos retrasados
    $retrasados = $db->fetchAll("
        SELECT DISTINCT
            e.id as equipo_id,
            e.codigo,
            e.nombre,
            'Preventivo' as tipo,
            1 as retrasado,
            CASE 
                WHEN m.destino_tipo = 'Central' THEN 'Almacén Central'
                WHEN m.destino_tipo = 'Sucursal' THEN s.nombre
                ELSE 'Sin ubicación'
            END as ubicacion,
            CASE WHEN m.destino_tipo = 'Sucursal' THEN 1 ELSE 0 END as en_sucursal,
            m.destino_id as sucursal_id,
            NULL as solicitud_id
        FROM mtto_equipos e
        LEFT JOIN (
            SELECT m1.*
            FROM mtto_equipos_movimientos m1
            INNER JOIN (
                SELECT equipo_id, MAX(id) as max_id
                FROM mtto_equipos_movimientos
                WHERE estado = 'Completado'
                GROUP BY equipo_id
            ) m2 ON m1.id = m2.max_id
        ) m ON e.id = m.equipo_id
        LEFT JOIN sucursales s ON m.destino_id = s.id AND m.destino_tipo = 'Sucursal'
        WHERE e.activo = 1
            AND NOT EXISTS (
                SELECT 1 FROM mtto_equipos_mantenimientos mt
                WHERE mt.equipo_id = e.id 
                    AND mt.estado IN ('Programado', 'En Proceso')
            )
            AND (
                SELECT DATE_ADD(MAX(mt2.fecha_realizada), 
                    INTERVAL e.frecuencia_mantenimiento_meses MONTH)
                FROM mtto_equipos_mantenimientos mt2
                WHERE mt2.equipo_id = e.id 
                    AND mt2.estado = 'Completado'
                    AND mt2.fecha_realizada IS NOT NULL
            ) < :primer_dia
    ", ['primer_dia' => $primerDia]);
    
    // Solicitudes de mantenimiento correctivo
    $correctivos = $db->fetchAll("
        SELECT DISTINCT
            e.id as equipo_id,
            e.codigo,
            e.nombre,
            'Correctivo' as tipo,
            0 as retrasado,
            s.nombre as ubicacion,
            1 as en_sucursal,
            sol.sucursal_id,
            sol.id as solicitud_id
        FROM mtto_equipos_solicitudes sol
        INNER JOIN mtto_equipos e ON sol.equipo_id = e.id
        INNER JOIN sucursales s ON sol.sucursal_id = s.id
        WHERE sol.estado IN ('Solicitado', 'Agendado')
            AND e.activo = 1
    ");
    
    $equipos = array_merge($retrasados, $correctivos, $preventivos);
    
    echo json_encode([
        'success' => true,
        'equipos' => $equipos
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>