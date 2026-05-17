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
$consDiario      = isset($_POST['cons_diario'])      ? (float)$_POST['cons_diario']      : null;
$consProyRecv    = isset($_POST['cons_proy_diario']) ? (float)$_POST['cons_proy_diario'] : null; // pre-calculado por JS
$semDesde        = isset($_POST['sem_desde'])        ? (int)$_POST['sem_desde']          : null;
$semHasta        = isset($_POST['sem_hasta'])        ? (int)$_POST['sem_hasta']          : null;
$despFactor      = isset($_POST['despacho_factor'])  ? (float)$_POST['despacho_factor']  : null;
$stockMaxFinal   = isset($_POST['stock_max_final'])  ? (float)$_POST['stock_max_final']  : null;

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

    // ── 3. Stock base — ALINEADO CON KARDEX (después del fix de línea morada) ──────────────
    // El Kardex ahora parte del ÚLTIMO DÍA DEL RANGO (semHasta fin) para proyectar.
    // Aquí hacemos lo mismo: buscamos el inventario físico de semHasta como punto de arranque
    // y proyectamos solo los días futuros (semHastaFin → D1).
    //
    // Flujo de prioridades:
    //   1. InventarioCotizacion de semHasta  → stock al cierre del rango analizado (= fin línea verde)
    //   2. InventarioCotizacion de semAntCorte + movimientos hasta semHastaFin (fallback)
    //   3. Tabla inventario ERP (fallback final)

    $stockBase      = null;   // Stock en el punto de partida del pronóstico
    $fechaBaseInv   = null;   // Fecha del domingo del snapshot de base
    $fechaMovDesde  = null;   // Primer día DESPUÉS del snapshot (solo para fallback)
    $sinInventario  = false;

    $phC = !$sinCods ? implode(',', array_fill(0, count($allCods), '?')) : null;

    // ── Opción 1: InventarioCotizacion de semHasta ───────────────────────────
    // Esto es lo que el Kardex usa como "pronosticoStartVal": el inventario físico al fin del rango.
    if (!$sinCods && $semHasta) {
        $stmtSemH = $conn->prepare("SELECT fecha_fin FROM SemanasSistema WHERE numero_semana = ? LIMIT 1");
        $stmtSemH->execute([$semHasta]);
        $fechaFinSemHasta = $stmtSemH->fetchColumn() ?: null;

        if ($fechaFinSemHasta) {
            $stmtIH = $conn->prepare(
                "SELECT k.CodCotizacion, k.Cantidad
                 FROM msaccess_masivo_InventarioCotizacion k
                 INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin
                 WHERE ss.numero_semana = ? AND k.CodCotizacion IN ($phC) AND k.Sucursal = ?"
            );
            $stmtIH->execute(array_merge([$semHasta], $allCods, [$sucInt]));
            $ihRows = $stmtIH->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($ihRows)) {
                $stockBase    = 0.0;
                foreach ($ihRows as $r) {
                    $info = $codMapBalance[(int)$r['CodCotizacion']] ?? null;
                    if ($info) $stockBase += (float)$r['Cantidad'] * $info['factor'];
                }
                $fechaBaseInv  = $fechaFinSemHasta;  // domingo de semHasta
                $fechaMovDesde = null;                // no se necesitan movimientos históricos
            }
        }
    }

    // ── Opción 2: InventarioCotizacion de semAntCorte (fallback) ────────────
    if ($stockBase === null && !$sinCods && $semAntCorte) {
        $stmtIC = $conn->prepare(
            "SELECT k.CodCotizacion, k.Cantidad
             FROM msaccess_masivo_InventarioCotizacion k
             INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin
             WHERE ss.numero_semana = ? AND k.CodCotizacion IN ($phC) AND k.Sucursal = ?"
        );
        $stmtIC->execute(array_merge([$semAntCorte], $allCods, [$sucInt]));
        $icRows = $stmtIC->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($icRows)) {
            $stockBase    = 0.0;
            foreach ($icRows as $r) {
                $info = $codMapBalance[(int)$r['CodCotizacion']] ?? null;
                if ($info) $stockBase += (float)$r['Cantidad'] * $info['factor'];
            }
            $fechaBaseInv  = $domingoAnt;      // domingo de semAntCorte
            $fechaMovDesde = $fechaInicioSem;  // lunes de semCorte
        }
    }

    // ── Opción 3: tabla inventario ERP (fallback final) ─────────────────────
    if ($stockBase === null) {
        $stmtInv = $conn->prepare(
            "SELECT cantidad FROM inventario
             WHERE cod_sucursal=? AND id_producto_presentacion=?
               AND fecha_inventario BETWEEN ? AND ?
             ORDER BY id DESC LIMIT 1"
        );
        $stmtInv->execute([$codSucursal, $idPP, $fechaInicioSem, $domingoCorte]);
        $invRow = $stmtInv->fetch(PDO::FETCH_ASSOC);
        if ($invRow) {
            $stockBase    = (float)$invRow['cantidad'];
            $fechaBaseInv = $domingoCorte;
            $fechaMovDesde = date('Y-m-d', strtotime($domingoCorte . ' +1 day'));
        }
    }

    $sinInventario = ($stockBase === null);

    // Alias para compatibilidad con la respuesta JSON (debug)
    $stockDomingo = $stockBase;
    $domingoBase  = $fechaBaseInv;

    // ── 4. Calcular fecha_D1 ─────────────────────────────────────────────────
    $fechaD1 = date('Y-m-d', strtotime($fechaDespacho . ' -1 day'));

    // fechaMovHasta: tope de movimientos reales = fin de semHasta (si aplica Opción 2/3)
    $fechaMovHasta = $fechaD1;
    if ($fechaMovDesde && $semHasta) {
        $stmtSH2 = $conn->prepare("SELECT fecha_fin FROM SemanasSistema WHERE numero_semana = ? LIMIT 1");
        $stmtSH2->execute([$semHasta]);
        $fechaFinSH2 = $stmtSH2->fetchColumn();
        if ($fechaFinSH2 && $fechaFinSH2 < $fechaD1) {
            $fechaMovHasta = $fechaFinSH2;
        }
    }

    // diasTranscurridos: días desde fechaBaseInv hasta D1
    // En Opción 1 (semHasta): solo los días futuros (semHastaFin → D1) → alineado con línea morada del Kardex
    // En Opción 2 (semAntCorte): incluye el rango histórico + días futuros
    $diasTranscurridos = 0;
    if (!$sinInventario && $fechaBaseInv) {
        $diasTranscurridos = max(0, (int)((strtotime($fechaD1) - strtotime($fechaBaseInv)) / 86400));
    }

    // ── 5. Movimientos reales entre fechaMovDesde y fechaMovHasta ──────────
    // SOLO aplica en fallback (Opción 2/3): cuando se usó semAntCorte como base,
    // necesitamos los movimientos del rango histórico.
    // En Opción 1 (semHasta como base), el inventario ya incluye esos movimientos.
    $movimientoNeto = 0.0;

    if (!$sinInventario && !empty($allCods) && $fechaMovDesde && $fechaMovDesde <= $fechaMovHasta) {
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

    // ── 5. Consumo proyección (Kardex-aligned) ─────────────────────────────────
    // El JS calcula cons_proy_diario = mean(semDesde..semCorte-1) / 7 usando semanas_consumo
    // ya convertidas por calcular_v2. Esto replica exactamente el _promDiario del Kardex.
    // Fallback: si no viene del JS, usar cons_diario estadístico.
    $semDesdeHist = ($semDesde && $semDesde < $semAntCorte) ? $semDesde : ($semAntCorte - 4); // para _debug
    $semHastaHist = $semAntCorte;
    if ($consProyRecv !== null && $consProyRecv > 0) {
        $consProyDiario = $consProyRecv;
    } else {
        $consProyDiario = ($consDiario !== null && $consDiario > 0) ? $consDiario : 0.0;
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
            'stock_base_opcion' => $fechaMovDesde === null ? 'semHasta' : ($domingoBase === $domingoAnt ? 'semAntCorte' : 'ERP'),
            'fecha_base_inv'    => $fechaBaseInv,
            'sem_ant_corte'     => $semAntCorte,
            'sem_hasta'         => $semHasta,
            'fecha_mov_desde'   => $fechaMovDesde,
            'fecha_mov_hasta'   => $fechaMovHasta,
            'dias'              => $diasTranscurridos,
            'stock_base_raw'    => $sinInventario ? null : $stockBase,
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
