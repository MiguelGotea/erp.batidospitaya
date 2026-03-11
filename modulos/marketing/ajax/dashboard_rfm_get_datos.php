<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('dashboard_rfm', 'vista', $cargoOperario)) {
    echo json_encode(['success' => false, 'message' => 'No tiene permiso para ver estos datos']);
    exit;
}

$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-90 days'));
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$sucursal = $_GET['sucursal'] ?? null;

try {
    // 1. Obtener Base RFM para Clientes Club
    $where = "WHERE Anulado = 0 AND CodCliente > 0 AND Fecha BETWEEN :f_inicio AND :f_fin";
    $params = [
        ':f_inicio' => $fecha_inicio,
        ':f_fin' => $fecha_fin
    ];
    
    if ($sucursal) {
        $where .= " AND Sucursal_Nombre = :sucursal";
        $params[':sucursal'] = $sucursal;
    }

    // CTE para obtener detalles por pedido (Monto unico por CodPedido)
    $sqlBase = "
        WITH ResumenPedidos AS (
            SELECT 
                CodCliente, 
                CodPedido, 
                MAX(Fecha) as Fecha, 
                MAX(MontoFactura) as TotalPedido
            FROM VentasGlobalesAccessCSV
            $where
            GROUP BY CodPedido
        ),
        RFM_Raw AS (
            SELECT 
                CodCliente,
                DATEDIFF(CURDATE(), MAX(Fecha)) as Recency,
                COUNT(CodPedido) as Frequency,
                SUM(TotalPedido) as Monetary
            FROM ResumenPedidos
            GROUP BY CodCliente
        )
        SELECT * FROM RFM_Raw
    ";

    $stmt = $conn->prepare($sqlBase);
    $stmt->execute($params);
    $rfm_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rfm_data)) {
        echo json_encode(['success' => true, 'data' => null, 'message' => 'No hay datos en el periodo']);
        exit;
    }

    // 2. Calcular Quintiles (PHP for flexibility with large datasets if needed)
    // Para simplificar, calcularemos los scores 1-5 basados en la distribución de la muestra
    $recencies = array_column($rfm_data, 'Recency');
    $frequencies = array_column($rfm_data, 'Frequency');
    $monetaries = array_column($rfm_data, 'Monetary');

    sort($recencies);
    sort($frequencies);
    sort($monetaries);

    $count = count($rfm_data);
    $get_quintile = function($val, $arr, $invert = false) use ($count) {
        $pos = array_search($val, $arr);
        $percentile = $pos / $count;
        if ($invert) $percentile = 1 - $percentile; // For Recency, lower is better
        
        if ($percentile <= 0.2) return 1;
        if ($percentile <= 0.4) return 2;
        if ($percentile <= 0.6) return 3;
        if ($percentile <= 0.8) return 4;
        return 5;
    };

    $segments_dist = [
        'Champions' => 0,
        'Loyal' => 0,
        'At Risk' => 0,
        'About to Sleep' => 0,
        'Lost' => 0,
        'Hibernating' => 0,
        'Other' => 0
    ];

    foreach ($rfm_data as &$row) {
        $row['R_Score'] = $get_quintile($row['Recency'], $recencies, true);
        $row['F_Score'] = $get_quintile($row['Frequency'], $frequencies);
        $row['M_Score'] = $get_quintile($row['Monetary'], $monetaries);
        
        $r = $row['R_Score'];
        $f = $row['F_Score'];
        $m = $row['M_Score'];

        // Segment Logic
        if ($r >= 4 && $f >= 4) $seg = 'Champions';
        elseif ($r >= 3 && $f >= 3) $seg = 'Loyal';
        elseif ($r <= 2 && $f >= 3) $seg = 'At Risk';
        elseif ($r <= 2 && $f <= 2) $seg = 'Lost';
        elseif ($r >= 4 && $f <= 2) $seg = 'New / Recent';
        else $seg = 'Hibernating';

        $row['Segment'] = $seg;
        $segments_dist[$seg] = ($segments_dist[$seg] ?? 0) + 1;
    }

    // 3. KPIs Globales
    $total_clientes_club = count($rfm_data);
    $activos_60d = count(array_filter($rfm_data, fn($x) => $x['Recency'] <= 60));
    $churn_rate = ($total_clientes_club > 0) ? (count(array_filter($rfm_data, fn($x) => $x['Recency'] > 60)) / $total_clientes_club) * 100 : 0;

    // 4. Hábitos (Query separada para mayor precisión por línea)
    // Para evitar el error de número de parámetros inválido al repetir el mismo placeholder, 
    // desactivamos temporalmente el chequeo estricto si el driver lo permite o usamos parámetros únicos.
    // Usaremos parámetros únicos para mayor compatibilidad.
    
    $whereH1 = str_replace([':f_inicio', ':f_fin', ':sucursal'], [':f1', ':f2', ':s1'], $where);
    $whereH2 = str_replace([':f_inicio', ':f_fin', ':sucursal'], [':f3', ':f4', ':s2'], $where);
    $whereH3 = str_replace([':f_inicio', ':f_fin', ':sucursal'], [':f5', ':f6', ':s3'], $where);
    $whereH4 = str_replace([':f_inicio', ':f_fin', ':sucursal'], [':f7', ':f8', ':s4'], $where);
    
    $paramsH = [
        ':f1' => $fecha_inicio, ':f2' => $fecha_fin,
        ':f3' => $fecha_inicio, ':f4' => $fecha_fin,
        ':f5' => $fecha_inicio, ':f6' => $fecha_fin,
        ':f7' => $fecha_inicio, ':f8' => $fecha_fin
    ];
    if ($sucursal) {
        $paramsH[':s1'] = $sucursal;
        $paramsH[':s2'] = $sucursal;
        $paramsH[':s3'] = $sucursal;
        $paramsH[':s4'] = $sucursal;
    }

    $sqlHabits = "
        SELECT 
            (SELECT DBBatidos_Nombre FROM VentasGlobalesAccessCSV $whereH1 AND Tipo IN ('Batido', 'Bowl', 'Limonada', 'Pitaya Store', 'Waffles') GROUP BY DBBatidos_Nombre ORDER BY COUNT(*) DESC LIMIT 1) as FavProduct,
            (SELECT Medida FROM VentasGlobalesAccessCSV $whereH2 AND Tipo IN ('Batido', 'Limonada') AND Medida IN ('S','M','L') GROUP BY Medida ORDER BY COUNT(*) DESC LIMIT 1) as FavSize,
            (SELECT Modalidad FROM VentasGlobalesAccessCSV $whereH3 GROUP BY Modalidad ORDER BY COUNT(*) DESC LIMIT 1) as FavModalidad,
            COUNT(DISTINCT CASE WHEN CodigoPromocion <> 5 THEN CodPedido END) as PromoOrders,
            COUNT(DISTINCT CodPedido) as TotalOrders,
            SUM(CASE WHEN Puntos < 0 THEN 1 ELSE 0 END) as RedemptionLines
        FROM VentasGlobalesAccessCSV
        $whereH4
    ";
    
    $stmtH = $conn->prepare($sqlHabits);
    $stmtH->execute($paramsH);
    $habits = $stmtH->fetch(PDO::FETCH_ASSOC);

    // 5. Ingresos Club vs General
    $sqlIngresos = "
        SELECT 
            SUM(CASE WHEN CodCliente > 0 THEN Precio ELSE 0 END) as IngresosClub,
            SUM(CASE WHEN CodCliente = 0 THEN Precio ELSE 0 END) as IngresosGeneral
        FROM VentasGlobalesAccessCSV
        WHERE Anulado = 0 AND Fecha BETWEEN :fi AND :ff
        " . ($sucursal ? " AND Sucursal_Nombre = :suc" : "");
    
    $paramsI = [':fi' => $fecha_inicio, ':ff' => $fecha_fin];
    if ($sucursal) $paramsI[':suc'] = $sucursal;

    $stmtI = $conn->prepare($sqlIngresos);
    $stmtI->execute($paramsI);
    $ingresos = $stmtI->fetch(PDO::FETCH_ASSOC);

    // 6. Formatear Respuesta
    echo json_encode([
        'success' => true,
        'summary' => [
            'total_club' => $total_clientes_club,
            'activos' => $activos_60d,
            'churn_rate' => round($churn_rate, 2),
            'ticket_promedio' => count($monetaries) > 0 ? array_sum($monetaries) / array_sum($frequencies) : 0,
            'ltv_total' => array_sum($monetaries)
        ],
        'segments' => $segments_dist,
        'habits' => [
            'fav_product' => $habits['FavProduct'] ?? 'N/A',
            'fav_size' => $habits['FavSize'] ?? 'N/A',
            'fav_modalidad' => $habits['FavModalidad'] ?? 'N/A',
            'perc_promo' => ($habits['TotalOrders'] > 0) ? round(($habits['PromoOrders'] / $habits['TotalOrders']) * 100, 2) : 0,
            'redenciones' => $habits['RedemptionLines']
        ],
        'ingresos' => $ingresos
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
