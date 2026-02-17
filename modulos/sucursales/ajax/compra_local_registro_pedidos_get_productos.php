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

    // Obtener todos los registros de configuración (aseguramos los 7 días por producto)
    $sql = "SELECT 
                clcd.id_producto_presentacion as id_producto,
                pp.Nombre as nombre_producto,
                pp.SKU,
                clcd.status,
                clcd.dia_entrega,
                clcd.is_delivery,
                clcd.base_consumption,
                clcd.event_factor,
                clcd.lead_time_days,
                clcd.shelf_life_days
            FROM compra_local_configuracion_despacho clcd
            INNER JOIN producto_presentacion pp ON clcd.id_producto_presentacion = pp.id
            WHERE clcd.codigo_sucursal = ?
            ORDER BY pp.Nombre, clcd.dia_entrega";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$codigo_sucursal]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $productos_map = [];
    foreach ($rows as $row) {
        $id = $row['id_producto'];
        if (!isset($productos_map[$id])) {
            $productos_map[$id] = [
                'id_producto' => $id,
                'nombre_producto' => $row['nombre_producto'],
                'SKU' => $row['SKU'],
                'status' => $row['status'],
                // Campos generales (se toman del primer registro encontrado)
                'lead_time_days' => intval($row['lead_time_days']),
                'shelf_life_days' => intval($row['shelf_life_days']),
                'config_diaria' => [],
                'dias_entrega' => []
            ];
        }

        // Guardar config de este día específico
        $productos_map[$id]['config_diaria'][$row['dia_entrega']] = [
            'base_consumption' => floatval($row['base_consumption']),
            'event_factor' => floatval($row['event_factor']),
            'is_delivery' => intval($row['is_delivery'])
        ];

        // Mantener compatibilidad con array de dias_entrega para lógica simple
        if ($row['is_delivery'] == 1) {
            $productos_map[$id]['dias_entrega'][] = intval($row['dia_entrega']);
        }
    }

    echo json_encode([
        'success' => true,
        'productos' => array_values($productos_map)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
