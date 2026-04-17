<?php
/**
 * AJAX — Dashboard Global Pitaya · KPIs estratégicos
 * modulos/gerencia/ajax/dashboard_global_pitaya_get_datos.php
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('dashboard_global_pitaya', 'vista', $cargoOperario)) {
    echo json_encode(['success' => false, 'message' => 'Sin acceso']);
    exit;
}

$periodo = $_POST['periodo'] ?? 'mes_actual';
$anio    = (int)($_POST['anio'] ?? date('Y'));

// ────────────────────────────────────────────────
// Calcular rango de fechas según período
// ────────────────────────────────────────────────
$hoy = date('Y-m-d');
switch ($periodo) {
    case 'mes_anterior':
        $ini = date('Y-m-01', strtotime('first day of last month'));
        $fin = date('Y-m-t',  strtotime('last day of last month'));
        break;
    case 'trimestre':
        $mes   = (int)date('n');
        $qIni  = ((int)ceil($mes / 3) - 1) * 3 + 1;
        $ini   = "$anio-" . str_pad($qIni, 2, '0', STR_PAD_LEFT) . "-01";
        $fin   = date('Y-m-t', strtotime("$anio-" . str_pad($qIni + 2, 2, '0', STR_PAD_LEFT) . "-01"));
        if ($fin > $hoy) $fin = $hoy;
        break;
    case 'anio':
        $ini = "$anio-01-01";
        $fin = ($anio == date('Y')) ? $hoy : "$anio-12-31";
        break;
    default: // mes_actual
        $ini = date('Y-m-01');
        $fin = $hoy;
        break;
}

try {
    // ────────────────────────────────────────────
    // 1. VENTAS TOTALES DEL PERÍODO
    // ────────────────────────────────────────────
    $sqlVentas = "
        SELECT
            SUM(CASE WHEN Anulado = 0 THEN Precio ELSE 0 END)                        AS ventas_totales,
            COUNT(DISTINCT CASE WHEN Anulado = 0 THEN CodPedido ELSE NULL END)        AS total_pedidos,
            COUNT(DISTINCT CASE WHEN Anulado = 0 THEN Sucursal_Nombre ELSE NULL END)  AS tiendas_activas
        FROM VentasGlobalesAccessCSV
        WHERE Fecha BETWEEN :ini AND :fin
    ";
    $st = $conn->prepare($sqlVentas);
    $st->execute([':ini' => $ini, ':fin' => $fin]);
    $ventas = $st->fetch(PDO::FETCH_ASSOC);

    $ventasTotales  = round($ventas['ventas_totales'] ?? 0, 2);
    $totalPedidos   = (int)($ventas['total_pedidos'] ?? 0);
    $tiendasActivas = (int)($ventas['tiendas_activas'] ?? 0);
    $ticketPromedio = $totalPedidos > 0 ? round($ventasTotales / $totalPedidos, 2) : 0;
    $ventaPorTienda = $tiendasActivas > 0 ? round($ventasTotales / $tiendasActivas, 2) : 0;

    // ── Período anterior para tendencia ──
    $dias     = (strtotime($fin) - strtotime($ini)) / 86400 + 1;
    $iniPrev  = date('Y-m-d', strtotime($ini) - $dias * 86400);
    $finPrev  = date('Y-m-d', strtotime($ini) - 86400);
    $stPrev   = $conn->prepare($sqlVentas);
    $stPrev->execute([':ini' => $iniPrev, ':fin' => $finPrev]);
    $ventasPrev = $stPrev->fetch(PDO::FETCH_ASSOC);
    $ventasPrevTotal = round($ventasPrev['ventas_totales'] ?? 0, 2);
    $trendPct  = $ventasPrevTotal > 0 ? round(($ventasTotales - $ventasPrevTotal) / $ventasPrevTotal * 100, 1) : null;

    // ────────────────────────────────────────────
    // 2. METAS DEL PERÍODO
    // ────────────────────────────────────────────
    $sqlMeta = "
        SELECT SUM(meta) AS meta_total
        FROM ventas_meta
        WHERE fecha BETWEEN :ini AND :fin
    ";
    $stm = $conn->prepare($sqlMeta);
    $stm->execute([':ini' => $ini, ':fin' => $fin]);
    $metaRow    = $stm->fetch(PDO::FETCH_ASSOC);
    $metaTotal  = round($metaRow['meta_total'] ?? 0, 2);
    $cumplimiento = $metaTotal > 0 ? round($ventasTotales / $metaTotal * 100, 1) : 0;

    // ────────────────────────────────────────────
    // 3. TENDENCIA VENTAS POR MES (últimos 12 meses)
    // ────────────────────────────────────────────
    $sqlTendMensual = "
        SELECT mes, total FROM (
            SELECT
                DATE_FORMAT(Fecha, '%Y-%m') AS mes,
                SUM(CASE WHEN Anulado = 0 THEN Precio ELSE 0 END) AS total
            FROM VentasGlobalesAccessCSV
            WHERE Fecha >= DATE_SUB(:hoy, INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(Fecha, '%Y-%m')
        ) sub
        ORDER BY mes ASC
    ";
    $stTM = $conn->prepare($sqlTendMensual);
    $stTM->execute([':hoy' => $hoy]);
    $tendenciaMensual = $stTM->fetchAll(PDO::FETCH_ASSOC);

    // ────────────────────────────────────────────
    // 4. RANKING TIENDAS
    // ────────────────────────────────────────────
    $sqlRanking = "
        SELECT
            Sucursal_Nombre AS tienda,
            SUM(CASE WHEN Anulado = 0 THEN Precio ELSE 0 END)                    AS ventas,
            COUNT(DISTINCT CASE WHEN Anulado = 0 THEN CodPedido ELSE NULL END)   AS pedidos
        FROM VentasGlobalesAccessCSV
        WHERE Fecha BETWEEN :ini AND :fin
        GROUP BY Sucursal_Nombre
        ORDER BY ventas DESC
    ";
    $stR = $conn->prepare($sqlRanking);
    $stR->execute([':ini' => $ini, ':fin' => $fin]);
    $rankingTiendas = $stR->fetchAll(PDO::FETCH_ASSOC);

    // Adjuntar metas por tienda al ranking
    $sqlMetaTienda = "
        SELECT s.codigo, s.nombre, SUM(vm.meta) AS meta_total
        FROM sucursales s
        LEFT JOIN ventas_meta vm ON vm.cod_sucursal = s.codigo AND vm.fecha BETWEEN :ini AND :fin
        WHERE s.sucursal = 1 AND s.activa = 1
        GROUP BY s.codigo, s.nombre
    ";
    $stMT = $conn->prepare($sqlMetaTienda);
    $stMT->execute([':ini' => $ini, ':fin' => $fin]);
    $metasPorTienda = $stMT->fetchAll(PDO::FETCH_ASSOC);
    $metaMap = [];
    foreach ($metasPorTienda as $m) {
        $metaMap[$m['nombre']] = (float)($m['meta_total'] ?? 0);
    }

    // ────────────────────────────────────────────
    // 5. CLUB PITAYA — KPIs
    // ────────────────────────────────────────────
    // Total membresías
    $stClubTotal = $conn->query("SELECT COUNT(*) as total FROM clientesclub");
    $totalMembresias = (int)$stClubTotal->fetch(PDO::FETCH_ASSOC)['total'];

    // Nuevos socios en período
    $sqlNuevos = "SELECT COUNT(*) as nuevos FROM clientesclub WHERE fecha_registro BETWEEN :ini AND :fin";
    $stN = $conn->prepare($sqlNuevos);
    $stN->execute([':ini' => $ini, ':fin' => $fin]);
    $nuevosSocios = (int)$stN->fetch(PDO::FETCH_ASSOC)['nuevos'];

    // Socios activos (última compra ≤ 60 días)
    $sqlActivos = "
        SELECT COUNT(DISTINCT CodCliente) AS activos
        FROM VentasGlobalesAccessCSV
        WHERE Anulado = 0 AND CodCliente > 0
          AND Fecha >= DATE_SUB(:hoy, INTERVAL 60 DAY)
    ";
    $stA = $conn->prepare($sqlActivos);
    $stA->execute([':hoy' => $hoy]);
    $sociosActivos = (int)$stA->fetch(PDO::FETCH_ASSOC)['activos'];

    // Universo con compra
    $stUni = $conn->query("SELECT COUNT(DISTINCT CodCliente) AS universo FROM VentasGlobalesAccessCSV WHERE Anulado = 0 AND CodCliente > 0");
    $universo = (int)$stUni->fetch(PDO::FETCH_ASSOC)['universo'];

    // Socios perdidos (>60 días) — churn
    $sqlPerdidos = "
        SELECT COUNT(DISTINCT CodCliente) AS perdidos
        FROM VentasGlobalesAccessCSV
        WHERE Anulado = 0 AND CodCliente > 0
          AND CodCliente NOT IN (
              SELECT DISTINCT CodCliente FROM VentasGlobalesAccessCSV
              WHERE Anulado = 0 AND CodCliente > 0
                AND Fecha >= DATE_SUB(:hoy, INTERVAL 60 DAY)
          )
    ";
    $stP = $conn->prepare($sqlPerdidos);
    $stP->execute([':hoy' => $hoy]);
    $perdidos = (int)$stP->fetch(PDO::FETCH_ASSOC)['perdidos'];
    $churnRate = $universo > 0 ? round($perdidos / $universo * 100, 1) : 0;

    // LTV Promedio (top 1000 para eficiencia)
    $sqlLTV = "
        SELECT AVG(ltv) AS ltv_prom FROM (
            SELECT CodCliente, SUM(Precio) AS ltv
            FROM VentasGlobalesAccessCSV
            WHERE Anulado = 0 AND CodCliente > 0
            GROUP BY CodCliente
            LIMIT 5000
        ) t
    ";
    $stLTV = $conn->query($sqlLTV);
    $ltvPromedio = round($stLTV->fetch(PDO::FETCH_ASSOC)['ltv_prom'] ?? 0, 2);

    // Participación club en ventas (período)
    $sqlPart = "
        SELECT
            SUM(CASE WHEN Anulado = 0 THEN Precio ELSE 0 END) AS total_ventas,
            SUM(CASE WHEN Anulado = 0 AND CodCliente > 0 THEN Precio ELSE 0 END) AS ventas_club
        FROM VentasGlobalesAccessCSV
        WHERE Fecha BETWEEN :ini AND :fin
    ";
    $stPart = $conn->prepare($sqlPart);
    $stPart->execute([':ini' => $ini, ':fin' => $fin]);
    $partRow  = $stPart->fetch(PDO::FETCH_ASSOC);
    $partClub = $partRow['total_ventas'] > 0
        ? round($partRow['ventas_club'] / $partRow['total_ventas'] * 100, 1) : 0;

    // Nuevos socios por mes (últimos 8 meses)
    $sqlNuevosMes = "
        SELECT mes, total FROM (
            SELECT DATE_FORMAT(fecha_registro,'%Y-%m') AS mes, COUNT(*) AS total
            FROM clientesclub
            WHERE fecha_registro >= DATE_SUB(:hoy, INTERVAL 8 MONTH)
            GROUP BY DATE_FORMAT(fecha_registro,'%Y-%m')
        ) sub
        ORDER BY mes ASC
    ";
    $stNM = $conn->prepare($sqlNuevosMes);
    $stNM->execute([':hoy' => $hoy]);
    $nuevosPorMes = $stNM->fetchAll(PDO::FETCH_ASSOC);

    // ────────────────────────────────────────────
    // 6. TOP PRODUCTOS
    // ────────────────────────────────────────────
    $sqlTop = "
        SELECT
            DBBatidos_Nombre AS producto,
            SUM(CASE WHEN Anulado = 0 THEN Cantidad ELSE 0 END) AS cantidad,
            SUM(CASE WHEN Anulado = 0 THEN Precio ELSE 0 END)   AS monto
        FROM VentasGlobalesAccessCSV
        WHERE Fecha BETWEEN :ini AND :fin
          AND DBBatidos_Nombre IS NOT NULL
          AND DBBatidos_Nombre != ''
        GROUP BY DBBatidos_Nombre
        ORDER BY monto DESC
        LIMIT 10
    ";
    $stTop = $conn->prepare($sqlTop);
    $stTop->execute([':ini' => $ini, ':fin' => $fin]);
    $topProductos = $stTop->fetchAll(PDO::FETCH_ASSOC);

    // ────────────────────────────────────────────
    // 7. MIX POR CATEGORÍA (NombreGrupo)
    // ────────────────────────────────────────────
    $sqlCat = "
        SELECT categoria, monto FROM (
            SELECT
                COALESCE(NombreGrupo, 'Otro') AS categoria,
                SUM(CASE WHEN Anulado = 0 THEN Precio ELSE 0 END) AS monto
            FROM VentasGlobalesAccessCSV
            WHERE Fecha BETWEEN :ini AND :fin
            GROUP BY COALESCE(NombreGrupo, 'Otro')
        ) sub
        ORDER BY monto DESC
        LIMIT 8
    ";
    $stCat = $conn->prepare($sqlCat);
    $stCat->execute([':ini' => $ini, ':fin' => $fin]);
    $mixCategorias = $stCat->fetchAll(PDO::FETCH_ASSOC);

    // ────────────────────────────────────────────
    // 8. SEGMENTOS RFM (simplificado)
    // ────────────────────────────────────────────
    $segmentos = [
        ['segmento' => 'Campeones',  'count' => 0],
        ['segmento' => 'Fieles',     'count' => 0],
        ['segmento' => 'En Riesgo',  'count' => 0],
        ['segmento' => 'Perdidos',   'count' => 0],
        ['segmento' => 'Nuevos',     'count' => 0],
    ];
    // Calcular segmentos RFM en PHP para evitar limitaciones de MySQL con GROUP BY alias
    $sqlSeg = "
        SELECT CodCliente,
               MAX(Fecha)               AS ultima_compra,
               COUNT(DISTINCT CodPedido) AS frecuencia
        FROM VentasGlobalesAccessCSV
        WHERE Anulado = 0 AND CodCliente > 0
        GROUP BY CodCliente
    ";
    $stSeg = $conn->query($sqlSeg);
    $clientesRFM = $stSeg->fetchAll(PDO::FETCH_ASSOC);

    $contadores = ['Campeones' => 0, 'Fieles' => 0, 'En Riesgo' => 0, 'Perdidos' => 0, 'Nuevos' => 0];
    $hoyTs = strtotime($hoy);
    foreach ($clientesRFM as $c) {
        $diasInactivo = (int)(($hoyTs - strtotime($c['ultima_compra'])) / 86400);
        $freq         = (int)$c['frecuencia'];
        if ($diasInactivo <= 15 && $freq >= 10)       $contadores['Campeones']++;
        elseif ($diasInactivo <= 30 && $freq >= 5)    $contadores['Fieles']++;
        elseif ($diasInactivo <= 60)                  $contadores['En Riesgo']++;
        elseif ($diasInactivo > 60)                   $contadores['Perdidos']++;
        else                                          $contadores['Nuevos']++;
    }
    $segmentos = [];
    foreach ($contadores as $seg => $cnt) {
        $segmentos[] = ['segmento' => $seg, 'total' => $cnt];
    }

    // ────────────────────────────────────────────
    // 9. TABLA DETALLE POR TIENDA (cumplimiento, ticket, club)
    // ────────────────────────────────────────────
    $sqlDetalle = "
        SELECT
            v.Sucursal_Nombre AS tienda,
            SUM(CASE WHEN v.Anulado = 0 THEN v.Precio ELSE 0 END)                    AS ventas,
            COUNT(DISTINCT CASE WHEN v.Anulado = 0 THEN v.CodPedido ELSE NULL END)   AS pedidos,
            COUNT(DISTINCT CASE WHEN v.Anulado = 0 AND v.CodCliente > 0 THEN v.CodCliente ELSE NULL END) AS socios
        FROM VentasGlobalesAccessCSV v
        WHERE v.Fecha BETWEEN :ini AND :fin
        GROUP BY v.Sucursal_Nombre
        ORDER BY ventas DESC
    ";
    $stDet = $conn->prepare($sqlDetalle);
    $stDet->execute([':ini' => $ini, ':fin' => $fin]);
    $detalleTiendas = $stDet->fetchAll(PDO::FETCH_ASSOC);

    // ────────────────────────────────────────────
    // 10. EXPANSIÓN — Datos históricos de aperturas
    // ────────────────────────────────────────────
    $sqlSucursalesApertura = "
        SELECT id, codigo, nombre, Fecha_Apertura,
               YEAR(Fecha_Apertura) AS anio_apertura
        FROM sucursales
        WHERE sucursal = 1
          AND Fecha_Apertura IS NOT NULL
        ORDER BY Fecha_Apertura ASC
    ";
    $stSA = $conn->query($sqlSucursalesApertura);
    $sucursalesApertura = $stSA->fetchAll(PDO::FETCH_ASSOC);

    // Ventas totales y primer año por tienda
    $sqlVentasTienda = "
        SELECT
            Sucursal_Nombre,
            YEAR(MIN(Fecha))  AS primer_anio_venta,
            SUM(CASE WHEN Anulado=0 THEN Precio ELSE 0 END) AS ventas_total_historico
        FROM VentasGlobalesAccessCSV
        GROUP BY Sucursal_Nombre
    ";
    $stVT = $conn->query($sqlVentasTienda);
    $vtMap = [];
    foreach ($stVT->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $vtMap[$r['Sucursal_Nombre']] = $r;
    }

    // Ventas por año (histórico completo)
    $sqlVentasAnio = "
        SELECT anio, ventas FROM (
            SELECT YEAR(Fecha) AS anio,
                   SUM(CASE WHEN Anulado=0 THEN Precio ELSE 0 END) AS ventas
            FROM VentasGlobalesAccessCSV
            GROUP BY YEAR(Fecha)
        ) sub ORDER BY anio ASC
    ";
    $stVA = $conn->query($sqlVentasAnio);
    $ventasPorAnio = $stVA->fetchAll(PDO::FETCH_ASSOC);

    // Calcular acumulado de tiendas por año en PHP
    $aperturasPorAnio = [];
    foreach ($sucursalesApertura as $s) {
        $a = (int)$s['anio_apertura'];
        $aperturasPorAnio[$a] = ($aperturasPorAnio[$a] ?? 0) + 1;
    }
    ksort($aperturasPorAnio);
    $expansion = [];
    $acum = 0;
    foreach ($aperturasPorAnio as $anio => $nuevas) {
        $acum += $nuevas;
        $expansion[] = ['anio' => $anio, 'nuevas' => $nuevas, 'acumulado' => $acum];
    }

    // Proyección lineal hacia 40 tiendas en 2028
    $anioActualExp  = (int)date('Y');
    $tiendasActualesTotal = $acum;
    $metaExpansion  = 40;
    $anioMeta       = 2028;
    $aniosRestantes = max(1, $anioMeta - $anioActualExp);
    $aperturasPorAnioNecesarias = ceil(($metaExpansion - $tiendasActualesTotal) / $aniosRestantes);

    // Línea de proyección (desde hoy hasta 2028)
    $proyeccion = [];
    $proyAcum = $tiendasActualesTotal;
    for ($y = $anioActualExp; $y <= $anioMeta; $y++) {
        $proyeccion[] = ['anio' => $y, 'proyectado' => $proyAcum];
        $proyAcum = min($metaExpansion, $proyAcum + $aperturasPorAnioNecesarias);
    }

    // Enriquecer lista de sucursales con ventas
    $listaSucursales = array_map(function($s) use ($vtMap) {
        $info = $vtMap[$s['nombre']] ?? null;
        return [
            'nombre'          => $s['nombre'],
            'fecha_apertura'  => $s['Fecha_Apertura'],
            'anio_apertura'   => (int)$s['anio_apertura'],
            'ventas_historico'=> $info ? (float)$info['ventas_total_historico'] : 0,
            'primer_anio_venta' => $info ? (int)$info['primer_anio_venta'] : null,
        ];
    }, $sucursalesApertura);

    // ────────────────────────────────────────────
    // CONSTRUIR RESPUESTA
    // ────────────────────────────────────────────
    echo json_encode([
        'success' => true,
        'periodo' => ['ini' => $ini, 'fin' => $fin],
        'ventas' => [
            'totales'       => $ventasTotales,
            'total_pedidos' => $totalPedidos,
            'ticket_prom'   => $ticketPromedio,
            'por_tienda'    => $ventaPorTienda,
            'trend_pct'     => $trendPct,
            'prev_total'    => $ventasPrevTotal,
        ],
        'meta' => [
            'total'         => $metaTotal,
            'cumplimiento'  => $cumplimiento,
        ],
        'tendencia_mensual' => $tendenciaMensual,
        'ranking_tiendas'   => array_map(function($r) use ($metaMap) {
            $meta = $metaMap[$r['tienda']] ?? 0;
            return [
                'tienda'      => $r['tienda'],
                'ventas'      => (float)$r['ventas'],
                'pedidos'     => (int)$r['pedidos'],
                'meta'        => $meta,
                'cumplimiento'=> $meta > 0 ? round((float)$r['ventas'] / $meta * 100, 1) : null,
            ];
        }, $rankingTiendas),
        'club' => [
            'total_membresias' => $totalMembresias,
            'socios_activos'   => $sociosActivos,
            'nuevos'           => $nuevosSocios,
            'churn_rate'       => $churnRate,
            'ltv_promedio'     => $ltvPromedio,
            'universo'         => $universo,
            'participacion'    => $partClub,
        ],
        'nuevos_por_mes'  => $nuevosPorMes,
        'top_productos'   => $topProductos,
        'mix_categorias'  => $mixCategorias,
        'segmentos_rfm'   => $segmentos,
        'detalle_tiendas' => array_map(function($r) use ($metaMap) {
            $meta = $metaMap[$r['tienda']] ?? 0;
            $ped  = (int)$r['pedidos'];
            return [
                'tienda'       => $r['tienda'],
                'ventas'       => (float)$r['ventas'],
                'pedidos'      => $ped,
                'ticket'       => $ped > 0 ? round((float)$r['ventas'] / $ped, 2) : 0,
                'miembros_club'=> (int)$r['socios'],
                'meta'         => $meta,
                'cumplimiento' => $meta > 0 ? round((float)$r['ventas'] / $meta * 100, 1) : null,
            ];
        }, $detalleTiendas),
        'expansion' => [
            'sucursales'            => $listaSucursales,
            'acumulado_por_anio'    => $expansion,
            'ventas_por_anio'       => $ventasPorAnio,
            'proyeccion'            => $proyeccion,
            'tiendas_actuales'      => $tiendasActualesTotal,
            'meta'                  => $metaExpansion,
            'anio_meta'             => $anioMeta,
            'aperturas_necesarias'  => $aperturasPorAnioNecesarias,
            'avance_pct'            => round($tiendasActualesTotal / $metaExpansion * 100, 1),
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener datos: ' . $e->getMessage()
    ]);
}
