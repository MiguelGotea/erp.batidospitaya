<?php
/* ===================================================
   AJAX: Obtener productos del Grupo G y su stock mínimo
   Módulo: Productos
   =================================================== */

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('configuracion_logistica', 'vista', $cargoOperario)) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit();
}

$codSucursal = $_POST['codigo_sucursal'] ?? '';
if (!$codSucursal) {
    echo json_encode(['success' => false, 'message' => 'Falta código de sucursal']);
    exit();
}

try {
    // Obtener todos los productos activos de categoría G
    // Y hacer LEFT JOIN con configuracion_logistica_stock_producto para esta sucursal
    $sql = "SELECT 
                pp.id AS id_producto_presentacion,
                pp.Nombre,
                pp.presentacion AS unidad,
                pm.Nombre AS nombre_maestro,
                sp.stock_minimo_unidades
            FROM producto_presentacion pp
            LEFT JOIN producto_maestro pm ON pp.id_producto_maestro = pm.id
            LEFT JOIN configuracion_logistica_stock_producto sp 
                ON sp.id_producto_presentacion = pp.id 
                AND sp.cod_sucursal = ?
            WHERE pp.categoria_insumo = 'G' 
              AND pp.Activo = 'SI'
            ORDER BY pm.Nombre ASC, pp.Nombre ASC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$codSucursal]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'productos' => $productos]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
