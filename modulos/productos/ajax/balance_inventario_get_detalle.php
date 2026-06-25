<?php
/* ============================================================
   AJAX: Detalle de registros kardex para un producto base
   modulos/productos/ajax/balance_inventario_get_detalle.php
   ============================================================ */
require_once '../../../core/auth/auth.php';
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
$semCorte = isset($_POST['semana_corte']) ? (int) $_POST['semana_corte'] : 0;
$sucsPost = isset($_POST['sucursales']) ? array_map('intval', (array) $_POST['sucursales']) : [];

if (!$idPP || !$semDesde || !$semHasta) {
    echo json_encode(['ok' => false, 'msg' => 'Parámetros incompletos']);
    exit();
}
$semDesde = min($semDesde, $semHasta);
$semHasta = max($semDesde, $semHasta);

if (!$semCorte || $semCorte < $semDesde || $semCorte > $semHasta) {
    echo json_encode(['ok' => false, 'msg' => 'Semana de corte inválida o fuera del rango']);
    exit();
}

try {
    ini_set('memory_limit', '512M');
    // ── Fechas del rango ─────────────────────────────────────────────
    $rDates = $conn->prepare("SELECT MIN(fecha_inicio) AS inicio, MAX(fecha_fin) AS fin FROM SemanasSistema WHERE numero_semana BETWEEN :d AND :h");
    $rDates->execute([':d' => $semDesde, ':h' => $semHasta]);
    $rangeDates = $rDates->fetch(PDO::FETCH_ASSOC);
    $fechaInicioRange = $rangeDates['inicio'];
    $fechaFinRange = $rangeDates['fin'];

    // ── Semana anterior al CORTE (punto de partida del inventario) ─────
    $r = $conn->prepare("SELECT numero_semana FROM SemanasSistema WHERE numero_semana < :c ORDER BY numero_semana DESC LIMIT 1");
    $r->execute([':c' => $semCorte]);
    $semAntCorte = $r->fetchColumn();
    if (!$semAntCorte) {
        echo json_encode(['ok' => false, 'msg' => 'Sin semana anterior al corte']);
        exit();
    }
    $semAnt = $semAntCorte; // alias para compatibilidad con código posterior

    // Fecha inicio de la semana de corte (pivot del gráfico)
    $rFechaCorte = $conn->prepare("SELECT fecha_inicio FROM SemanasSistema WHERE numero_semana = :c");
    $rFechaCorte->execute([':c' => $semCorte]);
    $fechaInicioCorte = $rFechaCorte->fetchColumn();

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

    // ── Mapeo Completo (REPLICA de balance_inventario_get_datos.php) ──
    
    // 1. Todos los productos base para construir maestroToBase
    $rMetaAll = $conn->prepare("
        SELECT pp.id, pp.id_unidad_producto AS unid, pp.cantidad AS cant, pp.id_producto_maestro AS mid, pp.Id_receta_producto
        FROM producto_presentacion pp
        WHERE pp.presentacion_basica_inventario=1 AND pp.Activo='SI'
    ");
    $rMetaAll->execute();
    $maestroToBase = [];
    foreach ($rMetaAll->fetchAll(PDO::FETCH_ASSOC) as $pm) {
        $mid = (int) $pm['mid'];
        if ($mid > 0) {
            // Priorizar el que NO es receta
            if (!isset($maestroToBase[$mid]) || empty($pm['Id_receta_producto'])) {
                $maestroToBase[$mid] = [
                    'base_pp_id' => (int)$pm['id'], 
                    'base_unid'  => (int)$pm['unid'], 
                    'base_cant'  => max((float)$pm['cant'], 0.001)
                ];
            }
        }
    }

    // 2. Cascade Map (Paquetes -> Base)
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

    // 3. Diccionario Completo (CodCotizacion -> Producto)
    $rDic = $conn->prepare("
        SELECT d.CodCotizacion, pp.id AS pp_id, pp.Nombre,
               pp.presentacion_basica_inventario AS es_base, pp.presentacion_receta AS es_receta,
               pp.id_unidad_producto AS pp_unid, pp.cantidad AS pp_cant,
               pp.id_producto_maestro AS id_maestro, pp.Id_receta_producto
        FROM diccionario_productos_legado d
        INNER JOIN producto_presentacion pp ON pp.id = d.id_producto_presentacion
        WHERE pp.Activo='SI'
    ");
    $rDic->execute();
    $diccionarioRaw = $rDic->fetchAll(PDO::FETCH_ASSOC);
    $diccionario = [];
    foreach($diccionarioRaw as $d) $diccionario[(int)$d['CodCotizacion']] = $d;

    // 4. altMap (Presentaciones alternativas -> Base)
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
        } else {
            continue;
        }
        $altMap[$pp_id] = ['base_id' => $base['base_pp_id'], 'factor' => $factor];
    }

    // 5. Mapeo para Balance (Elementos: inv, ajuste, merma, despacho, compra)
    // Solo incluimos lo que resolverCodCot resolvería hacia nuestro idPP
    $codMapBalance = [];
    foreach ($diccionario as $cod => $dic) {
        $pp_id = (int)$dic['pp_id'];
        $resBid = null;
        $resFac = 1.0;
        $resType = 'base';

        if (isset($cascadeMap[$pp_id])) {
            $resBid = $cascadeMap[$pp_id]['base_id'];
            $resFac = $cascadeMap[$pp_id]['factor'];
            $resType = 'cascada';
        } elseif (isset($altMap[$pp_id])) {
            $resBid = $altMap[$pp_id]['base_id'];
            $resFac = $altMap[$pp_id]['factor'];
            $resType = 'alternativa';
        } elseif ($dic['es_base']) {
            $resBid = $pp_id;
            $resFac = 1.0;
            $resType = 'base';
        }

        if ($resBid === $idPP) {
            $codMapBalance[$cod] = [
                'factor'  => $resFac,
                'nombre'  => $dic['Nombre'],
                'tipo'    => $resType,
                'pp_id'   => $pp_id,
                'id_unid' => (int)$dic['pp_unid'],
                'pp_cant' => (float)$dic['pp_cant'],
                'id_mae'  => (int)$dic['id_maestro']
            ];
        }
    }

    $allCods = array_keys($codMapBalance);
    if (empty($allCods)) {
        echo json_encode([
            'ok'                     => true,
            'id_pp'                  => $idPP,
            'producto'               => $prodMeta,
            'semana_ant'             => (int) $semAntCorte,
            'semana_corte'           => (int) $semCorte,
            'fecha_inicio_corte'     => $fechaInicioCorte,
            'fecha_inicio'           => $fechaInicioRange,
            'fecha_fin'              => $fechaFinRange,
            'inv_inicial_rango'      => 0,
            'semana_ant_rango'       => 0,
            'registros'              => [],
            'totales_tipo'           => ['inv_inicial' => 0, 'ajuste' => 0, 'despacho' => 0, 'compras' => 0, 'merma' => 0, 'inv_final' => 0],
            'consumo_real'           => 0,
            'consumo_teorico'        => 0,
            'consumo_teorico_diario' => [],
            'puntos_domingo'         => [],
            'num_mapeos'             => 0,
            'msg'                    => 'No hay códigos mapeados para balance'
        ]);
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
        $addReg('inv_inicial', $r, $codMapBalance[(int)$r['CodCotizacion']]);
    }

    // 2. Inventario Final
    $stmt2 = $conn->prepare("SELECT k.Fecha, k.Sucursal, k.CodCotizacion, k.Cantidad, ss.numero_semana AS semana FROM msaccess_masivo_InventarioCotizacion k INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin WHERE ss.numero_semana = ? AND k.CodCotizacion IN ($phCods) AND k.Sucursal IN ($phSucs)");
    $stmt2->execute(array_merge([$semHasta], $allCods, $sucFiltro));
    foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $addReg('inv_final', $r, $codMapBalance[(int)$r['CodCotizacion']]);
    }

    // 3. Ajustes
    $stmt3 = $conn->prepare("SELECT k.Fecha, k.Sucursal, k.CodCotizacion, k.Cantidad, ss.numero_semana AS semana FROM msaccess_masivo_AjustesInventario k INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin WHERE ss.numero_semana BETWEEN ? AND ? AND k.CodCotizacion IN ($phCods) AND k.Sucursal IN ($phSucs)");
    $stmt3->execute(array_merge([$semDesde, $semHasta], $allCods, $sucFiltro));
    foreach ($stmt3->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $addReg('ajuste', $r, $codMapBalance[(int)$r['CodCotizacion']]);
    }

    // 4. Mermas
    $stmt4 = $conn->prepare("SELECT k.Fecha, k.Sucursal, k.CodCotizacion, k.Cantidad, ss.numero_semana AS semana FROM msaccess_masivo_MermaCotizacion k INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin WHERE ss.numero_semana BETWEEN ? AND ? AND k.CodCotizacion IN ($phCods) AND k.Sucursal IN ($phSucs)");
    $stmt4->execute(array_merge([$semDesde, $semHasta], $allCods, $sucFiltro));
    foreach ($stmt4->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $addReg('merma', $r, $codMapBalance[(int)$r['CodCotizacion']]);
    }

    // 5. Despachos
    $stmt5 = $conn->prepare("SELECT pre.Fecha, pre.Destino, sub.CodCotizacion, sub.Cantidad, ss.numero_semana AS semana FROM msaccess_masivo_SubPreIngresosPitaya sub INNER JOIN msaccess_masivo_PreIngresoPitaya pre ON pre.CodPreIngresoPitaya = sub.CodPreIngresoPitaya INNER JOIN SemanasSistema ss ON pre.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin WHERE ss.numero_semana BETWEEN ? AND ? AND sub.CodCotizacion IN ($phCods) AND pre.Destino REGEXP '[Pp]itaya[[:space:]]+[0-9]+'");
    $stmt5->execute(array_merge([$semDesde, $semHasta], $allCods));
    foreach ($stmt5->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (!preg_match('/pitaya\s+(\d+)/i', $r['Destino'], $m)) continue;
        $suc = (int)$m[1];
        if (!in_array($suc, $sucFiltro)) continue;
        $r['Sucursal'] = $suc; // Requerido para addReg
        $addReg('despacho', $r, $codMapBalance[(int)$r['CodCotizacion']]);
    }

    // 6. Compras
    $stmt6 = $conn->prepare("SELECT k.Fecha, k.Sucursal, k.CodCotizacion AS CodCotizacion, k.Cantidad, ss.numero_semana AS semana FROM msaccess_masivo_Compras k INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin WHERE ss.numero_semana BETWEEN ? AND ? AND k.CodCotizacion IN ($phCods) AND k.Sucursal IN ($phSucs)");
    $stmt6->execute(array_merge([$semDesde, $semHasta], $allCods, $sucFiltro));
    foreach ($stmt6->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $r['Destino'] = ''; // no aplica
        $addReg('compras', $r, $codMapBalance[(int)$r['CodCotizacion']]);
    }
    // ── Consumo Teórico (REPLICA de lógica P1/P2/P3 de dashboard_consumo / balance_get_datos) ──
    $consTeoDiario = [];
    $codMapConsumo = [];
    
    // Paso A & B: Directo y AUTO por maestro
    foreach ($diccionario as $cod => $dic) {
        $mid = (int)$dic['id_maestro'];
        if ($dic['es_base'] && (int)$dic['pp_id'] === $idPP) {
            $codMapConsumo[$cod] = [
                'pp_id'   => (int)$dic['pp_id'],
                'pp_cant' => (float)$dic['pp_cant'],
                'id_unid' => (int)$dic['pp_unid'],
                'id_mae'  => $mid,
                'Id_receta_producto' => $dic['Id_receta_producto'],
                'tipo'    => 'consumo_directo'
            ];
        } elseif ($mid > 0 && isset($maestroToBase[$mid]) && $maestroToBase[$mid]['base_pp_id'] === $idPP) {
            $base = $maestroToBase[$mid];
            $codMapConsumo[$cod] = [
                'pp_id'   => $base['base_pp_id'],
                'pp_cant' => $base['base_cant'],
                'id_unid' => $base['base_unid'],
                'id_mae'  => $mid,
                'Id_receta_producto' => null,
                'tipo'    => 'consumo_auto'
            ];
        }
    }

    // Paso C: Fallback via CodIngrediente
    $codsEnConsumo = array_keys($codMapConsumo);
    if (!empty($codsEnConsumo)) {
        $phC_C = implode(',', array_fill(0, count($codsEnConsumo), '?'));
        $stmtIngs = $conn->prepare("SELECT DISTINCT CodIngrediente FROM Cotizaciones WHERE CodCotizacion IN ($phC_C)");
        $stmtIngs->execute($codsEnConsumo);
        $ingsEnConsumo = $stmtIngs->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($ingsEnConsumo)) {
            $phI_C = implode(',', array_fill(0, count($ingsEnConsumo), '?'));
            $stmtFallback = $conn->prepare("SELECT CodCotizacion FROM Cotizaciones WHERE CodIngrediente IN ($phI_C)");
            $stmtFallback->execute($ingsEnConsumo);
            foreach ($stmtFallback->fetchAll(PDO::FETCH_ASSOC) as $cf) {
                $cc = (int)$cf['CodCotizacion'];
                if (!isset($codMapConsumo[$cc])) {
                    // Si ya está en el diccionario, pertenece a otra presentación y no debe ser absorbido por fallback
                    if (isset($diccionario[$cc])) continue;

                    $codMapConsumo[$cc] = [
                        'pp_id'   => $idPP,
                        'pp_cant' => $baseCant,
                        'id_unid' => $baseUnid,
                        'id_mae'  => $idMaestro,
                        'Id_receta_producto' => null,
                        'tipo'    => 'consumo_fallback'
                    ];
                }
            }
        }
    }

    // Filtros de ventas
    $allCodsConsumo = array_keys($codMapConsumo);
    $phCodsCons = !empty($allCodsConsumo) ? implode(',', array_fill(0, count($allCodsConsumo), '?')) : '0';
    
    $rI1 = $conn->prepare("SELECT DISTINCT CodIngrediente FROM Cotizaciones WHERE CodCotizacion IN ($phCodsCons)");
    $rI1->execute(!empty($allCodsConsumo) ? $allCodsConsumo : []);
    $ingsRel = $rI1->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($ingsRel) || !empty($allCodsConsumo)) {
        // 2. Pre-cargar cotizaciones para P2/P3
        $cotP2P3 = [];
        if (!empty($ingsRel)) {
            $phI = implode(',', array_fill(0, count($ingsRel), '?'));
            $stmtCot = $conn->prepare("SELECT CodIngrediente, CodCotizacion, Conversion, Prioridad FROM Cotizaciones WHERE CodIngrediente IN ($phI) AND (Subproducto IS NULL OR Subproducto!=1) AND (Marca IS NULL OR Marca!='Almacen Global') ORDER BY CodIngrediente, Conversion DESC, Prioridad ASC");
            $stmtCot->execute($ingsRel);
            foreach ($stmtCot->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $ci = $c['CodIngrediente'];
                if (!isset($cotP2P3[$ci])) $cotP2P3[$ci] = ['p2' => null, 'p3' => null];
                if ($c['Conversion'] == 1 && $c['Prioridad'] == 1 && !$cotP2P3[$ci]['p2']) $cotP2P3[$ci]['p2'] = (int)$c['CodCotizacion'];
                if (!$cotP2P3[$ci]['p3']) $cotP2P3[$ci]['p3'] = (int)$c['CodCotizacion'];
            }
            $stmtIng = $conn->prepare("SELECT CodIngrediente, Unidad FROM DBIngredientes WHERE CodIngrediente IN ($phI)");
            $stmtIng->execute($ingsRel);
            $dbIng = [];
            foreach ($stmtIng->fetchAll(PDO::FETCH_ASSOC) as $row) $dbIng[$row['CodIngrediente']] = $row;
        }

        $stmtU = $conn->prepare("SELECT id, nombre, abreviado, nombres_opcionales FROM unidad_producto");
        $stmtU->execute();
        $uPorNom = [];
        foreach ($stmtU->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $uid = (int)$u['id'];
            $uPorNom[strtolower(trim($u['nombre']))] = $uid;
            if ($u['abreviado']) $uPorNom[strtolower(trim($u['abreviado']))] = $uid;
            if (!empty($u['nombres_opcionales'])) foreach (preg_split('/[,;|]+/', $u['nombres_opcionales']) as $al) { $ak = strtolower(trim($al)); if ($ak) $uPorNom[$ak] = $uid; }
        }

        $convIndex = [];
        $rConv = $conn->prepare("SELECT id_unidad_producto_inicio AS i, id_unidad_producto_final AS f, cantidad AS fac FROM conversion_unidad_producto");
        $rConv->execute();
        foreach ($rConv->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $convIndex[(int)$c['i']][(int)$c['f']] = (float)$c['fac'];
            $convIndex[(int)$c['f']][(int)$c['i']] = $c['fac'] != 0 ? 1/(float)$c['fac'] : 0;
        }

        $whereEx = "1=0";
        $pValSales = array_merge([$fechaInicioRange, $fechaFinRange, $semDesde, $semHasta], $sucFiltro);
        if (!empty($ingsRel)) { $whereEx .= " OR sr.CodIngrediente IN (" . implode(',', array_fill(0, count($ingsRel), '?')) . ")"; $pValSales = array_merge($pValSales, $ingsRel); }
        if (!empty($allCodsConsumo)) { $whereEx .= " OR sr.codporcion IN ($phCodsCons)"; $pValSales = array_merge($pValSales, $allCodsConsumo); }

        $sqlV = "SELECT v.Fecha, sr.CodIngrediente, sr.codporcion, SUM(v.Cantidad * sr.Cantidad) AS total FROM VentasGlobalesAccessCSV v INNER JOIN SubReceta sr ON sr.CodBatido = v.CodProducto WHERE v.Anulado=0 AND v.Fecha BETWEEN ? AND ? AND v.Semana BETWEEN ? AND ? AND v.local IN ($phSucs) AND v.CodProducto IS NOT NULL AND ($whereEx) AND (sr.codporcion IS NULL OR sr.codporcion NOT IN (SELECT CodCotizacionPorcion FROM MezclaPorcionesAccess WHERE CodCotizacionPorcion IS NOT NULL)) GROUP BY v.Fecha, sr.CodIngrediente, sr.codporcion";
        $stmtV = $conn->prepare($sqlV);
        $stmtV->execute($pValSales);

        foreach ($stmtV->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $mapeo = null;
            $esP1 = false;
            $cp = $f['codporcion'] ? (int)$f['codporcion'] : null;
            $ci = $f['CodIngrediente'];

            if ($cp && isset($codMapConsumo[$cp])) {
                $mapeo = $codMapConsumo[$cp];
                $esP1 = true;
            } elseif ($ci && isset($cotP2P3[$ci])) {
                $p2 = $cotP2P3[$ci]['p2'];
                $p3 = $cotP2P3[$ci]['p3'];
                if ($p2 && isset($codMapConsumo[$p2])) $mapeo = $codMapConsumo[$p2];
                elseif ($p3 && isset($codMapConsumo[$p3])) $mapeo = $codMapConsumo[$p3];
            }

            if ($mapeo) {
                $cantTotal = (float)$f['total'];
                $val = 0;
                $esGlobal = !empty($mapeo['Id_receta_producto']);
                
                if ($esGlobal) {
                    $val = $cantTotal;
                } else {
                    $unAcc = $dbIng[$ci]['Unidad'] ?? '';
                    $idUnAcc = isset($uPorNom[strtolower(trim($unAcc))]) ? $uPorNom[strtolower(trim($unAcc))] : null;
                    $factor = 1.0;
                    if ($idUnAcc && $idUnAcc !== $mapeo['id_unid']) { if (isset($convIndex[$idUnAcc][$mapeo['id_unid']])) { $factor = $convIndex[$idUnAcc][$mapeo['id_unid']]; } }
                    $val = ($cantTotal * $factor) / $mapeo['pp_cant'];
                    if ($esP1) $val = round($val * 2) / 2;
                }
                $fec = $f['Fecha'];
                if (!isset($consTeoDiario[$fec])) $consTeoDiario[$fec] = 0;
                $consTeoDiario[$fec] += $val;
            }
        }
    }

    // ── Inventario Físico de cada semana del rango (scatter visual) ────────
    $puntosDomingo = [];
    if (!empty($allCods) && !empty($sucFiltro)) {
        $stmtDom = $conn->prepare("
            SELECT k.Fecha, k.CodCotizacion, k.Cantidad
            FROM msaccess_masivo_InventarioCotizacion k
            INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin
            WHERE ss.numero_semana BETWEEN ? AND ?
              AND k.CodCotizacion IN ($phCods)
              AND k.Sucursal IN ($phSucs)
        ");
        $stmtDom->execute(array_merge([$semDesde, $semHasta], $allCods, $sucFiltro));
        foreach ($stmtDom->fetchAll(PDO::FETCH_ASSOC) as $pd) {
            $fecha = $pd['Fecha'];
            $info  = $codMapBalance[(int)$pd['CodCotizacion']] ?? null;
            if (!$info) continue;
            if (!isset($puntosDomingo[$fecha])) $puntosDomingo[$fecha] = 0;
            $puntosDomingo[$fecha] += round((float)$pd['Cantidad'] * $info['factor'], 4);
        }
    }

    $totales = ['inv_inicial' => 0, 'ajuste' => 0, 'despacho' => 0, 'compras' => 0, 'merma' => 0, 'inv_final' => 0];
    foreach ($registros as $reg)
        if (isset($totales[$reg['tipo']]))
            $totales[$reg['tipo']] += $reg['qty_base'];

    $consumoReal = round($totales['inv_inicial'] + $totales['ajuste'] + $totales['despacho'] + $totales['compras'] - $totales['merma'] - $totales['inv_final'], 4);

    // ── Inventario real al INICIO del rango (semana anterior a semDesde) ──────
    // Independiente del corte — siempre el arranque físico del período
    $invIniRango = 0;
    $semAntDesde = null;
    if (!empty($allCods) && !empty($sucFiltro)) {
        $rSAD = $conn->prepare("SELECT numero_semana FROM SemanasSistema WHERE numero_semana < :d ORDER BY numero_semana DESC LIMIT 1");
        $rSAD->execute([':d' => $semDesde]);
        $semAntDesde = $rSAD->fetchColumn() ?: null;

        if ($semAntDesde) {
            // Si la semana anterior al rango coincide con la del corte, reusar el dato ya calculado
            if ((int)$semAntDesde === (int)$semAntCorte) {
                $invIniRango = $totales['inv_inicial'];
            } else {
                // Query idéntico al stmt1 pero para la semana anterior al rango
                $stmtIR = $conn->prepare("SELECT k.Fecha, k.Sucursal, k.CodCotizacion, k.Cantidad, ss.numero_semana AS semana FROM msaccess_masivo_InventarioCotizacion k INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin WHERE ss.numero_semana = ? AND k.CodCotizacion IN ($phCods) AND k.Sucursal IN ($phSucs)");
                $stmtIR->execute(array_merge([$semAntDesde], $allCods, $sucFiltro));
                foreach ($stmtIR->fetchAll(PDO::FETCH_ASSOC) as $ri) {
                    $info = $codMapBalance[(int)$ri['CodCotizacion']] ?? null;
                    if (!$info) continue;
                    $invIniRango += round((float)$ri['Cantidad'] * $info['factor'], 4);
                }
            }
        }
    }

    echo json_encode([
        'ok'                     => true,
        'id_pp'                  => $idPP,
        'producto'               => $prodMeta,
        'semana_ant'             => (int) $semAntCorte,
        'semana_corte'           => (int) $semCorte,
        'fecha_inicio_corte'     => $fechaInicioCorte,
        'fecha_inicio'           => $fechaInicioRange,
        'fecha_fin'              => $fechaFinRange,
        'inv_inicial_rango'      => round($invIniRango, 4),
        'semana_ant_rango'       => (int) ($semAntDesde ?? 0),
        'registros'              => $registros,
        'totales_tipo'           => array_map(fn($v) => round($v, 4), $totales),
        'consumo_real'           => $consumoReal,
        'consumo_teorico'        => round(array_sum($consTeoDiario), 4),
        'consumo_teorico_diario' => $consTeoDiario,
        'puntos_domingo'         => $puntosDomingo,
        'num_mapeos'             => count($codMapBalance)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
