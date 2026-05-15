<?php
/* ============================================================
   DEBUG TEMPORAL — Pronóstico vs Kardex
   GET ?id_pp=X&sem_corte=539&sem_inv=541&cod_sucursal=1
   BORRAR después de diagnosticar.
   ============================================================ */
require_once '../../../core/auth/auth.php';
header('Content-Type: application/json; charset=utf-8');

$idPP       = (int)($_GET['id_pp']          ?? 0);
$semCorte   = (int)($_GET['sem_corte']       ?? 539);
$semInv     = (int)($_GET['sem_inv']         ?? 541);
$codSuc     = (int)($_GET['cod_sucursal']    ?? 1);

try {
    /* ── semana anterior al corte ─────────────────────── */
    $r = $conn->prepare("SELECT numero_semana FROM SemanasSistema WHERE numero_semana < ? ORDER BY numero_semana DESC LIMIT 1");
    $r->execute([$semCorte]);
    $semAnt = (int)$r->fetchColumn();

    /* ── fechas período (semCorte..semInv) ────────────── */
    $rF = $conn->prepare("SELECT MIN(fecha_inicio) as fi, MAX(fecha_fin) as ff FROM SemanasSistema WHERE numero_semana BETWEEN ? AND ?");
    $rF->execute([$semCorte, $semInv]);
    $fd = $rF->fetch(PDO::FETCH_ASSOC);

    /* ── producto meta ────────────────────────────────── */
    $rMeta = $conn->prepare("SELECT pp.id, pp.Nombre, pp.cantidad, pp.id_unidad_producto FROM producto_presentacion pp WHERE pp.id = ?");
    $rMeta->execute([$idPP]);
    $meta = $rMeta->fetch(PDO::FETCH_ASSOC);

    /* ── CodCotizaciones que mapean a este idPP ───────── */
    $rCods = $conn->prepare("SELECT d.CodCotizacion FROM diccionario_productos_legado d INNER JOIN producto_presentacion pp ON pp.id = d.id_producto_presentacion WHERE pp.id = ? AND pp.presentacion_basica_inventario = 1");
    $rCods->execute([$idPP]);
    $cods = $rCods->fetchAll(PDO::FETCH_COLUMN);

    if (empty($cods)) {
        echo json_encode(['error' => 'Sin códigos mapeados para id_pp=' . $idPP]); exit;
    }
    $ph = implode(',', array_fill(0, count($cods), '?'));

    /* ── 1. Inventario físico semAnt (igual que Kardex) ─ */
    $rInv = $conn->prepare("SELECT k.CodCotizacion, SUM(k.Cantidad) as qty FROM msaccess_masivo_InventarioCotizacion k INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin WHERE ss.numero_semana = ? AND k.CodCotizacion IN ($ph) AND k.Sucursal = ? GROUP BY k.CodCotizacion");
    $rInv->execute(array_merge([$semAnt], $cods, [$codSuc]));
    $invRows = $rInv->fetchAll(PDO::FETCH_ASSOC);
    $invTotal = array_sum(array_column($invRows, 'qty'));

    /* ── 2. Movimientos por tipo (semCorte..semInv) ────── */
    $movs = ['ajuste' => 0, 'merma' => 0, 'despacho' => 0, 'compras' => 0];

    // Ajustes
    $s = $conn->prepare("SELECT SUM(k.Cantidad) as qty FROM msaccess_masivo_AjustesInventario k INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin WHERE ss.numero_semana BETWEEN ? AND ? AND k.CodCotizacion IN ($ph) AND k.Sucursal = ?");
    $s->execute(array_merge([$semCorte, $semInv], $cods, [$codSuc]));
    $movs['ajuste'] = (float)($s->fetchColumn() ?? 0);

    // Mermas
    $s = $conn->prepare("SELECT SUM(k.Cantidad) as qty FROM msaccess_masivo_MermaCotizacion k INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin WHERE ss.numero_semana BETWEEN ? AND ? AND k.CodCotizacion IN ($ph) AND k.Sucursal = ?");
    $s->execute(array_merge([$semCorte, $semInv], $cods, [$codSuc]));
    $movs['merma'] = (float)($s->fetchColumn() ?? 0);

    // Despachos (filtrar por sucursal en PHP como el Kardex)
    $s = $conn->prepare("SELECT pre.Destino, sub.CodCotizacion, sub.Cantidad FROM msaccess_masivo_SubPreIngresosPitaya sub INNER JOIN msaccess_masivo_PreIngresoPitaya pre ON pre.CodPreIngresoPitaya = sub.CodPreIngresoPitaya INNER JOIN SemanasSistema ss ON pre.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin WHERE ss.numero_semana BETWEEN ? AND ? AND sub.CodCotizacion IN ($ph) AND pre.Destino REGEXP '^[Pp]itaya[[:space:]]+[0-9]+'");
    $s->execute(array_merge([$semCorte, $semInv], $cods));
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!preg_match('/[Pp]itaya\s+(\d+)/', $row['Destino'], $m)) continue;
        if ((int)$m[1] !== $codSuc) continue;
        $movs['despacho'] += (float)$row['Cantidad'];
    }

    // Compras
    $s = $conn->prepare("SELECT SUM(k.Cantidad) as qty FROM msaccess_masivo_Compras k INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin WHERE ss.numero_semana BETWEEN ? AND ? AND k.CodCotizacion IN ($ph) AND k.Sucursal = ?");
    $s->execute(array_merge([$semCorte, $semInv], $cods, [$codSuc]));
    $movs['compras'] = (float)($s->fetchColumn() ?? 0);

    $movTotal = $movs['ajuste'] - $movs['merma'] + $movs['despacho'] + $movs['compras'];

    /* ── 3. Consumo teórico con exclusión MezclaPorcionesAccess ── */
    $rI = $conn->prepare("SELECT DISTINCT CodIngrediente FROM Cotizaciones WHERE CodCotizacion IN ($ph)");
    $rI->execute($cods);
    $ings = $rI->fetchAll(PDO::FETCH_COLUMN);
    $consTeoTotal = 0;
    $consTeoSinMezcla = 0;

    if (!empty($ings)) {
        $phI = implode(',', array_fill(0, count($ings), '?'));
        // CON MezclaPorcionesAccess (como mi código actual)
        $sqlV = "SELECT SUM(v.Cantidad * sr.Cantidad) as tot FROM VentasGlobalesAccessCSV v INNER JOIN SubReceta sr ON sr.CodBatido = v.CodProducto WHERE v.Anulado=0 AND v.local=? AND v.Semana BETWEEN ? AND ? AND sr.CodIngrediente IN ($phI)";
        $s = $conn->prepare($sqlV);
        $s->execute(array_merge([$codSuc, $semCorte, $semInv], $ings));
        $consTeoTotal = (float)($s->fetchColumn() ?? 0);

        // SIN MezclaPorcionesAccess (como el Kardex)
        $sqlV2 = "SELECT SUM(v.Cantidad * sr.Cantidad) as tot FROM VentasGlobalesAccessCSV v INNER JOIN SubReceta sr ON sr.CodBatido = v.CodProducto WHERE v.Anulado=0 AND v.local=? AND v.Semana BETWEEN ? AND ? AND sr.CodIngrediente IN ($phI) AND (sr.codporcion IS NULL OR sr.codporcion NOT IN (SELECT CodCotizacionPorcion FROM MezclaPorcionesAccess WHERE CodCotizacionPorcion IS NOT NULL))";
        $s = $conn->prepare($sqlV2);
        $s->execute(array_merge([$codSuc, $semCorte, $semInv], $ings));
        $consTeoSinMezcla = (float)($s->fetchColumn() ?? 0);
    }

    // Dividir consumo bruto por pp_cant para convertir a unidades de presentación
    $ppCant = max((float)($meta['cantidad'] ?? 1), 0.001);
    $consUnid      = $consTeoTotal     / $ppCant;
    $consUnidSinM  = $consTeoSinMezcla / $ppCant;

    /* ── 4. Resultado ─────────────────────────────────── */
    $pronConMezcla   = $invTotal + $movTotal - $consUnid;
    $pronSinMezcla   = $invTotal + $movTotal - $consUnidSinM;

    echo json_encode([
        'producto'          => $meta['Nombre'] ?? '?',
        'id_pp'             => $idPP,
        'cods_mapeados'     => $cods,
        'semAnt_corte'      => $semAnt,
        'inv_inicial_semAnt'=> round($invTotal, 4),
        'movimientos'       => array_map(fn($v) => round($v, 4), $movs),
        'mov_total_neto'    => round($movTotal, 4),
        'cons_bruto_oz_con_mezcla'  => round($consTeoTotal, 4),
        'cons_bruto_oz_sin_mezcla'  => round($consTeoSinMezcla, 4),
        'pp_cant'           => $ppCant,
        'cons_unid_con_mezcla' => round($consUnid, 4),
        'cons_unid_sin_mezcla' => round($consUnidSinM, 4),
        'pron_con_mezcla_unid'  => round($pronConMezcla, 4),
        'pron_sin_mezcla_unid'  => round($pronSinMezcla, 4),
        'kardex_esperado_unid'  => 71,
        'nota' => 'Si pron_sin_mezcla ≈ 71, la causa es MezclaPorcionesAccess'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
