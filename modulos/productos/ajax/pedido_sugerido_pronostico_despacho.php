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
$semDesde      = isset($_POST['sem_desde'])       ? (int)$_POST['sem_desde']         : null;
$semHasta      = isset($_POST['sem_hasta'])       ? (int)$_POST['sem_hasta']         : null;
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
    $domingoCorte    = $semRow['fecha_fin'];
    $fechaInicioSem  = $semRow['fecha_inicio'];  // lunes de la semana de corte

    // Semana anterior al corte (fuente del inv_inicial, igual que el Kardex)
    $stmtAnt = $conn->prepare(
        "SELECT numero_semana, fecha_fin FROM SemanasSistema WHERE numero_semana < ? ORDER BY numero_semana DESC LIMIT 1"
    );
    $stmtAnt->execute([$semCorte]);
    $semAntRow    = $stmtAnt->fetch(PDO::FETCH_ASSOC);
    $semAntCorte  = $semAntRow ? (int)$semAntRow['numero_semana'] : null;
    $domingoAnt   = $semAntRow ? $semAntRow['fecha_fin'] : null; // domingo de semAntCorte

    // ── 2. Construir codMapBalance (necesario para el lookup de inventario legacy) ─
    $sucInt = (int)$codSucursal;

    // Conversiones
    $convIndex = [];
    $rConv = $conn->query("SELECT id_unidad_producto_inicio AS i,id_unidad_producto_final AS f,cantidad AS fac FROM conversion_unidad_producto");
    foreach ($rConv->fetchAll(PDO::FETCH_ASSOC) as $c) { $convIndex[(int)$c['i']][(int)$c['f']]=(float)$c['fac']; $convIndex[(int)$c['f']][(int)$c['i']]=$c['fac']!=0?1/(float)$c['fac']:0; }

    // maestroToBase
    $rMB=$conn->query("SELECT id,id_unidad_producto AS unid,cantidad AS cant,id_producto_maestro AS mid FROM producto_presentacion WHERE presentacion_basica_inventario=1 AND Activo='SI'");
    $maestroToBase=[];
    foreach($rMB->fetchAll(PDO::FETCH_ASSOC) as $pm){$mid=(int)$pm['mid'];if($mid>0)$maestroToBase[$mid]=['base_pp_id'=>(int)$pm['id'],'base_unid'=>(int)$pm['unid'],'base_cant'=>max((float)$pm['cant'],0.001)];}

    // cascadeMap
    $rCas=$conn->query("SELECT pp_pkg.id AS pkg_id,crp.id_presentacion_producto AS base_id,crp.cantidad AS factor FROM producto_presentacion pp_pkg INNER JOIN componentes_receta_producto crp ON crp.id_receta_producto_global=pp_pkg.Id_receta_producto INNER JOIN producto_presentacion pp_base ON pp_base.id=crp.id_presentacion_producto AND pp_base.presentacion_basica_inventario=1 WHERE pp_pkg.presentacion_receta=1 AND pp_pkg.Activo='SI' AND (SELECT COUNT(DISTINCT id_presentacion_producto) FROM componentes_receta_producto WHERE id_receta_producto_global=pp_pkg.Id_receta_producto)=1");
    $cascadeMap=[];
    foreach($rCas->fetchAll(PDO::FETCH_ASSOC) as $row)$cascadeMap[(int)$row['pkg_id']]=['base_id'=>(int)$row['base_id'],'factor'=>(float)$row['factor']];

    // diccionario
    $rDic=$conn->query("SELECT d.CodCotizacion,pp.id AS pp_id,pp.presentacion_basica_inventario AS es_base,pp.presentacion_receta AS es_receta,pp.id_unidad_producto AS pp_unid,pp.cantidad AS pp_cant,pp.id_producto_maestro AS id_maestro FROM diccionario_productos_legado d INNER JOIN producto_presentacion pp ON pp.id=d.id_producto_presentacion WHERE pp.Activo='SI'");
    $diccionario=[];
    foreach($rDic->fetchAll(PDO::FETCH_ASSOC) as $d)$diccionario[(int)$d['CodCotizacion']]=$d;

    // altMap
    $altMap=[];
    foreach($diccionario as $dic){$pp_id=(int)$dic['pp_id'];if($dic['es_base']||$dic['es_receta'])continue;if(isset($cascadeMap[$pp_id]))continue;$mid=(int)$dic['id_maestro'];if(!$mid||!isset($maestroToBase[$mid]))continue;$base=$maestroToBase[$mid];$altUnid=(int)$dic['pp_unid'];$basUnid=(int)$base['base_unid'];if($altUnid===$basUnid){$factor=(float)$dic['pp_cant']/$base['base_cant'];}elseif(isset($convIndex[$altUnid][$basUnid])){$factor=((float)$dic['pp_cant']*$convIndex[$altUnid][$basUnid])/$base['base_cant'];}else continue;$altMap[$pp_id]=['base_id'=>$base['base_pp_id'],'factor'=>$factor];}

    // codMapBalance — solo entradas que resuelven a nuestro idPP
    $codMapBalance=[];
    foreach($diccionario as $cod=>$dic){$pp_id=(int)$dic['pp_id'];$resBid=null;$resFac=1.0;if(isset($cascadeMap[$pp_id])){$resBid=$cascadeMap[$pp_id]['base_id'];$resFac=$cascadeMap[$pp_id]['factor'];}elseif(isset($altMap[$pp_id])){$resBid=$altMap[$pp_id]['base_id'];$resFac=$altMap[$pp_id]['factor'];}elseif($dic['es_base']){$resBid=$pp_id;$resFac=1.0;}if($resBid===$idPP)$codMapBalance[$cod]=['factor'=>$resFac];}

    $allCods   = array_keys($codMapBalance);
    $sinCods   = empty($allCods);

    // ── 3. Stock base: primero InventarioCotizacion de semAntCorte (= Kardex) ─
    // Si no hay datos ahí, fallback a tabla inventario del semCorte
    $stockDomingo   = null;
    $domingoBase    = null; // Fecha real del snapshot
    $fechaMovDesde  = null; // Primer día de movimientos a incluir

    if (!$sinCods && $semAntCorte) {
        $phC = implode(',', array_fill(0, count($allCods), '?'));
        // InventarioCotizacion de la semana ANTERIOR al corte (igual que Kardex inv_inicial)
        $stmtIC = $conn->prepare("SELECT k.CodCotizacion, k.Cantidad FROM msaccess_masivo_InventarioCotizacion k INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin WHERE ss.numero_semana = ? AND k.CodCotizacion IN ($phC) AND k.Sucursal = ?");
        $stmtIC->execute(array_merge([$semAntCorte], $allCods, [$sucInt]));
        $icRows = $stmtIC->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($icRows)) {
            $stockDomingo = 0.0;
            foreach ($icRows as $r) {
                $info = $codMapBalance[(int)$r['CodCotizacion']] ?? null;
                if ($info) $stockDomingo += (float)$r['Cantidad'] * $info['factor'];
            }
            $domingoBase   = $domingoAnt;       // domingo de semAntCorte
            $fechaMovDesde = $fechaInicioSem;   // lunes de semCorte (primer día a proyectar)
        }
    }

    // Fallback: tabla inventario del ERP para sem_corte
    if ($stockDomingo === null) {
        $stmtInv = $conn->prepare("SELECT cantidad FROM inventario WHERE cod_sucursal=? AND id_producto_presentacion=? AND fecha_inventario BETWEEN ? AND ? ORDER BY id DESC LIMIT 1");
        $stmtInv->execute([$codSucursal, $idPP, $fechaInicioSem, $domingoCorte]);
        $invRow = $stmtInv->fetch(PDO::FETCH_ASSOC);
        if ($invRow) {
            $stockDomingo  = (float)$invRow['cantidad'];
            $domingoBase   = $domingoCorte;
            $fechaMovDesde = date('Y-m-d', strtotime($domingoCorte . ' +1 day'));
        }
    }

    $sinInventario = ($stockDomingo === null);

    // ── 4. Calcular fecha_D1 y días de proyección ─────────────────────────
    $fechaD1 = date('Y-m-d', strtotime($fechaDespacho . ' -1 day'));
    // fechaMovDesde ya viene del paso 3 (lunes semCorte o domingo+1)
    if (!$fechaMovDesde) $fechaMovDesde = $fechaD1;

    // Limitar fechaMovHasta al fin de sem_hasta (idéntico al Kardex).
    // El Kardex solo consulta movimientos hasta el último sem analizado; los despachos de
    // semanas futuras aún no están confirmados y generarían discrepancias.
    $fechaMovHasta = $fechaD1;  // por defecto: hasta D-1
    if ($semHasta) {
        $stmtSH = $conn->prepare("SELECT fecha_fin FROM SemanasSistema WHERE numero_semana = ? LIMIT 1");
        $stmtSH->execute([$semHasta]);
        $fechaFinSemHasta = $stmtSH->fetchColumn();
        if ($fechaFinSemHasta && $fechaFinSemHasta < $fechaD1) {
            // El último sem analizado cierra antes de D-1 → usar ese tope para movimientos
            $fechaMovHasta = $fechaFinSemHasta;
        }
    }

    $diasTranscurridos = 0;
    if (!$sinInventario && $domingoBase) {
        // Los días de proyección siguen siendo hasta D-1 (para el consumo proyectado)
        $diasTranscurridos = max(0, (int)((strtotime($fechaD1) - strtotime($domingoBase)) / 86400));
    }

    // ── 5. Movimientos reales entre fechaMovDesde y fechaD1 ──────────────
    $movimientoNeto = 0.0;

    if (!$sinInventario && !empty($allCods) && $fechaMovDesde <= $fechaMovHasta) {
        $phCods = implode(',', array_fill(0, count($allCods), '?'));

        // Helper para sumar movimientos al $movimientoNeto
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

        // Despachos (+)
        $s = $conn->prepare("SELECT sub.CodCotizacion, sub.Cantidad FROM msaccess_masivo_SubPreIngresosPitaya sub INNER JOIN msaccess_masivo_PreIngresoPitaya pre ON pre.CodPreIngresoPitaya=sub.CodPreIngresoPitaya WHERE pre.Fecha BETWEEN ? AND ? AND sub.CodCotizacion IN ($phCods) AND pre.Destino REGEXP ?");
        $s->execute(array_merge([$fechaMovDesde, $fechaMovHasta], $allCods, ["[Pp]itaya[[:space:]]+{$sucInt}([^0-9]|$)"]));
        $sumarMovs($s->fetchAll(PDO::FETCH_ASSOC), false);

        // Compras (+)
        $s = $conn->prepare("SELECT CodCotizacion, Cantidad FROM msaccess_masivo_Compras WHERE Fecha BETWEEN ? AND ? AND CodCotizacion IN ($phCods) AND Sucursal = ?");
        $s->execute(array_merge([$fechaMovDesde, $fechaMovHasta], $allCods, [$sucInt]));
        $sumarMovs($s->fetchAll(PDO::FETCH_ASSOC), false);
    }

    // ── 5. Consumo proyección (modelo Kardex: media histórica ANTES del corte) ─────
    // El Kardex calcula su baseline de consumo SOLO con semanas antes de semCorte.
    // Usar semDesde..semAntCorte como ventana histórica y plain mean/7 (sin desv/ajuste).
    // Esto replica el _promDiario del Kardex para el mismo período, dando coincidencia exacta.
    $consProyDiario = null;
    $semDesdeHist   = ($semDesde && $semDesde < $semAntCorte) ? $semDesde : ($semAntCorte - 4);
    $semHastaHist   = $semAntCorte;  // semanas históricas: desde semDesde hasta sem ANTERIOR al corte
    if ($semDesdeHist <= $semHastaHist) {
        $stmtRng = $conn->prepare(
            "SELECT MIN(fecha_inicio) AS f1, MAX(fecha_fin) AS f2 FROM SemanasSistema WHERE numero_semana BETWEEN ? AND ?"
        );
        $stmtRng->execute([$semDesdeHist, $semHastaHist]);
        $rng = $stmtRng->fetch(PDO::FETCH_ASSOC);
        if ($rng && $rng['f1']) {
            // Suma de porciones teóricas por semana (igual que dashboard_consumo / balance_get_datos)
            $stmtVen = $conn->prepare("
                SELECT SUM(v.Cantidad * sr.Cantidad) AS total_cant
                FROM VentasGlobalesAccessCSV v
                INNER JOIN SubReceta sr ON sr.CodBatido = v.CodProducto
                INNER JOIN diccionario_productos_legado d ON d.CodCotizacion = sr.codporcion
                WHERE v.Anulado = 0
                  AND v.local = ?
                  AND v.Semana BETWEEN ? AND ?
                  AND v.Fecha BETWEEN ? AND ?
                  AND d.id_producto_presentacion = ?
            ");
            $stmtVen->execute([$codSucursal, $semDesdeHist, $semHastaHist, $rng['f1'], $rng['f2'], $idPP]);
            $totalCant = (float)($stmtVen->fetchColumn() ?: 0);
            $nSems     = max(1, $semHastaHist - $semDesdeHist + 1);
            if ($totalCant > 0) {
                $consProyDiario = ($totalCant / $nSems) / 7.0;  // plain mean diario
            }
        }
    }
    // Fallback: usar cons_diario estadístico si no hay datos de ventas
    if ($consProyDiario === null || $consProyDiario <= 0) {
        $consProyDiario = ($consDiario !== null && $consDiario > 0) ? $consDiario : 0.0;
        // Fallback profundo: calcular cons_diario estadístico desde VentasGlobalesAccessCSV
        if ($consProyDiario <= 0) {
            $sfD = $semCorte - 5; $sfH = $semCorte - 1;
            $stmtRng2 = $conn->prepare("SELECT MIN(fecha_inicio) as f1, MAX(fecha_fin) as f2 FROM SemanasSistema WHERE numero_semana BETWEEN ? AND ?");
            $stmtRng2->execute([$sfD, $sfH]);
            $rng2 = $stmtRng2->fetch(PDO::FETCH_ASSOC);
            if ($rng2 && $rng2['f1']) {
                $stmtV2 = $conn->prepare("SELECT v.Semana as sem, SUM(v.Cantidad*sr.Cantidad) as cant FROM VentasGlobalesAccessCSV v INNER JOIN SubReceta sr ON sr.CodBatido=v.CodProducto INNER JOIN diccionario_productos_legado d ON d.CodCotizacion=sr.codporcion WHERE v.Anulado=0 AND v.local=? AND v.Semana BETWEEN ? AND ? AND v.Fecha BETWEEN ? AND ? AND d.id_producto_presentacion=? GROUP BY v.Semana");
                $stmtV2->execute([$codSucursal, $sfD, $sfH, $rng2['f1'], $rng2['f2'], $idPP]);
                $vs = $stmtV2->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($vs)) {
                    $vals = []; for ($s = $sfD; $s <= $sfH; $s++) { $f = array_filter($vs, fn($r) => (int)$r['sem'] === $s); $vals[] = $f ? (float)array_values($f)[0]['cant'] : 0.0; }
                    if (array_sum($vals) > 0) { $mean = array_sum($vals)/count($vals); $consProyDiario = $mean / 7.0; }
                }
            }
        }
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

    // ── 7. Proyección D-1 ────────────────────────────────────────────────
    // stock_D1 = stock_domingo + mov_neto_real - consProyDiario × dias
    $stockD1Uso = $sinInventario
        ? null
        : max(0.0, $stockDomingo + $movimientoNeto - ($consProyDiario * $diasTranscurridos));

    $dfSafe          = ($despFactor > 0) ? $despFactor : 1.0;
    $stockD1Paquetes = ($stockD1Uso !== null) ? ($stockD1Uso / $dfSafe) : null;
    $stockMaxFinalPaq = $stockMaxFinal;

    $despachoPron = null;
    if (!$sinInventario && $stockMaxFinalPaq !== null && $stockD1Paquetes !== null)
        $despachoPron = max(0, (int)ceil($stockMaxFinalPaq - $stockD1Paquetes));

    // ── 8. Respuesta ─────────────────────────────────────────────────────
    echo json_encode([
        'ok'                           => true,
        'id_pp'                        => $idPP,
        'sem_corte'                    => $semCorte,
        'domingo_corte'                => $domingoBase ?? $domingoCorte,
        'stock_domingo'                => $sinInventario ? null : round($stockDomingo, 4),
        'movimiento_neto'              => round($movimientoNeto, 4),
        'cons_diario'                  => round($consDiario ?? 0, 6),
        'cons_proy_diario'             => round($consProyDiario, 6),
        'fecha_despacho'               => $fechaDespacho,
        'fecha_D1'                     => $fechaD1,
        'fecha_mov_desde'              => $fechaMovDesde,
        'dias_transcurridos'           => $diasTranscurridos,
        'stock_D1_uso'                 => $stockD1Uso      !== null ? round($stockD1Uso, 4)       : null,
        'stock_D1_paquetes'            => $stockD1Paquetes !== null ? round($stockD1Paquetes, 4)  : null,
        'stock_max_final_paquetes'     => $stockMaxFinalPaq !== null ? round($stockMaxFinalPaq, 4) : null,
        'despacho_sugerido_pronostico' => $despachoPron,
        'despacho_factor'              => round($dfSafe, 6),
        'sin_inventario'               => $sinInventario,
        '_debug' => [
            'suc_int'           => $sucInt,
            'sem_ant_corte'     => $semAntCorte,
            'sem_desde_hist'    => $semDesdeHist,   // ventana histórica para consumo (= modelo Kardex)
            'sem_hasta_hist'    => $semHastaHist,
            'domingo_base'      => $domingoBase,
            'fecha_inicio_sem'  => $fechaInicioSem,
            'fecha_mov_desde'   => $fechaMovDesde,
            'fecha_mov_hasta'   => $fechaMovHasta,
            'dias'              => $diasTranscurridos,
            'stock_domingo_raw' => $sinInventario ? null : $stockDomingo,
            'mov_neto'          => $movimientoNeto,
            'cons_diario_recv'  => (float)($_POST['cons_diario']     ?? 0),
            'cons_proy_diario'  => round($consProyDiario, 6),
            'desp_factor_recv'  => (float)($_POST['despacho_factor'] ?? 0),
            'n_cods'            => count($allCods),
        ],
    ]);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'Error interno: ' . $e->getMessage()]);
}
