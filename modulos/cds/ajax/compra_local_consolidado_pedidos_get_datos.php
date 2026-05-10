<?php
// compra_local_consolidado_pedidos_get_datos.php
// Obtiene datos consolidados de pedidos históricos por producto y día

require_once '../../../core/auth/auth.php';
// Configurar zona horaria de Managua
date_default_timezone_set('America/Managua');

header('Content-Type: application/json');

try {
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $fecha_fin = $_POST['fecha_fin'] ?? '';
    $filtro_sucursal = $_POST['sucursal'] ?? '';

    $params = [];
    if (empty($fecha_inicio) || empty($fecha_fin)) {
        // Fallback a ventana de 14 días si no hay parámetros
        $filtro_fechas = "clph.fecha_entrega >= CURDATE() - INTERVAL 7 DAY AND clph.fecha_entrega <= CURDATE() + INTERVAL 7 DAY";
    } else {
        $filtro_fechas = "clph.fecha_entrega >= ? AND clph.fecha_entrega <= ?";
        $params[] = $fecha_inicio;
        $params[] = $fecha_fin;
    }

    $where_sucursal = "";
    if (!empty($filtro_sucursal)) {
        $where_sucursal = " AND clph.codigo_sucursal = ?";
        $params[] = $filtro_sucursal;
    }

    // Construir query - Generar rango de fechas y unir con configuración para ver pedidos esperados
    $sql = "SELECT 
                clcd.id_producto_presentacion,
                pp.Nombre as nombre_producto,
                pp.SKU,
                dates.idx as dia_idx,
                SUM(clph.cantidad_total) as total_cantidad,
                COUNT(DISTINCT clcd.codigo_sucursal) as num_sucursales,
                GROUP_CONCAT(
                    CONCAT(s.codigo, ':', s.nombre, ':', IFNULL(clph.cantidad_total, 'null')) SEPARATOR '|'
                ) as detalles_sucursales
            FROM (
                SELECT 0 as idx, DATE_SUB(?, INTERVAL 1 DAY) as fecha_entrega 
                UNION SELECT 1, ?
                UNION SELECT 2, DATE_ADD(?, INTERVAL 1 DAY)
                UNION SELECT 3, DATE_ADD(?, INTERVAL 2 DAY)
                UNION SELECT 4, DATE_ADD(?, INTERVAL 3 DAY)
                UNION SELECT 5, DATE_ADD(?, INTERVAL 4 DAY)
                UNION SELECT 6, DATE_ADD(?, INTERVAL 5 DAY)
                UNION SELECT 7, DATE_ADD(?, INTERVAL 6 DAY)
            ) dates
            INNER JOIN compra_local_configuracion_despacho clcd ON clcd.dia_entrega = (IF(DAYOFWEEK(dates.fecha_entrega)=1, 7, DAYOFWEEK(dates.fecha_entrega)-1))
            INNER JOIN producto_presentacion pp ON clcd.id_producto_presentacion = pp.id
            INNER JOIN sucursales s ON clcd.codigo_sucursal = s.codigo
            LEFT JOIN (
                SELECT 
                    id_producto_presentacion, 
                    codigo_sucursal, 
                    fecha_entrega,
                    SUM(cantidad_pedido) as cantidad_total
                FROM compra_local_pedidos_historico
                GROUP BY id_producto_presentacion, codigo_sucursal, fecha_entrega
            ) clph ON clph.id_producto_presentacion = clcd.id_producto_presentacion 
                                                        AND clph.codigo_sucursal = clcd.codigo_sucursal 
                                                        AND clph.fecha_entrega = dates.fecha_entrega
            WHERE clcd.status = 'activo' AND clcd.is_delivery = 1
            $where_sucursal
            GROUP BY clcd.id_producto_presentacion, pp.Nombre, pp.SKU, dates.idx
            ORDER BY pp.Nombre, dates.idx";

    // Preparar parámetros para la subquery de fechas (8 veces) y el resto
    $query_params = array_fill(0, 8, $fecha_inicio);
    if (!empty($filtro_sucursal)) {
        $query_params[] = $filtro_sucursal;
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($query_params);
    $consolidado = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Procesar detalles de sucursales
    foreach ($consolidado as &$item) {
        $item['dia_entrega'] = intval($item['dia_idx']);
        $item['total_cantidad'] = ($item['total_cantidad'] === null) ? null : floatval($item['total_cantidad']);
        unset($item['dia_idx']);

        $detalles = [];
        if (!empty($item['detalles_sucursales'])) {
            $sucursales_data = explode('|', $item['detalles_sucursales']);
            foreach ($sucursales_data as $sucursal_data) {
                list($codigo, $nombre, $cantidad) = explode(':', $sucursal_data);
                $detalles[] = [
                    'codigo_sucursal' => $codigo,
                    'nombre_sucursal' => $nombre,
                    'cantidad' => ($cantidad === 'null') ? null : floatval($cantidad)
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
