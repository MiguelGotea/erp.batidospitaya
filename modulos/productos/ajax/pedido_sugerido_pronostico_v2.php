<?php
/* ============================================================
   AJAX: Pronóstico de existencia D-1 (bulk) — Pedido Sugerido v2
   modulos/productos/ajax/pedido_sugerido_pronostico_v2.php

   Matemática idéntica a dashboard_consumo_get_stock_pronosticado.php:
     · Kardex (msaccess_masivo_InventarioCotizacion) como stock base
     · Balance diario desde pivot de corte hasta fin del rango
       usando movimientos reales + consumo teórico
     · Proyección DOW-ponderada: 0.65×pDow + 0.35×promDiario
     · null si fecha_D1 ≤ fechaFinRango (fecha dentro del histórico)
     · null si no hay datos de Kardex para el producto

   Diferencias vs dashboard_consumo:
     · Una sola sucursal (cod_sucursal) en lugar de array
     · Cada producto tiene su propia fecha_D1 (fechas_d1[id_pp])
     · semCorte debe estar dentro de [semDesde, semHasta]

   Parámetros POST:
     ids_pp[]           int[]    — IDs de producto_presentacion
     fechas_d1[id_pp]   string   — mapa: id_pp → fecha_D1 (YYYY-MM-DD)
     semana_desde        int
     semana_hasta        int
     semana_corte        int      — debe estar dentro del rango
     cod_sucursal        int      — código numérico de la sucursal

   Respuesta:
     { ok: true, stocks: { "id_pp": float|null } }
   ============================================================ */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
header('Content-Type: application/json; charset=utf-8');
set_time_limit(0);
ini_set('memory_limit', '512M');

$usuario = obtenerUsuarioActual();
$cargo   = $usuario['CodNivelesCargos'];
// Este AJAX es reutilizado por pronostico_abastecimiento.php, por lo que
// se acepta el permiso de cualquiera de los dos módulos.
if (!tienePermiso('dashboard_consumo_insumos', 'vista', $cargo) && !tienePermiso('pronostico_abastecimiento', 'vista', $cargo)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Sin permiso para calcular el pronóstico.']);
    exit();
}

$idsPP    = isset($_POST['ids_pp'])       ? array_map('intval', (array)$_POST['ids_pp']) : [];
$fechasD1 = isset($_POST['fechas_d1'])    ? (array)$_POST['fechas_d1']                   : [];
$semDesde = isset($_POST['semana_desde']) ? (int)$_POST['semana_desde']                  : 0;
$semHasta = isset($_POST['semana_hasta']) ? (int)$_POST['semana_hasta']                  : 0;
$semCorte = isset($_POST['semana_corte']) ? (int)$_POST['semana_corte']                  : 0;
$codSuc   = isset($_POST['cod_sucursal']) ? (int)$_POST['cod_sucursal']                  : 0;

if (empty($idsPP) || empty($fechasD1) || !$semDesde || !$semHasta || !$semCorte || !$codSuc) {
    echo json_encode(['ok' => false, 'msg' => 'Parámetros incompletos']); exit();
}
$semDesde = min($semDesde, $semHasta);
$semHasta = max($semDesde, $semHasta);
if ($semCorte < $semDesde || $semCorte > $semHasta) {
    echo json_encode(['ok' => false, 'msg' => 'Semana de corte fuera del rango de análisis']); exit();
}

// Limpiar y validar mapa fechas_d1
$fechasD1Clean = [];
foreach ($fechasD1 as $idPP => $fecha) {
    $idPP = (int)$idPP;
    $fecha = trim($fecha);
    if (in_array($idPP, $idsPP) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        $fechasD1Clean[$idPP] = $fecha;
    }
}

