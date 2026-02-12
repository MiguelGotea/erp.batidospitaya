<?php
// compra_local_configuracion_despacho_get_configuracion.php
// Obtiene la configuración de productos para una sucursal

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $codigo_sucursal = $_POST['codigo_sucursal'] ?? '';

    if (empty($codigo_sucursal)) {
        throw new Exception('Código de sucursal requerido');
    }

    // Obtener configuración de productos para esta sucursal
    $sql = "SELECT 
                clcd.id,
                clcd.id_producto_presentacion,
                clcd.dia_entrega,
                clcd.status,
                pp.Nombre as nombre_producto,
                pp.SKU
            FROM compra_local_configuracion_despacho clcd
            INNER JOIN producto_presentacion pp ON clcd.id_producto_presentacion = pp.id
            WHERE clcd.codigo_sucursal = ?
            ORDER BY pp.Nombre, clcd.dia_entrega";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$codigo_sucursal]);
    $configuracion = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'configuracion' => $configuracion
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
