<?php
/* ===================================================
   AJAX: Guardar stock mínimo de producto Grupo G
   Módulo: Productos
   =================================================== */

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('configuracion_logistica', 'edicion', $cargoOperario)) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos de edición']);
    exit();
}

$codSucursal = $_POST['codigo_sucursal'] ?? '';
$idPP = $_POST['id_producto_presentacion'] ?? '';
$stockMin = $_POST['stock_minimo_unidades'] ?? '';

if (!$codSucursal || !$idPP || $stockMin === '') {
    echo json_encode(['success' => false, 'message' => 'Parámetros incompletos']);
    exit();
}

$stockMin = (float) $stockMin;

try {
    $sql = "INSERT INTO configuracion_logistica_stock_producto (cod_sucursal, id_producto_presentacion, stock_minimo_unidades, modificado_por, fecha_actualizacion) 
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
            stock_minimo_unidades = VALUES(stock_minimo_unidades),
            modificado_por = VALUES(modificado_por),
            fecha_actualizacion = NOW()";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute([$codSucursal, $idPP, $stockMin, $usuario['IdUsuarios']]);
    
    // Obtener la fecha de actualización generada
    $stmtD = $conn->prepare("SELECT DATE_FORMAT(fecha_actualizacion, '%Y-%m-%d %H:%i') as fecha FROM configuracion_logistica_stock_producto WHERE cod_sucursal = ? AND id_producto_presentacion = ?");
    $stmtD->execute([$codSucursal, $idPP]);
    $fecha = $stmtD->fetchColumn();

    $meta = [
        'fecha_actualizacion' => $fecha,
        'modificado_por_nombre' => $usuario['Nombres']
    ];
    
    echo json_encode(['success' => true, 'meta' => $meta]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
