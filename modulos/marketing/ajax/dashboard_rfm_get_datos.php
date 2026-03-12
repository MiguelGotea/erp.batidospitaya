<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

// Sincronizar zona horaria de MySQL para este script
if(isset($conn)) $conn->query("SET time_zone = '-06:00'");

header('Content-Type: application/json');
set_time_limit(120);
ini_set('memory_limit', '512M');

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
    $whereVmtap = " AND Sucursal_Nombre IN (SELECT nombre FROM sucursales WHERE VMTAP = 1)";
    
    // Filtro Global (Ignora Fecha para Salud/RFM)
    $whereGlobal = "WHERE Anulado = 0" . $whereVmtap;
    $paramsGlobal = [];

    // Filtro Periodo (Para Rendimiento/Ventas)
    $whereSimple = "WHERE Anulado = 0 AND Fecha BETWEEN :f_inicio AND :f_fin" . $whereVmtap;
    $params = [':f_inicio' => $fecha_inicio, ':f_fin' => $fecha_fin];

    if ($sucursal && $sucursal !== 'todas') {
        $whereSimple .= " AND Sucursal_Nombre = :sucursal";
        $whereGlobal .= " AND Sucursal_Nombre = :suc_global";
        $params[':sucursal'] = $sucursal;
        $paramsGlobal[':suc_global'] = $sucursal;
    }

    if ($tipo_cliente === 'club') {
        $whereSimple .= " AND CodCliente > 0";
        $whereGlobal .= " AND CodCliente > 0";
    } elseif ($tipo_cliente === 'general') {
        $whereSimple .= " AND CodCliente = 0";
        $whereGlobal .= " AND CodCliente = 0";
    }

    // 0.1 Participación Ingresos (Club vs General) - Independiente del filtro tipo_cliente
    $wherePart = "WHERE Anulado = 0 AND Fecha BETWEEN :f_inicio AND :f_fin" . $whereVmtap;
    if ($sucursal && $sucursal !== 'todas') { $wherePart .= " AND Sucursal_Nombre = :suc_part"; }
    
    $sqlPart = "
        SELECT 
            (ClienteID > 0) as EsClub, 
            SUM(MontoFactura) as Total 
        FROM (
            SELECT 
                CodPedido, 
                MAX(CodCliente) as ClienteID, 
                MAX(MontoFactura) as MontoFactura 
            FROM VentasGlobalesAccessCSV 
            $wherePart 
            GROUP BY CodPedido
        ) t 
        GROUP BY EsClub
    ";
    
    $stmtPart = $conn->prepare($sqlPart);
    $paramsPart = [':f_inicio' => $fecha_inicio, ':f_fin' => $fecha_fin];
    if ($sucursal && $sucursal !== 'todas') { $paramsPart[':suc_part'] = $sucursal; }
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

    $whereClub = "WHERE fecha_registro BETWEEN :p_inicio AND :p_fin AND nombre_sucursal IN (SELECT nombre FROM sucursales WHERE VMTAP = 1)";
    $paramsClub = [':p_inicio' => $p_inicio, ':p_fin' => $p_fin];
    if ($sucursal && $sucursal !== 'todas') {
        $whereClub .= " AND nombre_sucursal = :suc_club";
        $paramsClub[':suc_club'] = $sucursal;
    }

    $sqlPrev = "SELECT COUNT(DISTINCT membresia) as PrevNuevos FROM clientesclub $whereClub";
    $stmtPrev = $conn->prepare($sqlPrev);
    $stmtPrev->execute($paramsClub);
    $prev_nuevos = $stmtPrev->fetch(PDO::FETCH_ASSOC)['PrevNuevos'] ?? 0;

    // 1. Obtener Datos RFM (Agrupado por Cliente)
    $sqlRFM = "
        WITH ResumenPedidos AS (
            SELECT 
                MAX(CodCliente) as CodCliente, 
                CodPedido, 
                MAX(Fecha) as Fecha, 
                MAX(MontoFactura) as TotalPedido, 
                MAX(Sucursal_Nombre) as Sucursal
            FROM VentasGlobalesAccessCSV
            $whereGlobal
            GROUP BY CodPedido
        )
        SELECT 
            r.CodCliente,
            COALESCE(NULLIF(MAX(c.nombre_sucursal), ''), MAX(r.Sucursal)) as Sucursal,
            DATEDIFF(CURDATE(), MAX(r.Fecha)) as Recency,
            COUNT(r.CodCliente) as Frequency,
            SUM(r.TotalPedido) as Monetary,
            MAX(CONCAT(COALESCE(c.nombre,''), ' ', COALESCE(c.apellido, ''))) as ClienteNombre,
            MAX(c.fecha_registro) as FechaRegistro
        FROM ResumenPedidos r
        LEFT JOIN clientesclub c ON r.CodCliente = c.membresia
        GROUP BY r.CodCliente
    ";

    $stmt = $conn->prepare($sqlRFM);
    $stmt->execute($paramsGlobal);
    $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Conteo Universo Total de Socios (Para % de base)
    $whereUniverso = "WHERE VMTAP = 1";
    $paramsUniv = [];
    if($sucursal && $sucursal !== 'todas') {
        $whereUniverso .= " AND nombre = :s_univ";
        $paramsUniv[':s_univ'] = $sucursal;
    }
    $total_universo = $conn->prepare("SELECT COUNT(*) FROM clientesclub WHERE nombre_sucursal IN (SELECT nombre FROM sucursales $whereUniverso)");
    $total_universo->execute($paramsUniv);
    $universo_count = $total_universo->fetchColumn() ?: 1;

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

    $activos = 0; $en_riesgo = 0; $perdidos = 0;

    foreach ($raw_data as &$row) {
        $row['R_Score'] = $get_q($row['Recency'], $recencies, true);
        $row['F_Score'] = $get_q($row['Frequency'], $frequencies);
        $row['M_Score'] = $get_q($row['Monetary'], $monetaries);
        $row['ScoreTotal'] = $row['R_Score'] + $row['F_Score'] + $row['M_Score'];

        $r = $row['R_Score']; $f = $row['F_Score'];
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
            if ($r >= 4 && $f >= 4) $seg = 'Champions';
            elseif ($r >= 3 && $f >= 3) $seg = 'Loyal';
            elseif ($r >= 4 && $f <= 2) $seg = 'New';
            else $seg = 'Hibernating';
            $activos++;
        }

        $row['Segment'] = $seg;
        $segments[$seg]++;
    }

    // 3. KPIs Resumen
    $activos = count(array_filter($raw_data, fn($x) => $x['Recency'] <= $umbral_perdido));
    $en_riesgo = count(array_filter($raw_data, fn($x) => $x['Recency'] > ($umbral_perdido/2) && $x['Recency'] <= $umbral_perdido));
    $perdidos = count(array_filter($raw_data, fn($x) => $x['Recency'] > $umbral_perdido));
    
    // Clientes nuevos (Registrados en el periodo)
    $whereClubNow = "WHERE fecha_registro BETWEEN :f_inicio AND :f_fin AND nombre_sucursal IN (SELECT nombre FROM sucursales WHERE VMTAP = 1)";
    $paramsClubNow = [':f_inicio' => $fecha_inicio, ':f_fin' => $fecha_fin];
    if ($sucursal && $sucursal !== 'todas') {
        $whereClubNow .= " AND nombre_sucursal = :suc_club_now";
        $paramsClubNow[':suc_club_now'] = $sucursal;
    }

    $stmtNew = $conn->prepare("SELECT COUNT(*) as Nuevos FROM clientesclub $whereClubNow");
    $stmtNew->execute($paramsClubNow);
    $nuevos_res = $stmtNew->fetch();

    // Ingresos y Tickets del PERIODO (para KPIs de Rendimiento)
    $stmtPeriod = $conn->prepare("
        SELECT 
            SUM(MontoPedido) as TotalIngresos,
            COUNT(*) as TotalPedidos
        FROM (
            SELECT CodPedido, MAX(MontoFactura) as MontoPedido 
            FROM VentasGlobalesAccessCSV 
            $whereSimple AND CodCliente > 0
            GROUP BY CodPedido
        ) t
    ");
    $stmtPeriod->execute($params);
    $period_stats = $stmtPeriod->fetch(PDO::FETCH_ASSOC);
    
    $sum_m_period = $period_stats['TotalIngresos'] ?? 0;
    $sum_f_period = $period_stats['TotalPedidos'] ?? 0;
    $ticket_club = $sum_f_period > 1 ? $sum_m_period / $sum_f_period : 0;

    // 4. Evolución de Segmentos (Temporal) - Usamos el histórico real y SemanasSistema
    $sqlEvol = "
        SELECT S.numero_semana as Semana, COUNT(DISTINCT V.CodPedido) as Pedidos
        FROM VentasGlobalesAccessCSV V
        JOIN SemanasSistema S ON V.Fecha BETWEEN S.fecha_inicio AND S.fecha_fin
        WHERE V.Anulado = 0 AND V.Fecha BETWEEN :f_inicio AND :f_fin
    ";
    if ($sucursal && $sucursal !== 'todas') { $sqlEvol .= " AND V.Sucursal_Nombre = :sucursal"; }
    if ($tipo_cliente === 'club') { $sqlEvol .= " AND V.CodCliente > 0"; } 
    elseif ($tipo_cliente === 'general') { $sqlEvol .= " AND V.CodCliente = 0"; }
    $sqlEvol .= " GROUP BY S.numero_semana, S.fecha_inicio ORDER BY S.fecha_inicio ASC";
    $stmtEvol = $conn->prepare($sqlEvol);
    $stmtEvol->execute($params);
    $evolution = $stmtEvol->fetchAll(PDO::FETCH_ASSOC);

    // 5. Análisis por Sucursal y Detalle Individual Incremental
    $branch_stats = [];
    $segment_revenue = [];
    foreach ($raw_data as &$r) {
        $bn = $r['Sucursal'] ?: 'Desconocida';
        if (!isset($branch_stats[$bn])) {
            $branch_stats[$bn] = [
                'monto' => 0, 
                'count' => 0, 
                'score' => 0, 
                'segments' => [],
                'top_customers' => [] // Para calcular el top 5 después
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

        $segment_revenue[$r['Segment']] = ($segment_revenue[$r['Segment']] ?? 0) + $r['Monetary'];

        // Enriquecer registro individual
        $r['TicketPromedio'] = ($r['Frequency'] > 0) ? $r['Monetary'] / $r['Frequency'] : 0;
        $r['Antiguedad'] = $r['FechaRegistro'] ? (int)floor((time() - strtotime($r['FechaRegistro'])) / 86400) : 0;
    }

    // Procesar Top 5 por sucursal
    foreach ($branch_stats as $bn => &$stats) {
        usort($stats['top_customers'], function($a, $b) {
            return $b['ltv'] <=> $a['ltv'];
        });
        $stats['top_5_ltv'] = array_slice($stats['top_customers'], 0, 5);
        unset($stats['top_customers']); // Limpiar para no enviar datos excesivos
    }
    unset($stats);

    // Obtener Último Producto para los top 1000 de forma eficiente
    if (!empty($raw_data)) {
        $slice = array_slice($raw_data, 0, 1000);
        $ids = array_column($slice, 'CodCliente');
        $inClause = implode(',', array_map('intval', $ids));
        
        $sqlLast = "
            SELECT v.CodCliente, v.DBBatidos_Nombre 
            FROM VentasGlobalesAccessCSV v
            INNER JOIN (
                SELECT CodCliente, MAX(Fecha) as MaxF, MAX(Hora) as MaxH
                FROM VentasGlobalesAccessCSV
                WHERE CodCliente IN ($inClause) AND Anulado = 0
                GROUP BY CodCliente
            ) t ON v.CodCliente = t.CodCliente AND v.Fecha = t.MaxF AND v.Hora = t.MaxH
        ";
        
        $last_res = $conn->query($sqlLast)->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($raw_data as &$r) {
            $r['UltimoProducto'] = $last_res[$r['CodCliente']] ?? '--';
        }
    }

    // 6. Hábitos Expandidos
    $sqlHabits = "
        SELECT 
            Medida, Modalidad, (CodigoPromocion IS NOT NULL AND CodigoPromocion <> '') as EsPromo, COUNT(*) as Count
        FROM VentasGlobalesAccessCSV
        $whereSimple
        GROUP BY Medida, Modalidad, EsPromo
    ";
    $stmtHab = $conn->prepare($sqlHabits);
    $stmtHab->execute($params);
    $habits_raw = $stmtHab->fetchAll(PDO::FETCH_ASSOC);

    $h_medida = []; $h_modalidad = []; $h_promo = ['si' => 0, 'no' => 0];
    foreach ($habits_raw as $hr) {
        $m = ($hr['Medida'] && trim($hr['Medida']) !== '') ? $hr['Medida'] : 'No especificada';
        $mod = ($hr['Modalidad'] && trim($hr['Modalidad']) !== '') ? $hr['Modalidad'] : 'General';
        
        $h_medida[$m] = ($h_medida[$m] ?? 0) + $hr['Count'];
        $h_modalidad[$mod] = ($h_modalidad[$mod] ?? 0) + $hr['Count'];
        
        if ($hr['EsPromo']) $h_promo['si'] += $hr['Count']; 
        else $h_promo['no'] += $hr['Count'];
    }

    $sqlHeatmap = "
        SELECT 
            HOUR(Hora) as Hour, 
            CASE WHEN DAYOFWEEK(Fecha) = 1 THEN 7 ELSE DAYOFWEEK(Fecha) - 1 END as Day, 
            COUNT(DISTINCT CodPedido) as Count 
        FROM VentasGlobalesAccessCSV 
        $whereSimple 
        GROUP BY Hour, Day
    ";
    $stmtHeat = $conn->prepare($sqlHeatmap);
    $stmtHeat->execute($params);
    $heatmap = $stmtHeat->fetchAll(PDO::FETCH_ASSOC);

    $sqlTopProd = "SELECT DBBatidos_Nombre as Product, COUNT(*) as Count FROM VentasGlobalesAccessCSV $whereSimple AND Tipo IN ('Batido', 'Bowl', 'Limonada', 'Pitaya Store', 'Waffles') GROUP BY Product ORDER BY Count DESC LIMIT 10";
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
            'prev_nuevos' => $prev_nuevos,
            'en_riesgo' => $en_riesgo,
            'perdidos' => $perdidos,
            'ticket_club' => round($ticket_club, 2),
            'monto_total' => round($sum_m_period, 2),
            'churn_rate' => round(($perdidos / max(1, $total_count)) * 100, 2),
            'retention_metrics' => calculateRetentionDetail($conn, $whereSimple, $params),
            'participacion' => $participacion,
            'universo_total' => $universo_count,
            'raw' => [
                'total_pedidos' => $sum_f_period,
                'total_ingresos' => $sum_m_period
            ]
        ],
        'segments' => $segments,
        'segment_revenue' => $segment_revenue,
        'evolution' => $evolution,
        'individual' => array_slice($raw_data, 0, 1000),
        'branch_analysis' => $branch_stats,
        'habits' => [
            'top_products' => $top_products,
            'heatmap' => $heatmap,
            'medida' => $h_medida,
            'modalidad' => $h_modalidad,
            'promo' => $h_promo
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function calculateRetentionDetail($conn, $where, $params) {
    try {
        // 1. Obtener los límites de tiempo del periodo filtrado
        $i_f = $params[':f_inicio'];
        $i_t = $params[':f_fin'];
        
        $start = new DateTime($i_f);
        $end = new DateTime($i_t);
        // Diferencia en días para calcular el periodo anterior equivalente
        $diff = $start->diff($end)->days + 1;
        
        $p_start = clone $start;
        $p_start->modify("-{$diff} days");
        $p_end = clone $start;
        $p_end->modify("-1 day");
        
        $p_inicio = $p_start->format('Y-m-d');
        $p_fin = $p_end->format('Y-m-d');

        // 2. Definir filtros para el Periodo Anterior (Cohort H1)
        $whereH1 = str_replace([':f_inicio', ':f_fin'], [':p_inicio', ':p_fin'], $where);
        $paramsH1 = $params;
        $paramsH1[':p_inicio'] = $p_inicio;
        $paramsH1[':p_fin'] = $p_fin;
        // Limpiamos los params originales para el subquery
        unset($paramsH1[':f_inicio'], $paramsH1[':f_fin']);
        
        // Conteo H1
        $sqlH1 = "SELECT COUNT(DISTINCT CodCliente) FROM VentasGlobalesAccessCSV $whereH1 AND CodCliente > 0";
        $stmtH1 = $conn->prepare($sqlH1);
        $stmtH1->execute($paramsH1);
        $h1_count = (int)$stmtH1->fetchColumn();

        if ($h1_count === 0) return ['rate' => 0, 'h1' => 0, 'h2' => 0];

        // 3. Contar cuántos de ese cohort (H1) compraron en el periodo actual (H2)
        // Parámetros finales para el query principal
        $paramsFinal = $params;
        $paramsFinal[':p_inicio'] = $p_inicio;
        $paramsFinal[':p_fin'] = $p_fin;

        $sqlH2 = "
            SELECT COUNT(DISTINCT CodCliente)
            FROM VentasGlobalesAccessCSV
            $where
            AND CodCliente > 0
            AND CodCliente IN (
                SELECT CodCliente 
                FROM VentasGlobalesAccessCSV 
                $whereH1 AND CodCliente > 0
            )
        ";

        $stmtH2 = $conn->prepare($sqlH2);
        $stmtH2->execute($paramsFinal);
        $h2_retained = (int)$stmtH2->fetchColumn();

        return [
            'rate' => round(($h2_retained / $h1_count) * 100, 2),
            'h1' => $h1_count,
            'h2' => $h2_retained
        ];
    } catch (Exception $e) {
        return ['rate' => 0, 'h1' => 0, 'h2' => 0, 'error' => $e->getMessage()];
    }
}
?>