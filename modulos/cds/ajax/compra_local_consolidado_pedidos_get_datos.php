<?php
// compra_local_consolidado_pedidos_get_datos.php
// Obtiene datos consolidados de pedidos por producto y día

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

// Configurar zona horaria de Managua
date_default_timezone_set('America/Managua');

header('Content-Type: application/json');

try {
    $filtro_sucursal = $_POST['sucursal'] ?? '';

    // Construir query base
    $sql = "SELECT 
                clpd.id_producto_presentacion,
                pp.Nombre as nombre_producto,
                pp.SKU,
                clpd.dia_entrega,
                SUM(clpd.cantidad_pedido) as total_cantidad,
                COUNT(DISTINCT clpd.codigo_sucursal) as num_sucursales,
                GROUP_CONCAT(
                    CONCAT(s.codigo, ':', s.nombre, ':', clpd.cantidad_pedido)
                    SEPARATOR '|'
                ) as detalles_sucursales
            FROM compra_local_productos_despacho clpd
            INNER JOIN producto_presentacion pp ON clpd.id_producto_presentacion = pp.id
            INNER JOIN sucursales s ON clpd.codigo_sucursal = s.codigo
            WHERE clpd.cantidad_pedido > 0
            AND clpd.status = 'activo'";

    $params = [];

    // Aplicar filtro de sucursal
    if (!empty($filtro_sucursal)) {
        $sql .= " AND clpd.codigo_sucursal = ?";
        $params[] = $filtro_sucursal;
    }

    // Aplicar filtro de día
    if (!empty($filtro_dia)) {
        $sql .= " AND clpd.dia_entrega = ?";
        $params[] = $filtro_dia;
    }

    $sql .= " GROUP BY clpd.id_producto_presentacion, pp.Nombre, pp.SKU, clpd.dia_entrega
              ORDER BY pp.Nombre, clpd.dia_entrega";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $consolidado = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Procesar detalles de sucursales
    foreach ($consolidado as &$item) {
        $detalles = [];
        if (!empty($item['detalles_sucursales'])) {
            $sucursales_data = explode('|', $item['detalles_sucursales']);
            foreach ($sucursales_data as $sucursal_data) {
                list($codigo, $nombre, $cantidad) = explode(':', $sucursal_data);
                $detalles[] = [
                    'codigo_sucursal' => $codigo,
                    'nombre_sucursal' => $nombre,
                    'cantidad' => intval($cantidad)
                ];
            }
        }
        $item['detalles'] = $detalles;
        unset($item['detalles_sucursales']);
    }

    echo json_encode([
        'success' => true,
        'consolidado' => $consolidado
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
