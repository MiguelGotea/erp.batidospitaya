<?php
// public_html/modulos/mantenimiento/ajax/equipos_solicitud_guardar.php
header('Content-Type: application/json');
require_once '../../../includes/auth.php';
require_once '../config/database.php';

try {
    $equipo_id = $_POST['equipo_id'] ?? 0;
    $solicitado_por = $_POST['solicitado_por'] ?? 0;
    $descripcion_problema = $_POST['descripcion_problema'] ?? '';
    $observaciones = $_POST['observaciones'] ?? '';
    
    // Validaciones
    if (empty($equipo_id) || empty($solicitado_por) || empty($descripcion_problema)) {
        throw new Exception('Faltan datos requeridos');
    }
    
    if (empty($_FILES['archivos']['name'][0])) {
        throw new Exception('Debe adjuntar al menos un archivo');
    }
    
    // Obtener sucursal del equipo
    $stmtEquipo = $db->query("
        SELECT e.id,
            (
                SELECT m.destino_id
                FROM mtto_equipos_movimientos m
                WHERE m.equipo_id = e.id 
                    AND m.destino_tipo = 'Sucursal'
                    AND m.estado = 'Completado'
                ORDER BY m.fecha_ejecutada DESC, m.id DESC
                LIMIT 1
            ) as sucursal_id
        FROM mtto_equipos e
        WHERE e.id = :equipo_id
    ", ['equipo_id' => $equipo_id]);
    
    $equipo = $stmtEquipo->fetch();
    
    if (!$equipo || !$equipo['sucursal_id']) {
        throw new Exception('No se pudo determinar la ubicación del equipo');
    }
    
    $db->getConnection()->beginTransaction();
    
    // Insertar solicitud
    $sqlInsert = "
        INSERT INTO mtto_equipos_solicitudes 
        (equipo_id, tipo_mantenimiento, sucursal_id, descripcion_problema, 
         solicitado_por, observaciones, estado)
        VALUES 
        (:equipo_id, 'Correctivo', :sucursal_id, :descripcion_problema, 
         :solicitado_por, :observaciones, 'Solicitado')
    ";
    
    $stmt = $db->getConnection()->prepare($sqlInsert);
    $stmt->execute([
        'equipo_id' => $equipo_id,
        'sucursal_id' => $equipo['sucursal_id'],
        'descripcion_problema' => $descripcion_problema,
        'solicitado_por' => $solicitado_por,
        'observaciones' => $observaciones
    ]);
    
    $solicitud_id = $db->lastInsertId();
    
    // Procesar archivos
    $uploadDir = '../uploads/solicitudes/' . $solicitud_id . '/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $archivosSubidos = 0;
    foreach ($_FILES['archivos']['tmp_name'] as $key => $tmp_name) {
        if ($_FILES['archivos']['error'][$key] == 0) {
            $nombreOriginal = $_FILES['archivos']['name'][$key];
            $extension = pathinfo($nombreOriginal, PATHINFO_EXTENSION);
            $nombreUnico = uniqid() . '_' . time() . '.' . $extension;
            $rutaDestino = $uploadDir . $nombreUnico;
            
            if (move_uploaded_file($tmp_name, $rutaDestino)) {
                // Guardar en base de datos
                $sqlArchivo = "
                    INSERT INTO mtto_equipos_solicitudes_archivos 
                    (solicitud_id, nombre_archivo, ruta_archivo, tipo_archivo, tamanio)
                    VALUES 
                    (:solicitud_id, :nombre_archivo, :ruta_archivo, :tipo_archivo, :tamanio)
                ";
                
                $stmtArchivo = $db->getConnection()->prepare($sqlArchivo);
                $stmtArchivo->execute([
                    'solicitud_id' => $solicitud_id,
                    'nombre_archivo' => $nombreOriginal,
                    'ruta_archivo' => $rutaDestino,
                    'tipo_archivo' => $_FILES['archivos']['type'][$key],
                    'tamanio' => $_FILES['archivos']['size'][$key]
                ]);
                
                $archivosSubidos++;
            }
        }
    }
    
    if ($archivosSubidos == 0) {
        throw new Exception('No se pudo subir ningún archivo');
    }
    
    $db->getConnection()->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Solicitud creada exitosamente',
        'solicitud_id' => $solicitud_id
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