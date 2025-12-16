<?php
// public_html/modulos/mantenimiento/ajax/equipos_reporte_guardar.php
header('Content-Type: application/json');
require_once '../../../includes/auth.php';
require_once '../config/database.php';

try {
    $mantenimiento_id = $_POST['mantenimiento_id'] ?? 0;
    $fecha_realizada = $_POST['fecha_realizada'] ?? '';
    $proveedor_servicio = $_POST['proveedor_servicio'] ?? '';
    $problema_encontrado = $_POST['problema_encontrado'] ?? '';
    $trabajo_realizado = $_POST['trabajo_realizado'] ?? '';
    $costo_mano_obra = $_POST['costo_mano_obra'] ?? 0;
    $costo_total = $_POST['costo_total'] ?? 0;
    $observaciones = $_POST['observaciones'] ?? '';
    $registrado_por = $_POST['registrado_por'] ?? 0;
    $repuestos = json_decode($_POST['repuestos'] ?? '[]', true);
    
    if (empty($mantenimiento_id) || empty($fecha_realizada) || empty($proveedor_servicio) || 
        empty($problema_encontrado) || empty($trabajo_realizado)) {
        throw new Exception('Faltan datos requeridos');
    }
    
    $db->getConnection()->beginTransaction();
    
    // Actualizar mantenimiento
    $sqlUpdate = "
        UPDATE mtto_equipos_mantenimientos 
        SET fecha_realizada = :fecha_realizada,
            proveedor_servicio = :proveedor_servicio,
            problema_encontrado = :problema_encontrado,
            trabajo_realizado = :trabajo_realizado,
            costo_mano_obra = :costo_mano_obra,
            costo_total = :costo_total,
            observaciones = :observaciones,
            estado = 'Completado'
        WHERE id = :id
    ";
    
    $stmt = $db->getConnection()->prepare($sqlUpdate);
    $stmt->execute([
        'fecha_realizada' => $fecha_realizada,
        'proveedor_servicio' => $proveedor_servicio,
        'problema_encontrado' => $problema_encontrado,
        'trabajo_realizado' => $trabajo_realizado,
        'costo_mano_obra' => $costo_mano_obra,
        'costo_total' => $costo_total,
        'observaciones' => $observaciones,
        'id' => $mantenimiento_id
    ]);
    
    // Insertar repuestos
    foreach ($repuestos as $repuesto) {
        if (empty($repuesto['repuesto_id'])) continue;
        
        $cantidad = floatval($repuesto['cantidad']);
        $costoUnitario = floatval($repuesto['costo_unitario_real']);
        $costoTotal = $cantidad * $costoUnitario;
        
        $sqlRepuesto = "
            INSERT INTO mtto_equipos_mantenimientos_repuestos 
            (mantenimiento_id, repuesto_id, cantidad, costo_unitario_real, costo_total, observaciones)
            VALUES 
            (:mantenimiento_id, :repuesto_id, :cantidad, :costo_unitario_real, :costo_total, :observaciones)
        ";
        
        $stmtRepuesto = $db->getConnection()->prepare($sqlRepuesto);
        $stmtRepuesto->execute([
            'mantenimiento_id' => $mantenimiento_id,
            'repuesto_id' => $repuesto['repuesto_id'],
            'cantidad' => $cantidad,
            'costo_unitario_real' => $costoUnitario,
            'costo_total' => $costoTotal,
            'observaciones' => $repuesto['observaciones'] ?? null
        ]);
    }
    
    // Procesar archivos
    if (!empty($_FILES['archivos']['name'][0])) {
        $uploadDir = '../uploads/mantenimientos/' . $mantenimiento_id . '/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        foreach ($_FILES['archivos']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['archivos']['error'][$key] == 0) {
                $nombreOriginal = $_FILES['archivos']['name'][$key];
                $extension = pathinfo($nombreOriginal, PATHINFO_EXTENSION);
                $nombreUnico = uniqid() . '_' . time() . '.' . $extension;
                $rutaDestino = $uploadDir . $nombreUnico;
                
                if (move_uploaded_file($tmp_name, $rutaDestino)) {
                    $sqlArchivo = "
                        INSERT INTO mtto_equipos_mantenimientos_archivos 
                        (mantenimiento_id, nombre_archivo, ruta_archivo, tipo_archivo, tamanio)
                        VALUES 
                        (:mantenimiento_id, :nombre_archivo, :ruta_archivo, :tipo_archivo, :tamanio)
                    ";
                    
                    $stmtArchivo = $db->getConnection()->prepare($sqlArchivo);
                    $stmtArchivo->execute([
                        'mantenimiento_id' => $mantenimiento_id,
                        'nombre_archivo' => $nombreOriginal,
                        'ruta_archivo' => $rutaDestino,
                        'tipo_archivo' => $_FILES['archivos']['type'][$key],
                        'tamanio' => $_FILES['archivos']['size'][$key]
                    ]);
                }
            }
        }
    }
    
    // Obtener información del mantenimiento para actualizar solicitud si existe
    $mtto = $db->fetchOne("
        SELECT solicitud_id, equipo_id FROM mtto_equipos_mantenimientos WHERE id = :id
    ", ['id' => $mantenimiento_id]);
    
    if ($mtto['solicitud_id']) {
        $sqlUpdateSol = "
            UPDATE mtto_equipos_solicitudes 
            SET estado = 'Finalizado'
            WHERE id = :id
        ";
        
        $stmtUpdateSol = $db->getConnection()->prepare($sqlUpdateSol);
        $stmtUpdateSol->execute(['id' => $mtto['solicitud_id']]);
    }
    
    // Registrar movimiento del equipo a proveedor si está en proceso
    $equipoUbicacion = $db->fetchOne("
        SELECT destino_tipo 
        FROM mtto_equipos_movimientos 
        WHERE equipo_id = :equipo_id 
            AND estado = 'Completado'
        ORDER BY fecha_ejecutada DESC, id DESC
        LIMIT 1
    ", ['equipo_id' => $mtto['equipo_id']]);
    
    // Si el equipo está en Central, registrar movimiento a Proveedor y de vuelta
    if ($equipoUbicacion && $equipoUbicacion['destino_tipo'] == 'Central') {
        // Movimiento a proveedor
        $sqlMovProv = "
            INSERT INTO mtto_equipos_movimientos 
            (equipo_id, tipo_movimiento, origen_tipo, destino_tipo, proveedor_nombre,
             fecha_planificada, fecha_ejecutada, estado, observaciones, registrado_por)
            VALUES 
            (:equipo_id, 'Central a Proveedor', 'Central', 'Proveedor', :proveedor_nombre,
             :fecha_realizada, :fecha_realizada, 'Completado', 
             'Enviado a mantenimiento', :registrado_por)
        ";
        
        $stmtMovProv = $db->getConnection()->prepare($sqlMovProv);
        $stmtMovProv->execute([
            'equipo_id' => $mtto['equipo_id'],
            'proveedor_nombre' => $proveedor_servicio,
            'fecha_realizada' => $fecha_realizada,
            'registrado_por' => $registrado_por
        ]);
        
        // Movimiento de vuelta a central
        $sqlMovCentral = "
            INSERT INTO mtto_equipos_movimientos 
            (equipo_id, tipo_movimiento, origen_tipo, destino_tipo, proveedor_nombre,
             fecha_planificada, fecha_ejecutada, estado, observaciones, registrado_por)
            VALUES 
            (:equipo_id, 'Proveedor a Central', 'Proveedor', 'Central', :proveedor_nombre,
             :fecha_realizada, :fecha_realizada, 'Completado', 
             'Retorno de mantenimiento', :registrado_por)
        ";
        
        $stmtMovCentral = $db->getConnection()->prepare($sqlMovCentral);
        $stmtMovCentral->execute([
            'equipo_id' => $mtto['equipo_id'],
            'proveedor_nombre' => $proveedor_servicio,
            'fecha_realizada' => $fecha_realizada,
            'registrado_por' => $registrado_por
        ]);
    }
    
    $db->getConnection()->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Reporte guardado exitosamente'
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