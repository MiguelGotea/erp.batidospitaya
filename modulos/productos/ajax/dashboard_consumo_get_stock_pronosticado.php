<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
header('Content-Type: application/json; charset=utf-8');
set_time_limit(0);
ini_set('memory_limit', '512M');
$usuario = obtenerUsuarioActual();
$cargo   = $usuario['CodNivelesCargos'];
if (!tienePermiso('dashboard_consumo_insumos', 'vista', $cargo)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Sin permiso']);
    exit();
}
$idsPP     = isset($_POST['ids_pp'])           ? array_map('intval', (array)$_POST['ids_pp'])     : [];
$semDesde  = isset($_POST['semana_desde'])     ? (int)$_POST['semana_desde']    : 0;
$semHasta  = isset($_POST['semana_hasta'])     ? (int)$_POST['semana_hasta']    : 0;
$semCorte  = isset($_POST['semana_corte'])     ? (int)$_POST['semana_corte']    : 0;
$fechaPron = isset($_POST['fecha_pronostico']) ? trim($_POST['fecha_pronostico']): '';
$sucsPost  = isset($_POST['sucursales'])       ? array_map('intval', (array)$_POST['sucursales']) : [];
if (empty($idsPP) || !$semDesde || !$semHasta || !$semCorte || !$fechaPron) {
    echo json_encode(['ok' => false, 'msg' => 'Parámetros incompletos']); exit();
}
$semDesde = min($semDesde, $semHasta);
$semHasta = max($semDesde, $semHasta);
if ($semCorte < $semDesde || $semCorte > $semHasta) {
    echo json_encode(['ok' => false, 'msg' => 'Sem. corte fuera de rango']); exit();
}
try {
    $rD = $conn->prepare("SELECT MIN(fecha_inicio) AS ini, MAX(fecha_fin) AS fin FROM SemanasSistema WHERE numero_semana BETWEEN :d AND :h");
    $rD->execute([':d' => $semDesde, ':h' => $semHasta]);
    $rangeDates    = $rD->fetch(PDO::FETCH_ASSOC);
    $fechaIniRango = $rangeDates['ini'];
    $fechaFinRango = $rangeDates['fin'];

    // ── Detectar semana actual incompleta ──────────────────────────────
    // Si fecha_fin del rango (domingo de semHasta según BD) está en el futuro,
    // la semana actual no ha terminado. El pronóstico puede arrancar desde hoy.
    $hoy  = date('Y-m-d');
    $ayer = date('Y-m-d', strtotime('-1 day'));
    $semanaActualIncompleta = ($fechaFinRango > $ayer);

    // Validación de fecha pronóstico:
    // - Semana completa: debe ser posterior al domingo de semHasta
    // - Semana incompleta: debe ser hoy o posterior (la semana aún no cerró)
    if ($semanaActualIncompleta) {
        if ($fechaPron < $hoy) {
            echo json_encode(['ok' => false, 'msg' => 'Fecha pronóstico debe ser hoy o posterior (semana '.$semHasta.' aún en curso)']); exit();
        }
    } else {
        if ($fechaPron <= $fechaFinRango) {
            echo json_encode(['ok' => false, 'msg' => 'Fecha pronóstico debe ser posterior al fin del rango ('.$fechaFinRango.')']); exit();
        }
    }

    $rSA = $conn->prepare("SELECT numero_semana FROM SemanasSistema WHERE numero_semana < :c ORDER BY numero_semana DESC LIMIT 1");
    $rSA->execute([':c' => $semCorte]);
    $semAnt = $rSA->fetchColumn();
    if (!$semAnt) { echo json_encode(['ok' => false, 'msg' => 'Sin semana anterior al corte']); exit(); }
    $rFC = $conn->prepare("SELECT fecha_inicio FROM SemanasSistema WHERE numero_semana = :c");
    $rFC->execute([':c' => $semCorte]);
    $fechaIniCorte = $rFC->fetchColumn();
    $rSucs = $conn->prepare("SELECT codigo FROM sucursales WHERE activa=1 AND sucursal=1");
    $rSucs->execute();
    $allSucsCods = $rSucs->fetchAll(PDO::FETCH_COLUMN);
    $sucFiltro = !empty($sucsPost) ? $sucsPost : array_map('intval', $allSucsCods);
    $phSucs = implode(',', array_fill(0, count($sucFiltro), '?'));
    $convIndex = [];
    $rConv = $conn->prepare("SELECT id_unidad_producto_inicio AS i, id_unidad_producto_final AS f, cantidad AS fac FROM conversion_unidad_producto");
    $rConv->execute();
    foreach ($rConv->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $convIndex[(int)$c['i']][(int)$c['f']] = (float)$c['fac'];
        $convIndex[(int)$c['f']][(int)$c['i']] = $c['fac'] != 0 ? 1/(float)$c['fac'] : 0;
    }
    // ── Maestro → Base map ────────────────────────────────────
    $rMetaAll = $conn->prepare("
        SELECT pp.id, pp.id_unidad_producto AS unid, pp.cantidad AS cant, pp.id_producto_maestro AS mid, pp.Id_receta_producto
        FROM producto_presentacion pp
        WHERE pp.presentacion_basica_inventario=1 AND pp.Activo='SI'
        ORDER BY pp.Nombre ASC
    ");
    $rMetaAll->execute();
    $maestroToBase = [];
    foreach ($rMetaAll->fetchAll(PDO::FETCH_ASSOC) as $pm) {
        $mid = (int) $pm['mid'];
        if ($mid > 0) {
            $esReceta = !empty($pm['Id_receta_producto']) && $pm['Id_receta_producto'] !== '0';
            if (!isset($maestroToBase[$mid]) || !$esReceta) {
                $maestroToBase[$mid] = [
                    'base_pp_id' => (int)$pm['id'], 
                    'base_unid'  => (int)$pm['unid'], 
                    'base_cant'  => max((float)$pm['cant'], 0.001)
                ];
            }
        }
    }

    // ── Cascade Map ───────────────────────────────────────────
    $rCas = $conn->prepare("SELECT pp_pkg.id AS pkg_id, crp.id_presentacion_producto AS base_id, crp.cantidad AS factor FROM producto_presentacion pp_pkg INNER JOIN componentes_receta_producto crp ON crp.id_receta_producto_global = pp_pkg.Id_receta_producto INNER JOIN producto_presentacion pp_base ON pp_base.id = crp.id_presentacion_producto AND pp_base.presentacion_basica_inventario = 1 WHERE pp_pkg.presentacion_receta = 1 AND pp_pkg.Activo = 'SI' AND (SELECT COUNT(DISTINCT id_presentacion_producto) FROM componentes_receta_producto WHERE id_receta_producto_global = pp_pkg.Id_receta_producto) = 1");
    $rCas->execute();
    $cascadeMap = [];
    foreach ($rCas->fetchAll(PDO::FETCH_ASSOC) as $row) $cascadeMap[(int)$row['pkg_id']] = ['base_id' => (int)$row['base_id'], 'factor' => (float)$row['factor']];

    // ── Diccionario ───────────────────────────────────────────
    $rDic = $conn->prepare("SELECT d.CodCotizacion, pp.id AS pp_id, pp.Nombre, pp.presentacion_basica_inventario AS es_base, pp.presentacion_receta AS es_receta, pp.id_unidad_producto AS pp_unid, pp.cantidad AS pp_cant, pp.id_producto_maestro AS id_maestro, pp.Id_receta_producto FROM diccionario_productos_legado d INNER JOIN producto_presentacion pp ON pp.id = d.id_producto_presentacion WHERE pp.Activo='SI'");
    $rDic->execute();
    $diccionario = [];
    foreach ($rDic->fetchAll(PDO::FETCH_ASSOC) as $d) $diccionario[(int)$d['CodCotizacion']] = $d;

    // ── Alt Map ───────────────────────────────────────────────
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

    // ── codMapBalance por id_pp ───────────────────────────────
    // Para cada id_pp del array, construir su mapa de códigos
    $codMapBalanceAll = []; // [id_pp][cod] = ['factor','tipo','nombre','pp_id','id_unid','pp_cant','id_mae']
    foreach ($idsPP as $targetId) {
        foreach ($diccionario as $cod => $dic) {
            $pp_id = (int)$dic['pp_id'];
            $resBid = null; $resFac = 1.0; $resType = 'base';
            if (isset($cascadeMap[$pp_id]))      { $resBid = $cascadeMap[$pp_id]['base_id']; $resFac = $cascadeMap[$pp_id]['factor']; $resType = 'cascada'; }
            elseif (isset($altMap[$pp_id]))      { $resBid = $altMap[$pp_id]['base_id'];     $resFac = $altMap[$pp_id]['factor'];     $resType = 'alternativa'; }
            elseif ($dic['es_base'])             { $resBid = $pp_id; $resFac = 1.0; $resType = 'base'; }
            if ($resBid === $targetId) {
                $codMapBalanceAll[$targetId][$cod] = ['factor' => $resFac, 'tipo' => $resType, 'pp_id' => $pp_id, 'id_unid' => (int)$dic['pp_unid'], 'pp_cant' => (float)$dic['pp_cant'], 'id_mae' => (int)$dic['id_maestro']];
            }
        }
    }

    // ── codMapConsumo por id_pp (Pasos A,B,C del consumo teórico) ──
    $codMapConsumoAll = []; // [id_pp][cod] = info
    foreach ($idsPP as $targetId) {
        // Obtener meta del producto base
        $rMeta = $conn->prepare("SELECT pp.cantidad AS pp_cant, pp.id_unidad_producto, pp.id_producto_maestro, pp.Id_receta_producto FROM producto_presentacion pp WHERE pp.id = :id");
        $rMeta->execute([':id' => $targetId]);
        $prodMeta = $rMeta->fetch(PDO::FETCH_ASSOC);
        if (!$prodMeta) continue;
        $baseCant = max((float)$prodMeta['pp_cant'], 0.001);
        $baseUnid = (int)$prodMeta['id_unidad_producto'];
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

        // Paso C fallback via CodIngrediente
        $codsEnConsumo = array_keys($codMapConsumoAll[$targetId] ?? []);
        if (!empty($codsEnConsumo)) {
            $phC = implode(',', array_fill(0, count($codsEnConsumo), '?'));
            $stmtI = $conn->prepare("SELECT DISTINCT CodIngrediente FROM Cotizaciones WHERE CodCotizacion IN ($phC)");
            $stmtI->execute($codsEnConsumo);
            $ings = $stmtI->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($ings)) {
                $phI = implode(',', array_fill(0, count($ings), '?'));
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

    // ── Agrupar todos los CodCotizacion de balance (para queries batch) ──
    $allCodsBalance = [];
    foreach ($codMapBalanceAll as $targetId => $cmap) foreach (array_keys($cmap) as $cod) $allCodsBalance[$cod] = true;
    $allCodsBalance = array_keys($allCodsBalance);

    // ── Construir lista de días del rango ─────────────────────
    $start = new DateTime($fechaIniRango . ' 12:00:00');
    $end   = new DateTime($fechaFinRango . ' 12:00:00');
    $allDays = [];
    $cur = clone $start;
    while ($cur <= $end) { $allDays[] = $cur->format('Y-m-d'); $cur->modify('+1 day'); }

    // originalRangeLen = días reales (pasados) en allDays.
    // Si la semana está incompleta, limitamos al último día <= ayer.
    // (misma lógica que renderChartKardex en el JS)
    if ($semanaActualIncompleta) {
        $lastRealIdx = -1;
        foreach ($allDays as $idx => $day) {
            if ($day <= $ayer) $lastRealIdx = $idx;
        }
        $originalRangeLen = $lastRealIdx >= 0 ? $lastRealIdx + 1 : 0;
    } else {
        $originalRangeLen = count($allDays);
    }

    // Extender hasta fecha pronóstico (partiendo del último día del array, que puede ser futuro)
    $endExt = new DateTime($fechaPron . ' 12:00:00');
    $extCur = clone $end; $extCur->modify('+1 day');
    while ($extCur <= $endExt) { $allDays[] = $extCur->format('Y-m-d'); $extCur->modify('+1 day'); }


    // ── Índice del pivot (primer día de semana de corte) ──────
    $pivotIdx = array_search($fechaIniCorte, $allDays);
    $pIdx = ($pivotIdx !== false && $pivotIdx >= 0) ? $pivotIdx : 0;

    // ── Queries batch de Kardex ────────────────────────────────
    $invCorteByPP   = [];  // [id_pp] = float
    $movsByPPFecha  = [];  // [id_pp][fecha] = float net
    $consTeoByPPFecha = []; // [id_pp][fecha] = float

    if (!empty($allCodsBalance)) {
        $phCods = implode(',', array_fill(0, count($allCodsBalance), '?'));

        // 1. InventarioCotizacion de semAnt (stock base del corte)
        $st1 = $conn->prepare("SELECT k.CodCotizacion, k.Cantidad, k.Sucursal FROM msaccess_masivo_InventarioCotizacion k INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin WHERE ss.numero_semana = ? AND k.CodCotizacion IN ($phCods) AND k.Sucursal IN ($phSucs)");
        $st1->execute(array_merge([$semAnt], $allCodsBalance, $sucFiltro));
        foreach ($st1->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $cod = (int)$r['CodCotizacion'];
            foreach ($idsPP as $tid) {
                if (isset($codMapBalanceAll[$tid][$cod])) {
                    if (!isset($invCorteByPP[$tid])) $invCorteByPP[$tid] = 0;
                    $invCorteByPP[$tid] += round((float)$r['Cantidad'] * $codMapBalanceAll[$tid][$cod]['factor'], 4);
                }
            }
        }

        // Helper para movimientos
        $addMov = function($rows, $sign) use (&$movsByPPFecha, $idsPP, $codMapBalanceAll) {
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

        // 2. Ajustes
        $st3 = $conn->prepare("SELECT k.Fecha, k.CodCotizacion, k.Cantidad FROM msaccess_masivo_AjustesInventario k INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin WHERE ss.numero_semana BETWEEN ? AND ? AND k.CodCotizacion IN ($phCods) AND k.Sucursal IN ($phSucs)");
        $st3->execute(array_merge([$semDesde, $semHasta], $allCodsBalance, $sucFiltro));
        $addMov($st3->fetchAll(PDO::FETCH_ASSOC), 1);

        // 3. Mermas
        $st4 = $conn->prepare("SELECT k.Fecha, k.CodCotizacion, k.Cantidad FROM msaccess_masivo_MermaCotizacion k INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin WHERE ss.numero_semana BETWEEN ? AND ? AND k.CodCotizacion IN ($phCods) AND k.Sucursal IN ($phSucs)");
        $st4->execute(array_merge([$semDesde, $semHasta], $allCodsBalance, $sucFiltro));
        $addMov($st4->fetchAll(PDO::FETCH_ASSOC), -1);

        // 4. Compras
        $st6 = $conn->prepare("SELECT k.Fecha, k.CodCotizacion, k.Cantidad FROM msaccess_masivo_Compras k INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin WHERE ss.numero_semana BETWEEN ? AND ? AND k.CodCotizacion IN ($phCods) AND k.Sucursal IN ($phSucs)");
        $st6->execute(array_merge([$semDesde, $semHasta], $allCodsBalance, $sucFiltro));
        $addMov($st6->fetchAll(PDO::FETCH_ASSOC), 1);

        // 5. Despachos
        $st5 = $conn->prepare("SELECT pre.Fecha, sub.CodCotizacion, sub.Cantidad, pre.Destino FROM msaccess_masivo_SubPreIngresosPitaya sub INNER JOIN msaccess_masivo_PreIngresoPitaya pre ON pre.CodPreIngresoPitaya = sub.CodPreIngresoPitaya INNER JOIN SemanasSistema ss ON pre.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin WHERE ss.numero_semana BETWEEN ? AND ? AND sub.CodCotizacion IN ($phCods) AND pre.Destino REGEXP '^[Pp]itaya[[:space:]]+[0-9]+'");
        $st5->execute(array_merge([$semDesde, $semHasta], $allCodsBalance));
        foreach ($st5->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (!preg_match('/[Pp]itaya\s+(\d+)/', $r['Destino'], $m)) continue;
            $suc = (int)$m[1]; if (!in_array($suc, $sucFiltro)) continue;
            $cod = (int)$r['CodCotizacion']; $fecha = $r['Fecha'];
            foreach ($idsPP as $tid) {
                if (isset($codMapBalanceAll[$tid][$cod])) {
                    if (!isset($movsByPPFecha[$tid][$fecha])) $movsByPPFecha[$tid][$fecha] = 0;
                    $movsByPPFecha[$tid][$fecha] += round((float)$r['Cantidad'] * $codMapBalanceAll[$tid][$cod]['factor'], 4);
                }
            }
        }
    }

    // ── Consumo teórico diario por id_pp ──────────────────────
    // Unidades ERP
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

        // Bloquear P2/P3 solo para productos "paquete" que apuntan a una BASE diferente
        // (ej: Granola 230gr → Granola base). Esto evita absorber consumo del ingrediente
        // compartido (oz). Productos que SON su propia presentación control/despacho
        // (cascadeMap apunta a sí mismos o no están en cascadeMap) conservan P2/P3.
        $esRecetaTarget = isset($cascadeMap[$targetId]) && $cascadeMap[$targetId]['base_id'] !== $targetId;
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

        $pValS = array_merge([$fechaIniRango, $fechaFinRango, $semDesde, $semHasta], $sucFiltro);
        $whereEx = "1=0";
        if (!empty($ingsRel)) { $whereEx .= " OR sr.CodIngrediente IN (" . implode(',', array_fill(0, count($ingsRel), '?')) . ")"; $pValS = array_merge($pValS, $ingsRel); }
        if (!empty($allCodsCons)) { $whereEx .= " OR sr.codporcion IN ($phCC)"; $pValS = array_merge($pValS, $allCodsCons); }

        $sqlV = "SELECT v.Fecha, sr.CodIngrediente, sr.codporcion, SUM(v.Cantidad * sr.Cantidad) AS total FROM VentasGlobalesAccessCSV v INNER JOIN SubReceta sr ON sr.CodBatido = v.CodProducto WHERE v.Anulado=0 AND v.Fecha BETWEEN ? AND ? AND v.Semana BETWEEN ? AND ? AND v.local IN ($phSucs) AND v.CodProducto IS NOT NULL AND ($whereEx) AND (sr.codporcion IS NULL OR sr.codporcion NOT IN (SELECT CodCotizacionPorcion FROM MezclaPorcionesAccess WHERE CodCotizacionPorcion IS NOT NULL)) GROUP BY v.Fecha, sr.CodIngrediente, sr.codporcion";
        $stV = $conn->prepare($sqlV); $stV->execute($pValS);

        foreach ($stV->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $mapeo = null; $esP1 = false;
            $cp = $f['codporcion'] ? (int)$f['codporcion'] : null;
            $ci = $f['CodIngrediente'];
            if ($cp && isset($cmc[$cp])) { $mapeo = $cmc[$cp]; $esP1 = true; }
            elseif (!$esRecetaTarget && $ci && isset($cotP2P3[$ci])) {
                // P2/P3 solo aplica si el producto NO es presentacion_receta
                $p2 = $cotP2P3[$ci]['p2']; $p3 = $cotP2P3[$ci]['p3'];
                if ($p2 && isset($cmc[$p2])) $mapeo = $cmc[$p2];
                elseif ($p3 && isset($cmc[$p3])) $mapeo = $cmc[$p3];
            }
            if ($mapeo) {
                $cantTotal = (float)$f['total']; $val = 0;
                $esGlobal = !empty($mapeo['Id_receta_producto']);
                if ($esGlobal) { $val = $cantTotal; }
                else {
                    $unAcc = $dbIng[$ci]['Unidad'] ?? '';
                    $idUnAcc = isset($uPorNom[strtolower(trim($unAcc))]) ? $uPorNom[strtolower(trim($unAcc))] : null;
                    $factor = 1.0;
                    if ($idUnAcc && $idUnAcc !== $mapeo['id_unid'] && isset($convIndex[$idUnAcc][$mapeo['id_unid']])) $factor = $convIndex[$idUnAcc][$mapeo['id_unid']];
                    $val = ($cantTotal * $factor) / $mapeo['pp_cant'];
                    if ($esP1) $val = round($val * 2) / 2;
                }
                $fec = $f['Fecha'];
                if (!isset($consTeoByPPFecha[$targetId][$fec])) $consTeoByPPFecha[$targetId][$fec] = 0;
                $consTeoByPPFecha[$targetId][$fec] += $val;
            }
        }
    }

    // ── Cálculo final: balance diario + proyección ────────────
    $stocks = [];
    foreach ($idsPP as $targetId) {
        $invCorte   = $invCorteByPP[$targetId] ?? 0;
        $movsFecha  = $movsByPPFecha[$targetId]  ?? [];
        $consFecha  = $consTeoByPPFecha[$targetId] ?? [];

        // Balance hacia adelante desde pIdx hasta fin del rango REAL (hasta ayer si semana incompleta).
        // Si originalRangeLen=0 (hoy es el primer día del período), $anchorVal = $invCorte directamente.
        $balFwd = $invCorte;
        $efectivoPIdx = min($pIdx, max($originalRangeLen - 1, 0));
        for ($i = $efectivoPIdx; $i < $originalRangeLen; $i++) {
            $balFwd += ($movsFecha[$allDays[$i]] ?? 0) - ($consFecha[$allDays[$i]] ?? 0);
        }
        $anchorVal = $balFwd;

        // Si invCorte=0 y sin movimientos y sin consumo → null
        if ($invCorte == 0 && empty($movsFecha) && empty($consFecha)) {
            $stocks[(string)$targetId] = null;
            continue;
        }

        // Promedio diario y por día de semana
        $sumDow = array_fill(0, 7, 0); $cntDow = array_fill(0, 7, 0); $totalCons = 0;
        foreach ($consFecha as $f => $v) {
            if ($v > 0) { $dow = (int)(new DateTime($f))->format('w'); $sumDow[$dow] += $v; $cntDow[$dow]++; }
            $totalCons += $v;
        }
        $totalDias = count($consFecha) > 0 ? count($consFecha) : 1;
        $promDiario = $totalCons / $totalDias;
        $promDow = [];
        for ($d = 0; $d < 7; $d++) $promDow[$d] = $cntDow[$d] > 0 ? $sumDow[$d] / $cntDow[$d] : $promDiario;

        // Proyección desde anchorVal
        $balFc = $anchorVal;
        for ($i = $originalRangeLen; $i < count($allDays); $i++) {
            if ($allDays[$i] > $fechaPron) break;
            $dow = (int)(new DateTime($allDays[$i]))->format('w');
            $pDow = $promDow[$dow] > 0 ? $promDow[$dow] : $promDiario;
            $consProy = 0.65 * $pDow + 0.35 * $promDiario;
            $balFc -= $consProy;
        }

        $stocks[(string)$targetId] = round($balFc, 2);
    }

    echo json_encode(['ok' => true, 'stocks' => $stocks], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
