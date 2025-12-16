<?php
// public_html/modulos/mantenimiento/ajax/equipos_listar.php
header('Content-Type: application/json');
require_once '../../../includes/auth.php';
require_once '../config/database.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $cargo = $input['cargo'] ?? 0;
    $sucursal = $input['sucursal'] ?? '';
    
    $esLiderInfraestructura = ($cargo == 35);
    
    // Consulta base
    $sql = "
        SELECT 
            e.id,
            e.codigo,
            e.nombre,
            e.marca,
            e.modelo,
            e.serial,
            e.frecuencia_mantenimiento_meses,
            et.nombre as tipo_nombre,
            
            -- Ubicación actual (calculada desde último movimiento)
            (
                SELECT CASE 
                    WHEN m.destino_tipo = 'Central' THEN 'Almacén Central'
                    WHEN m.destino_tipo = 'Sucursal' THEN s.nombre
                    WHEN m.destino_tipo = 'Proveedor' THEN CONCAT('Proveedor: ', m.proveedor_nombre)
                    ELSE 'Sin ubicación'
                END
                FROM mtto_equipos_movimientos m
                LEFT JOIN sucursales s ON m.destino_id = s.id AND m.destino_tipo = 'Sucursal'
                WHERE m.equipo_id = e.id 
                    AND m.estado = 'Completado'
                ORDER BY m.fecha_ejecutada DESC, m.id DESC
                LIMIT 1
            ) as ubicacion_actual,
            
            -- Último mantenimiento
            (
                SELECT DATE_FORMAT(mt.fecha_realizada, '%d/%m/%Y')
                FROM mtto_equipos_mantenimientos mt
                WHERE mt.equipo_id = e.id 
                    AND mt.estado = 'Completado'
                    AND mt.fecha_realizada IS NOT NULL
                ORDER BY mt.fecha_realizada DESC
                LIMIT 1
            ) as ultimo_mantenimiento,
            
            -- Verificar si está en mantenimiento
            (
                SELECT COUNT(*)
                FROM mtto_equipos_mantenimientos mt
                WHERE mt.equipo_id = e.id 
                    AND mt.estado IN ('Programado', 'En Proceso')
            ) as en_mantenimiento,
            
            -- Verificar si tiene solicitud pendiente
            (
                SELECT COUNT(*)
                FROM mtto_equipos_solicitudes sol
                WHERE sol.equipo_id = e.id 
                    AND sol.estado IN ('Solicitado', 'Agendado')
            ) as solicitud_pendiente,
            
            -- Fecha de movimiento agendado
            (
                SELECT DATE_FORMAT(sol.fecha_atencion, '%d/%m/%Y')
                FROM mtto_equipos_solicitudes sol
                WHERE sol.equipo_id = e.id 
                    AND sol.estado = 'Agendado'
                    AND sol.fecha_atencion IS NOT NULL
                ORDER BY sol.fecha_atencion ASC
                LIMIT 1
            ) as fecha_movimiento
            
        FROM mtto_equipos e
        INNER JOIN mtto_equipos_tipos et ON e.tipo_id = et.id
        WHERE e.activo = 1
    ";
    
    // Si no es líder de infraestructura, filtrar por sucursal
    if (!$esLiderInfraestructura) {
        $sql .= "
            AND (
                SELECT CASE 
                    WHEN m.destino_tipo = 'Sucursal' THEN s.codigo
                    ELSE NULL
                END
                FROM mtto_equipos_movimientos m
                LEFT JOIN sucursales s ON m.destino_id = s.id
                WHERE m.equipo_id = e.id 
                    AND m.estado = 'Completado'
                ORDER BY m.fecha_ejecutada DESC, m.id DESC
                LIMIT 1
            ) = :sucursal
        ";
    }
    
    $sql .= " ORDER BY e.codigo ASC";
    
    $stmt = $db->getConnection()->prepare($sql);
    
    if (!$esLiderInfraestructura) {
        $stmt->bindParam(':sucursal', $sucursal);
    }
    
    $stmt->execute();
    $equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convertir valores numéricos
    foreach ($equipos as &$equipo) {
        $equipo['en_mantenimiento'] = (int)$equipo['en_mantenimiento'] > 0;
        $equipo['solicitud_pendiente'] = (int)$equipo['solicitud_pendiente'] > 0;
    }
    
    echo json_encode([
        'success' => true,
        'equipos' => $equipos
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar equipos: ' . $e->getMessage()
    ]);
}