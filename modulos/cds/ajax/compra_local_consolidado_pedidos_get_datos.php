<?php
// compra_local_consolidado_pedidos_get_datos.php
// Obtiene datos consolidados de pedidos históricos por producto y día

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

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

    // Construir query base - consultar historial de pedidos con agregación por sucursal
    $sql = "SELECT 
                clph.id_producto_presentacion,
                pp.Nombre as nombre_producto,
                pp.SKU,
                DAYOFWEEK(clph.fecha_entrega) as dia_entrega,
                SUM(clph.cantidad_pedido) as total_cantidad,
                COUNT(DISTINCT clph.codigo_sucursal) as num_sucursales,
                GROUP_CONCAT(
                    detalles.info_suc SEPARATOR '|'
                ) as detalles_sucursales
            FROM compra_local_pedidos_historico clph
            INNER JOIN producto_presentacion pp ON clph.id_producto_presentacion = pp.id
            INNER JOIN (
                SELECT 
                    id_producto_presentacion, 
                    fecha_entrega, 
                    codigo_sucursal,
                    CONCAT(s.codigo, ':', s.nombre, ':', SUM(cantidad_pedido)) as info_suc
                FROM compra_local_pedidos_historico clph_sub
                INNER JOIN sucursales s ON clph_sub.codigo_sucursal = s.codigo
                WHERE clph_sub.cantidad_pedido > 0
                GROUP BY id_producto_presentacion, fecha_entrega, codigo_sucursal
            ) detalles ON clph.id_producto_presentacion = detalles.id_producto_presentacion 
                       AND clph.fecha_entrega = detalles.fecha_entrega 
                       AND clph.codigo_sucursal = detalles.codigo_sucursal
            WHERE clph.cantidad_pedido > 0
            AND $filtro_fechas $where_sucursal
            GROUP BY clph.id_producto_presentacion, pp.Nombre, pp.SKU, dia_entrega
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