try {
    // ── 1. Fechas del rango de análisis ──────────────────────────────────
    $rD = $conn->prepare("SELECT MIN(fecha_inicio) AS ini, MAX(fecha_fin) AS fin FROM SemanasSistema WHERE numero_semana BETWEEN :d AND :h");
    $rD->execute([':d' => $semDesde, ':h' => $semHasta]);
    $rangeDates    = $rD->fetch(PDO::FETCH_ASSOC);
    $fechaIniRango = $rangeDates['ini'];
    $fechaFinRango = $rangeDates['fin'];

    // ── Detectar semana actual incompleta ─────────────────────────────────
    // Si fecha_fin (domingo de semHasta en BD) es futuro, la semana en curso aún no terminó.
    // Aplicamos la misma lógica que dashboard_consumo_get_stock_pronosticado.php:
    //   a) Limitar la query de ventas a ayer (excluir días futuros con consumo=0)
    //   b) Limitar originalRangeLen a ayer (el balance forward no procesa días futuros)
    $hoy  = date('Y-m-d');
    $ayer = date('Y-m-d', strtotime('-1 day'));
    $semanaActualIncompleta = ($fechaFinRango > $ayer);
    $fechaFinQuery = $semanaActualIncompleta ? $ayer : $fechaFinRango;

    // ── 2. Semana anterior al corte (stock base del inventario) ──────────
    $rSA = $conn->prepare("SELECT numero_semana FROM SemanasSistema WHERE numero_semana < :c ORDER BY numero_semana DESC LIMIT 1");
    $rSA->execute([':c' => $semCorte]);
    $semAnt = $rSA->fetchColumn();
    if (!$semAnt) { echo json_encode(['ok' => false, 'msg' => 'Sin semana anterior al corte']); exit(); }

    // ── 3. Fecha inicio de la semana de corte (pivot del gráfico) ────────
    $rFC = $conn->prepare("SELECT fecha_inicio FROM SemanasSistema WHERE numero_semana = :c");
    $rFC->execute([':c' => $semCorte]);
    $fechaIniCorte = $rFC->fetchColumn();

    // ── 4. Conversiones ───────────────────────────────────────────────────
    $convIndex = [];
    $rConv = $conn->prepare("SELECT id_unidad_producto_inicio AS i, id_unidad_producto_final AS f, cantidad AS fac FROM conversion_unidad_producto");
    $rConv->execute();
    foreach ($rConv->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $convIndex[(int)$c['i']][(int)$c['f']] = (float)$c['fac'];
        $convIndex[(int)$c['f']][(int)$c['i']] = $c['fac'] != 0 ? 1/(float)$c['fac'] : 0;
    }

    // ── 5. Maestro → Base map ─────────────────────────────────────────────
    $rMB = $conn->prepare("
        SELECT pp.id, pp.id_unidad_producto AS unid, pp.cantidad AS cant, pp.id_producto_maestro AS mid, pp.Id_receta_producto
        FROM producto_presentacion pp
        WHERE pp.presentacion_basica_inventario=1 AND pp.Activo='SI'
        ORDER BY pp.id ASC
    ");
    $rMB->execute();
    $maestroToBase = [];
    foreach ($rMB->fetchAll(PDO::FETCH_ASSOC) as $pm) {
        $mid = (int) $pm['mid'];
        if ($mid > 0) {
            $esReceta = !empty($pm['Id_receta_producto']) && $pm['Id_receta_producto'] !== '0';
            if (!isset($maestroToBase[$mid])) {
                $maestroToBase[$mid] = [
                    'base_pp_id' => (int)$pm['id'], 
                    'base_unid'  => (int)$pm['unid'], 
                    'base_cant'  => max((float)$pm['cant'], 0.001),
                    'es_receta'  => $esReceta
                ];
            } elseif (!$esReceta && $maestroToBase[$mid]['es_receta']) {
                $maestroToBase[$mid] = [
                    'base_pp_id' => (int)$pm['id'], 
                    'base_unid'  => (int)$pm['unid'], 
                    'base_cant'  => max((float)$pm['cant'], 0.001),
                    'es_receta'  => $esReceta
                ];
            }
        }
    }

    // ── 6. Cascade Map ────────────────────────────────────────────────────
    $rCas = $conn->prepare("SELECT pp_pkg.id AS pkg_id, crp.id_presentacion_producto AS base_id, crp.cantidad AS factor FROM producto_presentacion pp_pkg INNER JOIN componentes_receta_producto crp ON crp.id_receta_producto_global = pp_pkg.Id_receta_producto INNER JOIN producto_presentacion pp_base ON pp_base.id = crp.id_presentacion_producto AND pp_base.presentacion_basica_inventario = 1 WHERE pp_pkg.presentacion_receta = 1 AND pp_pkg.Activo = 'SI' AND (SELECT COUNT(DISTINCT id_presentacion_producto) FROM componentes_receta_producto WHERE id_receta_producto_global = pp_pkg.Id_receta_producto) = 1");
    $rCas->execute();
    $cascadeMap = [];
    foreach ($rCas->fetchAll(PDO::FETCH_ASSOC) as $row) $cascadeMap[(int)$row['pkg_id']] = ['base_id' => (int)$row['base_id'], 'factor' => (float)$row['factor']];

    // ── 7. Diccionario ────────────────────────────────────────────────────
    $rDic = $conn->prepare("SELECT d.CodCotizacion, pp.id AS pp_id, pp.Nombre, pp.presentacion_basica_inventario AS es_base, pp.presentacion_receta AS es_receta, pp.id_unidad_producto AS pp_unid, pp.cantidad AS pp_cant, pp.id_producto_maestro AS id_maestro, pp.Id_receta_producto FROM diccionario_productos_legado d INNER JOIN producto_presentacion pp ON pp.id = d.id_producto_presentacion WHERE pp.Activo='SI'");
    $rDic->execute();
    $diccionario = [];
    foreach ($rDic->fetchAll(PDO::FETCH_ASSOC) as $d) $diccionario[(int)$d['CodCotizacion']] = $d;

    // ── 8. Alt Map ────────────────────────────────────────────────────────
    $altMap = [];
    foreach ($diccionario as $dic) {
        $pp_id = (int)$dic['pp_id'];
        if ($dic['es_base'] || $dic['es_receta']) continue;
        if (isset($cascadeMap[$pp_id])) continue;
        $mid = (int)$dic['id_maestro'];
        if (!$mid || !isset($maestroToBase[$mid])) continue;
        $base = $maestroToBase[$mid];
        $altUnid = (int)$dic['pp_unid']; $basUnid = (int)$base['base_unid'];
        if ($altUnid === $basUnid) $factor = (float)$dic['pp_cant'] / $base['base_cant'];
        elseif (isset($convIndex[$altUnid][$basUnid])) $factor = ((float)$dic['pp_cant'] * $convIndex[$altUnid][$basUnid]) / $base['base_cant'];
        else continue;
        $altMap[$pp_id] = ['base_id' => $base['base_pp_id'], 'factor' => $factor];
    }

    // ── 9. codMapBalance y codMapConsumo por id_pp ────────────────────────
    $codMapBalanceAll = [];
    $codMapConsumoAll = [];

    foreach ($idsPP as $targetId) {
        // Balance map
        foreach ($diccionario as $cod => $dic) {
            $pp_id = (int)$dic['pp_id'];
            $resBid = null; $resFac = 1.0;
            if      (isset($cascadeMap[$pp_id])) { $resBid = $cascadeMap[$pp_id]['base_id']; $resFac = $cascadeMap[$pp_id]['factor']; }
            elseif  (isset($altMap[$pp_id]))      { $resBid = $altMap[$pp_id]['base_id'];     $resFac = $altMap[$pp_id]['factor']; }
            elseif  ($dic['es_base'])              { $resBid = $pp_id; $resFac = 1.0; }
            if ($resBid === $targetId) {
                $codMapBalanceAll[$targetId][$cod] = ['factor' => $resFac];
            }
        }

        // Consumo map — pasos A, B, C
        $rMeta = $conn->prepare("SELECT pp.cantidad AS pp_cant, pp.id_unidad_producto, pp.id_producto_maestro, pp.Id_receta_producto FROM producto_presentacion pp WHERE pp.id = :id");
        $rMeta->execute([':id' => $targetId]);
        $prodMeta = $rMeta->fetch(PDO::FETCH_ASSOC);
        if (!$prodMeta) continue;
        $baseCant  = max((float)$prodMeta['pp_cant'], 0.001);
        $baseUnid  = (int)$prodMeta['id_unidad_producto'];
        $idMaestro = (int)$prodMeta['id_producto_maestro'];

        foreach ($diccionario as $cod => $dic) {
            $mid = (int)$dic['id_maestro'];
            if ($dic['es_base'] && (int)$dic['pp_id'] === $targetId) {
                $codMapConsumoAll[$targetId][$cod] = ['pp_id' => (int)$dic['pp_id'], 'pp_cant' => (float)$dic['pp_cant'], 'id_unid' => (int)$dic['pp_unid'], 'id_mae' => $mid, 'Id_receta_producto' => $dic['Id_receta_producto'], 'tipo' => 'directo'];
            } elseif ($mid > 0 && isset($maestroToBase[$mid]) && $maestroToBase[$mid]['base_pp_id'] === $targetId) {
                $base = $maestroToBase[$mid];
                $codMapConsumoAll[$targetId][$cod] = ['pp_id' => $base['base_pp_id'], 'pp_cant' => $base['base_cant'], 'id_unid' => $base['base_unid'], 'id_mae' => $mid, 'Id_receta_producto' => null, 'tipo' => 'auto'];
            }
        }

        // Paso C: fallback via CodIngrediente
        $codsEnConsumo = array_keys($codMapConsumoAll[$targetId] ?? []);
        if (!empty($codsEnConsumo)) {
            $phC  = implode(',', array_fill(0, count($codsEnConsumo), '?'));
            $stmtI = $conn->prepare("SELECT DISTINCT CodIngrediente FROM Cotizaciones WHERE CodCotizacion IN ($phC)");
            $stmtI->execute($codsEnConsumo);
            $ings = $stmtI->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($ings)) {
                $phI    = implode(',', array_fill(0, count($ings), '?'));
                $stmtFb = $conn->prepare("SELECT CodCotizacion FROM Cotizaciones WHERE CodIngrediente IN ($phI)");
                $stmtFb->execute($ings);
                foreach ($stmtFb->fetchAll(PDO::FETCH_ASSOC) as $cf) {
                    $cc = (int)$cf['CodCotizacion'];
                    if (!isset($codMapConsumoAll[$targetId][$cc]) && !isset($diccionario[$cc])) {
                        $codMapConsumoAll[$targetId][$cc] = ['pp_id' => $targetId, 'pp_cant' => $baseCant, 'id_unid' => $baseUnid, 'id_mae' => $idMaestro, 'Id_receta_producto' => null, 'tipo' => 'fallback'];
                    }
                }
            }
        }
    }

    // ── 10. Todos los CodCotizacion de balance ───────────────────────────
    $allCodsBalance = [];
    foreach ($codMapBalanceAll as $cmap) foreach (array_keys($cmap) as $cod) $allCodsBalance[$cod] = true;
    $allCodsBalance = array_keys($allCodsBalance);

    // ── 11. Lista de días del rango extendida hasta max(fechas_D1) ────────
    $maxFechaD1 = $fechaFinRango;
    foreach ($fechasD1Clean as $f) { if ($f > $maxFechaD1) $maxFechaD1 = $f; }

    $start   = new DateTime($fechaIniRango . ' 12:00:00');
    $end     = new DateTime($fechaFinRango . ' 12:00:00');
    $allDays = [];
    $cur = clone $start;
    while ($cur <= $end) { $allDays[] = $cur->format('Y-m-d'); $cur->modify('+1 day'); }

    // originalRangeLen = días reales (pasados) — limitar a ayer si semana incompleta
    if ($semanaActualIncompleta) {
        $lastRealIdx = -1;
        foreach ($allDays as $idx => $day) {
            if ($day <= $ayer) $lastRealIdx = $idx;
        }
        $originalRangeLen = $lastRealIdx >= 0 ? $lastRealIdx + 1 : 0;
    } else {
        $originalRangeLen = count($allDays);
    }

    // Extender hasta la fecha_D1 más lejana (partiendo siempre desde fecha_fin de la BD)
    $endExt = new DateTime($maxFechaD1 . ' 12:00:00');
    $extCur = clone $end; $extCur->modify('+1 day');
    while ($extCur <= $endExt) { $allDays[] = $extCur->format('Y-m-d'); $extCur->modify('+1 day'); }

    // ── 12. Pivot index (primer día de la semana de corte) ───────────────
    $pivotIdx = array_search($fechaIniCorte, $allDays);
    $pIdx = ($pivotIdx !== false && $pivotIdx >= 0) ? (int)$pivotIdx : 0;

    // ── 13. Queries batch de Kardex ──────────────────────────────────────
    $invCorteByPP     = [];   // [id_pp] = float  — stock en semana anterior al corte
    $movsByPPFecha    = [];   // [id_pp][fecha] = float neto (ajuste+, merma-, compra+, despacho+)
    $consTeoByPPFecha = [];   // [id_pp][fecha] = float consumo teórico

    if (!empty($allCodsBalance)) {
        $phCods = implode(',', array_fill(0, count($allCodsBalance), '?'));

        // 13a. Inventario de semAnt (stock base del corte) — Kardex
        $st1 = $conn->prepare("SELECT k.CodCotizacion, k.Cantidad FROM msaccess_masivo_InventarioCotizacion k INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin WHERE ss.numero_semana = ? AND k.CodCotizacion IN ($phCods) AND k.Sucursal = ?");
        $st1->execute(array_merge([$semAnt], $allCodsBalance, [$codSuc]));
        foreach ($st1->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $cod = (int)$r['CodCotizacion'];
            foreach ($idsPP as $tid) {
                if (isset($codMapBalanceAll[$tid][$cod])) {
                    if (!isset($invCorteByPP[$tid])) $invCorteByPP[$tid] = 0;
                    $invCorteByPP[$tid] += round((float)$r['Cantidad'] * $codMapBalanceAll[$tid][$cod]['factor'], 4);
                }
            }
        }

        // Helper: sumar movimientos a movsByPPFecha
        $addMov = function(array $rows, int $sign) use (&$movsByPPFecha, $idsPP, $codMapBalanceAll) {
            foreach ($rows as $r) {
                $cod = (int)$r['CodCotizacion']; $fecha = $r['Fecha'];
                foreach ($idsPP as $tid) {
                    if (isset($codMapBalanceAll[$tid][$cod])) {
                        if (!isset($movsByPPFecha[$tid][$fecha])) $movsByPPFecha[$tid][$fecha] = 0;
                        $movsByPPFecha[$tid][$fecha] += $sign * round((float)$r['Cantidad'] * $codMapBalanceAll[$tid][$cod]['factor'], 4);
                    }
                }
            }
        };

        // 13b. Ajustes (+)
        $st3 = $conn->prepare("SELECT k.Fecha, k.CodCotizacion, k.Cantidad FROM msaccess_masivo_AjustesInventario k INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin WHERE ss.numero_semana BETWEEN ? AND ? AND k.CodCotizacion IN ($phCods) AND k.Sucursal = ?");
        $st3->execute(array_merge([$semDesde, $semHasta], $allCodsBalance, [$codSuc]));
        $addMov($st3->fetchAll(PDO::FETCH_ASSOC), 1);

        // 13c. Mermas (-)
        $st4 = $conn->prepare("SELECT k.Fecha, k.CodCotizacion, k.Cantidad FROM msaccess_masivo_MermaCotizacion k INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin WHERE ss.numero_semana BETWEEN ? AND ? AND k.CodCotizacion IN ($phCods) AND k.Sucursal = ?");
        $st4->execute(array_merge([$semDesde, $semHasta], $allCodsBalance, [$codSuc]));
        $addMov($st4->fetchAll(PDO::FETCH_ASSOC), -1);

        // 13d. Compras (+)
        $st6 = $conn->prepare("SELECT k.Fecha, k.CodCotizacion, k.Cantidad FROM msaccess_masivo_Compras k INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin WHERE ss.numero_semana BETWEEN ? AND ? AND k.CodCotizacion IN ($phCods) AND k.Sucursal = ?");
        $st6->execute(array_merge([$semDesde, $semHasta], $allCodsBalance, [$codSuc]));
        $addMov($st6->fetchAll(PDO::FETCH_ASSOC), 1);

        // 13e. Despachos recibidos (+) — filtro: Destino = "Pitaya {codSuc}"
        $st5 = $conn->prepare("SELECT pre.Fecha, sub.CodCotizacion, sub.Cantidad FROM msaccess_masivo_SubPreIngresosPitaya sub INNER JOIN msaccess_masivo_PreIngresoPitaya pre ON pre.CodPreIngresoPitaya = sub.CodPreIngresoPitaya INNER JOIN SemanasSistema ss ON pre.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin WHERE ss.numero_semana BETWEEN ? AND ? AND sub.CodCotizacion IN ($phCods) AND pre.Destino REGEXP ?");
        $st5->execute(array_merge([$semDesde, $semHasta], $allCodsBalance, ["[Pp]itaya[[:space:]]+{$codSuc}([^0-9]|\$)"]));
        $addMov($st5->fetchAll(PDO::FETCH_ASSOC), 1);
        
        // 13f. Despachos Reales (Preingresos) desde HOY hacia el futuro (incluye el de Hoy)
        $despachosRealesByPPFecha = [];
        $fechaLimite = date('Y-m-d', strtotime('+30 days'));
        $stFuturo = $conn->prepare("SELECT pre.Fecha, sub.CodCotizacion, sub.Cantidad FROM msaccess_masivo_SubPreIngresosPitaya sub INNER JOIN msaccess_masivo_PreIngresoPitaya pre ON pre.CodPreIngresoPitaya = sub.CodPreIngresoPitaya WHERE pre.Fecha BETWEEN ? AND ? AND sub.CodCotizacion IN ($phCods) AND pre.Destino REGEXP ?");
        $stFuturo->execute(array_merge([$hoy, $fechaLimite], $allCodsBalance, ["[Pp]itaya[[:space:]]+{$codSuc}([^0-9]|\$)"]));
        foreach ($stFuturo->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $cod = (int)$r['CodCotizacion']; 
            $fec = $r['Fecha'];
            foreach ($idsPP as $tid) {
                if (isset($codMapBalanceAll[$tid][$cod])) {
                    if (!isset($despachosRealesByPPFecha[$tid])) $despachosRealesByPPFecha[$tid] = [];
                    if (!isset($despachosRealesByPPFecha[$tid][$fec])) $despachosRealesByPPFecha[$tid][$fec] = 0;
                    $despachosRealesByPPFecha[$tid][$fec] += round((float)$r['Cantidad'] * $codMapBalanceAll[$tid][$cod]['factor'], 4);
                }
            }
        }
    }

    // ── 14. Consumo teórico diario por id_pp ─────────────────────────────
    $stmtU = $conn->prepare("SELECT id, nombre, abreviado, nombres_opcionales FROM unidad_producto");
    $stmtU->execute();
    $uPorNom = [];
    foreach ($stmtU->fetchAll(PDO::FETCH_ASSOC) as $u) {
        $uid = (int)$u['id'];
        $uPorNom[strtolower(trim($u['nombre']))] = $uid;
        if ($u['abreviado']) $uPorNom[strtolower(trim($u['abreviado']))] = $uid;
        if (!empty($u['nombres_opcionales'])) foreach (preg_split('/[,;|]+/', $u['nombres_opcionales']) as $al) { $ak = strtolower(trim($al)); if ($ak) $uPorNom[$ak] = $uid; }
    }

    foreach ($idsPP as $targetId) {
        $cmc = $codMapConsumoAll[$targetId] ?? [];
        if (empty($cmc)) continue;
        $allCodsCons = array_keys($cmc);
        $phCC = implode(',', array_fill(0, count($allCodsCons), '?'));

        $rI = $conn->prepare("SELECT DISTINCT CodIngrediente FROM Cotizaciones WHERE CodCotizacion IN ($phCC)");
        $rI->execute($allCodsCons);
        $ingsRel = $rI->fetchAll(PDO::FETCH_COLUMN);

        $cotP2P3 = []; $dbIng = [];
        if (!empty($ingsRel)) {
            $phI = implode(',', array_fill(0, count($ingsRel), '?'));
            $sCot = $conn->prepare("SELECT CodIngrediente, CodCotizacion, Conversion, Prioridad FROM Cotizaciones WHERE CodIngrediente IN ($phI) AND (Subproducto IS NULL OR Subproducto!=1) AND (Marca IS NULL OR Marca!='Almacen Global') ORDER BY CodIngrediente, Conversion DESC, Prioridad ASC");
            $sCot->execute($ingsRel);
            foreach ($sCot->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $ci = $c['CodIngrediente'];
                if (!isset($cotP2P3[$ci])) $cotP2P3[$ci] = ['p2' => null, 'p3' => null];
                if ($c['Conversion']==1 && $c['Prioridad']==1 && !$cotP2P3[$ci]['p2']) $cotP2P3[$ci]['p2'] = (int)$c['CodCotizacion'];
                if (!$cotP2P3[$ci]['p3']) $cotP2P3[$ci]['p3'] = (int)$c['CodCotizacion'];
            }
            $sIng = $conn->prepare("SELECT CodIngrediente, Unidad FROM DBIngredientes WHERE CodIngrediente IN ($phI)");
            $sIng->execute($ingsRel);
            foreach ($sIng->fetchAll(PDO::FETCH_ASSOC) as $row) $dbIng[$row['CodIngrediente']] = $row;
        }

        // Query de ventas — limitar a $fechaFinQuery (ayer si semana incompleta)
        // para no traer días futuros con consumo=0 que distorsionen el promedio DOW.
        $phSucsQ = '?';
        $pValS   = [$fechaIniRango, $fechaFinQuery, $semDesde, $semHasta, $codSuc];
        $whereEx = "1=0";
        if (!empty($ingsRel))    { $whereEx .= " OR sr.CodIngrediente IN (" . implode(',', array_fill(0, count($ingsRel), '?')) . ")"; $pValS = array_merge($pValS, $ingsRel); }
        if (!empty($allCodsCons)){ $whereEx .= " OR sr.codporcion IN ($phCC)"; $pValS = array_merge($pValS, $allCodsCons); }

        $sqlV = "SELECT v.Fecha, sr.CodIngrediente, sr.codporcion, SUM(v.Cantidad * sr.Cantidad) AS total
                 FROM VentasGlobalesAccessCSV v
                 INNER JOIN SubReceta sr ON sr.CodBatido = v.CodProducto
                 WHERE v.Anulado=0
                   AND v.Fecha   BETWEEN ? AND ?
                   AND v.Semana  BETWEEN ? AND ?
                   AND v.local   IN ($phSucsQ)
                   AND v.CodProducto IS NOT NULL
                   AND ($whereEx)
                   AND (sr.codporcion IS NULL OR sr.codporcion NOT IN
                        (SELECT CodCotizacionPorcion FROM MezclaPorcionesAccess WHERE CodCotizacionPorcion IS NOT NULL))
                 GROUP BY v.Fecha, sr.CodIngrediente, sr.codporcion";
        $stV = $conn->prepare($sqlV);
        $stV->execute($pValS);

        foreach ($stV->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $mapeo = null; $esP1 = false;
            $cp = $f['codporcion'] ? (int)$f['codporcion'] : null;
            $ci = $f['CodIngrediente'];
            if ($cp && isset($cmc[$cp])) { $mapeo = $cmc[$cp]; $esP1 = true; }
            elseif ($ci && isset($cotP2P3[$ci])) {
                $p2 = $cotP2P3[$ci]['p2']; $p3 = $cotP2P3[$ci]['p3'];
                $selectedCot = null;
                if ($p2 && (isset($diccionario[$p2]) || isset($cmc[$p2]))) $selectedCot = $p2;
                elseif ($p3 && (isset($diccionario[$p3]) || isset($cmc[$p3]))) $selectedCot = $p3;
                if ($selectedCot && isset($cmc[$selectedCot])) $mapeo = $cmc[$selectedCot];
            }
            if ($mapeo) {
                $cantTotal = (float)$f['total']; $val = 0;
                $esGlobal  = !empty($mapeo['Id_receta_producto']);
                if ($esGlobal) {
                    $val = $cantTotal;
                } else {
                    $unAcc   = $dbIng[$ci]['Unidad'] ?? '';
                    $idUnAcc = isset($uPorNom[strtolower(trim($unAcc))]) ? $uPorNom[strtolower(trim($unAcc))] : null;
                    $factor  = 1.0;
                    if ($idUnAcc && $idUnAcc !== $mapeo['id_unid'] && isset($convIndex[$idUnAcc][$mapeo['id_unid']])) {
                        $factor = $convIndex[$idUnAcc][$mapeo['id_unid']];
                    }
                    $val = ($cantTotal * $factor) / $mapeo['pp_cant'];
                    if ($esP1) $val = round($val * 2) / 2;
                }
                $fec = $f['Fecha'];
                if (!isset($consTeoByPPFecha[$targetId][$fec])) $consTeoByPPFecha[$targetId][$fec] = 0;
                $consTeoByPPFecha[$targetId][$fec] += $val;
            }
        }
    }

    $stocks = [];
    $preingresosHoyRes = [];
    $despachosRealesRes = [];
    $diasProyRes = [];
    foreach ($idsPP as $targetId) {
        $fechaD1 = $fechasD1Clean[$targetId] ?? null;

        // Default preingreso hoy (backward compatibility)
        $preingresosHoyRes[(string)$targetId] = round($despachosRealesByPPFecha[$targetId][$hoy] ?? 0, 2);

        // Despachos reales estructurados por fecha
        if (isset($despachosRealesByPPFecha[$targetId])) {
            $despachosRealesRes[(string)$targetId] = [];
            foreach ($despachosRealesByPPFecha[$targetId] as $f => $val) {
                $despachosRealesRes[(string)$targetId][$f] = round($val, 2);
            }
        } else {
            $despachosRealesRes[(string)$targetId] = new stdClass(); // Objeto vacío para JSON
        }

        // Sin fecha asignada → null
        if (!$fechaD1) { $stocks[(string)$targetId] = null; continue; }

        // fecha_D1 dentro del rango histórico completo → null (por diseño).
        // EXCEPCIÓN: si la semana está incompleta, fechaFinRango es un domingo futuro;
        // en ese caso permitimos fecha_D1 dentro de la semana actual (>= hoy) porque
        // la proyección arranca desde ayer (anchorVal) hacia adelante.
        $esD1ValidaSemIncompleta = $semanaActualIncompleta && $fechaD1 >= $ayer;
        if ($fechaD1 <= $fechaFinRango && !$esD1ValidaSemIncompleta) {
            $stocks[(string)$targetId] = null; continue;
        }

        $invCorte  = $invCorteByPP[$targetId]     ?? 0;
        $movsFecha = $movsByPPFecha[$targetId]     ?? [];
        $consFecha = $consTeoByPPFecha[$targetId]  ?? [];

        // Sin ningún dato de Kardex → null
        if ($invCorte == 0 && empty($movsFecha) && empty($consFecha)) {
            $stocks[(string)$targetId] = null;
            continue;
        }

        // Balance hacia adelante: desde pivot de corte hasta fin del rango
        // (movimientos reales + consumo teórico real — igual que la línea azul del Kardex)
        $balFwd = $invCorte;
        for ($i = $pIdx; $i < $originalRangeLen; $i++) {
            $balFwd += ($movsFecha[$allDays[$i]] ?? 0) - ($consFecha[$allDays[$i]] ?? 0);
        }
        $anchorVal = $balFwd;  // Stock estimado al final del rango histórico

        // En lugar de restar usando la media histórica (DOW), retornamos el stock real hasta ayer (anchorVal)
        // y el número de días a proyectar, para que el frontend pueda restarle usando el consumo diario WLS.
        $diasD1 = 0;
        for ($i = $originalRangeLen; $i < count($allDays); $i++) {
            if ($allDays[$i] > $fechaD1) break;
            $diasD1++;
        }

        $stocks[(string)$targetId] = round($anchorVal, 2);
        $diasProyRes[(string)$targetId] = $diasD1;
    }

    echo json_encode(['ok' => true, 'stocks' => $stocks, 'preingresos_hoy' => $preingresosHoyRes, 'despachos_reales' => $despachosRealesRes, 'dias_proy' => $diasProyRes], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
