<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

// Sincronizar zona horaria de MySQL para este script
		if (isset($conn))
				$conn->query("SET time_zone = '-06:00'");

		header('Content-Type: application/json');
		ini_set('display_errors', 0); // Desactivar para que warnings no rompan el JSON
		error_reporting(E_ALL);
		set_time_limit(300); // 5 minutos para procesos pesados
		ini_set('memory_limit', '1024M');

        // Intentar subir límites si el host lo permite (evitar 500 si no existe la variable)
        if (isset($conn)) {
            try {
                @$conn->exec("SET SESSION max_statement_time = 300"); // MariaDB
                @$conn->exec("SET SESSION max_execution_time = 300000"); // MySQL
            } catch (Exception $e) { /* Ignorar si no tiene permisos */ }
        }

		ob_start(); // Capturar cualquier salida accidental (warnings/notices)

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('dashboard_rfm', 'vista', $cargoOperario)) {
    echo json_encode(['success' => false, 'message' => 'No tiene permiso para ver estos datos']);
    exit;
}

// 0. Captura de Filtros
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-90 days'));
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$sucursal = $_GET['sucursal'] ?? null;
$tipo_cliente = 'club'; // Hardcodeado a club por solicitud del usuario
$umbral_perdido = intval($_GET['umbral_perdido'] ?? 60);

