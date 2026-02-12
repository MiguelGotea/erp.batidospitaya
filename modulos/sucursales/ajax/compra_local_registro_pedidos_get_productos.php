<?php
// compra_local_registro_pedidos_get_productos.php
// Obtiene los productos configurados para una sucursal

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $codigo_sucursal = $_POST['codigo_sucursal'] ?? '';

    if (empty($codigo_sucursal)) {
        throw new Exception('Código de sucursal requerido');
    }

    // Obtener productos configurados para esta sucursal con sus días de entrega
    $sql = "SELECT 
                clcd.id_producto_presentacion as id_producto,
                pp.Nombre as nombre_producto,
                pp.SKU,
                clcd.status,
                GROUP_CONCAT(DISTINCT clcd.dia_entrega ORDER BY clcd.dia_entrega) as dias_entrega
            FROM compra_local_configuracion_despacho clcd
            INNER JOIN producto_presentacion pp ON clcd.id_producto_presentacion = pp.id
            WHERE clcd.codigo_sucursal = ?
            GROUP BY clcd.id_producto_presentacion, pp.Nombre, pp.SKU, clcd.status
            ORDER BY pp.Nombre";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$codigo_sucursal]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Convertir dias_entrega de string a array
    foreach ($productos as &$producto) {
        $producto['dias_entrega'] = !empty($producto['dias_entrega'])
            ? array_map('intval', explode(',', $producto['dias_entrega']))
            : [];
    }

    echo json_encode([
        'success' => true,
        'productos' => $productos
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
