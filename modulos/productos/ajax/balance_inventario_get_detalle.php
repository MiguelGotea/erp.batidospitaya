<?php
/* ============================================================
   AJAX: Detalle de registros kardex para un producto base
   modulos/productos/ajax/balance_inventario_get_detalle.php
   Devuelve todos los registros brutos que componen cada
   columna del balance (inv_inicial, ajuste, despacho, merma, inv_final)
   ============================================================ */
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');
set_time_limit(0);

$usuario = obtenerUsuarioActual();
$cargo   = $usuario['CodNivelesCargos'];
if (!tienePermiso('balance_inventario_access_host', 'vista', $cargo)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Sin permiso']);
    exit();
}

$idPP     = isset($_POST['id_pp'])       ? (int) $_POST['id_pp']       : 0;
$semDesde = isset($_POST['semana_desde']) ? (int) $_POST['semana_desde'] : 0;
$semHasta = isset($_POST['semana_hasta']) ? (int) $_POST['semana_hasta'] : 0;
$sucsPost = isset($_POST['sucursales'])   ? array_map('intval', (array) $_POST['sucursales']) : [];

if (!$idPP || !$semDesde || !$semHasta) {
    echo json_encode(['ok' => false, 'msg' => 'Parámetros incompletos']);
    exit();
}
$semDesde = min($semDesde, $semHasta);
$semHasta = max($semDesde, $semHasta);