try {
    // Definición de base de filtros
    $whereVmtap = " AND local IN (SELECT codigo FROM sucursales WHERE VMTAP = 1)";

    // Filtro Global (Ignora Fecha para Salud/RFM)
    $whereGlobal = "WHERE Anulado = 0";
    $paramsGlobal = [];

    // Filtro Periodo (Para Rendimiento/Ventas)
    $whereSimple = "WHERE Anulado = 0 AND Fecha BETWEEN :f_inicio AND :f_fin";
    $params = [':f_inicio' => $fecha_inicio, ':f_fin' => $fecha_fin];

    $codigo_local = null;
    if ($sucursal && $sucursal !== 'todas') {
        $stmtLoc = $conn->prepare("SELECT codigo FROM sucursales WHERE nombre = :suc");
        $stmtLoc->execute([':suc' => $sucursal]);
        $codigo_local = $stmtLoc->fetchColumn() ?: -1;

        $whereSimple .= " AND local = :suc_local";
        $whereGlobal .= " AND local = :suc_local";
        $params[':suc_local'] = $codigo_local;
        $paramsGlobal[':suc_local'] = $codigo_local;
    }
    // Nota: El filtro por nombre se mantiene para el selector de UI por ahora, 
    // pero la integridad de VMTAP usa los códigos.

    /* 
       ELIMINADO: Filtro de CodCliente a nivel de fila.
       Se aplicará después de agrupar por CodPedido para no perder montos de líneas sin ID (tips/envío) 
       en pedidos que sÍ son de miembros.
    */

    // 0.1 Participación Ingresos (Club vs General) - Independiente del filtro tipo_cliente
    $wherePart = "WHERE Anulado = 0 AND Fecha BETWEEN :f_inicio AND :f_fin";
    if ($sucursal && $sucursal !== 'todas') {
        $wherePart .= " AND local = :suc_local";
    }

    $sqlPart = "
        SELECT 
            (ClienteID > 0) as EsClub, 
            SUM(MontoFactura) as Total 
        FROM (
            SELECT 
                local,
                CodPedido, 
                MAX(CodCliente) as ClienteID, 
                MAX(MontoFactura) as MontoFactura 
            FROM VentasGlobalesAccessCSV 
            $wherePart 
            GROUP BY local, CodPedido
        ) t 
        GROUP BY EsClub
    ";


    $stmtPart = $conn->prepare($sqlPart);
    $paramsPart = [':f_inicio' => $fecha_inicio, ':f_fin' => $fecha_fin];
    if ($sucursal && $sucursal !== 'todas') {
        $paramsPart[':suc_local'] = $codigo_local;
    }
    $stmtPart->execute($paramsPart);
    $part_raw = $stmtPart->fetchAll(PDO::FETCH_KEY_PAIR);

    $participacion = [
        'club' => $part_raw[1] ?? 0,
        'general' => $part_raw[0] ?? 0,
        'total' => ($part_raw[1] ?? 0) + ($part_raw[0] ?? 0)
    ];

    // 0.2 Comparativa Periodo Anterior (Nuevos Clientes - Rendimiento)
    $diff = strtotime($fecha_fin) - strtotime($fecha_inicio);
    $p_inicio = date('Y-m-d', strtotime($fecha_inicio) - $diff - 86400);
    $p_fin = date('Y-m-d', strtotime($fecha_inicio) - 86400);

    $whereClub = "WHERE fecha_registro BETWEEN :p_inicio AND :p_fin";
    $paramsClub = [':p_inicio' => $p_inicio, ':p_fin' => $p_fin];
    if ($sucursal && $sucursal !== 'todas') {
        $whereClub .= " AND sucursal = :suc_local";
        $paramsClub[':suc_local'] = $codigo_local;
    }

    $sqlPrev = "SELECT COUNT(DISTINCT membresia) as PrevNuevos FROM clientesclub $whereClub";
    $stmtPrev = $conn->prepare($sqlPrev);
    $stmtPrev->execute($paramsClub);
    $prev_nuevos = $stmtPrev->fetch(PDO::FETCH_ASSOC)['PrevNuevos'] ?? 0;

    // 1. Obtener Datos RFM (Agrupado por Cliente)
    // Para CLUB: Usamos Atribución de Origen (Home Branch de clientesclub) + Ciclo de vida TOTAL
    // Para GENERAL: Usamos Atribución de Transacción (Donde ocurrió la venta)
    $havingRFM = "HAVING r.CodCliente > 0";
    $whereRFM = "WHERE v.Anulado = 0";

    $paramsRFM = [];
    $sqlRFM = "
        SELECT 
            r.CodCliente,
            COALESCE(MAX(c.nombre_sucursal), MAX(s.nombre), 'Desconocida') as Sucursal,
            DATEDIFF(CURDATE(), MAX(r.Fecha)) as Recency,
            COUNT(r.CodPedido) as Frequency,
            SUM(r.TotalPedido) as Monetary,
            MAX(CONCAT(COALESCE(c.nombre,''), ' ', COALESCE(c.apellido, ''))) as ClienteNombre,
            MAX(c.fecha_registro) as FechaRegistro
        FROM (
            SELECT 
                MAX(v.CodCliente) as CodCliente, 
                v.CodPedido, 
                MAX(v.Fecha) as Fecha, 
                MAX(v.MontoFactura) as TotalPedido, 
                v.local
            FROM VentasGlobalesAccessCSV v
            WHERE v.Anulado = 0 AND v.CodCliente > 0
            GROUP BY v.local, v.CodPedido
        ) r
        LEFT JOIN clientesclub c ON r.CodCliente = c.membresia
        LEFT JOIN sucursales s ON r.local = s.codigo
    ";

    if ($sucursal && $sucursal !== 'todas') {
        // OPTIMIZACIÓN: Filtrar por socios de la sucursal usando el índice de CodCliente
        $sqlRFM .= " WHERE r.CodCliente IN (SELECT membresia FROM clientesclub WHERE sucursal = :suc_local)";
        $paramsRFM[':suc_local'] = $codigo_local;
    }

    $sqlRFM .= " GROUP BY r.CodCliente $havingRFM";

    $stmt = $conn->prepare($sqlRFM);
    $stmt->execute($paramsRFM ?? []);
    $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Conteo Universo con Compra (Denominator for Health %)
    // Socios registrados que han tenido al menos una compra en la historia
    // OPTIMIZACIÓN: Buscamos directamente en ventas si el cliente es miembro
    if ($sucursal && $sucursal !== 'todas') {
        // OPTIMIZACIÓN: Usar IN (SELECT) en lugar de JOIN para aprovechar índice CodCliente
        $sqlUniv = "
            SELECT COUNT(DISTINCT CodCliente) 
            FROM VentasGlobalesAccessCSV 
            WHERE CodCliente IN (SELECT membresia FROM clientesclub WHERE sucursal = :suc_local)
            AND Anulado = 0
        ";
        $paramsUniv = [':suc_local' => $codigo_local];
    } else {
        $sqlUniv = "SELECT COUNT(DISTINCT CodCliente) FROM VentasGlobalesAccessCSV WHERE CodCliente > 0 AND Anulado = 0";
        $paramsUniv = [];
    }

    $stmtUniv = $conn->prepare($sqlUniv);
    $stmtUniv->execute($paramsUniv);
    $universo_count = $stmtUniv->fetchColumn() ?: 1;

    // Salir si no hay datos
    if (empty($raw_data)) {
        echo json_encode(['success' => true, 'summary' => null, 'message' => 'Sin datos']);
        exit;
    }

    // 2. Procesamiento de Segmentos (Lógica PHP por flexibilidad)
    $recencies = array_column($raw_data, 'Recency');
    $frequencies = array_column($raw_data, 'Frequency');
    $monetaries = array_column($raw_data, 'Monetary');
    sort($recencies);
    sort($frequencies);
    sort($monetaries);
    $total_count = count($raw_data);
    $avg_ltv = $total_count > 0 ? array_sum($monetaries) / $total_count : 0;

    $get_q = function ($val, $arr, $inv = false) use ($total_count) {
        $pos = array_search($val, $arr);
        $p = $pos / $total_count;
        if ($inv)
            $p = 1 - $p;
        if ($p <= 0.2)
            return 1;
        if ($p <= 0.4)
            return 2;
        if ($p <= 0.6)
            return 3;
        if ($p <= 0.8)
            return 4;
        return 5;
    };

    $segments = [
        'Champions' => 0,
        'Loyal' => 0,
        'New' => 0,
        'At Risk' => 0,
        'Hibernating' => 0,
        'Lost' => 0
    ];

    $activos = 0;
    $en_riesgo = 0;
    $perdidos = 0;

    foreach ($raw_data as &$row) {
        $row['R_Score'] = $get_q($row['Recency'], $recencies, true);
        $row['F_Score'] = $get_q($row['Frequency'], $frequencies);
        $row['M_Score'] = $get_q($row['Monetary'], $monetaries);
        $row['ScoreTotal'] = $row['R_Score'] + $row['F_Score'] + $row['M_Score'];

        $r = $row['R_Score'];
        $f = $row['F_Score'];
        $recency = $row['Recency'];

        // Lógica de Segmentos y Contadores basada en Umbral
        if ($recency > $umbral_perdido) {
            $seg = 'Lost';
            $perdidos++;
        } elseif ($recency > ($umbral_perdido / 2)) {
            $seg = 'At Risk';
            $en_riesgo++;
            $activos++; // Sigue siendo activo hasta que pase el umbral total
        } else {
            // Segmentación RFM Tradicional para los que NO son perdidos/riesgo por umbral
            if ($r >= 4 && $f >= 4)
                $seg = 'Champions';
            elseif ($r >= 3 && $f >= 3)
                $seg = 'Loyal';
            elseif ($r >= 4 && $f <= 2)
                $seg = 'New';
            else
                $seg = 'Hibernating';
            $activos++;
        }

        $row['Segment'] = $seg;
        $segments[$seg]++;
    }

    // 3. KPIs Resumen
    $activos = count(array_filter($raw_data, fn($x) => $x['Recency'] <= $umbral_perdido));
    $en_riesgo = count(array_filter($raw_data, fn($x) => $x['Recency'] > ($umbral_perdido / 2) && $x['Recency'] <= $umbral_perdido));
    $perdidos = count(array_filter($raw_data, fn($x) => $x['Recency'] > $umbral_perdido));

    // Clientes nuevos (Registrados en el periodo)
    $whereClubNow = "WHERE fecha_registro BETWEEN :f_inicio AND :f_fin";
    $paramsClubNow = [':f_inicio' => $fecha_inicio, ':f_fin' => $fecha_fin];
    if ($sucursal && $sucursal !== 'todas') {
        $whereClubNow .= " AND sucursal = :suc_local";
        $paramsClubNow[':suc_local'] = $codigo_local;
    }

    $stmtNew = $conn->prepare("SELECT COUNT(*) as Nuevos FROM clientesclub $whereClubNow");
    $stmtNew->execute($paramsClubNow);
    $nuevos_res = $stmtNew->fetch();

    // Ingresos y Tickets del PERIODO (para KPIs de Rendimiento)
    // Usamos EXACTAMENTE la misma lógica que sqlPart para asegurar consistencia
    $stmtPeriod = $conn->prepare("
        SELECT 
            SUM(MontoPedido) as TotalIngresos,
            COUNT(*) as TotalPedidos
        FROM (
            SELECT 
                local,
                CodPedido, 
                MAX(CodCliente) as ClienteID, 
                MAX(MontoFactura) as MontoPedido 
            FROM VentasGlobalesAccessCSV 
            $whereSimple 
            GROUP BY local, CodPedido
            HAVING ClienteID > 0
        ) t
    ");
    $stmtPeriod->execute($params);
    $period_stats = $stmtPeriod->fetch(PDO::FETCH_ASSOC);

    $sum_m_period = $period_stats['TotalIngresos'] ?? 0;
    $sum_f_period = $period_stats['TotalPedidos'] ?? 0;
    $ticket_club = $sum_f_period > 0 ? $sum_m_period / $sum_f_period : 0;

    // Filtro para pedidos Club (basado en transacción completa) - OPTIMIZADO con JOIN
    $paramsFO = [':fo_i' => $fecha_inicio, ':fo_f' => $fecha_fin];

    // Evolución y hábitos se cargan en Fase 2
    $clientSegments = array_column($raw_data, 'Segment', 'CodCliente');

    // 5. Análisis por Sucursal y Detalle Individual
    $sqlBranchPeriod = "
        SELECT 
            s.nombre as Sucursal,
            SUM(t.MontoPedido) as TotalMonto,
            COUNT(*) as TotalPedidos
        FROM (
            SELECT 
                local,
                CodPedido, 
                MAX(MontoFactura) as MontoPedido 
            FROM VentasGlobalesAccessCSV 
            $whereSimple 
            AND local IN (SELECT codigo FROM sucursales WHERE VMTAP = 1)
            GROUP BY local, CodPedido
            HAVING MAX(CodCliente) > 0
        ) t
        JOIN sucursales s ON t.local = s.codigo
        GROUP BY s.nombre
    ";
    $stmtBP = $conn->prepare($sqlBranchPeriod);
    $stmtBP->execute($params);
    $period_bench = $stmtBP->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

    // Obtener lista de sucursales activas para filtrar el enlistado visual en gráficas de benchmarking
    $stmtActive = $conn->query("SELECT nombre FROM sucursales WHERE VMTAP = 1");
    $activeBranches = $stmtActive->fetchAll(PDO::FETCH_COLUMN);

    $branch_stats = [];
    $segment_revenue = [];
    foreach ($raw_data as &$r) {
        $bn = $r['Sucursal'] ?: 'Desconocida';

        // Los KPI de Ingresos por Segmento y Listado Maestro deben incluir TODO
        $segment_revenue[$r['Segment']] = ($segment_revenue[$r['Segment']] ?? 0) + $r['Monetary'];

        // Enriquecer registro individual para el Listado Maestro
        $r['TicketPromedio'] = ($r['Frequency'] > 0) ? $r['Monetary'] / $r['Frequency'] : 0;
        $r['Antiguedad'] = $r['FechaRegistro'] ? (int) floor((time() - strtotime($r['FechaRegistro'])) / 86400) : 0;

        // SOLO para las gráficas de Benchmarking por sucursal, restringimos visualmente a VMTAP=1
        if (!in_array($bn, $activeBranches))
            continue;

        if (!isset($branch_stats[$bn])) {
            $branch_stats[$bn] = [
                'monto' => 0,
                'count' => 0,
                'score' => 0,
                'segments' => [],
                'top_customers' => [],
                'period_monto' => $period_bench[$bn]['TotalMonto'] ?? 0,
                'period_pedidos' => $period_bench[$bn]['TotalPedidos'] ?? 0
            ];
        }
        $branch_stats[$bn]['monto'] += $r['Monetary'];
        $branch_stats[$bn]['count']++;
        $branch_stats[$bn]['score'] += $r['ScoreTotal'];
        $branch_stats[$bn]['segments'][$r['Segment']] = ($branch_stats[$bn]['segments'][$r['Segment']] ?? 0) + 1;

        $branch_stats[$bn]['top_customers'][] = [
            'name' => $r['ClienteNombre'],
            'ltv' => $r['Monetary']
        ];
    }

    // Procesar Top 5 por sucursal
    foreach ($branch_stats as $bn => &$stats) {
        usort($stats['top_customers'], function ($a, $b) {
            return $b['ltv'] <=> $a['ltv'];
        });
        $stats['top_5_ltv'] = array_slice($stats['top_customers'], 0, 5);
        unset($stats['top_customers']); // Limpiar para no enviar datos excesivos
    }
    unset($stats);

    // UltimoProducto se carga en Fase 2

    // Hábitos cargados en Fase 2

    // Capturar cualquier salida sucia (warnings/notices) que haya ocurrido
    $debug_output = ob_get_clean();

    // Preparar lista compacta de clientes para Fase 2
    $clientesCompactos = array_map(fn($r) => [
        'CodCliente'    => $r['CodCliente'],
        'Sucursal'      => $r['Sucursal'],
        'Monetary'      => $r['Monetary'],
        'ScoreTotal'    => $r['ScoreTotal'],
        'Segment'       => $r['Segment'],
        'ClienteNombre' => $r['ClienteNombre'],
        'Frequency'     => $r['Frequency']
    ], $raw_data);

    echo json_encode([
        'success' => true,
        'debug'   => $debug_output,
        'summary' => [
            'total_club'       => $total_count,
            'activos'          => $activos,
            'nuevos'           => $nuevos_res['Nuevos'],
            'prev_nuevos'      => $prev_nuevos,
            'en_riesgo'        => $en_riesgo,
            'perdidos'         => $perdidos,
            'avg_ltv'          => round($avg_ltv, 2),
            'ticket_club'      => round($ticket_club, 2),
            'monto_total'      => round($sum_m_period, 2),
            'churn_rate'       => round(($perdidos / max(1, $total_count)) * 100, 2),
            'retention_metrics'=> ['rate' => 0, 'h1' => 0, 'h2' => 0], // se actualiza en fase 2
            'participacion'    => $participacion,
            'universo_total'   => $universo_count,
            'raw' => [
                'total_pedidos'  => $sum_f_period,
                'total_ingresos' => $sum_m_period
            ]
        ],
        'segments'        => $segments,
        'segment_revenue' => $segment_revenue,
        'evolution'       => [],   // se llena en fase 2
        'individual'      => array_slice($raw_data, 0, 1000),
        'branch_analysis' => $branch_stats,
        'habits'          => null, // se llena en fase 2
        'clientes_fase2'  => $clientesCompactos // mapa compacto para fase 2
    ]);

} catch (Throwable $e) {
    ob_get_clean(); // Limpiar si falló
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
}

// calculateRetentionDetail movido a dashboard_rfm_get_datos2.php
?>