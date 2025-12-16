<?php
// public_html/modulos/mantenimiento/ajax/equipos_repuestos_listar.php
header('Content-Type: application/json');
require_once '../../../includes/auth.php';
require_once '../config/database.php';

try {
    $repuestos = $db->fetchAll("
        SELECT 
            id,
            nombre,
            descripcion,
            costo_base,
            unidad_medida,
            activo
        FROM mtto_equipos_repuestos
        ORDER BY activo DESC, nombre ASC
    ");
    
    echo json_encode([
        'success' => true,
        'repuestos' => $repuestos
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>