<?php
/* ============================================================
   AJAX: Detalle de registros kardex para un producto base
   modulos/productos/ajax/balance_inventario_get_detalle.php
   ============================================================ */
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');
set_time_limit(0);

$usuario = obtenerUsuarioActual();
$cargo = $usuario['CodNivelesCargos'];
if (!tienePermiso('balance_inventario_access_host', 'vista', $cargo)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Sin permiso']);
    exit();
}

$idPP = isset($_POST['id_pp']) ? (int) $_POST['id_pp'] : 0;
$semDesde = isset($_POST['semana_desde']) ? (int) $_POST['semana_desde'] : 0;
$semHasta = isset($_POST['semana_hasta']) ? (int) $_POST['semana_hasta'] : 0;
$sucsPost = isset($_POST['sucursales']) ? array_map('intval', (array) $_POST['sucursales']) : [];

if (!$idPP || !$semDesde || !$semHasta) {
    echo json_encode(['ok' => false, 'msg' => 'Parámetros incompletos']);
    exit();
}
$semDesde = min($semDesde, $semHasta);
$semHasta = max($semDesde, $semHasta);

try {
    // ── Fechas del rango ─────────────────────────────────────────────
    $rDates = $conn->prepare("SELECT MIN(fecha_inicio) AS inicio, MAX(fecha_fin) AS fin FROM SemanasSistema WHERE numero_semana BETWEEN :d AND :h");
    $rDates->execute([':d' => $semDesde, ':h' => $semHasta]);
    $rangeDates = $rDates->fetch(PDO::FETCH_ASSOC);
    $fechaInicioRange = $rangeDates['inicio'];
    $fechaFinRange = $rangeDates['fin'];

    // ── Semana anterior ──────────────────────────────────────────────
    $r = $conn->prepare("SELECT numero_semana FROM SemanasSistema WHERE numero_semana < :d ORDER BY numero_semana DESC LIMIT 1");
    $r->execute([':d' => $semDesde]);
    $semAnt = $r->fetchColumn();
    if (!$semAnt) {
        echo json_encode(['ok' => false, 'msg' => 'Sin semana anterior']);
        exit();
    }

    // ── Sucursales ───────────────────────────────────────────────────
    $r2 = $conn->prepare("SELECT codigo, nombre FROM sucursales WHERE activa=1 AND sucursal=1");
    $r2->execute();
    $allSucs = [];
    foreach ($r2->fetchAll(PDO::FETCH_ASSOC) as $s)
        $allSucs[(int) $s['codigo']] = $s['nombre'];
    $sucFiltro = !empty($sucsPost) ? $sucsPost : array_keys($allSucs);

    // ── Producto base (meta) ──────────────────────────────────────────
    $rMeta = $conn->prepare("
        SELECT pp.id, pp.Nombre, pp.cantidad AS pp_cant,
               pp.id_unidad_producto, pp.id_producto_maestro,
               pm.Nombre AS maestro, u.nombre AS unidad
        FROM producto_presentacion pp
        LEFT JOIN producto_maestro pm ON pm.id = pp.id_producto_maestro
        LEFT JOIN unidad_producto u   ON u.id  = pp.id_unidad_producto
        WHERE pp.id = :id
    ");
    $rMeta->execute([':id' => $idPP]);
    $prodMeta = $rMeta->fetch(PDO::FETCH_ASSOC);
    if (!$prodMeta) {
        echo json_encode(['ok' => false, 'msg' => 'Producto no encontrado']);
        exit();
    }

    $baseUnid = (int) $prodMeta['id_unidad_producto'];
    $baseCant = max((float) $prodMeta['pp_cant'], 0.001);
    $idMaestro = (int) $prodMeta['id_producto_maestro'];

    // ── Conversiones ─────────────────────────────────────────────────
    $convIndex = [];
    $rConv = $conn->prepare("SELECT id_unidad_producto_inicio AS i, id_unidad_producto_final AS f, cantidad AS fac FROM conversion_unidad_producto");
    $rConv->execute();
    foreach ($rConv->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $convIndex[(int) $c['i']][(int) $c['f']] = (float) $c['fac'];
        $convIndex[(int) $c['f']][(int) $c['i']] = $c['fac'] != 0 ? 1 / (float) $c['fac'] : 0;
    }

    // ── Mapeo Completo (CodMap) ──────────────────────────────────────
    $codMap = []; // CodCotizacion => {factor, nombre, tipo}

    // A. Mapeo directo al idPP
    $rA = $conn->prepare("SELECT d.CodCotizacion FROM diccionario_productos_legado d WHERE d.id_producto_presentacion = :id");
    $rA->execute([':id' => $idPP]);
    foreach ($rA->fetchAll(PDO::FETCH_COLUMN) as $cod) {
        $codMap[(int) $cod] = ['factor' => 1.0, 'nombre' => $prodMeta['Nombre'], 'tipo' => 'base'];
    }

    // B. Mapeo por Maestro (Lógica AUTO)
    if ($idMaestro > 0) {
        $rB = $conn->prepare("
            SELECT d.CodCotizacion, pp.id, pp.Nombre, pp.cantidad, pp.id_unidad_producto,
                   pp.presentacion_basica_inventario, pp.presentacion_receta
            FROM diccionario_productos_legado d
            INNER JOIN producto_presentacion pp ON pp.id = d.id_producto_presentacion
            WHERE pp.id_producto_maestro = :m AND pp.Activo = 'SI'
        ");
        $rB->execute([':m' => $idMaestro]);
        foreach ($rB->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cid = (int) $row['CodCotizacion'];
            if (isset($codMap[$cid]))
                continue;

            $u = (int) $row['id_unidad_producto'];
            $c = max((float) $row['cantidad'], 0.001);
            $fac = null;
            if ($u === $baseUnid)
                $fac = $c / $baseCant;
            elseif (isset($convIndex[$u][$baseUnid]))
                $fac = ($c * $convIndex[$u][$baseUnid]) / $baseCant;

            if ($fac !== null) {
                $type = 'alternativa';
                if ($row['presentacion_basica_inventario'])
                    $type = 'base';
                if ($row['presentacion_receta'])
                    $type = 'cascada';
                $codMap[$cid] = ['factor' => $fac, 'nombre' => $row['Nombre'], 'tipo' => $type];
            }
        }
    }

    $allCods = array_keys($codMap);
    if (empty($allCods)) {
        echo json_encode(['ok' => true, 'registros' => [], 'producto' => $prodMeta]);
        exit();
    }

    $phCods = implode(',', array_fill(0, count($allCods), '?'));
    $phSucs = implode(',', array_fill(0, count($sucFiltro), '?'));

    // ── Registros Kardex ─────────────────────────────────────────────
    $registros = [];

    // 1. Inventario Inicial
    $stmt1 = $conn->prepare("SELECT k.Fecha, k.Sucursal, k.CodCotizacion, k.Cantidad FROM msaccess_masivo_InventarioCotizacion k INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin WHERE ss.numero_semana = ? AND k.CodCotizacion IN ($phCods) AND k.Sucursal IN ($phSucs)");
    $stmt1->execute(array_merge([$semAnt], $allCods, $sucFiltro));
    foreach ($stmt1->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $info = $codMap[(int) $r['CodCotizacion']];
        $registros[] = ['tipo' => 'inv_inicial', 'fecha' => $r['Fecha'], 'sucursal' => (int) $r['Sucursal'], 'suc_nombre' => $allSucs[(int) $r['Sucursal']] ?? $r['Sucursal'], 'qty_original' => (float) $r['Cantidad'], 'qty_base' => round($r['Cantidad'] * $info['factor'], 4), 'nombre_original' => $info['nombre']];
    }

    // 2. Inventario Final
    $stmt2 = $conn->prepare("SELECT k.Fecha, k.Sucursal, k.CodCotizacion, k.Cantidad FROM msaccess_masivo_InventarioCotizacion k INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin WHERE ss.numero_semana = ? AND k.CodCotizacion IN ($phCods) AND k.Sucursal IN ($phSucs)");
    $stmt2->execute(array_merge([$semHasta], $allCods, $sucFiltro));
    foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $info = $codMap[(int) $r['CodCotizacion']];
        $registros[] = ['tipo' => 'inv_final', 'fecha' => $r['Fecha'], 'sucursal' => (int) $r['Sucursal'], 'suc_nombre' => $allSucs[(int) $r['Sucursal']] ?? $r['Sucursal'], 'qty_original' => (float) $r['Cantidad'], 'qty_base' => round($r['Cantidad'] * $info['factor'], 4), 'nombre_original' => $info['nombre']];
    }

    // 3. Ajustes
    $stmt3 = $conn->prepare("SELECT k.Fecha, k.Sucursal, k.CodCotizacion, k.Cantidad FROM msaccess_masivo_AjustesInventario k INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin WHERE ss.numero_semana BETWEEN ? AND ? AND k.CodCotizacion IN ($phCods) AND k.Sucursal IN ($phSucs)");
    $stmt3->execute(array_merge([$semDesde, $semHasta], $allCods, $sucFiltro));
    foreach ($stmt3->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $info = $codMap[(int) $r['CodCotizacion']];
        $registros[] = ['tipo' => 'ajuste', 'fecha' => $r['Fecha'], 'sucursal' => (int) $r['Sucursal'], 'suc_nombre' => $allSucs[(int) $r['Sucursal']] ?? $r['Sucursal'], 'qty_original' => (float) $r['Cantidad'], 'qty_base' => round($r['Cantidad'] * $info['factor'], 4), 'nombre_original' => $info['nombre']];
    }

    // 4. Mermas
    $stmt4 = $conn->prepare("SELECT k.Fecha, k.Sucursal, k.CodCotizacion, k.Cantidad FROM msaccess_masivo_MermaCotizacion k INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin WHERE ss.numero_semana BETWEEN ? AND ? AND k.CodCotizacion IN ($phCods) AND k.Sucursal IN ($phSucs)");
    $stmt4->execute(array_merge([$semDesde, $semHasta], $allCods, $sucFiltro));
    foreach ($stmt4->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $info = $codMap[(int) $r['CodCotizacion']];
        $registros[] = ['tipo' => 'merma', 'fecha' => $r['Fecha'], 'sucursal' => (int) $r['Sucursal'], 'suc_nombre' => $allSucs[(int) $r['Sucursal']] ?? $r['Sucursal'], 'qty_original' => (float) $r['Cantidad'], 'qty_base' => round($r['Cantidad'] * $info['factor'], 4), 'nombre_original' => $info['nombre']];
    }

    // 5. Despachos
    $stmt5 = $conn->prepare("SELECT pre.Fecha, pre.Destino, sub.CodCotizacion, sub.Cantidad FROM msaccess_masivo_SubPreIngresosPitaya sub INNER JOIN msaccess_masivo_PreIngresoPitaya pre ON pre.CodPreIngresoPitaya = sub.CodPreIngresoPitaya INNER JOIN SemanasSistema ss ON pre.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin WHERE ss.numero_semana BETWEEN ? AND ? AND sub.CodCotizacion IN ($phCods) AND pre.Destino REGEXP '^[Pp]itaya[[:space:]]+[0-9]+'");
    $stmt5->execute(array_merge([$semDesde, $semHasta], $allCods));
    foreach ($stmt5->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (!preg_match('/[Pp]itaya\s+(\d+)/', $r['Destino'], $m))
            continue;
        $suc = (int) $m[1];
        if (!in_array($suc, $sucFiltro))
            continue;
        $info = $codMap[(int) $r['CodCotizacion']];
        $registros[] = ['tipo' => 'despacho', 'fecha' => $r['Fecha'], 'sucursal' => $suc, 'suc_nombre' => $allSucs[$suc] ?? $suc, 'qty_original' => (float) $r['Cantidad'], 'qty_base' => round($r['Cantidad'] * $info['factor'], 4), 'nombre_original' => $info['nombre']];
    }

    // ── Consumo Teórico ──────────────────────────────────────────────
    $consTeoDiario = [];

    // Buscar ingredientes relevantes
    $rI1 = $conn->prepare("SELECT DISTINCT CodIngrediente FROM Cotizaciones WHERE CodCotizacion IN ($phCods)");
    $rI1->execute($allCods);
    $ings1 = $rI1->fetchAll(PDO::FETCH_COLUMN);
    $rI2 = $conn->prepare("SELECT DISTINCT CodIngrediente FROM SubReceta WHERE codporcion IN ($phCods)");
    $rI2->execute($allCods);
    $ings2 = $rI2->fetchAll(PDO::FETCH_COLUMN);
    $ingsRel = array_unique(array_filter(array_merge($ings1, $ings2)));

    if (!empty($ingsRel) || !empty($allCods)) {
        // P2/P3 mappings
        $cotP2P3 = [];
        if (!empty($ingsRel)) {
            $phI = implode(',', array_fill(0, count($ingsRel), '?'));
            $stmtC = $conn->prepare("SELECT CodIngrediente, CodCotizacion, Conversion, Prioridad FROM Cotizaciones WHERE CodIngrediente IN ($phI) AND (Subproducto IS NULL OR Subproducto!=1) AND (Marca IS NULL OR Marca!='Almacen Global') ORDER BY CodIngrediente, Conversion DESC, Prioridad ASC");
            $stmtC->execute($ingsRel);
            foreach ($stmtC->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $ci = $c['CodIngrediente'];
                if (!isset($cotP2P3[$ci]))
                    $cotP2P3[$ci] = ['p2' => null, 'p3' => null];
                if ($c['Conversion'] == 1 && $c['Prioridad'] == 1 && !$cotP2P3[$ci]['p2'])
                    $cotP2P3[$ci]['p2'] = (int) $c['CodCotizacion'];
                if (!$cotP2P3[$ci]['p3'])
                    $cotP2P3[$ci]['p3'] = (int) $c['CodCotizacion'];
            }
        }

        // Sales Query
        $whereEx = "1=0";
        $pVal = array_merge([$fechaInicioRange, $fechaFinRange, $semDesde, $semHasta], $sucFiltro);
        if (!empty($ingsRel)) {
            $whereEx .= " OR sr.CodIngrediente IN (" . implode(',', array_fill(0, count($ingsRel), '?')) . ")";
            $pVal = array_merge($pVal, $ingsRel);
        }
        if (!empty($allCods)) {
            $whereEx .= " OR sr.codporcion IN ($phCods)";
            $pVal = array_merge($pVal, $allCods);
        }

        $sqlV = "
            SELECT v.Fecha, sr.CodIngrediente, sr.codporcion, SUM(v.Cantidad * sr.Cantidad) AS total
            FROM VentasGlobalesAccessCSV v
            INNER JOIN SubReceta sr ON sr.CodBatido = v.CodProducto
            WHERE v.Anulado=0 AND v.Fecha BETWEEN ? AND ? AND v.Semana BETWEEN ? AND ? AND v.local IN ($phSucs)
              AND v.CodProducto IS NOT NULL AND ($whereEx)
              AND (sr.codporcion IS NULL OR sr.codporcion NOT IN (SELECT CodCotizacionPorcion FROM MezclaPorcionesAccess WHERE CodCotizacionPorcion IS NOT NULL))
            GROUP BY v.Fecha, sr.CodIngrediente, sr.codporcion
        ";
        $stmtV = $conn->prepare($sqlV);
        $stmtV->execute($pVal);
        foreach ($stmtV->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $target = null;
            $isP1 = false;
            $cp = $f['codporcion'] ? (int) $f['codporcion'] : null;
            $ci = $f['CodIngrediente'];

            if ($cp && isset($codMap[$cp])) {
                $target = $cp;
                $isP1 = true;
            } elseif ($ci && isset($cotP2P3[$ci])) {
                if (isset($cotP2P3[$ci]['p2']) && isset($codMap[$cotP2P3[$ci]['p2']]))
                    $target = $cotP2P3[$ci]['p2'];
                elseif (isset($cotP2P3[$ci]['p3']) && isset($codMap[$cotP2P3[$ci]['p3']]))
                    $target = $cotP2P3[$ci]['p3'];
            }


            if ($target) {
                $val = $f['total'] * $codMap[$target]['factor'];
                if ($isP1)
                    $val = round($val * 2) / 2;
                $fec = $f['Fecha'];
                if (!isset($consTeoDiario[$fec]))
                    $consTeoDiario[$fec] = 0;
                $consTeoDiario[$fec] += $val;
            }
        }
    }

    $totales = ['inv_inicial' => 0, 'ajuste' => 0, 'despacho' => 0, 'merma' => 0, 'inv_final' => 0];
    foreach ($registros as $reg)
        if (isset($totales[$reg['tipo']]))
            $totales[$reg['tipo']] += $reg['qty_base'];

    $consumoReal = round($totales['inv_inicial'] + $totales['ajuste'] + $totales['despacho'] - $totales['merma'] - $totales['inv_final'], 4);

    echo json_encode([
        'ok' => true,
        'producto' => $prodMeta,
        'semana_ant' => (int) $semAnt,
        'fecha_inicio' => $fechaInicioRange,
        'fecha_fin' => $fechaFinRange,
        'registros' => $registros,
        'totales_tipo' => array_map(fn($v) => round($v, 4), $totales),
        'consumo_real' => $consumoReal,
        'consumo_teorico' => round(array_sum($consTeoDiario), 4),
        'consumo_teorico_diario' => $consTeoDiario,
        'num_mapeos' => count($codMap)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
