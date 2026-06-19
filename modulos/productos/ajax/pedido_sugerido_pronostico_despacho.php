<?php
/* ============================================================
   AJAX: Pronóstico de Despacho D-1 por producto
   modulos/productos/ajax/pedido_sugerido_pronostico_despacho.php

   Fórmula (idéntica a la línea morada del Kardex):
     stock_D1 = stock_domingo
                + Σ movimientos_reales(despacho+, compras+, ajuste±, merma-)
                  entre domingo_corte+1 y fecha_D1
                - cons_diario × dias_transcurridos

   Parámetros POST:
     id_pp           int   — id de producto_presentacion
     cod_sucursal    str   — código numérico de sucursal
     sem_corte       int   — semana de referencia (inventario real del domingo)
     fecha_despacho  str   — fecha ISO del próximo despacho
     cons_diario     float — consumo diario calculado por v2
     despacho_factor float — factor de conversión a paquetes
     stock_max_final float — stock máximo final en paquetes
   ============================================================ */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');

$usuario = obtenerUsuarioActual();
if (!tienePermiso('pedido_sugerido', 'vista', $usuario['CodNivelesCargos'])) {
    echo json_encode(['ok' => false, 'msg' => 'Sin permiso.']);
    exit();
}

$idPP          = isset($_POST['id_pp'])          ? (int)$_POST['id_pp']             : 0;
$codSucursal   = trim($_POST['cod_sucursal']     ?? '');
$semCorte      = isset($_POST['sem_corte'])      ? (int)$_POST['sem_corte']          : 0;
$fechaDespacho = trim($_POST['fecha_despacho']   ?? '');
$consDiario    = isset($_POST['cons_diario'])     ? (float)$_POST['cons_diario']     : null;
$despFactor    = isset($_POST['despacho_factor']) ? (float)$_POST['despacho_factor'] : null;
$stockMaxFinal = isset($_POST['stock_max_final']) ? (float)$_POST['stock_max_final'] : null;

if (!$idPP || !$codSucursal || !$semCorte || !$fechaDespacho) {
    echo json_encode(['ok' => false, 'msg' => 'Faltan parámetros requeridos.']);
    exit();
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDespacho)) {
    echo json_encode(['ok' => false, 'msg' => 'Formato de fecha_despacho inválido. Use YYYY-MM-DD.']);
    exit();
}

/**
 * Calcula la proyección de consumo usando regresión lineal Weighted Least Squares (WLS).
 * Asigna mayor peso a los datos más recientes (w = i).
 * Retorna el promedio proyectado de las próximas 3 semanas.
 */
function calcularProyeccionWLS(array $valores): float
{
    $n = count($valores);
    if ($n === 0) return 0.0;
    if ($n === 1) return max(0.0, (float)$valores[0]);

    $sum_w = 0.0;
    $sum_wx = 0.0;
    $sum_wy = 0.0;
    $sum_wxx = 0.0;
    $sum_wxy = 0.0;

    // x = 1, 2, ..., n
    foreach ($valores as $i => $y) {
        $x = $i + 1;
        $w = $x; // Pesos lineales decrecientes hacia el pasado (más reciente = mayor peso)
        
        $sum_w += $w;
        $sum_wx += $w * $x;
        $sum_wy += $w * $y;
        $sum_wxx += $w * $x * $x;
        $sum_wxy += $w * $x * $y;
    }

    $denominator = ($sum_w * $sum_wxx) - ($sum_wx * $sum_wx);
    if (abs($denominator) < 0.0001) {
        return array_sum($valores) / $n;
    }

    $slope = (($sum_w * $sum_wxy) - ($sum_wx * $sum_wy)) / $denominator;
    $intercept = ($sum_wy - $slope * $sum_wx) / $sum_w;

    $w1 = max(0.0, $slope * ($n + 1) + $intercept);
    $w2 = max(0.0, $slope * ($n + 2) + $intercept);
    $w3 = max(0.0, $slope * ($n + 3) + $intercept);

    return ($w1 + $w2 + $w3) / 3.0;
}

