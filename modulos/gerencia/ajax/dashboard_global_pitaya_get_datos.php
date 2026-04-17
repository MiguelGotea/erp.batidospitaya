<?php
/**
 * AJAX — Dashboard Global Pitaya · KPIs estratégicos
 * modulos/gerencia/ajax/dashboard_global_pitaya_get_datos.php
 */
ob_start(); // Buffer any stray output (warnings, notices, deprecations)

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
    // Intentar la query enriquecida con subquery de tiendas activas por mes.
    // Si la tabla sucursales no tiene Fecha_Cierre o hay timeout, usamos fallback simple.
    try {
        $sqlTendMensual = "
            SELECT
                sub.mes,
                sub.total,
                sub.pedidos,
                (
                    SELECT COUNT(*)
                    FROM sucursales s2
                    WHERE s2.sucursal = 1
                      AND s2.Fecha_Apertura <= LAST_DAY(CONCAT(sub.mes, '-01'))
                      AND (
                            s2.activa = 1
                            OR s2.Fecha_Cierre > LAST_DAY(CONCAT(sub.mes, '-01'))
                      )
                ) AS tiendas_activas_mes
            FROM (
                SELECT
                    DATE_FORMAT(Fecha, '%Y-%m') AS mes,
                    SUM(CASE WHEN Anulado = 0 THEN Precio ELSE 0 END) AS total,
                    COUNT(DISTINCT CASE WHEN Anulado=0 THEN CodPedido ELSE NULL END) AS pedidos
                FROM VentasGlobalesAccessCSV
                WHERE Fecha >= DATE_SUB(:hoy, INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(Fecha, '%Y-%m')
            ) sub
            ORDER BY mes ASC
        ";
        $stTM = $conn->prepare($sqlTendMensual);
        $stTM->execute([':hoy' => $hoy]);
        $tendenciaMensual = $stTM->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Fallback: query simple sin subquery de tiendas
        $sqlTendSimple = "
            SELECT mes, total, pedidos, NULL AS tiendas_activas_mes FROM (
                SELECT
                    DATE_FORMAT(Fecha, '%Y-%m') AS mes,
                    SUM(CASE WHEN Anulado = 0 THEN Precio ELSE 0 END) AS total,
                    COUNT(DISTINCT CASE WHEN Anulado=0 THEN CodPedido ELSE NULL END) AS pedidos
                FROM VentasGlobalesAccessCSV
                WHERE Fecha >= DATE_SUB(:hoy, INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(Fecha, '%Y-%m')
            ) sub ORDER BY mes ASC
        ";
        $stTM = $conn->prepare($sqlTendSimple);
        $stTM->execute([':hoy' => $hoy]);
        $tendenciaMensual = $stTM->fetchAll(PDO::FETCH_ASSOC);
    }
    // Calcular venta_por_tienda (usa tiendas_activas_mes o fallback al total de activas)
    // Tiendas activas actuales — se usa en expansión y proyección
    $tiendasActualesTotal = (int)($conn->query(
        "SELECT COUNT(*) FROM sucursales WHERE sucursal=1 AND activa=1"
    )->fetchColumn() ?: 14);
    $tiendasActivasFallback = $tiendasActualesTotal;
    foreach ($tendenciaMensual as &$tm) {
        $t = max(1, (int)($tm['tiendas_activas_mes'] ?? $tiendasActivasFallback));
        $tm['venta_por_tienda'] = round((float)$tm['total'] / $t, 2);
    }
    unset($tm);


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
    // REGLA: tienda activa = sucursal=1 AND activa=1
    // Para el HISTORIAL de aperturas incluimos TODAS las que alguna vez abrieron (activa 0 y 1)
    // para reflejar el ritmo bruto real de crecimiento.
    $sqlSucursalesApertura = "
        SELECT id, codigo, nombre, Fecha_Apertura, activa,
               IF(activa=0, Fecha_Cierre, NULL) AS fecha_cierre,
               YEAR(Fecha_Apertura) AS anio_apertura
        FROM sucursales
        WHERE sucursal = 1
          AND Fecha_Apertura IS NOT NULL
        ORDER BY Fecha_Apertura ASC
    ";
    $stSA = $conn->query($sqlSucursalesApertura);
    $sucursalesApertura = $stSA->fetchAll(PDO::FETCH_ASSOC);

    // Separar las cerradas para calcular el ritmo de cierres
    $cerradas = array_filter($sucursalesApertura, fn($s) => (int)$s['activa'] === 0 && $s['fecha_cierre']);
    $totalCerradas = count($cerradas);

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

    // Ventas por año — solo desde 2024 (base de datos contiene datos desde 2024)
    $sqlVentasAnio = "
        SELECT anio, ventas FROM (
            SELECT YEAR(Fecha) AS anio,
                   SUM(CASE WHEN Anulado=0 THEN Precio ELSE 0 END) AS ventas
            FROM VentasGlobalesAccessCSV
            WHERE YEAR(Fecha) >= 2024
            GROUP BY YEAR(Fecha)
        ) sub ORDER BY anio ASC
    ";
    $stVA = $conn->query($sqlVentasAnio);
    $ventasPorAnio = $stVA->fetchAll(PDO::FETCH_ASSOC);

    // Calcular acumulado de aperturas brutas por año en PHP
    // y cierres por año para mostrar el neto
    $aperturasPorAnio = [];
    $cierresPorAnio   = [];
    foreach ($sucursalesApertura as $s) {
        $a = (int)$s['anio_apertura'];
        $aperturasPorAnio[$a] = ($aperturasPorAnio[$a] ?? 0) + 1;
    }
    foreach ($cerradas as $s) {
        if ($s['fecha_cierre']) {
            $ac = (int)date('Y', strtotime($s['fecha_cierre']));
            $cierresPorAnio[$ac] = ($cierresPorAnio[$ac] ?? 0) + 1;
        }
    }
    ksort($aperturasPorAnio);
    $expansion = [];
    $acumBruto = 0;
    $acumNeto  = 0;
    $allAnios  = array_unique(array_merge(array_keys($aperturasPorAnio), array_keys($cierresPorAnio)));
    sort($allAnios);
    foreach ($allAnios as $anio) {
        $nuevas  = $aperturasPorAnio[$anio] ?? 0;
        $cerr    = $cierresPorAnio[$anio]   ?? 0;
        $acumBruto += $nuevas;
        $acumNeto  += ($nuevas - $cerr);
        $expansion[] = [
            'anio'      => $anio,
            'nuevas'    => $nuevas,
            'cierres'   => $cerr,
            'acumulado' => $acumBruto,   // bruto (total abiertos alguna vez)
            'neto'      => $acumNeto,    // neto (activas si no hubiera más cierres pendientes)
        ];
    }
    // El acumulado neto real es el count de activas (ya definido arriba)
    $tiendasActivasActuales = $tiendasActualesTotal;

    // Proyección lineal hacia 40 tiendas en 2028 (basada en activas)
    $anioActualExp  = (int)date('Y');
    $totalAbiertasAlguna  = count($sucursalesApertura); // todas las que han existido
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
            'nombre'            => $s['nombre'],
            'fecha_apertura'    => $s['Fecha_Apertura'],
            'anio_apertura'     => (int)$s['anio_apertura'],
            'ventas_historico'  => $info ? (float)$info['ventas_total_historico'] : 0,
            'primer_anio_venta' => $info ? (int)$info['primer_anio_venta'] : null,
        ];
    }, $sucursalesApertura);

    // ── Viabilidad mejorada con cierres ──
    $hoyTs        = time();
    $primeraFecha = !empty($sucursalesApertura) ? $sucursalesApertura[0]['Fecha_Apertura'] : null;
    $aniosDesdeInicio = $primeraFecha
        ? max(0.5, ($hoyTs - strtotime($primeraFecha)) / 31536000)
        : 1;

    // Ritmo bruto de aperturas (todo lo que se ha abierto, incluyendo cerradas)
    $ritmoAperturasBruto = round($totalAbiertasAlguna / $aniosDesdeInicio, 2);

    // Ritmo neto histórico (activas hoy / años desde inicio)
    $ritmoHistorico = round($tiendasActualesTotal / $aniosDesdeInicio, 2);

    // Tasa de cierre anual
    $tasaCierre = round($totalCerradas / $aniosDesdeInicio, 2);

    // Ritmo reciente BRUTO (aperturas brutas en últimos 2 años)
    $anioCorte = $anioActualExp - 2;
    $recientesBruto = array_filter($sucursalesApertura, fn($s) => (int)$s['anio_apertura'] >= $anioCorte);
    $recientesCerr  = array_filter($cerradas, function($s) use ($anioCorte) {
        if (!$s['fecha_cierre']) return false;
        return (int)date('Y', strtotime($s['fecha_cierre'])) >= $anioCorte;
    });
    $ritmoAperReciente = count($recientesBruto) > 0 ? round(count($recientesBruto) / 2, 2) : $ritmoAperturasBruto;
    $ritmoCieReciente  = count($recientesCerr)  > 0 ? round(count($recientesCerr)  / 2, 2) : $tasaCierre;
    $ritmoNetReciente  = round($ritmoAperReciente - $ritmoCieReciente, 2);

    // Proyección NETA al ritmo reciente (considerando cierres)
    $proyReciente  = (int)round($tiendasActualesTotal + $ritmoNetReciente  * $aniosRestantes);
    // Proyección BRUTA al ritmo reciente (sin descontar cierres potenciales)
    $proyBruta     = (int)round($tiendasActualesTotal + $ritmoAperReciente * $aniosRestantes);
    // Proyección al ritmo histórico neto
    $proyHistorica = (int)round($tiendasActualesTotal + $ritmoHistorico * $aniosRestantes);

    // Ritmo necesario (sin cierres futuros — política nueva)
    $ritmoNecesario = $aniosRestantes > 0
        ? round(($metaExpansion - $tiendasActualesTotal) / $aniosRestantes, 1)
        : 0;

    // Viabilidad: ritmo neto reciente vs necesario
    $ratioViabilidad = $ritmoNecesario > 0 ? round($ritmoAperReciente / $ritmoNecesario * 100, 1) : 100;
    if ($ratioViabilidad >= 100)     $viabilidadLabel = 'viable';
    elseif ($ratioViabilidad >= 70)  $viabilidadLabel = 'posible';
    else                              $viabilidadLabel = 'desafiante';

    $viabilidad = [
        'ritmo_historico'         => $ritmoHistorico,
        'ritmo_apertura_bruto'    => $ritmoAperturasBruto,
        'ritmo_reciente'          => $ritmoAperReciente,
        'ritmo_neto_reciente'     => $ritmoNetReciente,
        'tasa_cierre'             => $tasaCierre,
        'tasa_cierre_reciente'    => $ritmoCieReciente,
        'ritmo_necesario'         => $ritmoNecesario,
        'proyeccion_neta'         => min($metaExpansion + 5, $proyReciente),
        'proyeccion_bruta'        => min($metaExpansion + 5, $proyBruta),
        'proyeccion_historica'    => min($metaExpansion + 5, $proyHistorica),
        'ratio_viabilidad'        => $ratioViabilidad,
        'estado'                  => $viabilidadLabel,
        'anios_desde_inicio'      => round($aniosDesdeInicio, 1),
        'anos_restantes'          => $aniosRestantes,
        'total_cerradas'          => $totalCerradas,
        'total_abiertas_alguna'   => $totalAbiertasAlguna,
    ];

    // ────────────────────────────────────────────────────────────────────
    // PROYECCIÓN INTELIGENTE — 3 ESCENARIOS hasta Diciembre 2028
    //
    // Contexto: política de NO más cierres → proyecciones son solo aperturas.
    // Escenario 1 (Conservador):  ritmo histórico bruto de aperturas desde el inicio
    // Escenario 2 (Moderado):     ritmo bruto de los últimos 2 años
    // Escenario 3 (Optimista):    crecimiento lineal hasta llegar a 40 en Dic 2028
    // VPT: regresión lineal sobre los meses COMPLETOS (excluye el mes en curso)
    // ────────────────────────────────────────────────────────────────────
    $mesActualStr = date('Y-m');  // e.g. '2026-04'
    $diaHoy       = (int)date('j');
    $diasEnMes    = (int)date('t');

    // Variables base para proyección
    $nmeses      = count($tendenciaMensual);
    $vptSeries   = array_values(array_map(fn($m) => (float)$m['venta_por_tienda'], $tendenciaMensual));
    $baseTiendas = $tiendasActualesTotal;
    $lastVpt     = !empty($vptSeries) ? end($vptSeries) : 0;

    // Separar mes actual (incompleto) del histórico de meses completos
    $lastMesTend      = !empty($tendenciaMensual) ? $tendenciaMensual[$nmeses-1]['mes'] : $mesActualStr;
    $mesActualEstimado = null;

    if ($lastMesTend === $mesActualStr && $diaHoy > 1) {
        // Extrapolar el mes actual a mes completo (ventas/días_transcurridos × días_en_mes)
        $ventasParciales  = (float)$tendenciaMensual[$nmeses-1]['total'];
        $diasTranscurridos = max(1, $diaHoy - 1);  // ventas hasta ayer
        $ventaEstimada    = round($ventasParciales / $diasTranscurridos * $diasEnMes, 2);
        $tiendasMesAct    = max(1, (int)($tendenciaMensual[$nmeses-1]['tiendas_activas_mes'] ?? $baseTiendas));
        $mesActualEstimado = [
            'mes'             => $mesActualStr,
            'ventas'          => $ventaEstimada,
            'venta_por_tienda'=> round($ventaEstimada / $tiendasMesAct, 2),
            'tiendas_activas_mes' => $tiendasMesAct,
            'estimado'        => true,
        ];
        $nc = $nmeses - 1;  // meses completos para regresión
    } else {
        $nc = $nmeses;
    }

    // Regresión lineal sobre VPT de los meses COMPLETOS solamente
    $vptCompletos = array_slice($vptSeries, 0, $nc);
    $sx=0; $sy=0; $sxy=0; $sxx=0;
    for ($i=0; $i<$nc; $i++) {
        $sx  += $i;   $sy  += $vptCompletos[$i];
        $sxy += $i * $vptCompletos[$i];
        $sxx += $i * $i;
    }
    $den2  = $nc * $sxx - $sx * $sx;
    $slope2     = $den2 != 0 ? ($nc * $sxy - $sx * $sy) / $den2 : 0;
    $intercept2 = $nc > 0    ? ($sy - $slope2 * $sx) / $nc       : 0;
    $lastVptCompleto = $nc > 0 ? $vptCompletos[$nc-1] : ($lastVpt ?: 1);

    // Mes base para la proyección = último mes completo
    $lastMesCompleto = $nc > 0 ? $tendenciaMensual[$nc-1]['mes'] : date('Y-m', strtotime('-1 month'));

    // Ritmos mensuales SIN cierres (política nueva = nunca más se cierra una tienda)
    $ritmoHistMens = $ritmoAperturasBruto / 12;   // histórico bruto todo el tiempo
    $ritmoRecMens  = $ritmoAperReciente   / 12;   // reciente 2 años bruto

    // Generar proyección mes a mes hasta Dic 2028 (enfoque por fecha, no por count)
    $proyeccionTendencia = [];
    $cursorTs  = strtotime($lastMesCompleto . '-01 +1 month');   // primer mes proyectado
    $finalTs   = strtotime('2028-12-01');
    $totalMeses = 0;

    // Pre-scan para saber cuántos meses hay → permite calcular ritmoMetaMens exacto
    $scanTs = $cursorTs;
    while ($scanTs <= $finalTs) {
        $totalMeses++;
        $scanTs = strtotime(date('Y-m-01', $scanTs) . ' +1 month');
    }
    $ritmoMetaMens = $totalMeses > 0 ? ($metaExpansion - $baseTiendas) / $totalMeses : 0;

    $i = 0;
    while ($cursorTs <= $finalTs) {
        $i++;
        $mesLabel = date('Y-m', $cursorTs);

        // VPT proyectado por regresión, acotado al −25%/+35% del último real
        $vptReg  = $intercept2 + $slope2 * ($nc - 1 + $i);
        $vptProy = max($lastVptCompleto * 0.75, min($lastVptCompleto * 1.35, $vptReg));

        // Tiendas esperadas por escenario (sin cierres → solo sube)
        $tHist = min($metaExpansion, $baseTiendas + $ritmoHistMens * $i);
        $tRec  = min($metaExpansion, $baseTiendas + $ritmoRecMens  * $i);
        $tMeta = min($metaExpansion, $baseTiendas + $ritmoMetaMens * $i);

        $proyeccionTendencia[] = [
            'mes'          => $mesLabel,
            'vpt'          => round($vptProy, 2),
            'tiendas_hist' => round($tHist, 1),
            'tiendas_rec'  => round($tRec,  1),
            'tiendas_meta' => round($tMeta, 1),
            'ventas_hist'  => round($vptProy * $tHist, 2),  // Conservador
            'ventas_rec'   => round($vptProy * $tRec,  2),  // Moderado
            'ventas_meta'  => round($vptProy * $tMeta, 2),  // Optimista → 40 en 2028
        ];

        $cursorTs = strtotime(date('Y-m-01', $cursorTs) . ' +1 month');
    }

    // ────────────────────────────────────────────
    // CONSTRUIR RESPUESTA
    // ────────────────────────────────────────────
    ob_clean(); // Descartar cualquier warning/notice de PHP antes del JSON
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
        'tendencia_mensual'      => $tendenciaMensual,
        'mes_actual_estimado'    => $mesActualEstimado,
        'proyeccion_tendencia'   => $proyeccionTendencia,
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
            'acumulado_por_anio'    => $expansion,   // incluye nuevas, cierres, acumulado bruto, neto
            'ventas_por_anio'       => $ventasPorAnio,
            'proyeccion'            => $proyeccion,
            'tiendas_actuales'      => $tiendasActualesTotal,
            'total_abiertas'        => $totalAbiertasAlguna,
            'total_cerradas'        => $totalCerradas,
            'meta'                  => $metaExpansion,
            'anio_meta'             => $anioMeta,
            'aperturas_necesarias'  => $aperturasPorAnioNecesarias,
            'avance_pct'            => round($tiendasActualesTotal / $metaExpansion * 100, 1),
            'viabilidad'            => $viabilidad,
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener datos: ' . $e->getMessage()
    ]);
}
