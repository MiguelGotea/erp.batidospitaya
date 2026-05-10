<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

if (isset($conn)) $conn->query("SET time_zone = '-06:00'");

header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);
set_time_limit(300);
ini_set('memory_limit', '1024M');

if (isset($conn)) {
    try {
        @$conn->exec("SET SESSION max_statement_time = 300");
        @$conn->exec("SET SESSION max_execution_time = 300000");
    } catch (Exception $e) {}
}

ob_start();

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
if (!tienePermiso('dashboard_rfm', 'vista', $cargoOperario)) {
    echo json_encode(['success' => false, 'message' => 'Sin permiso']);
    exit;
}

// ── Parámetros comunes ────────────────────────────────────────────────────────
$seccion       = $_POST['seccion']        ?? $_GET['seccion']        ?? 'habitos';
$fecha_inicio  = $_POST['fecha_inicio']   ?? $_GET['fecha_inicio']   ?? date('Y-m-d', strtotime('-90 days'));
$fecha_fin     = $_POST['fecha_fin']      ?? $_GET['fecha_fin']      ?? date('Y-m-d');
$sucursal      = $_POST['sucursal']       ?? $_GET['sucursal']       ?? null;

// Datos recibidos desde Fase 1 (solo para secciones que los necesitan)
$segmentos_json = $_POST['segmentos'] ?? '{}';
$clientes_json  = $_POST['clientes']  ?? '[]';
$clientSegments = json_decode($segmentos_json, true) ?: [];
$clientes       = json_decode($clientes_json, true)  ?: [];

