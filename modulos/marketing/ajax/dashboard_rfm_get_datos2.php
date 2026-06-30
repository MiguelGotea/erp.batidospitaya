<?php
require_once '../../../core/auth/auth.php';
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

    if (in_array($seccion, ['evolucion', 'habitos'])) {
        $conn->exec("CREATE TEMPORARY TABLE IF NOT EXISTS tmp_pedidos_club_rfm (local INT, CodPedido VARCHAR(50), KEY(local, CodPedido))");
        $conn->exec("TRUNCATE TABLE tmp_pedidos_club_rfm");
        
        $sqlTmp = "INSERT INTO tmp_pedidos_club_rfm (local, CodPedido) 
                   SELECT DISTINCT local, CodPedido 
                   FROM VentasGlobalesAccessCSV $whereSimple AND CodCliente > 0";
        $stmtTmp = $conn->prepare($sqlTmp);
        $stmtTmp->execute($params);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SECCIÓN: evolucion + sucursales  (necesita $clientSegments y $clientes)
    // ══════════════════════════════════════════════════════════════════════════
    if ($seccion === 'evolucion') {
        $sqlEvol = "
            SELECT S.numero_semana as Semana, V.CodPedido, MAX(V.CodCliente) as CodCliente
            FROM VentasGlobalesAccessCSV V
            JOIN SemanasSistema S ON V.Fecha BETWEEN S.fecha_inicio AND S.fecha_fin
            INNER JOIN tmp_pedidos_club_rfm fo ON V.local = fo.local AND V.CodPedido = fo.CodPedido
            WHERE V.Anulado = 0 AND V.Fecha BETWEEN :f_inicio AND :f_fin
        ";
        if ($sucursal && $sucursal !== 'todas') $sqlEvol .= " AND V.local = :suc_local";
        $sqlEvol .= " GROUP BY S.numero_semana, V.local, V.CodPedido ORDER BY S.fecha_inicio ASC";

        $stmtEvol = $conn->prepare($sqlEvol);
        $stmtEvol->execute($params);
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
        $whereSimpleV = str_replace(['Anulado', 'Fecha', ' local '], ['v.Anulado', 'v.Fecha', ' v.local '], $whereSimple);

        // CREATE A MASTER TEMP TABLE FOR HABITS TO AVOID 4 FULL SCANS
        $conn->exec("CREATE TEMPORARY TABLE IF NOT EXISTS tmp_vtas_habitos (
            Medida VARCHAR(50), Modalidad VARCHAR(50), CodigoPromocion VARCHAR(50), 
            Hora TIME, Fecha DATE, local INT, CodPedido VARCHAR(50), 
            Tipo VARCHAR(50), DBBatidos_Nombre VARCHAR(100)
        )");
        $conn->exec("TRUNCATE TABLE tmp_vtas_habitos");

        $sqlV = "
            INSERT INTO tmp_vtas_habitos (Medida, Modalidad, CodigoPromocion, Hora, Fecha, local, CodPedido, Tipo, DBBatidos_Nombre)
            SELECT v.Medida, v.Modalidad, v.CodigoPromocion, v.Hora, v.Fecha, v.local, v.CodPedido, g.Tipo, v.DBBatidos_Nombre
            FROM VentasGlobalesAccessCSV v
            INNER JOIN tmp_pedidos_club_rfm fo ON v.local = fo.local AND v.CodPedido = fo.CodPedido
            JOIN DBBatidos d ON v.CodProducto = d.CodBatido
            JOIN GrupoProductosVenta g ON d.CodGrupo = g.CodGrupo
            $whereSimpleV
        ";
        $stmtV = $conn->prepare($sqlV);
        $stmtV->execute($params);

        // Medida
        $stmtMed = $conn->query("
            SELECT Medida, COUNT(*) as Count FROM tmp_vtas_habitos
            WHERE Tipo IN ('Batido', 'Limonada') GROUP BY Medida
        ");
        $h_medida = $stmtMed->fetchAll(PDO::FETCH_KEY_PAIR);

        // Modalidad + Promo
        $stmtHab = $conn->query("
            SELECT Modalidad,
                   (CodigoPromocion IS NOT NULL AND CodigoPromocion <> '' AND CodigoPromocion <> '5') as EsPromo,
                   COUNT(*) as Count
            FROM tmp_vtas_habitos
            WHERE Tipo IN ('Batido', 'Limonada', 'Bowl', 'Membresia', 'Pitaya Store', 'Waffles')
            GROUP BY Modalidad, EsPromo
        ");
        $h_modalidad = [];
        $h_promo = ['si' => 0, 'no' => 0];
        foreach ($stmtHab->fetchAll(PDO::FETCH_ASSOC) as $hr) {
            $mod = ($hr['Modalidad'] && trim($hr['Modalidad']) !== '') ? $hr['Modalidad'] : 'General';
            $h_modalidad[$mod] = ($h_modalidad[$mod] ?? 0) + $hr['Count'];
            if ($hr['EsPromo']) $h_promo['si'] += $hr['Count'];
            else                $h_promo['no'] += $hr['Count'];
        }

        // Heatmap
        $stmtHeat = $conn->query("
            SELECT HOUR(Hora) as Hour,
                   CASE WHEN DAYOFWEEK(Fecha) = 1 THEN 7 ELSE DAYOFWEEK(Fecha) - 1 END as Day,
                   COUNT(DISTINCT CONCAT(local, '-', CodPedido)) as Count
            FROM tmp_vtas_habitos GROUP BY Hour, Day
        ");
        $heatmap = $stmtHeat->fetchAll(PDO::FETCH_ASSOC);

        // Top Productos
        $stmtTP = $conn->query("
            SELECT DBBatidos_Nombre as Product, COUNT(*) as Count
            FROM tmp_vtas_habitos
            WHERE Tipo IN ('Batido', 'Bowl', 'Limonada', 'Pitaya Store', 'Waffles')
            GROUP BY Product ORDER BY Count DESC LIMIT 10
        ");
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
            $conn->exec("CREATE TEMPORARY TABLE IF NOT EXISTS tmp_clientes_rfm (CodCliente INT, PRIMARY KEY(CodCliente))");
            $conn->exec("TRUNCATE TABLE tmp_clientes_rfm");
            
            $ids = array_column($clientes, 'CodCliente');
            $chunks = array_chunk($ids, 1000);
            foreach ($chunks as $chunk) {
                $values = array_map(fn($id) => '(' . intval($id) . ')', $chunk);
                $conn->exec("INSERT INTO tmp_clientes_rfm (CodCliente) VALUES " . implode(',', $values));
            }

            // Using GROUP_CONCAT to avoid nested loop joining of VentasGlobalesAccessCSV
            // Bound to last 12 months to avoid full table scan execution times.
            $sqlLast = "
                SELECT v.CodCliente, 
                       SUBSTRING_INDEX(GROUP_CONCAT(v.DBBatidos_Nombre ORDER BY v.Fecha DESC, v.Hora DESC SEPARATOR '||'), '||', 1) as DBBatidos_Nombre
                FROM VentasGlobalesAccessCSV v
                INNER JOIN tmp_clientes_rfm tc ON v.CodCliente = tc.CodCliente
                WHERE v.Anulado = 0 AND v.DBBatidos_Nombre IS NOT NULL AND v.DBBatidos_Nombre != ''
                  AND v.Fecha >= DATE_SUB(:f_inicio, INTERVAL 1 YEAR)
                GROUP BY v.CodCliente
            ";
            $stmtLast = $conn->prepare($sqlLast);
            $stmtLast->execute([':f_inicio' => $fecha_inicio]);
            $ultimo_producto = $stmtLast->fetchAll(PDO::FETCH_KEY_PAIR);
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
            SELECT COUNT(t1.CodCliente) 
            FROM (SELECT DISTINCT CodCliente FROM VentasGlobalesAccessCSV $where AND CodCliente > 0) t1
            INNER JOIN (SELECT DISTINCT CodCliente FROM VentasGlobalesAccessCSV $whereH1 AND CodCliente > 0) t2 
            ON t1.CodCliente = t2.CodCliente
        ");
        $stmtH2->execute($combined);
        $h2 = (int)$stmtH2->fetchColumn();

        return ['rate' => round(($h2 / $h1) * 100, 2), 'h1' => $h1, 'h2' => $h2];
    } catch (Exception $e) {
        return ['rate' => 0, 'h1' => 0, 'h2' => 0, 'error' => $e->getMessage()];
    }
}
?>
