<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

// Sincronizar zona horaria de MySQL para este script
$conn->query("SET time_zone = '-06:00'");

header('Content-Type: application/json');

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
$tipo_cliente = $_GET['tipo_cliente'] ?? 'club'; // club, general, todos
$umbral_perdido = intval($_GET['umbral_perdido'] ?? 60);

try {
    // Definición de base de filtros
    $whereSimple = "WHERE Anulado = 0 AND Fecha BETWEEN :f_inicio AND :f_fin";
    $params = [':f_inicio' => $fecha_inicio, ':f_fin' => $fecha_fin];

    if ($sucursal && $sucursal !== 'todas') {
        $whereSimple .= " AND Sucursal_Nombre = :sucursal";
        $params[':sucursal'] = $sucursal;
    }

    if ($tipo_cliente === 'club') {
        $whereSimple .= " AND CodCliente > 0";
    } elseif ($tipo_cliente === 'general') {
        $whereSimple .= " AND CodCliente = 0";
    }

    // 1. Obtener Datos RFM (Agrupado por Cliente)
    $sqlRFM = "
        WITH ResumenPedidos AS (
            SELECT 
                CodCliente, CodPedido, MAX(Fecha) as Fecha, MAX(MontoFactura) as TotalPedido, MAX(Sucursal_Nombre) as Sucursal
            FROM VentasGlobalesAccessCSV
            $whereSimple
            GROUP BY CodPedido
        )
        SELECT 
            r.CodCliente,
            MAX(r.Sucursal) as Sucursal,
            DATEDIFF(CURDATE(), MAX(r.Fecha)) as Recency,
            COUNT(r.CodPedido) as Frequency,
            SUM(r.TotalPedido) as Monetary,
            MAX(CONCAT(COALESCE(c.nombre,''), ' ', COALESCE(c.apellido, ''))) as ClienteNombre,
            MAX(c.fecha_registro) as FechaRegistro
        FROM ResumenPedidos r
        LEFT JOIN clientesclub c ON r.CodCliente = c.membresia
        GROUP BY r.CodCliente
    ";

    $stmt = $conn->prepare($sqlRFM);
    $stmt->execute($params);
    $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Salir si no hay datos
    if (empty($raw_data)) {
        echo json_encode(['success' => true, 'summary' => null, 'message' => 'Sin datos']);
        exit;
    }

    // 2. Procesamiento de Segmentos (Lógica PHP por flexibilidad)
    $recencies = array_column($raw_data, 'Recency');
    $frequencies = array_column($raw_data, 'Frequency');
    $monetaries = array_column($raw_data, 'Monetary');
    sort($recencies); sort($frequencies); sort($monetaries);
    $total_count = count($raw_data);

    $get_q = function($val, $arr, $inv = false) use ($total_count) {
        $pos = array_search($val, $arr);
        $p = $pos / $total_count;
        if ($inv) $p = 1 - $p;
        if ($p <= 0.2) return 1;
        if ($p <= 0.4) return 2;
        if ($p <= 0.6) return 3;
        if ($p <= 0.8) return 4;
        return 5;
    };

    $segments = [
        'Champions' => 0, 'Loyal' => 0, 'New' => 0, 
        'At Risk' => 0, 'Hibernating' => 0, 'Lost' => 0
    ];

    foreach ($raw_data as &$row) {
        $row['R_Score'] = $get_q($row['Recency'], $recencies, true);
        $row['F_Score'] = $get_q($row['Frequency'], $frequencies);
        $row['M_Score'] = $get_q($row['Monetary'], $monetaries);
        $row['ScoreTotal'] = $row['R_Score'] + $row['F_Score'] + $row['M_Score'];

        $r = $row['R_Score']; $f = $row['F_Score'];
        if ($r >= 4 && $f >= 4) $seg = 'Champions';
        elseif ($r >= 3 && $f >= 3) $seg = 'Loyal';
        elseif ($r <= 2 && $f >= 3) $seg = 'At Risk';
        elseif ($r <= 2 && $f <= 2) $seg = 'Lost';
        elseif ($r >= 4 && $f <= 2) $seg = 'New';
        else $seg = 'Hibernating';

        $row['Segment'] = $seg;
        $segments[$seg]++;
    }

    // 3. KPIs Resumen
    $activos = count(array_filter($raw_data, fn($x) => $x['Recency'] <= $umbral_perdido));
    $en_riesgo = count(array_filter($raw_data, fn($x) => $x['Recency'] > ($umbral_perdido/2) && $x['Recency'] <= $umbral_perdido));
    $perdidos = count(array_filter($raw_data, fn($x) => $x['Recency'] > $umbral_perdido));
    
    // Clientes nuevos (Registrados en el periodo)
    $stmtNew = $conn->prepare("SELECT COUNT(*) as Nuevos FROM clientesclub WHERE fecha_registro BETWEEN :f_inicio AND :f_fin");
    $stmtNew->execute([':f_inicio' => $fecha_inicio, ':f_fin' => $fecha_fin]);
    $nuevos_res = $stmtNew->fetch();

    // Ingresos y Tickets
    $sum_m = array_sum($monetaries);
    $sum_f = array_sum($frequencies);
    $ticket_club = $sum_f > 0 ? $sum_m / $sum_f : 0;

    // 4. Evolución de Segmentos (Temporal) - Usamos el histórico real
    $sqlEvol = "
        SELECT DATE_FORMAT(Fecha, '%x-%v') as Semana, COUNT(DISTINCT CodPedido) as Pedidos
        FROM VentasGlobalesAccessCSV
        $whereSimple
        GROUP BY Semana ORDER BY Semana ASC
    ";
    $evol_data = $conn->prepare($sqlEvol);
    $evol_data->execute($params);
    $evolution = $evol_data->fetchAll(PDO::FETCH_ASSOC);

    // 5. Análisis por Sucursal
    $branch_stats = [];
    foreach ($raw_data as $r) {
        $bn = $r['Sucursal'] ?: 'Desconocida';
        if (!isset($branch_stats[$bn])) $branch_stats[$bn] = ['monto' => 0, 'count' => 0, 'score' => 0, 'segments' => []];
        $branch_stats[$bn]['monto'] += $r['Monetary'];
        $branch_stats[$bn]['count']++;
        $branch_stats[$bn]['score'] += $r['ScoreTotal'];
        $branch_stats[$bn]['segments'][$r['Segment']] = ($branch_stats[$bn]['segments'][$r['Segment']] ?? 0) + 1;
    }

    // 6. Hábitos (Heatmap y Top 10)
    $sqlHeatmap = "
        SELECT HOUR(Hora) as Hour, DAYOFWEEK(Fecha) as Day, COUNT(*) as Count
        FROM VentasGlobalesAccessCSV
        $whereSimple
        GROUP BY Hour, Day
    ";
    $stmtHeat = $conn->prepare($sqlHeatmap);
    $stmtHeat->execute($params);
    $heatmap = $stmtHeat->fetchAll(PDO::FETCH_ASSOC);

    $sqlTopProd = "
        SELECT DBBatidos_Nombre as Product, COUNT(*) as Count
        FROM VentasGlobalesAccessCSV
        $whereSimple AND Tipo IN ('Batido', 'Bowl', 'Limonada', 'Pitaya Store', 'Waffles')
        GROUP BY Product ORDER BY Count DESC LIMIT 10
    ";
    $stmtTP = $conn->prepare($sqlTopProd);
    $stmtTP->execute($params);
    $top_products = $stmtTP->fetchAll(PDO::FETCH_ASSOC);

    // 7. Respuesta Final Estructurada
    echo json_encode([
        'success' => true,
        'summary' => [
            'total_club' => $total_count,
            'activos' => $activos,
            'nuevos' => $nuevos_res['Nuevos'],
            'en_riesgo' => $en_riesgo,
            'perdidos' => $perdidos,
            'ticket_club' => round($ticket_club, 2),
            'monto_total' => round($sum_m, 2),
            'churn_rate' => round(($perdidos / $total_count) * 100, 2),
            'retention_rate' => calculateRetention($conn, $whereSimple, $params)
        ],
        'segments' => $segments,
        'evolution' => $evolution,
        'individual' => array_slice($raw_data, 0, 1000), // Limitamos para no saturar el JSON
        'branch_analysis' => $branch_stats,
        'habits' => [
            'top_products' => $top_products,
            'heatmap' => $heatmap
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Calcula la tasa de retención comparando visitas en la primera vs segunda mitad del periodo
 */
function calculateRetention($conn, $where, $params) {
    try {
        $sql = "SELECT CodCliente, Fecha FROM VentasGlobalesAccessCSV $where GROUP BY CodCliente, CodPedido, Fecha ORDER BY Fecha ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $visitas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($visitas)) return 0;

        $user_visits = [];
        $min_ts = null; $max_ts = null;
        foreach ($visitas as $v) {
            $ts = strtotime($v['Fecha']);
            $user_visits[$v['CodCliente']][] = $ts;
            if ($min_ts === null || $ts < $min_ts) $min_ts = $ts;
            if ($max_ts === null || $ts > $max_ts) $max_ts = $ts;
        }

        if ($min_ts === $max_ts) return 0;

        $ts_mitad = $min_ts + ($max_ts - $min_ts) / 2;
        $users_h1 = 0;
        $users_h1_returned = 0;

        foreach ($user_visits as $cid => $dates) {
            $has_h1 = false; $has_h2 = false;
            foreach ($dates as $d) {
                if ($d <= $ts_mitad) $has_h1 = true;
                if ($d > $ts_mitad) $has_h2 = true;
            }
            if ($has_h1) {
                $users_h1++;
                if ($has_h2) $users_h1_returned++;
            }
        }

        return ($users_h1 > 0) ? round(($users_h1_returned / $users_h1) * 100, 2) : 0;
    } catch (Exception $e) {
        return 0;
    }
}
?>