try {
    // ── 1. Fechas de la semana de corte ─────────────────────────────────
    $stmtS = $conn->prepare(
        "SELECT fecha_inicio, fecha_fin FROM SemanasSistema WHERE numero_semana = ? LIMIT 1"
    );
    $stmtS->execute([$semCorte]);
    $semRow = $stmtS->fetch(PDO::FETCH_ASSOC);
    if (!$semRow) {
        echo json_encode(['ok' => false, 'msg' => "Semana de corte {$semCorte} no encontrada."]);
        exit();
    }
    $domingoCorte    = $semRow['fecha_fin'];   // domingo (snapshot)
    $fechaInicioSem  = $semRow['fecha_inicio'];

    // ── 2. Stock inventariado del domingo de corte ───────────────────────
    $stmtInv = $conn->prepare("
        SELECT cantidad FROM inventario
        WHERE cod_sucursal             = ?
          AND id_producto_presentacion = ?
          AND fecha_inventario BETWEEN ? AND ?
        ORDER BY id DESC LIMIT 1
    ");
    $stmtInv->execute([$codSucursal, $idPP, $fechaInicioSem, $domingoCorte]);
    $invRow       = $stmtInv->fetch(PDO::FETCH_ASSOC);
    $stockDomingo = $invRow ? (float)$invRow['cantidad'] : null;
    $sinInventario = ($stockDomingo === null);

    // ── 3. Calcular fecha_D1 y rango de movimientos ──────────────────────
    $fechaD1       = date('Y-m-d', strtotime($fechaDespacho . ' -1 day'));
    $fechaMovDesde = date('Y-m-d', strtotime($domingoCorte  . ' +1 day')); // lunes post-corte
    $fechaMovHasta = $fechaD1;

    $diasTranscurridos = 0;
    if (!$sinInventario) {
        $diasTranscurridos = max(0, (int)((strtotime($fechaD1) - strtotime($domingoCorte)) / 86400));
    }

    // ── 4. Movimientos reales entre domingoCorte+1 y fechaD1 ─────────────
    // Replica la lógica de movsPorFecha de dashboard_consumo.js líneas 2227-2234.
    // Solo si hay inventario base y hay días por proyectar.
    $movimientoNeto = 0.0;

    if (!$sinInventario && $fechaMovDesde <= $fechaMovHasta) {

        // 4a. Construir codMapBalance para este idPP
        // (versión simplificada de balance_inventario_get_detalle.php)
        $sucInt = (int)$codSucursal;

        // Conversiones
        $convIndex = [];
        $rConv = $conn->query("SELECT id_unidad_producto_inicio AS i, id_unidad_producto_final AS f, cantidad AS fac FROM conversion_unidad_producto");
        foreach ($rConv->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $convIndex[(int)$c['i']][(int)$c['f']] = (float)$c['fac'];
            $convIndex[(int)$c['f']][(int)$c['i']] = $c['fac'] != 0 ? 1/(float)$c['fac'] : 0;
        }

        // maestroToBase
        $rMB = $conn->query("SELECT id, id_unidad_producto AS unid, cantidad AS cant, id_producto_maestro AS mid FROM producto_presentacion WHERE presentacion_basica_inventario=1 AND Activo='SI'");
        $maestroToBase = [];
        foreach ($rMB->fetchAll(PDO::FETCH_ASSOC) as $pm) {
            $mid = (int)$pm['mid'];
            if ($mid > 0) $maestroToBase[$mid] = ['base_pp_id' => (int)$pm['id'], 'base_unid' => (int)$pm['unid'], 'base_cant' => max((float)$pm['cant'], 0.001)];
        }

        // cascadeMap
        $rCas = $conn->query("SELECT pp_pkg.id AS pkg_id, crp.id_presentacion_producto AS base_id, crp.cantidad AS factor FROM producto_presentacion pp_pkg INNER JOIN componentes_receta_producto crp ON crp.id_receta_producto_global = pp_pkg.Id_receta_producto INNER JOIN producto_presentacion pp_base ON pp_base.id = crp.id_presentacion_producto AND pp_base.presentacion_basica_inventario=1 WHERE pp_pkg.presentacion_receta=1 AND pp_pkg.Activo='SI' AND (SELECT COUNT(DISTINCT id_presentacion_producto) FROM componentes_receta_producto WHERE id_receta_producto_global=pp_pkg.Id_receta_producto)=1");
        $cascadeMap = [];
        foreach ($rCas->fetchAll(PDO::FETCH_ASSOC) as $row) $cascadeMap[(int)$row['pkg_id']] = ['base_id' => (int)$row['base_id'], 'factor' => (float)$row['factor']];

        // diccionario
        $rDic = $conn->query("SELECT d.CodCotizacion, pp.id AS pp_id, pp.presentacion_basica_inventario AS es_base, pp.presentacion_receta AS es_receta, pp.id_unidad_producto AS pp_unid, pp.cantidad AS pp_cant, pp.id_producto_maestro AS id_maestro FROM diccionario_productos_legado d INNER JOIN producto_presentacion pp ON pp.id=d.id_producto_presentacion WHERE pp.Activo='SI'");
        $diccionario = [];
        foreach ($rDic->fetchAll(PDO::FETCH_ASSOC) as $d) $diccionario[(int)$d['CodCotizacion']] = $d;

        // altMap
        $altMap = [];
        foreach ($diccionario as $dic) {
            $pp_id = (int)$dic['pp_id'];
            if ($dic['es_base'] || $dic['es_receta']) continue;
            if (isset($cascadeMap[$pp_id])) continue;
            $mid = (int)$dic['id_maestro'];
            if (!$mid || !isset($maestroToBase[$mid])) continue;
            $base = $maestroToBase[$mid];
            $altUnid = (int)$dic['pp_unid'];
            $basUnid = (int)$base['base_unid'];
            if ($altUnid === $basUnid) {
                $factor = (float)$dic['pp_cant'] / $base['base_cant'];
            } elseif (isset($convIndex[$altUnid][$basUnid])) {
                $factor = ((float)$dic['pp_cant'] * $convIndex[$altUnid][$basUnid]) / $base['base_cant'];
            } else continue;
            $altMap[$pp_id] = ['base_id' => $base['base_pp_id'], 'factor' => $factor];
        }

        // codMapBalance — solo entradas que resuelven a nuestro idPP
        $codMapBalance = [];
        foreach ($diccionario as $cod => $dic) {
            $pp_id = (int)$dic['pp_id'];
            $resBid = null; $resFac = 1.0;
            if      (isset($cascadeMap[$pp_id])) { $resBid = $cascadeMap[$pp_id]['base_id']; $resFac = $cascadeMap[$pp_id]['factor']; }
            elseif  (isset($altMap[$pp_id]))      { $resBid = $altMap[$pp_id]['base_id'];     $resFac = $altMap[$pp_id]['factor']; }
            elseif  ($dic['es_base'])              { $resBid = $pp_id; $resFac = 1.0; }
            if ($resBid === $idPP) $codMapBalance[$cod] = ['factor' => $resFac];
        }

        if (!empty($codMapBalance)) {
            $allCods  = array_keys($codMapBalance);
            $phCods   = implode(',', array_fill(0, count($allCods), '?'));

            // Helper para sumar movimientos de una tabla al $movimientoNeto
            $sumarMovs = function(array $rows, bool $negativo) use (&$movimientoNeto, $codMapBalance) {
                foreach ($rows as $r) {
                    $info = $codMapBalance[(int)$r['CodCotizacion']] ?? null;
                    if (!$info) continue;
                    $qty = (float)$r['Cantidad'] * $info['factor'];
                    $movimientoNeto += $negativo ? -$qty : $qty;
                }
            };

            // Ajustes (+)
            $s = $conn->prepare("SELECT CodCotizacion, Cantidad FROM msaccess_masivo_AjustesInventario WHERE Fecha BETWEEN ? AND ? AND CodCotizacion IN ($phCods) AND Sucursal = ?");
            $s->execute(array_merge([$fechaMovDesde, $fechaMovHasta], $allCods, [$sucInt]));
            $sumarMovs($s->fetchAll(PDO::FETCH_ASSOC), false);

            // Merma (-)
            $s = $conn->prepare("SELECT CodCotizacion, Cantidad FROM msaccess_masivo_MermaCotizacion WHERE Fecha BETWEEN ? AND ? AND CodCotizacion IN ($phCods) AND Sucursal = ?");
            $s->execute(array_merge([$fechaMovDesde, $fechaMovHasta], $allCods, [$sucInt]));
            $sumarMovs($s->fetchAll(PDO::FETCH_ASSOC), true);

            // Despachos (+) — filtrar por Destino = "Pitaya N"
            $s = $conn->prepare("SELECT sub.CodCotizacion, sub.Cantidad FROM msaccess_masivo_SubPreIngresosPitaya sub INNER JOIN msaccess_masivo_PreIngresoPitaya pre ON pre.CodPreIngresoPitaya=sub.CodPreIngresoPitaya WHERE pre.Fecha BETWEEN ? AND ? AND sub.CodCotizacion IN ($phCods) AND pre.Destino REGEXP ?");
            $s->execute(array_merge([$fechaMovDesde, $fechaMovHasta], $allCods, ["[Pp]itaya[[:space:]]+{$sucInt}([^0-9]|$)"]));
            $sumarMovs($s->fetchAll(PDO::FETCH_ASSOC), false);

            // Compras (+)
            $s = $conn->prepare("SELECT CodCotizacion, Cantidad FROM msaccess_masivo_Compras WHERE Fecha BETWEEN ? AND ? AND CodCotizacion IN ($phCods) AND Sucursal = ?");
            $s->execute(array_merge([$fechaMovDesde, $fechaMovHasta], $allCods, [$sucInt]));
            $sumarMovs($s->fetchAll(PDO::FETCH_ASSOC), false);
        }
    }

    // ── 5. Fallback cons_diario ──────────────────────────────────────────
    if ($consDiario === null || $consDiario <= 0) {
        $semDesdeF = $semCorte - 5; $semHastaF = $semCorte - 1;
        $stmtRng = $conn->prepare("SELECT MIN(fecha_inicio) as f1, MAX(fecha_fin) as f2 FROM SemanasSistema WHERE numero_semana BETWEEN ? AND ?");
        $stmtRng->execute([$semDesdeF, $semHastaF]);
        $rng = $stmtRng->fetch(PDO::FETCH_ASSOC);
        if ($rng && $rng['f1']) {
            $stmtVen = $conn->prepare("SELECT v.Semana as sem, SUM(v.Cantidad*sr.Cantidad) as cant FROM VentasGlobalesAccessCSV v INNER JOIN SubReceta sr ON sr.CodBatido=v.CodProducto INNER JOIN diccionario_productos_legado d ON d.CodCotizacion=sr.codporcion WHERE v.Anulado=0 AND v.local=? AND v.Semana BETWEEN ? AND ? AND v.Fecha BETWEEN ? AND ? AND d.id_producto_presentacion=? GROUP BY v.Semana");
            $stmtVen->execute([$codSucursal, $semDesdeF, $semHastaF, $rng['f1'], $rng['f2'], $idPP]);
            $ventasSem = $stmtVen->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($ventasSem)) {
                $vals = [];
                for ($s = $semDesdeF; $s <= $semHastaF; $s++) {
                    $found = array_filter($ventasSem, fn($r) => (int)$r['sem'] === $s);
                    $vals[] = $found ? (float)array_values($found)[0]['cant'] : 0.0;
                }
                $nonZero = array_filter($vals, fn($v) => $v > 0);
                if (!empty($nonZero)) {
                    $semC = calcularProyeccionWLS($vals);
                    $stmtAdj = $conn->prepare("SELECT clp.ajuste_demanda FROM producto_presentacion pp LEFT JOIN configuracion_logistica_producto clp ON clp.codigo_insumo=pp.categoria_insumo AND clp.cod_sucursal=? WHERE pp.id=? LIMIT 1");
                    $stmtAdj->execute([$codSucursal, $idPP]);
                    $adj = (float)($stmtAdj->fetchColumn() ?: 0);
                    $consDiario = ($semC * (1 + $adj)) / 7.0;
                }
            }
        }
        if ($consDiario === null) $consDiario = 0.0;
    }

    // ── 6. Fallback despacho_factor ──────────────────────────────────────
    if ($despFactor === null || $despFactor <= 0) {
        $despFactor = 1.0;
        $stmtDB = $conn->prepare("SELECT crp.cantidad AS factor FROM producto_presentacion ppd INNER JOIN componentes_receta_producto crp ON crp.id_receta_producto_global=ppd.Id_receta_producto WHERE ppd.presentacion_despacho=1 AND ppd.Activo='SI' AND crp.id_presentacion_producto=? AND (SELECT COUNT(*) FROM componentes_receta_producto crp2 WHERE crp2.id_receta_producto_global=ppd.Id_receta_producto)=1 ORDER BY ppd.id ASC LIMIT 1");
        $stmtDB->execute([$idPP]);
        $dfRow = $stmtDB->fetch(PDO::FETCH_ASSOC);
        if ($dfRow && (float)$dfRow['factor'] > 0) {
            $despFactor = (float)$dfRow['factor'];
        } else {
            $stmtDA = $conn->prepare("SELECT ppd.cantidad AS d_cant, ppd.id_unidad_producto AS d_uid, pp.cantidad AS pp_cant, pp.id_unidad_producto AS pp_uid FROM producto_presentacion pp INNER JOIN producto_presentacion ppd ON ppd.id_producto_maestro=pp.id_producto_maestro AND ppd.presentacion_despacho=1 AND ppd.Activo='SI' AND pp.id_producto_maestro IS NOT NULL WHERE pp.id=? AND pp.Activo='SI' ORDER BY ppd.id ASC LIMIT 1");
            $stmtDA->execute([$idPP]);
            $daRow = $stmtDA->fetch(PDO::FETCH_ASSOC);
            if ($daRow && (float)$daRow['pp_cant'] > 0 && $daRow['d_uid'] === $daRow['pp_uid'])
                $despFactor = (float)$daRow['d_cant'] / (float)$daRow['pp_cant'];
        }
    }

    // ── 7. Proyección D-1 con movimientos reales ─────────────────────────
    // stock_D1 = stock_domingo + mov_neto_real - cons_diario × dias
    $stockD1Uso = $sinInventario
        ? null
        : max(0.0, $stockDomingo + $movimientoNeto - ($consDiario * $diasTranscurridos));

    $dfSafe          = ($despFactor > 0) ? $despFactor : 1.0;
    $stockD1Paquetes = ($stockD1Uso !== null) ? ($stockD1Uso / $dfSafe) : null;
    $stockMaxFinalPaq = $stockMaxFinal;

    $despachoPron = null;
    if (!$sinInventario && $stockMaxFinalPaq !== null && $stockD1Paquetes !== null)
        $despachoPron = max(0, (int)ceil($stockMaxFinalPaq - $stockD1Paquetes));

    // ── 8. Respuesta ─────────────────────────────────────────────────────
    echo json_encode([
        'ok'                          => true,
        'id_pp'                       => $idPP,
        'sem_corte'                   => $semCorte,
        'domingo_corte'               => $domingoCorte,
        'stock_domingo'               => $sinInventario ? null : round($stockDomingo, 4),
        'movimiento_neto'             => round($movimientoNeto, 4),
        'cons_diario'                 => round($consDiario, 6),
        'fecha_despacho'              => $fechaDespacho,
        'fecha_D1'                    => $fechaD1,
        'dias_transcurridos'          => $diasTranscurridos,
        'stock_D1_uso'                => $stockD1Uso     !== null ? round($stockD1Uso, 4)      : null,
        'stock_D1_paquetes'           => $stockD1Paquetes !== null ? round($stockD1Paquetes, 4) : null,
        'stock_max_final_paquetes'    => $stockMaxFinalPaq !== null ? round($stockMaxFinalPaq, 4) : null,
        'despacho_sugerido_pronostico' => $despachoPron,
        'despacho_factor'             => round($dfSafe, 6),
        'sin_inventario'              => $sinInventario,
    ]);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'Error interno: ' . $e->getMessage()]);
}
