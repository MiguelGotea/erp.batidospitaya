<?php
// public_html/modulos/mantenimiento/ajax/equipos_repuestos_guardar.php
header('Content-Type: application/json');
require_once '../../../includes/auth.php';
require_once '../config/database.php';

try {
    $repuesto_id = $_POST['repuesto_id'] ?? null;
    $nombre = $_POST['nombre'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $costo_base = $_POST['costo_base'] ?? 0;
    $unidad_medida = $_POST['unidad_medida'] ?? '';
    
    if (empty($nombre) || empty($costo_base)) {
        throw new Exception('Faltan datos requeridos');
    }
    
    $db->getConnection()->beginTransaction();
    
    if ($repuesto_id) {
        // Actualizar repuesto existente
        $sql = "
            UPDATE mtto_equipos_repuestos 
            SET nombre = :nombre,
                descripcion = :descripcion,
                costo_base = :costo_base,
                unidad_medida = :unidad_medida
            WHERE id = :id
        ";
        
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->execute([
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'costo_base' => $costo_base,
            'unidad_medida' => $unidad_medida,
            'id' => $repuesto_id
        ]);
        
        $mensaje = 'Repuesto actualizado exitosamente';
    } else {
        // Insertar nuevo repuesto
        $sql = "
            INSERT INTO mtto_equipos_repuestos 
            (nombre, descripcion, costo_base, unidad_medida)
            VALUES 
            (:nombre, :descripcion, :costo_base, :unidad_medida)
        ";
        
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->execute([
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'costo_base' => $costo_base,
            'unidad_medida' => $unidad_medida
        ]);
        
        $mensaje = 'Repuesto creado exitosamente';
    }
    
    $db->getConnection()->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $mensaje
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
?>