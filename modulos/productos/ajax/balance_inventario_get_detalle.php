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

    // ── Mapeo Completo (Lógica 3 Etapas: Directo, Maestro, Ingrediente) ──
    $codMap = []; // CodCotizacion => {factor, nombre, tipo}

    // 1. Cargar todas las presentaciones activas para el "mapeo reverso"
    $rDic = $conn->prepare("
        SELECT d.CodCotizacion, pp.id AS pp_id, pp.Nombre,
               pp.presentacion_basica_inventario, pp.presentacion_receta,
               pp.id_unidad_producto AS pp_unid, pp.cantidad AS pp_cant,
               pp.id_producto_maestro AS id_maestro
        FROM diccionario_productos_legado d
        INNER JOIN producto_presentacion pp ON pp.id = d.id_producto_presentacion
        WHERE pp.Activo='SI'
    ");
    $rDic->execute();
    $allDic = $rDic->fetchAll(PDO::FETCH_ASSOC);

    // 2. Cascade Map (Paquetes)
    $rCas = $conn->prepare("
        SELECT pp_pkg.id AS pkg_id, crp.id_presentacion_producto AS base_id, crp.cantidad AS factor
        FROM producto_presentacion pp_pkg
        INNER JOIN componentes_receta_producto crp ON crp.id_receta_producto_global = pp_pkg.Id_receta_producto
        INNER JOIN producto_presentacion pp_base ON pp_base.id = crp.id_presentacion_producto
            AND pp_base.presentacion_basica_inventario = 1
        WHERE pp_pkg.presentacion_receta = 1 AND pp_pkg.Activo = 'SI'
          AND (SELECT COUNT(DISTINCT id_presentacion_producto) FROM componentes_receta_producto WHERE id_receta_producto_global = pp_pkg.Id_receta_producto) = 1
    ");
    $rCas->execute();
    $cascadeMap = [];
    foreach ($rCas->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cascadeMap[(int)$row['pkg_id']] = ['base_id' => (int)$row['base_id'], 'factor' => (float)$row['factor']];
    }

    // 3. Step C Mapping (Ingredient Fallback)
    $stmtStepC = $conn->prepare("
        SELECT c_src.CodCotizacion
        FROM Cotizaciones c_src
        INNER JOIN Cotizaciones c_all ON c_all.CodIngrediente = c_src.CodIngrediente
        INNER JOIN diccionario_productos_legado d2 ON d2.CodCotizacion = c_all.CodCotizacion
        INNER JOIN producto_presentacion pp_any ON pp_any.id = d2.id_producto_presentacion
                                               AND pp_any.Activo = 'SI'
                                               AND pp_any.id_producto_maestro IS NOT NULL
        INNER JOIN producto_presentacion pp_base
                ON pp_base.id_producto_maestro = pp_any.id_producto_maestro
               AND pp_base.presentacion_basica_inventario = 1
               AND pp_base.Activo = 'SI'
        WHERE pp_base.id = :targetID
        GROUP BY c_src.CodCotizacion
    ");
    $stmtStepC->execute([':targetID' => $idPP]);
    $stepCCods = $stmtStepC->fetchAll(PDO::FETCH_COLUMN);

    // 4. Construir el codMap final para este producto específico
    foreach ($allDic as $row) {
        $cid = (int)$row['CodCotizacion'];
        $ppid = (int)$row['pp_id'];
        $mid = (int)$row['id_maestro'];
        $u = (int)$row['pp_unid'];
        $c = max((float)$row['pp_cant'], 0.001);
        $fac = null;
        $type = 'alternativa';

        // A. Mapeo Directo
        if ($ppid === $idPP) {
            $fac = 1.0;
            $type = 'base';
        }
        // B. Mapeo Cascada
        elseif (isset($cascadeMap[$ppid]) && $cascadeMap[$ppid]['base_id'] === $idPP) {
            $fac = $cascadeMap[$ppid]['factor'];
            $type = 'cascada';
        }
        // C. Mapeo Maestro
        elseif ($mid > 0 && $mid === $idMaestro) {
            if ($u === $baseUnid) {
                $fac = $c / $baseCant;
            } elseif (isset($convIndex[$u][$baseUnid])) {
                $fac = ($c * $convIndex[$u][$baseUnid]) / $baseCant;
            }
            if ($row['presentacion_basica_inventario']) $type = 'base';
        }
        // D. Mapeo Step C
        elseif (in_array($cid, $stepCCods)) {
            if ($u === $baseUnid) {
                $fac = $c / $baseCant;
            } elseif (isset($convIndex[$u][$baseUnid])) {
                $fac = ($c * $convIndex[$u][$baseUnid]) / $baseCant;
            }
        }

        if ($fac !== null) {
            $codMap[$cid] = ['factor' => $fac, 'nombre' => $row['Nombre'], 'tipo' => $type, 'pp_id' => $ppid, 'id_unid' => $u, 'pp_cant' => $c, 'id_mae' => $mid];
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

    // Helper para añadir registros con campos consistentes
    $addReg = function($tipo, $r, $info) use (&$registros, $allSucs) {
        $registros[] = [
            'tipo'             => $tipo,
            'semana'           => (int)($r['semana'] ?? 0),
            'fecha'            => $r['Fecha'],
            'sucursal'         => (int)$r['Sucursal'],
            'suc_nombre'       => $allSucs[(int)$r['Sucursal']] ?? $r['Sucursal'],
            'cod_cotizacion'   => (int)$r['CodCotizacion'],
            'nombre_original'  => $info['nombre'],
            'tipo_conversion'  => $info['tipo'],
            'factor'           => round($info['factor'], 4),
            'qty_original'     => (float)$r['Cantidad'],
            'qty_base'         => round($r['Cantidad'] * $info['factor'], 4),
            'destino_texto'    => $r['Destino'] ?? ''
        ];
    };

    // 1. Inventario Inicial
    $stmt1 = $conn->prepare("SELECT k.Fecha, k.Sucursal, k.CodCotizacion, k.Cantidad, ss.numero_semana AS semana FROM msaccess_masivo_InventarioCotizacion k INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin WHERE ss.numero_semana = ? AND k.CodCotizacion IN ($phCods) AND k.Sucursal IN ($phSucs)");
    $stmt1->execute(array_merge([$semAnt], $allCods, $sucFiltro));
    foreach ($stmt1->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $addReg('inv_inicial', $r, $codMap[(int)$r['CodCotizacion']]);
    }

    // 2. Inventario Final
    $stmt2 = $conn->prepare("SELECT k.Fecha, k.Sucursal, k.CodCotizacion, k.Cantidad, ss.numero_semana AS semana FROM msaccess_masivo_InventarioCotizacion k INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin WHERE ss.numero_semana = ? AND k.CodCotizacion IN ($phCods) AND k.Sucursal IN ($phSucs)");
    $stmt2->execute(array_merge([$semHasta], $allCods, $sucFiltro));
    foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $addReg('inv_final', $r, $codMap[(int)$r['CodCotizacion']]);
    }

    // 3. Ajustes
    $stmt3 = $conn->prepare("SELECT k.Fecha, k.Sucursal, k.CodCotizacion, k.Cantidad, ss.numero_semana AS semana FROM msaccess_masivo_AjustesInventario k INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin WHERE ss.numero_semana BETWEEN ? AND ? AND k.CodCotizacion IN ($phCods) AND k.Sucursal IN ($phSucs)");
    $stmt3->execute(array_merge([$semDesde, $semHasta], $allCods, $sucFiltro));
    foreach ($stmt3->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $addReg('ajuste', $r, $codMap[(int)$r['CodCotizacion']]);
    }

    // 4. Mermas
    $stmt4 = $conn->prepare("SELECT k.Fecha, k.Sucursal, k.CodCotizacion, k.Cantidad, ss.numero_semana AS semana FROM msaccess_masivo_MermaCotizacion k INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin WHERE ss.numero_semana BETWEEN ? AND ? AND k.CodCotizacion IN ($phCods) AND k.Sucursal IN ($phSucs)");
    $stmt4->execute(array_merge([$semDesde, $semHasta], $allCods, $sucFiltro));
    foreach ($stmt4->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $addReg('merma', $r, $codMap[(int)$r['CodCotizacion']]);
    }

    // 5. Despachos
    $stmt5 = $conn->prepare("SELECT pre.Fecha, pre.Destino, sub.CodCotizacion, sub.Cantidad, ss.numero_semana AS semana FROM msaccess_masivo_SubPreIngresosPitaya sub INNER JOIN msaccess_masivo_PreIngresoPitaya pre ON pre.CodPreIngresoPitaya = sub.CodPreIngresoPitaya INNER JOIN SemanasSistema ss ON pre.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin WHERE ss.numero_semana BETWEEN ? AND ? AND sub.CodCotizacion IN ($phCods) AND pre.Destino REGEXP '^[Pp]itaya[[:space:]]+[0-9]+'");
    $stmt5->execute(array_merge([$semDesde, $semHasta], $allCods));
    foreach ($stmt5->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (!preg_match('/[Pp]itaya\s+(\d+)/', $r['Destino'], $m)) continue;
        $suc = (int)$m[1];
        if (!in_array($suc, $sucFiltro)) continue;
        $addReg('despacho', $r, $codMap[(int)$r['CodCotizacion']]);
    }

    // ── Consumo Teórico (Réplica exacta de lógica P1/P2/P3) ─────────
    $consTeoDiario = [];

    // Pre-cargar cotizaciones para P2/P3 de todos los ingredientes posibles
    $stmtCot = $conn->prepare("
        SELECT CodIngrediente, CodCotizacion, Conversion, Prioridad 
        FROM Cotizaciones 
        WHERE (Subproducto IS NULL OR Subproducto!=1) AND (Marca IS NULL OR Marca!='Almacen Global')
        ORDER BY CodIngrediente, Conversion DESC, Prioridad ASC
    ");
    $stmtCot->execute();
    $cotP2P3 = [];
    foreach ($stmtCot->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $ci = $c['CodIngrediente'];
        if (!isset($cotP2P3[$ci])) $cotP2P3[$ci] = ['p2' => null, 'p3' => null];
        if ($c['Conversion'] == 1 && $c['Prioridad'] == 1 && !$cotP2P3[$ci]['p2']) $cotP2P3[$ci]['p2'] = (int)$c['CodCotizacion'];
        if (!$cotP2P3[$ci]['p3']) $cotP2P3[$ci]['p3'] = (int)$c['CodCotizacion'];
    }

    // Pre-cargar ingredientes para unidades
    $stmtIng = $conn->prepare("SELECT CodIngrediente, Unidad FROM DBIngredientes");
    $stmtIng->execute();
    $dbIng = [];
    foreach ($stmtIng->fetchAll(PDO::FETCH_ASSOC) as $row) $dbIng[$row['CodIngrediente']] = $row;

    // Unidades para conversión de nombres (Access -> ERP)
    $stmtU = $conn->prepare("SELECT id, nombre, abreviado, nombres_opcionales FROM unidad_producto");
    $stmtU->execute();
    $uPorNom = [];
    foreach ($stmtU->fetchAll(PDO::FETCH_ASSOC) as $u) {
        $uid = (int)$u['id'];
        $uPorNom[strtolower(trim($u['nombre']))] = $uid;
        if ($u['abreviado']) $uPorNom[strtolower(trim($u['abreviado']))] = $uid;
        if (!empty($u['nombres_opcionales'])) {
            foreach (preg_split('/[,;|]+/', $u['nombres_opcionales']) as $al) {
                $ak = strtolower(trim($al));
                if ($ak) $uPorNom[$ak] = $uid;
            }
        }
    }

    // Ventas Globales
    $sqlV = "
        SELECT v.Fecha, sr.CodIngrediente, sr.codporcion, SUM(v.Cantidad * sr.Cantidad) AS total
        FROM VentasGlobalesAccessCSV v
        INNER JOIN SubReceta sr ON sr.CodBatido = v.CodProducto
        WHERE v.Anulado=0 AND v.Fecha BETWEEN ? AND ? AND v.Semana BETWEEN ? AND ? AND v.local IN ($phSucs)
          AND v.CodProducto IS NOT NULL
          AND (sr.codporcion IS NULL OR sr.codporcion NOT IN (SELECT CodCotizacionPorcion FROM MezclaPorcionesAccess WHERE CodCotizacionPorcion IS NOT NULL))
        GROUP BY v.Fecha, sr.CodIngrediente, sr.codporcion
    ";
    $stmtV = $conn->prepare($sqlV);
    $stmtV->execute(array_merge([$fechaInicioRange, $fechaFinRange, $semDesde, $semHasta], $sucFiltro));

    foreach ($stmtV->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $mapeo = null;
        $esP1 = false;
        $cp = $f['codporcion'] ? (int)$f['codporcion'] : null;
        $ci = $f['CodIngrediente'];

        // Resolución P1/P2/P3
        if ($cp && isset($codMap[$cp])) {
            $mapeo = $codMap[$cp];
            $esP1 = true;
        } elseif ($ci && isset($cotP2P3[$ci])) {
            $p2 = $cotP2P3[$ci]['p2'];
            $p3 = $cotP2P3[$ci]['p3'];
            if ($p2 && isset($codMap[$p2])) $mapeo = $codMap[$p2];
            elseif ($p3 && isset($codMap[$p3])) $mapeo = $codMap[$p3];
        }

        if ($mapeo) {
            $cantTotal = (float)$f['total'];
            $val = 0;
            
            if ($mapeo['tipo'] === 'cascada') {
                // Producto global (receta-paquete)
                $val = $cantTotal;
            } else {
                // Insumo base o alternativo
                $unAcc = $dbIng[$ci]['Unidad'] ?? '';
                $idUnAcc = isset($uPorNom[strtolower(trim($unAcc))]) ? $uPorNom[strtolower(trim($unAcc))] : null;
                $factor = 1.0;

                if ($idUnAcc && $idUnAcc !== $mapeo['id_unid']) {
                    if (isset($convIndex[$idUnAcc][$mapeo['id_unid']])) {
                        $factor = $convIndex[$idUnAcc][$mapeo['id_unid']];
                    }
                }
                
                $val = ($cantTotal * $factor) / $mapeo['pp_cant'];
                if ($esP1) $val = round($val * 2) / 2;
            }

            $fec = $f['Fecha'];
            if (!isset($consTeoDiario[$fec])) $consTeoDiario[$fec] = 0;
            $consTeoDiario[$fec] += $val;
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
