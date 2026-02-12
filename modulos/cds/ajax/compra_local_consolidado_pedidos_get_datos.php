<?php
// compra_local_consolidado_pedidos_get_datos.php
// Obtiene datos consolidados de pedidos históricos por producto y día

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

// Configurar zona horaria de Managua
date_default_timezone_set('America/Managua');

header('Content-Type: application/json');

try {
    $filtro_sucursal = $_POST['sucursal'] ?? '';

    // Construir query base - consultar historial de pedidos
    $sql = "SELECT 
                clph.id_producto_presentacion,
                pp.Nombre as nombre_producto,
                pp.SKU,
                DAYOFWEEK(clph.fecha_entrega) as dia_entrega,
                SUM(clph.cantidad_pedido) as total_cantidad,
                COUNT(DISTINCT clph.codigo_sucursal) as num_sucursales,
                GROUP_CONCAT(
                    CONCAT(s.codigo, ':', s.nombre, ':', clph.cantidad_pedido)
                    SEPARATOR '|'
                ) as detalles_sucursales
            FROM compra_local_pedidos_historico clph
            INNER JOIN producto_presentacion pp ON clph.id_producto_presentacion = pp.id
            INNER JOIN sucursales s ON clph.codigo_sucursal = s.codigo
            WHERE clph.cantidad_pedido > 0
            AND clph.fecha_entrega >= CURDATE() - INTERVAL 7 DAY
            AND clph.fecha_entrega <= CURDATE() + INTERVAL 7 DAY";

    $params = [];

    // Aplicar filtro de sucursal
    if (!empty($filtro_sucursal)) {
        $sql .= " AND clph.codigo_sucursal = ?";
        $params[] = $filtro_sucursal;
    }

    $sql .= " GROUP BY clph.id_producto_presentacion, pp.Nombre, pp.SKU, dia_entrega
              ORDER BY pp.Nombre, dia_entrega";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $consolidado = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Procesar detalles de sucursales y convertir DAYOFWEEK a formato 1-7
    foreach ($consolidado as &$item) {
        // Convertir DAYOFWEEK (1=Dom, 2=Lun) a nuestro formato (1=Lun, 7=Dom)
        $dia_mysql = $item['dia_entrega'];
        $item['dia_entrega'] = ($dia_mysql == 1) ? 7 : ($dia_mysql - 1);

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
