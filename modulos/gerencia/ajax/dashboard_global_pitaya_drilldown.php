<?php
/**
 * AJAX — Drill-Down Tendencia de Ventas · Ventas diarias por sucursal
 * modulos/gerencia/ajax/dashboard_global_pitaya_drilldown.php
 *
 * Recibe: POST { mes: 'YYYY-MM' }
 * Devuelve:
 *   {
 *     success: true,
 *     mes: 'YYYY-MM',
 *     mes_label: 'Mar \'26',
 *     dias: ['YYYY-MM-DD', ...],      // todos los días del mes con datos
 *     tiendas: ['Tienda A', ...],     // sucursales (sin 'Total')
 *     series: {
 *       'Tienda A': [1200.0, 980.5, ...],  // valor por cada día en dias[]
 *       'Total':    [2070.0, 980.5, ...],
 *     }
 *   }
 */
ob_start();

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('dashboard_global_pitaya', 'vista', $cargoOperario)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Sin acceso']);
    exit;
}

// ── Validar y parsear el mes ───────────────────────────────────
$mes = trim($_POST['mes'] ?? '');
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Mes inválido']);
    exit;
}

$anioMes = (int) substr($mes, 0, 4);
$numMes  = (int) substr($mes, 5, 2);
if ($numMes < 1 || $numMes > 12) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Mes fuera de rango']);
    exit;
}

$MESES_ES = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
$mesLabel  = $MESES_ES[$numMes - 1] . " '" . substr($anioMes, 2);

// Primer y último día del mes
$mesIni = $mes . '-01';
$mesFin = date('Y-m-t', strtotime($mesIni));

try {
    // ── Ventas diarias por sucursal ────────────────────────────
    $sql = "
        SELECT
            DATE(v.Fecha)          AS dia,
            v.Sucursal_Nombre      AS tienda,
            SUM(CASE WHEN v.Anulado = 0 THEN v.Precio ELSE 0 END)                        AS ventas,
            COUNT(DISTINCT CASE WHEN v.Anulado = 0 THEN v.CodPedido ELSE NULL END)        AS pedidos
        FROM VentasGlobalesAccessCSV v
        INNER JOIN sucursales s ON s.codigo = v.local
        WHERE v.Fecha BETWEEN :ini AND :fin
          AND s.sucursal = 1
          AND v.Anulado = 0
        GROUP BY DATE(v.Fecha), v.Sucursal_Nombre
        ORDER BY dia ASC, v.Sucursal_Nombre ASC
    ";
    $st = $conn->prepare($sql);
    $st->execute([':ini' => $mesIni, ':fin' => $mesFin]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        ob_clean();
        echo json_encode([
            'success'   => true,
            'mes'       => $mes,
            'mes_label' => $mesLabel,
            'dias'      => [],
            'tiendas'   => [],
            'series'    => (object)[],
            'sin_datos' => true,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── Construir estructura: sets de días y tiendas ───────────
    $diasSet    = [];
    $tiendasSet = [];
    foreach ($rows as $r) {
        $diasSet[$r['dia']]       = true;
        $tiendasSet[$r['tienda']] = true;
    }
    $dias    = array_keys($diasSet);
    $tiendas = array_keys($tiendasSet);
    sort($dias);
    sort($tiendas);

    // Índice de día → posición en el array de días
    $diaIdx = array_flip($dias);

    // Inicializar series con 0 para cada día
    $series = [];
    foreach ($tiendas as $t) {
        $series[$t] = array_fill(0, count($dias), 0.0);
    }
    $totalPorDia = array_fill(0, count($dias), 0.0);

    // Rellenar valores
    foreach ($rows as $r) {
        $pos           = $diaIdx[$r['dia']];
        $ventas        = (float) $r['ventas'];
        $series[$r['tienda']][$pos] += $ventas;
        $totalPorDia[$pos]           += $ventas;
    }

    // Redondear
    foreach ($tiendas as $t) {
        $series[$t] = array_map(fn($v) => round($v, 2), $series[$t]);
    }
    $series['Total'] = array_map(fn($v) => round($v, 2), $totalPorDia);

    ob_clean();
    echo json_encode([
        'success'   => true,
        'mes'       => $mes,
        'mes_label' => $mesLabel,
        'dias'      => $dias,
        'tiendas'   => $tiendas,
        'series'    => $series,
    ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
    ]);
}