try {
    // ── Fechas del rango ─────────────────────────────────────────────
    $rDates = $conn->prepare("
        SELECT MIN(fecha_inicio) AS inicio, MAX(fecha_fin) AS fin 
        FROM SemanasSistema 
        WHERE numero_semana BETWEEN :d AND :h
    ");
    $rDates->execute([':d' => $semDesde, ':h' => $semHasta]);
    $rangeDates = $rDates->fetch(PDO::FETCH_ASSOC);
    $fechaInicioRange = $rangeDates['inicio'];
    $fechaFinRange    = $rangeDates['fin'];

    // ── Semana anterior ──────────────────────────────────────────────
    $r = $conn->prepare("SELECT numero_semana FROM SemanasSistema WHERE numero_semana < :d ORDER BY numero_semana DESC LIMIT 1");
    $r->execute([':d' => $semDesde]);
    $semAnt = $r->fetchColumn();
    if (!$semAnt) { echo json_encode(['ok' => false, 'msg' => 'Sin semana anterior']); exit(); }

    // ── Sucursales disponibles ────────────────────────────────────────

    $r2 = $conn->prepare("SELECT codigo, nombre FROM sucursales WHERE activa=1 AND sucursal=1");
    $r2->execute();
    $allSucs = [];
    foreach ($r2->fetchAll(PDO::FETCH_ASSOC) as $s) $allSucs[(int)$s['codigo']] = $s['nombre'];
    $sucFiltro = !empty($sucsPost) ? $sucsPost : array_keys($allSucs);

    // ── Producto base (meta) ──────────────────────────────────────────
    $rMeta = $conn->prepare("
        SELECT pp.id, pp.Nombre, pp.cantidad AS pp_cant,
               pp.id_unidad_producto, pp.id_producto_maestro,
               pm.Nombre AS maestro, u.nombre AS unidad
        FROM producto_presentacion pp
        LEFT JOIN producto_maestro pm ON pm.id = pp.id_producto_maestro
        LEFT JOIN unidad_producto u   ON u.id  = pp.id_unidad_producto
        WHERE pp.id = :id AND pp.presentacion_basica_inventario = 1
    ");
    $rMeta->execute([':id' => $idPP]);
    $prodMeta = $rMeta->fetch(PDO::FETCH_ASSOC);
    if (!$prodMeta) { echo json_encode(['ok' => false, 'msg' => 'Producto base no encontrado']); exit(); }

    // ── Pre-cargar diccionario + cascada + altMap ─────────────────────
    // (mismo proceso que get_datos.php, pero solo para este producto)

    // CodCotizaciones que mapean al producto base
    $rCods = $conn->prepare("
        SELECT d.CodCotizacion
        FROM diccionario_productos_legado d
        WHERE d.id_producto_presentacion = :id
    ");
    $rCods->execute([':id' => $idPP]);
    $codsDirectos = array_column($rCods->fetchAll(PDO::FETCH_ASSOC), 'CodCotizacion');
    $codsDirectos = array_map('intval', $codsDirectos);

    // CodCotizaciones que llegan al mismo producto via cascada (paquete → base)
    $rCasc = $conn->prepare("
        SELECT d.CodCotizacion, crp.cantidad AS factor, pp_pkg.Nombre AS nombre_pkg
        FROM diccionario_productos_legado d
        INNER JOIN producto_presentacion pp_pkg ON pp_pkg.id = d.id_producto_presentacion
            AND pp_pkg.presentacion_receta = 1 AND pp_pkg.Activo = 'SI'
        INNER JOIN componentes_receta_producto crp ON crp.id_receta_producto_global = pp_pkg.Id_receta_producto
            AND crp.id_presentacion_producto = :id
        WHERE (SELECT COUNT(DISTINCT id_presentacion_producto) FROM componentes_receta_producto WHERE id_receta_producto_global = pp_pkg.Id_receta_producto) = 1
    ");
    $rCasc->execute([':id' => $idPP]);
    $codsCascada = [];
    foreach ($rCasc->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $codsCascada[(int)$row['CodCotizacion']] = [
            'factor'      => (float)$row['factor'],
            'nombre_orig' => $row['nombre_pkg'],
            'tipo'        => 'cascada',
        ];
    }

    // CodCotizaciones que llegan via presentación alternativa (misma unidad distinta)
    // Buscar productos activos con mismo maestro, ni base ni receta
    $convIndex = [];
    $rConv = $conn->prepare("SELECT id_unidad_producto_inicio AS i, id_unidad_producto_final AS f, cantidad AS fac FROM conversion_unidad_producto");
    $rConv->execute();
    foreach ($rConv->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $convIndex[(int)$c['i']][(int)$c['f']] = (float)$c['fac'];
        $convIndex[(int)$c['f']][(int)$c['i']] = $c['fac'] != 0 ? 1/(float)$c['fac'] : 0;
    }
    $baseUnid = (int)$prodMeta['id_unidad_producto'];
    $baseCant = max((float)$prodMeta['pp_cant'], 0.001);
    $idMaestro = (int)$prodMeta['id_producto_maestro'];

    $codsAlt = [];
    if ($idMaestro > 0) {
        $rAlt = $conn->prepare("
            SELECT d.CodCotizacion, pp.id AS pp_id, pp.cantidad AS pp_cant,
                   pp.id_unidad_producto AS pp_unid, pp.Nombre AS nombre_pp
            FROM diccionario_productos_legado d
            INNER JOIN producto_presentacion pp ON pp.id = d.id_producto_presentacion
            WHERE pp.Activo = 'SI'
              AND pp.presentacion_basica_inventario = 0
              AND pp.presentacion_receta = 0
              AND pp.id_producto_maestro = :maestro
        ");
        $rAlt->execute([':maestro' => $idMaestro]);
        foreach ($rAlt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $altUnid = (int)$row['pp_unid'];
            $altCant = max((float)$row['pp_cant'], 0.001);
            $factor = null;
            if ($altUnid === $baseUnid) {
                $factor = $altCant / $baseCant;
            } elseif (isset($convIndex[$altUnid][$baseUnid])) {
                $factor = ($altCant * $convIndex[$altUnid][$baseUnid]) / $baseCant;
            }
            if ($factor !== null) {
                $codsAlt[(int)$row['CodCotizacion']] = [
                    'factor'      => $factor,
                    'nombre_orig' => $row['nombre_pp'],
                    'tipo'        => 'alternativa',
                ];
            }
        }
    }

    // Mapa unificado: CodCotizacion → {factor, nombre_orig, tipo}
    $codMap = [];
    $idMaestro = (int)$prodMeta['id_producto_maestro'];
    $baseUnid  = (int)$prodMeta['id_unidad_producto'];
    $baseCant  = max((float)$prodMeta['pp_cant'], 0.001);

    // Paso 1: Incluir los mapeos directos del producto base (idPP)
    $rDirect = $conn->prepare("SELECT CodCotizacion FROM diccionario_productos_legado WHERE id_producto_presentacion = :id");
    $rDirect->execute([':id' => $idPP]);
    foreach ($rDirect->fetchAll(PDO::FETCH_COLUMN) as $cod) {
        $codMap[(int)$cod] = ['factor' => 1.0, 'nombre_orig' => $prodMeta['Nombre'], 'tipo' => 'base'];
    }

    // Paso 2: Lógica AUTO (por Maestro)
    if ($idMaestro > 0) {
        $rAllMaestro = $conn->prepare("
            SELECT d.CodCotizacion, pp.id AS pp_id, pp.cantidad AS pp_cant,
                   pp.id_unidad_producto AS pp_unid, pp.Nombre AS nombre_pp,
                   pp.presentacion_basica_inventario, pp.presentacion_receta
            FROM diccionario_productos_legado d
            INNER JOIN producto_presentacion pp ON pp.id = d.id_producto_presentacion
            WHERE pp.id_producto_maestro = :maestro AND pp.Activo = 'SI'
        ");
        $rAllMaestro->execute([':maestro' => $idMaestro]);
        foreach ($rAllMaestro->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $altUnid = (int)$row['pp_unid'];
            $altCant = max((float)$row['pp_cant'], 0.001);
            $factor = null;
            if ($altUnid === $baseUnid) {
                $factor = $altCant / $baseCant;
            } elseif (isset($convIndex[$altUnid][$baseUnid])) {
                $factor = ($altCant * $convIndex[$altUnid][$baseUnid]) / $baseCant;
            }
            if ($factor !== null) {
                $type = 'alternativa';
                if ($row['presentacion_basica_inventario']) $type = 'base';
                if ($row['presentacion_receta']) $type = 'cascada';

                $codMap[(int)$row['CodCotizacion']] = [
                    'factor'      => (float)$factor,
                    'nombre_orig' => $row['nombre_pp'],
                    'tipo'        => $type,
                ];
            }
        }
    }


    // ── Preparar para Consumo Teórico (P1/P2/P3) ──────────────────────
    $rIngs = $conn->prepare("SELECT DISTINCT CodIngrediente FROM Cotizaciones WHERE CodCotizacion IN (".implode(',', array_fill(0, count(array_keys($codMap)), '?')).")");
    $rIngs->execute(array_keys($codMap));
    $ingsRel = $rIngs->fetchAll(PDO::FETCH_COLUMN);

    // Mapeo inverso para resolución rápida: CodCotizacion -> {factor}
    $resMap = $codMap;



    if (empty($codMap)) {
        echo json_encode(['ok' => true, 'registros' => [], 'producto' => $prodMeta]);
        exit();
    }

    $allCods   = array_keys($codMap);
    $phCods    = implode(',', array_fill(0, count($allCods), '?'));
    $phSuc     = implode(',', array_fill(0, count($sucFiltro), '?'));

    // ── Helper: consultar tabla kardex simple ─────────────────────────
    function queryKardex(PDO $conn, string $tabla, string $tipo, int $semDesde, int $semHasta,
        array $allCods, string $phCods, array $sucFiltro, string $phSuc, array $codMap, array $allSucs): array
    {
        $stmt = $conn->prepare("
            SELECT k.Fecha, k.Sucursal, k.CodCotizacion, k.Cantidad,
                   ss.numero_semana
            FROM `{$tabla}` k
            INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin
            WHERE ss.numero_semana BETWEEN ? AND ?
              AND k.CodCotizacion IN ({$phCods})
              AND k.Sucursal IN ({$phSuc})
            ORDER BY k.Fecha ASC, k.Sucursal ASC
        ");
        $params = array_merge([$semDesde, $semHasta], $allCods, $sucFiltro);
        $stmt->execute($params);
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cod  = (int)$row['CodCotizacion'];
            $info = $codMap[$cod];
            $qty_orig = (float)$row['Cantidad'];
            $rows[] = [
                'tipo'         => $tipo,
                'fecha'        => $row['Fecha'],
                'semana'       => (int)$row['numero_semana'],
                'sucursal'     => (int)$row['Sucursal'],
                'suc_nombre'   => $allSucs[(int)$row['Sucursal']] ?? ('Suc. '.$row['Sucursal']),
                'cod_cotizacion'  => $cod,
                'nombre_original' => $info['nombre_orig'],
                'tipo_conversion' => $info['tipo'],
                'factor'       => round($info['factor'], 6),
                'qty_original' => $qty_orig,
                'qty_base'     => round($qty_orig * $info['factor'], 4),
            ];
        }
        return $rows;
    }

    $registros = [];

    // ── Inventario Inicial (semana anterior) ──────────────────────────
    $stmt = $conn->prepare("
        SELECT k.Fecha, k.Sucursal, k.CodCotizacion, k.Cantidad,
               ss.numero_semana
        FROM msaccess_masivo_InventarioCotizacion k
        INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin
        WHERE ss.numero_semana = ?
          AND k.CodCotizacion IN ({$phCods})
          AND k.Sucursal IN ({$phSuc})
        ORDER BY k.Fecha ASC, k.Sucursal ASC
    ");
    $stmt->execute(array_merge([(int)$semAnt], $allCods, $sucFiltro));
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cod  = (int)$row['CodCotizacion'];
        $info = $codMap[$cod];
        $qty  = (float)$row['Cantidad'];
        $registros[] = [
            'tipo' => 'inv_inicial', 'fecha' => $row['Fecha'],
            'semana' => (int)$row['numero_semana'],
            'sucursal' => (int)$row['Sucursal'],
            'suc_nombre' => $allSucs[(int)$row['Sucursal']] ?? 'Suc. '.$row['Sucursal'],
            'cod_cotizacion' => $cod, 'nombre_original' => $info['nombre_orig'],
            'tipo_conversion' => $info['tipo'], 'factor' => round($info['factor'], 6),
            'qty_original' => $qty, 'qty_base' => round($qty * $info['factor'], 4),
        ];
    }

    // ── Inventario Final (última semana del rango) ────────────────────
    $stmt = $conn->prepare("
        SELECT k.Fecha, k.Sucursal, k.CodCotizacion, k.Cantidad,
               ss.numero_semana
        FROM msaccess_masivo_InventarioCotizacion k
        INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin
        WHERE ss.numero_semana = ?
          AND k.CodCotizacion IN ({$phCods})
          AND k.Sucursal IN ({$phSuc})
        ORDER BY k.Fecha ASC, k.Sucursal ASC
    ");
    $stmt->execute(array_merge([$semHasta], $allCods, $sucFiltro));
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cod  = (int)$row['CodCotizacion'];
        $info = $codMap[$cod];
        $qty  = (float)$row['Cantidad'];
        $registros[] = [
            'tipo' => 'inv_final', 'fecha' => $row['Fecha'],
            'semana' => (int)$row['numero_semana'],
            'sucursal' => (int)$row['Sucursal'],
            'suc_nombre' => $allSucs[(int)$row['Sucursal']] ?? 'Suc. '.$row['Sucursal'],
            'cod_cotizacion' => $cod, 'nombre_original' => $info['nombre_orig'],
            'tipo_conversion' => $info['tipo'], 'factor' => round($info['factor'], 6),
            'qty_original' => $qty, 'qty_base' => round($qty * $info['factor'], 4),
        ];
    }

    // ── Ajustes ───────────────────────────────────────────────────────
    $ajustes = queryKardex($conn, 'msaccess_masivo_AjustesInventario', 'ajuste',
        $semDesde, $semHasta, $allCods, $phCods, $sucFiltro, $phSuc, $codMap, $allSucs);
    $registros = array_merge($registros, $ajustes);

    // ── Merma ─────────────────────────────────────────────────────────
    $mermas = queryKardex($conn, 'msaccess_masivo_MermaCotizacion', 'merma',
        $semDesde, $semHasta, $allCods, $phCods, $sucFiltro, $phSuc, $codMap, $allSucs);
    $registros = array_merge($registros, $mermas);

    // ── Despacho ──────────────────────────────────────────────────────
    $stmtD = $conn->prepare("
        SELECT pre.Fecha AS Fecha, pre.Destino, sub.CodCotizacion, sub.Cantidad,
               ss.numero_semana
        FROM msaccess_masivo_SubPreIngresosPitaya sub
        INNER JOIN msaccess_masivo_PreIngresoPitaya pre ON pre.CodPreIngresoPitaya = sub.CodPreIngresoPitaya
        INNER JOIN SemanasSistema ss ON pre.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin
        WHERE ss.numero_semana BETWEEN ? AND ?
          AND sub.CodCotizacion IN ({$phCods})
          AND pre.Destino REGEXP '^[Pp]itaya[[:space:]]+[0-9]+'
        ORDER BY pre.Fecha ASC, pre.Destino ASC
    ");
    $stmtD->execute(array_merge([$semDesde, $semHasta], $allCods));
    foreach ($stmtD->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!preg_match('/[Pp]itaya\s+(\d+)/', $row['Destino'], $m)) continue;
        $suc = (int)$m[1];
        if (!in_array($suc, $sucFiltro)) continue;
        $cod  = (int)$row['CodCotizacion'];
        $info = $codMap[$cod];
        $qty  = (float)$row['Cantidad'];
        $registros[] = [
            'tipo' => 'despacho', 'fecha' => $row['Fecha'],
            'semana' => (int)$row['numero_semana'],
            'sucursal' => $suc,
            'suc_nombre' => $allSucs[$suc] ?? 'Suc. '.$suc,
            'destino_texto' => $row['Destino'],
            'cod_cotizacion' => $cod, 'nombre_original' => $info['nombre_orig'],
            'tipo_conversion' => $info['tipo'], 'factor' => round($info['factor'], 6),
            'qty_original' => $qty, 'qty_base' => round($qty * $info['factor'], 4),
        ];
    }

    // ── Consumo Teórico Diario ───────────────────────────────────────
    $consTeoricoDiario = []; // [fecha] => qty
    if (!empty($ingsRel)) {
        // P2/P3 para estos ingredientes
        $phI = implode(',', array_fill(0, count($ingsRel), '?'));
        $stmtCot = $conn->prepare("
            SELECT CodIngrediente, CodCotizacion, Conversion, Prioridad
            FROM Cotizaciones
            WHERE CodIngrediente IN ({$phI})
              AND (Subproducto IS NULL OR Subproducto!=1)
              AND (Marca IS NULL OR Marca!='Almacen Global')
            ORDER BY CodIngrediente, Conversion DESC, Prioridad ASC
        ");
        $stmtCot->execute($ingsRel);
        $cotP2P3 = [];
        foreach ($stmtCot->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $ci = $c['CodIngrediente'];
            if (!isset($cotP2P3[$ci])) $cotP2P3[$ci] = ['p2'=>null, 'p3'=>null];
            if ($c['Conversion']==1 && $c['Prioridad']==1 && !$cotP2P3[$ci]['p2']) $cotP2P3[$ci]['p2'] = (int)$c['CodCotizacion'];
            if (!$cotP2P3[$ci]['p3']) $cotP2P3[$ci]['p3'] = (int)$c['CodCotizacion'];
        }

        // Consultar ventas
        $phS = implode(',', array_fill(0, count($sucFiltro), '?'));
        $sqlV = "
            SELECT v.Fecha, sr.CodIngrediente, sr.codporcion, SUM(v.Cantidad * sr.Cantidad) AS cant_total
            FROM VentasGlobalesAccessCSV v
            INNER JOIN SubReceta sr ON sr.CodBatido = v.CodProducto
            WHERE v.Anulado=0
              AND v.Fecha BETWEEN ? AND ?
              AND v.Semana BETWEEN ? AND ?
              AND v.local IN ({$phS})
              AND sr.CodIngrediente IN ({$phI})
              AND v.CodProducto IS NOT NULL
              AND (sr.codporcion IS NULL OR sr.codporcion NOT IN (
                  SELECT CodCotizacionPorcion FROM MezclaPorcionesAccess WHERE CodCotizacionPorcion IS NOT NULL
              ))
            GROUP BY v.Fecha, sr.CodIngrediente, sr.codporcion
        ";
        $stmtV = $conn->prepare($sqlV);
        $stmtV->execute(array_merge([$fechaInicioRange, $fechaFinRange, $semDesde, $semHasta], $sucFiltro, $ingsRel));

        
        foreach ($stmtV->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $ci = $fila['CodIngrediente'];
            $cp = $fila['codporcion'] ? (int)$fila['codporcion'] : null;
            $qtyVenta = (float)$fila['cant_total'];
            $targetCod = null;
            $isP1 = false;

            if ($cp && isset($resMap[$cp])) {
                $targetCod = $cp; $isP1 = true;
            } elseif (isset($cotP2P3[$ci]['p2']) && isset($resMap[$cotP2P3[$ci]['p2']])) {
                $targetCod = $cotP2P3[$ci]['p2'];
            } elseif (isset($cotP2P3[$ci]['p3']) && isset($resMap[$cotP2P3[$ci]['p3']])) {
                $targetCod = $cotP2P3[$ci]['p3'];
            }

            if ($targetCod) {
                $qtyBase = $qtyVenta * $resMap[$targetCod]['factor'];
                if ($isP1) $qtyBase = round($qtyBase * 2) / 2;
                $f = $fila['Fecha'];
                if (!isset($consTeoricoDiario[$f])) $consTeoricoDiario[$f] = 0;
                $consTeoricoDiario[$f] += $qtyBase;
            }
        }
    }

    // ── Totales por tipo ──────────────────────────────────────────────
    $totalesTipo = ['inv_inicial' => 0, 'ajuste' => 0, 'despacho' => 0, 'merma' => 0, 'inv_final' => 0];
    foreach ($registros as $reg) {
        if (isset($totalesTipo[$reg['tipo']])) $totalesTipo[$reg['tipo']] += $reg['qty_base'];
    }
    $consumoTeoricoTotal = array_sum($consTeoricoDiario);
    $consumoReal = round(
        $totalesTipo['inv_inicial'] + $totalesTipo['ajuste'] + $totalesTipo['despacho']
        - $totalesTipo['merma'] - $totalesTipo['inv_final'], 4
    );


    echo json_encode([
        'ok'          => true,
        'producto'    => $prodMeta,
        'semana_ant'  => (int)$semAnt,
        'sem_desde'   => $semDesde,
        'sem_hasta'   => $semHasta,
        'fecha_inicio'=> $fechaInicioRange,
        'fecha_fin'   => $fechaFinRange,
        'registros'   => $registros,

        'totales_tipo'=> array_map(fn($v) => round($v, 4), $totalesTipo),
        'consumo_real'=> $consumoReal,
        'consumo_teorico' => round($consumoTeoricoTotal, 4),
        'consumo_teorico_diario' => $consTeoricoDiario,
        'num_cods_mapeados' => count($codMap),

    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
}
