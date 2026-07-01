<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('plan_despacho_global', 'vista', $cargoOperario)) {
    echo json_encode(['success' => false, 'message' => 'Sin permiso']);
    exit();
}

$codSucursal = $_POST['cod_sucursal'] ?? '';
if (empty($codSucursal)) {
    echo json_encode(['success' => false, 'message' => 'Faltan parámetros']);
    exit();
}

try {
    $sql = "SELECT p.id as id_producto_presentacion, p.Nombre as nombre, COALESCE(p.presentacion, u.nombre) as presentacion,
                   COALESCE(c.stock_minimo_unidades, 0) as stock_minimo_unidades
            FROM producto_presentacion p
            LEFT JOIN unidad_producto u ON u.id = p.id_unidad_producto
            LEFT JOIN configuracion_logistica_stock_producto c 
                   ON c.id_producto_presentacion = p.id AND c.cod_sucursal = ?
            WHERE p.Activo = 'SI' 
              AND p.categoria_insumo = 'G'
              AND p.presentacion_basica_inventario = 1
              AND p.Id_receta_producto IS NULL
            ORDER BY p.Nombre ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$codSucursal]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'productos' => $productos]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
}
