<?php
// public_html/modulos/mantenimiento/ajax/equipos_movimiento_guardar.php
header('Content-Type: application/json');
require_once '../../../includes/auth.php';
require_once '../config/database.php';

try {
    $solicitud_id = $_POST['solicitud_id'] ?? null;
    $equipo_enviar_id = $_POST['equipo_enviar_id'] ?? 0;
    $fecha_movimiento = $_POST['fecha_movimiento'] ?? '';
    $observaciones = $_POST['observaciones_movimiento'] ?? '';
    $registrado_por = $_POST['registrado_por'] ?? 0;
    
    if (empty($equipo_enviar_id) || empty($fecha_movimiento)) {
        throw new Exception('Faltan datos requeridos');
    }
    
    $db->getConnection()->beginTransaction();
    
    // Obtener información de la solicitud
    $solicitud = null;
    $equipo_recoger_id = null;
    $sucursal_id = null;
    
    if ($solicitud_id) {
        $solicitud = $db->fetchOne("
            SELECT equipo_id, sucursal_id 
            FROM mtto_equipos_solicitudes 
            WHERE id = :id
        ", ['id' => $solicitud_id]);
        
        if ($solicitud) {
            $equipo_recoger_id = $solicitud['equipo_id'];
            $sucursal_id = $solicitud['sucursal_id'];
        }
    }
    
    if (!$sucursal_id) {
        throw new Exception('No se pudo determinar la sucursal');
    }
    
    // Movimiento 1: Enviar equipo operativo de Central a Sucursal
    $sqlMov1 = "
        INSERT INTO mtto_equipos_movimientos 
        (solicitud_id, equipo_id, tipo_movimiento, origen_tipo, origen_id, 
         destino_tipo, destino_id, fecha_planificada, estado, observaciones, registrado_por)
        VALUES 
        (:solicitud_id, :equipo_id, 'Central a Sucursal', 'Central', NULL,
         'Sucursal', :sucursal_id, :fecha_planificada, 'Planificado', :observaciones, :registrado_por)
    ";
    
    $stmtMov1 = $db->getConnection()->prepare($sqlMov1);
    $stmtMov1->execute([
        'solicitud_id' => $solicitud_id,
        'equipo_id' => $equipo_enviar_id,
        'sucursal_id' => $sucursal_id,
        'fecha_planificada' => $fecha_movimiento,
        'observaciones' => $observaciones,
        'registrado_por' => $registrado_por
    ]);
    
    // Movimiento 2: Recoger equipo dañado de Sucursal a Central (si aplica)
    if ($equipo_recoger_id) {
        $sqlMov2 = "
            INSERT INTO mtto_equipos_movimientos 
            (solicitud_id, equipo_id, tipo_movimiento, origen_tipo, origen_id, 
             destino_tipo, destino_id, fecha_planificada, estado, observaciones, registrado_por)
            VALUES 
            (:solicitud_id, :equipo_id, 'Sucursal a Central', 'Sucursal', :sucursal_id,
             'Central', NULL, :fecha_planificada, 'Planificado', :observaciones, :registrado_por)
        ";
        
        $stmtMov2 = $db->getConnection()->prepare($sqlMov2);
        $stmtMov2->execute([
            'solicitud_id' => $solicitud_id,
            'equipo_id' => $equipo_recoger_id,
            'sucursal_id' => $sucursal_id,
            'fecha_planificada' => $fecha_movimiento,
            'observaciones' => "Equipo a recoger para mantenimiento. " . $observaciones,
            'registrado_por' => $registrado_por
        ]);
    }
    
    // Actualizar estado de solicitud si existe
    if ($solicitud_id) {
        $sqlUpdateSol = "
            UPDATE mtto_equipos_solicitudes 
            SET estado = 'Agendado', fecha_atencion = :fecha_atencion
            WHERE id = :id
        ";
        
        $stmtUpdateSol = $db->getConnection()->prepare($sqlUpdateSol);
        $stmtUpdateSol->execute([
            'fecha_atencion' => $fecha_movimiento,
            'id' => $solicitud_id
        ]);
    }
    
    $db->getConnection()->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Movimiento agendado exitosamente'
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