try {
    $whereSimple = "WHERE Anulado = 0 AND Fecha BETWEEN :f_inicio AND :f_fin";
    $params      = [':f_inicio' => $fecha_inicio, ':f_fin' => $fecha_fin];

    $codigo_local = null;
    if ($sucursal && $sucursal !== 'todas') {
        $stmtLoc = $conn->prepare("SELECT codigo FROM sucursales WHERE nombre = :suc");
        $stmtLoc->execute([':suc' => $sucursal]);
        $codigo_local = $stmtLoc->fetchColumn() ?: -1;
        $whereSimple .= " AND local = :suc_local";
        $params[':suc_local'] = $codigo_local;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SECCIÓN: evolucion + sucursales  (necesita $clientSegments y $clientes)
    // ══════════════════════════════════════════════════════════════════════════
    if ($seccion === 'evolucion') {
        $paramsFO = [':fo_i' => $fecha_inicio, ':fo_f' => $fecha_fin];

        $sqlEvol = "
            SELECT S.numero_semana as Semana, V.CodPedido, MAX(V.CodCliente) as CodCliente
            FROM VentasGlobalesAccessCSV V
            JOIN SemanasSistema S ON V.Fecha BETWEEN S.fecha_inicio AND S.fecha_fin
            INNER JOIN (
                SELECT DISTINCT local, CodPedido FROM VentasGlobalesAccessCSV
                WHERE CodCliente > 0 AND Fecha BETWEEN :fo_i AND :fo_f AND Anulado = 0
            ) fo ON V.local = fo.local AND V.CodPedido = fo.CodPedido
            WHERE V.Anulado = 0 AND V.Fecha BETWEEN :f_inicio AND :f_fin
        ";
        if ($sucursal && $sucursal !== 'todas') $sqlEvol .= " AND V.local = :suc_local";
        $sqlEvol .= " GROUP BY S.numero_semana, V.local, V.CodPedido ORDER BY S.fecha_inicio ASC";

        $stmtEvol = $conn->prepare($sqlEvol);
        $stmtEvol->execute(array_merge($params, $paramsFO));
        $evolutionRaw = $stmtEvol->fetchAll(PDO::FETCH_ASSOC);

        $evolutionDetail = [];
        foreach ($evolutionRaw as $row) {
            $week = 'Sem ' . $row['Semana'];
            $seg  = $clientSegments[$row['CodCliente']] ?? 'Hibernating';
            if (!isset($evolutionDetail[$week]))
                $evolutionDetail[$week] = ['Semana' => $week, 'Champions' => 0, 'Loyal' => 0, 'New' => 0, 'At Risk' => 0, 'Hibernating' => 0, 'Lost' => 0];
            $evolutionDetail[$week][$seg]++;
        }

        // Branch analysis (aprovecha el mismo request)
        $stmtBP = $conn->prepare("
            SELECT s.nombre as Sucursal, SUM(t.MontoPedido) as TotalMonto, COUNT(*) as TotalPedidos
            FROM (
                SELECT local, CodPedido, MAX(MontoFactura) as MontoPedido
                FROM VentasGlobalesAccessCSV $whereSimple
                AND local IN (SELECT codigo FROM sucursales WHERE VMTAP = 1)
                GROUP BY local, CodPedido HAVING MAX(CodCliente) > 0
            ) t JOIN sucursales s ON t.local = s.codigo GROUP BY s.nombre
        ");
        $stmtBP->execute($params);
        $period_bench = $stmtBP->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

        $activeBranches = $conn->query("SELECT nombre FROM sucursales WHERE VMTAP = 1")->fetchAll(PDO::FETCH_COLUMN);

        $branch_stats = [];
        foreach ($clientes as $r) {
            $bn = $r['Sucursal'] ?: 'Desconocida';
            if (!in_array($bn, $activeBranches)) continue;
            if (!isset($branch_stats[$bn])) {
                $branch_stats[$bn] = [
                    'monto' => 0, 'count' => 0, 'score' => 0,
                    'segments' => [], 'top_customers' => [],
                    'period_monto'   => $period_bench[$bn]['TotalMonto']   ?? 0,
                    'period_pedidos' => $period_bench[$bn]['TotalPedidos'] ?? 0
                ];
            }
            $branch_stats[$bn]['monto']  += $r['Monetary'];
            $branch_stats[$bn]['count']++;
            $branch_stats[$bn]['score']  += $r['ScoreTotal'];
            $branch_stats[$bn]['segments'][$r['Segment']] = ($branch_stats[$bn]['segments'][$r['Segment']] ?? 0) + 1;
            $branch_stats[$bn]['top_customers'][] = ['name' => $r['ClienteNombre'], 'ltv' => $r['Monetary']];
        }
        foreach ($branch_stats as &$stats) {
            usort($stats['top_customers'], fn($a, $b) => $b['ltv'] <=> $a['ltv']);
            $stats['top_5_ltv'] = array_slice($stats['top_customers'], 0, 5);
            unset($stats['top_customers']);
        }
        unset($stats);

        ob_get_clean();
        echo json_encode([
            'success'         => true,
            'evolution'       => array_values($evolutionDetail),
            'branch_analysis' => $branch_stats
        ]);
        exit;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SECCIÓN: habitos + UltimoProducto
    // ══════════════════════════════════════════════════════════════════════════
    if ($seccion === 'habitos') {
        $paramsFO     = [':fo_i' => $fecha_inicio, ':fo_f' => $fecha_fin];
        $whereSimpleV = str_replace(['Anulado', 'Fecha', ' local '], ['v.Anulado', 'v.Fecha', ' v.local '], $whereSimple);
        $joinClub     = " INNER JOIN (SELECT DISTINCT local, CodPedido FROM VentasGlobalesAccessCSV WHERE CodCliente > 0 AND Fecha BETWEEN :fo_i AND :fo_f AND Anulado = 0) fo ON v.local = fo.local AND v.CodPedido = fo.CodPedido ";

        // Medida
        $stmtMed = $conn->prepare("
            SELECT v.Medida, COUNT(*) as Count FROM VentasGlobalesAccessCSV v
            JOIN DBBatidos d ON v.CodProducto = d.CodBatido
            JOIN GrupoProductosVenta g ON d.CodGrupo = g.CodGrupo
            $joinClub $whereSimpleV AND g.Tipo IN ('Batido', 'Limonada') GROUP BY v.Medida
        ");
        $stmtMed->execute(array_merge($params, $paramsFO));
        $h_medida = $stmtMed->fetchAll(PDO::FETCH_KEY_PAIR);

        // Modalidad + Promo
        $stmtHab = $conn->prepare("
            SELECT v.Modalidad,
                   (v.CodigoPromocion IS NOT NULL AND v.CodigoPromocion <> '' AND v.CodigoPromocion <> '5') as EsPromo,
                   COUNT(*) as Count
            FROM VentasGlobalesAccessCSV v
            JOIN DBBatidos d ON v.CodProducto = d.CodBatido
            JOIN GrupoProductosVenta g ON d.CodGrupo = g.CodGrupo
            $joinClub $whereSimpleV
            AND g.Tipo IN ('Batido', 'Limonada', 'Bowl', 'Membresia', 'Pitaya Store', 'Waffles')
            GROUP BY v.Modalidad, EsPromo
        ");
        $stmtHab->execute(array_merge($params, $paramsFO));
        $h_modalidad = [];
        $h_promo = ['si' => 0, 'no' => 0];
        foreach ($stmtHab->fetchAll(PDO::FETCH_ASSOC) as $hr) {
            $mod = ($hr['Modalidad'] && trim($hr['Modalidad']) !== '') ? $hr['Modalidad'] : 'General';
            $h_modalidad[$mod] = ($h_modalidad[$mod] ?? 0) + $hr['Count'];
            if ($hr['EsPromo']) $h_promo['si'] += $hr['Count'];
            else                $h_promo['no'] += $hr['Count'];
        }

        // Heatmap
        $stmtHeat = $conn->prepare("
            SELECT HOUR(v.Hora) as Hour,
                   CASE WHEN DAYOFWEEK(v.Fecha) = 1 THEN 7 ELSE DAYOFWEEK(v.Fecha) - 1 END as Day,
                   COUNT(DISTINCT CONCAT(v.local, '-', v.CodPedido)) as Count
            FROM VentasGlobalesAccessCSV v $joinClub $whereSimpleV GROUP BY Hour, Day
        ");
        $stmtHeat->execute(array_merge($params, $paramsFO));
        $heatmap = $stmtHeat->fetchAll(PDO::FETCH_ASSOC);

        // Top Productos
        $stmtTP = $conn->prepare("
            SELECT v.DBBatidos_Nombre as Product, COUNT(*) as Count
            FROM VentasGlobalesAccessCSV v
            JOIN DBBatidos d ON v.CodProducto = d.CodBatido
            JOIN GrupoProductosVenta g ON d.CodGrupo = g.CodGrupo
            $joinClub $whereSimpleV
            AND g.Tipo IN ('Batido', 'Bowl', 'Limonada', 'Pitaya Store', 'Waffles')
            GROUP BY Product ORDER BY Count DESC LIMIT 10
        ");
        $stmtTP->execute(array_merge($params, $paramsFO));
        $top_products = $stmtTP->fetchAll(PDO::FETCH_ASSOC);

        ob_get_clean();
        echo json_encode([
            'success' => true,
            'habits'  => [
                'top_products' => $top_products,
                'heatmap'      => $heatmap,
                'medida'       => $h_medida,
                'modalidad'    => $h_modalidad,
                'promo'        => $h_promo
            ]
        ]);
        exit;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SECCIÓN: retencion + UltimoProducto
    // ══════════════════════════════════════════════════════════════════════════
    if ($seccion === 'retencion') {
        $retention = calcRetention($conn, $whereSimple, $params);

        // UltimoProducto
        $ultimo_producto = [];
        if (!empty($clientes)) {
            $ids      = array_column($clientes, 'CodCliente');
            $inClause = implode(',', array_map('intval', $ids));
            $sqlLast  = "
                SELECT v.CodCliente, v.DBBatidos_Nombre
                FROM VentasGlobalesAccessCSV v
                INNER JOIN (
                    SELECT CodCliente, MAX(Fecha) as MaxF, MAX(Hora) as MaxH
                    FROM VentasGlobalesAccessCSV
                    WHERE CodCliente IN ($inClause) AND Anulado = 0
                    GROUP BY CodCliente
                ) t ON v.CodCliente = t.CodCliente AND v.Fecha = t.MaxF AND v.Hora = t.MaxH
            ";
            $ultimo_producto = $conn->query($sqlLast)->fetchAll(PDO::FETCH_KEY_PAIR);
        }

        ob_get_clean();
        echo json_encode([
            'success'         => true,
            'retention'       => $retention,
            'ultimo_producto' => $ultimo_producto
        ]);
        exit;
    }

    ob_get_clean();
    echo json_encode(['success' => false, 'message' => 'Sección no reconocida: ' . $seccion]);

} catch (Throwable $e) {
    ob_get_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'line' => $e->getLine()]);
}

function calcRetention($conn, $where, $params)
{
    try {
        $start = new DateTime($params[':f_inicio']);
        $end   = new DateTime($params[':f_fin']);
        $diff  = $start->diff($end)->days + 1;

        $p_start = clone $start; $p_start->modify("-{$diff} days");
        $p_end   = clone $start; $p_end->modify("-1 day");
        $p_inicio = $p_start->format('Y-m-d');
        $p_fin    = $p_end->format('Y-m-d');

        $whereH1 = str_replace([':f_inicio', ':f_fin', ':suc_local'], [':p_inicio', ':p_fin', ':p_suc_local'], $where);
        $paramsH1 = [':p_inicio' => $p_inicio, ':p_fin' => $p_fin];
        if (isset($params[':suc_local'])) $paramsH1[':p_suc_local'] = $params[':suc_local'];

        $stmtH1 = $conn->prepare("SELECT COUNT(DISTINCT CodCliente) FROM VentasGlobalesAccessCSV $whereH1 AND CodCliente > 0");
        $stmtH1->execute($paramsH1);
        $h1 = (int)$stmtH1->fetchColumn();
        if ($h1 === 0) return ['rate' => 0, 'h1' => 0, 'h2' => 0];

        $combined = $params;
        $combined[':p_inicio'] = $p_inicio;
        $combined[':p_fin']    = $p_fin;
        if (isset($params[':suc_local'])) $combined[':p_suc_local'] = $params[':suc_local'];

        $stmtH2 = $conn->prepare("
            SELECT COUNT(DISTINCT CodCliente) FROM VentasGlobalesAccessCSV $where
            AND CodCliente > 0
            AND CodCliente IN (SELECT CodCliente FROM VentasGlobalesAccessCSV $whereH1 AND CodCliente > 0)
        ");
        $stmtH2->execute($combined);
        $h2 = (int)$stmtH2->fetchColumn();

        return ['rate' => round(($h2 / $h1) * 100, 2), 'h1' => $h1, 'h2' => $h2];
    } catch (Exception $e) {
        return ['rate' => 0, 'h1' => 0, 'h2' => 0, 'error' => $e->getMessage()];
    }
}
?>
