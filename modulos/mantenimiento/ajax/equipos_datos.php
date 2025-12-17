<?php
require_once '../config/database.php';

header('Content-Type: application/json');

$accion = $_GET['accion'] ?? '';

try {
    switch ($accion) {
        case 'buscar':
            $termino = $_GET['termino'] ?? '';
            
            if (strlen($termino) < 2) {
                echo json_encode(['success' => true, 'data' => []]);
                exit;
            }
            
            $equipos = $db->fetchAll("
                SELECT 
                    e.id, e.codigo, e.marca, e.modelo,
                    (SELECT s.nombre 
                     FROM mtto_equipos_movimientos m 
                     INNER JOIN sucursales s ON m.sucursal_destino_id = s.id 
                     WHERE m.equipo_id = e.id AND m.estado = 'finalizado' 
                     ORDER BY m.fecha_realizada DESC LIMIT 1) as ubicacion_actual
                FROM mtto_equipos e
                WHERE e.activo = 1
                AND (e.codigo LIKE ? OR e.marca LIKE ? OR e.modelo LIKE ?)
                LIMIT 20
            ", ["%$termino%", "%$termino%", "%$termino%"]);
            
            echo json_encode(['success' => true, 'data' => $equipos]);
            break;
            
        case 'repuestos':
            $repuestos = $db->fetchAll("
                SELECT id, nombre, descripcion, costo_base, unidad_medida
                FROM mtto_equipos_repuestos
                WHERE activo = 1
                ORDER BY nombre
            ");
            
            echo json_encode(['success' => true, 'data' => $repuestos]);
            break;
            
        case 'proveedores':
            $proveedores = $db->fetchAll("
                SELECT id, nombre
                FROM proveedores_compras_servicios
                WHERE activo = 1
                ORDER BY nombre
            ");
            
            echo json_encode(['success' => true, 'data' => $proveedores]);
            break;
            
        case 'solicitudes_pendientes':
            $solicitudes = $db->fetchAll("
                SELECT 
                    s.id, s.descripcion_problema, s.fecha_solicitud,
                    e.codigo, e.marca, e.modelo,
                    suc.nombre as sucursal,
                    o.Nombre as solicitante_nombre, o.Apellido as solicitante_apellido
                FROM mtto_equipos_solicitudes s
                INNER JOIN mtto_equipos e ON s.equipo_id = e.id
                INNER JOIN sucursales suc ON s.sucursal_id = suc.id
                INNER JOIN Operarios o ON s.solicitado_por = o.CodOperario
                WHERE s.estado = 'solicitado'
                ORDER BY s.fecha_solicitud DESC
            ");
            
            echo json_encode(['success' => true, 'data' => $solicitudes]);
            break;
            
        case 'detalle_solicitud':
            $id = $_GET['id'] ?? 0;
            
            $solicitud = $db->fetchOne("
                SELECT 
                    s.*,
                    e.codigo, e.marca, e.modelo,
                    suc.nombre as sucursal,
                    o.Nombre as solicitante_nombre, o.Apellido as solicitante_apellido,
                    of.Nombre as finalizador_nombre, of.Apellido as finalizador_apellido
                FROM mtto_equipos_solicitudes s
                INNER JOIN mtto_equipos e ON s.equipo_id = e.id
                INNER JOIN sucursales suc ON s.sucursal_id = suc.id
                INNER JOIN Operarios o ON s.solicitado_por = o.CodOperario
                LEFT JOIN Operarios of ON s.finalizado_por = of.CodOperario
                WHERE s.id = ?
            ", [$id]);
            
            if ($solicitud) {
                $fotos = $db->fetchAll(
                    "SELECT ruta_archivo FROM mtto_equipos_solicitudes_fotos WHERE solicitud_id = ?",
                    [$id]
                );
                $solicitud['fotos'] = $fotos;
            }
            
            echo json_encode(['success' => true, 'data' => $solicitud]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>