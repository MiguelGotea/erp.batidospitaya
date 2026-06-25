<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
header('Content-Type: application/json; charset=utf-8');
set_time_limit(0);
ini_set('memory_limit', '256M');

$usuario = obtenerUsuarioActual();
$cargo = $usuario['CodNivelesCargos'];
if (!tienePermiso('balance_inventario_access_host', 'vista', $cargo)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Sin permiso']);
    exit();
}

$semDesde = isset($_POST['semana_desde']) ? (int) $_POST['semana_desde'] : 0;
$semHasta = isset($_POST['semana_hasta']) ? (int) $_POST['semana_hasta'] : 0;
$sucsPost = isset($_POST['sucursales']) ? array_map('intval', (array) $_POST['sucursales']) : [];

if (!$semDesde || !$semHasta) {
    echo json_encode(['ok' => false, 'msg' => 'Ingresa los números de semana']);
    exit();
}
$semDesde = min($semDesde, $semHasta);
$semHasta = max($semDesde, $semHasta);

try {
    // ── SEMANA ANTERIOR ──────────────────────────────────────────────
    $r = $conn->prepare("SELECT numero_semana,fecha_inicio,fecha_fin FROM SemanasSistema WHERE numero_semana < :d ORDER BY numero_semana DESC LIMIT 1");
    $r->execute([':d' => $semDesde]);
    $semAnterior = $r->fetch(PDO::FETCH_ASSOC);
    if (!$semAnterior) {
        echo json_encode(['ok' => false, 'msg' => 'No hay semana anterior al rango']);
        exit();
    }
    $numSemAnt = (int) $semAnterior['numero_semana'];

    // ── SEMANAS DEL RANGO ────────────────────────────────────────────
    $r2 = $conn->prepare("SELECT numero_semana,fecha_inicio,fecha_fin FROM SemanasSistema WHERE numero_semana BETWEEN :d AND :h ORDER BY numero_semana");
    $r2->execute([':d' => $semDesde, ':h' => $semHasta]);
    $semsRango = $r2->fetchAll(PDO::FETCH_ASSOC);
    if (empty($semsRango)) {
        echo json_encode(['ok' => false, 'msg' => "Sin semanas {$semDesde}–{$semHasta}"]);
        exit();
    }
    $fechaIni = $semsRango[0]['fecha_inicio'];
    $fechaFin = end($semsRango)['fecha_fin'];

    // ── SUCURSALES ───────────────────────────────────────────────────
    $r3 = $conn->prepare("SELECT codigo,nombre FROM sucursales WHERE activa=1 AND sucursal=1 ORDER BY nombre");
    $r3->execute();
    $allSucs = $r3->fetchAll(PDO::FETCH_ASSOC);
    $sucNombres = [];
    foreach ($allSucs as $s)
        $sucNombres[(int) $s['codigo']] = $s['nombre'];
    $sucFiltro = !empty($sucsPost) ? $sucsPost : array_keys($sucNombres);

    // ── TODOS LOS PRODUCTOS BASE (presentacion_basica_inventario=1) ──
    $r4 = $conn->prepare("
        SELECT pp.id,pp.Nombre,pp.presentacion_receta,pp.Id_receta_producto,
               pp.categoria_insumo,pp.cantidad AS pp_cant,
               pp.id_producto_maestro AS id_maestro,
               pm.Nombre AS maestro,u.nombre AS unidad,u.id AS id_unidad
        FROM producto_presentacion pp
        LEFT JOIN producto_maestro pm ON pm.id=pp.id_producto_maestro
        LEFT JOIN unidad_producto u   ON u.id=pp.id_unidad_producto
        WHERE pp.presentacion_basica_inventario=1 AND pp.Activo='SI'
        ORDER BY pm.Nombre,pp.Nombre
    ");
    $r4->execute();
    $todosProductos = $r4->fetchAll(PDO::FETCH_ASSOC);
    $productosMeta = [];
    foreach ($todosProductos as $p)
        $productosMeta[(int) $p['id']] = $p;
    $idsBase = array_keys($productosMeta);
    // Índice: maestro → producto base (para resolución de presentación alternativa)
    $maestroToBase = [];
    foreach ($productosMeta as $pid => $pm) {
        $mid = (int) $pm['id_maestro'];
        if ($mid > 0) {
            // Si no existe, lo agregamos.
            // Si ya existe, pero el actual es insumo crudo (sin receta), sobreescribimos para priorizar el crudo.
            $esReceta = !empty($pm['Id_receta_producto']) && $pm['Id_receta_producto'] !== '0';
            if (!isset($maestroToBase[$mid]) || !$esReceta) {
                $maestroToBase[$mid] = ['base_pp_id' => $pid, 'base_unid' => (int) $pm['id_unidad'], 'base_cant' => max((float) $pm['pp_cant'], 0.001)];
            }
        }
    }

    // ── MAPA DE CASCADA (paquete → base) ─────────────────────────────
    // Producto receta con exactamente 1 componente base → es paquete
    $r5 = $conn->prepare("
        SELECT pp_pkg.id AS pkg_id, crp.id_presentacion_producto AS base_id, crp.cantidad AS factor
        FROM producto_presentacion pp_pkg
        INNER JOIN componentes_receta_producto crp ON crp.id_receta_producto_global = pp_pkg.Id_receta_producto
        INNER JOIN producto_presentacion pp_base    ON pp_base.id = crp.id_presentacion_producto
            AND pp_base.presentacion_basica_inventario = 1
        WHERE pp_pkg.presentacion_receta = 1
          AND pp_pkg.Activo = 'SI'
          AND (SELECT COUNT(DISTINCT id_presentacion_producto) FROM componentes_receta_producto WHERE id_receta_producto_global = pp_pkg.Id_receta_producto) = 1
    ");
    $r5->execute();
    $cascadeMap = []; // pkg_id => {base_id, factor}
    foreach ($r5->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cascadeMap[(int) $row['pkg_id']] = ['base_id' => (int) $row['base_id'], 'factor' => (float) $row['factor']];
    }

    // ── CONVERSIONES DE UNIDAD (cargado temprano para altMap) ────────
    $stmtConvEarly = $conn->prepare("SELECT id_unidad_producto_inicio AS i, id_unidad_producto_final AS f, cantidad AS fac FROM conversion_unidad_producto");
    $stmtConvEarly->execute();
    $convIndex = [];
    foreach ($stmtConvEarly->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $convIndex[(int) $c['i']][(int) $c['f']] = (float) $c['fac'];
        $convIndex[(int) $c['f']][(int) $c['i']] = $c['fac'] != 0 ? 1 / (float) $c['fac'] : 0;
    }

    // ── DICCIONARIO: CodCotizacion → pp (todos los productos activos) ─
    // Incluye: base, receta-paquete, Y presentaciones alternativas (misma unidad diferente)
    $r6 = $conn->prepare("
        SELECT d.CodCotizacion, pp.id AS pp_id,
               pp.presentacion_basica_inventario, pp.presentacion_receta,
               pp.id_unidad_producto AS pp_unid, pp.cantidad AS pp_cant,
               pp.id_producto_maestro AS id_maestro
        FROM diccionario_productos_legado d
        INNER JOIN producto_presentacion pp ON pp.id = d.id_producto_presentacion
        WHERE pp.Activo='SI'
    ");
    $r6->execute();
    $diccionario = []; // CodCotizacion => ['pp_id','pp_unid','pp_cant','id_maestro','es_base','es_receta']
    foreach ($r6->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $diccionario[(int) $row['CodCotizacion']] = [
            'pp_id' => (int) $row['pp_id'],
            'pp_unid' => (int) $row['pp_unid'],
            'pp_cant' => max((float) $row['pp_cant'], 0.001),
            'id_maestro' => (int) $row['id_maestro'],
            'es_base' => (bool) $row['presentacion_basica_inventario'],
            'es_receta' => (bool) $row['presentacion_receta'],
        ];
    }

    // ── MAPA DE PRESENTACIONES ALTERNATIVAS (misma unidad, conversión) ─
    // Producto NO base, NO receta, mismo maestro que un producto base → convertir vía convIndex
    $altMap = []; // alt_pp_id => ['base_id'=>int, 'factor'=>float]
    foreach ($diccionario as $dic) {
        $pp_id = $dic['pp_id'];
        if ($dic['es_base'] || $dic['es_receta'])
            continue;   // ya manejados
        if (isset($cascadeMap[$pp_id]))
            continue;   // ya en cascada
        if (isset($altMap[$pp_id]))
            continue;   // ya procesado
        $mid = $dic['id_maestro'];
        if (!$mid || !isset($maestroToBase[$mid]))
            continue;  // maestro sin base
        $base = $maestroToBase[$mid];
        $altUnid = $dic['pp_unid'];
        $basUnid = $base['base_unid'];
        if ($altUnid === $basUnid) {
            // Misma unidad, solo ajuste de cantidad
            $factor = $dic['pp_cant'] / $base['base_cant'];
        } elseif (isset($convIndex[$altUnid][$basUnid])) {
            $factor = ($dic['pp_cant'] * $convIndex[$altUnid][$basUnid]) / $base['base_cant'];
        } else {
            continue; // Sin conversión conocida
        }
        $altMap[$pp_id] = ['base_id' => $base['base_pp_id'], 'factor' => $factor];
    }

    // ── FUNCIÓN: resolver CodCotizacion a base_id + cantidad ─────────
    // Retorna [base_id, cantidad_convertida] o null si no hay mapeo
    // Orden de resolución:
    //   1. cascadeMap  → producto receta con 1 componente base (Ej: caja de 12)
    //   2. altMap      → presentación alternativa del mismo maestro (Ej: galón → litro)
    //   3. es_base     → producto base directo
    function resolverCodCot(int $cod, float $qty, array &$diccionario, array &$cascadeMap, array &$altMap): ?array
    {
        if (!isset($diccionario[$cod]))
            return null;
        $dic = $diccionario[$cod];
        $pp_id = $dic['pp_id'];
        if (isset($cascadeMap[$pp_id])) {
            return [$cascadeMap[$pp_id]['base_id'], $qty * $cascadeMap[$pp_id]['factor']];
        }
        if (isset($altMap[$pp_id])) {
            return [$altMap[$pp_id]['base_id'], $qty * $altMap[$pp_id]['factor']];
        }
        if ($dic['es_base'])
            return [$pp_id, $qty];
        return null; // producto sin resolución (otro tipo no contemplado)
    }

    // ── AGREGADO BALANCE ─────────────────────────────────────────────
    // $bal[base_id][suc] = [inv_inicial,ajuste,despacho,merma,inv_final]
    $bal = [];
    // Inicializar con todos los productos × sucursales filtradas
    foreach ($idsBase as $pid) {
        $bal[$pid] = [];
        foreach ($sucFiltro as $suc) {
            $bal[$pid][$suc] = ['inv_inicial' => 0, 'ajuste' => 0, 'despacho' => 0, 'compras' => 0, 'merma' => 0, 'inv_final' => 0];
        }
    }

    // Helper: aplicar kardex simple (inventario, ajuste, merma)
    function aplicarKardexSimple(
        PDO &$conn,
        string $tabla,
        string $pk,
        string $tipo,
        int $semDesde,
        int $semHasta,
        array $sucFiltro,
        array &$diccionario,
        array &$cascadeMap,
        array &$altMap,
        array &$bal
    ): void {
        $phSuc = implode(',', array_fill(0, count($sucFiltro), '?'));
        $stmt = $conn->prepare("
            SELECT k.CodCotizacion, k.Sucursal, SUM(k.Cantidad) AS total
            FROM `{$tabla}` k
            INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin
            WHERE ss.numero_semana BETWEEN ? AND ?
              AND k.Sucursal IN ({$phSuc})
            GROUP BY k.CodCotizacion, k.Sucursal
        ");
        $params = array_merge([$semDesde, $semHasta], $sucFiltro);
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $res = resolverCodCot((int) $row['CodCotizacion'], (float) $row['total'], $diccionario, $cascadeMap, $altMap);
            if (!$res)
                continue;
            [$bid, $qty] = $res;
            $suc = (int) $row['Sucursal'];
            if (isset($bal[$bid][$suc]))
                $bal[$bid][$suc][$tipo] += $qty;
        }
    }

    // Helper: inventario (semana fija = snapshot)
    function aplicarInventario(
        PDO &$conn,
        int $numSem,
        string $tipo,
        array $sucFiltro,
        array &$diccionario,
        array &$cascadeMap,
        array &$altMap,
        array &$bal
    ): void {
        $phSuc = implode(',', array_fill(0, count($sucFiltro), '?'));
        $stmt = $conn->prepare("
            SELECT k.CodCotizacion, k.Sucursal, SUM(k.Cantidad) AS total
            FROM msaccess_masivo_InventarioCotizacion k
            INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin
            WHERE ss.numero_semana = ?
              AND k.Sucursal IN ({$phSuc})
            GROUP BY k.CodCotizacion, k.Sucursal
        ");
        $params = array_merge([$numSem], $sucFiltro);
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $res = resolverCodCot((int) $row['CodCotizacion'], (float) $row['total'], $diccionario, $cascadeMap, $altMap);
            if (!$res)
                continue;
            [$bid, $qty] = $res;
            $suc = (int) $row['Sucursal'];
            if (isset($bal[$bid][$suc]))
                $bal[$bid][$suc][$tipo] += $qty;
        }
    }

    // ── INVENTARIO INICIAL (semana anterior) ─────────────────────────
    aplicarInventario($conn, $numSemAnt, 'inv_inicial', $sucFiltro, $diccionario, $cascadeMap, $altMap, $bal);

    // ── INVENTARIO FINAL (última semana del rango) ───────────────────
    aplicarInventario($conn, $semHasta, 'inv_final', $sucFiltro, $diccionario, $cascadeMap, $altMap, $bal);

    // ── AJUSTES ──────────────────────────────────────────────────────
    aplicarKardexSimple(
        $conn,
        'msaccess_masivo_AjustesInventario',
        'CodAjustesInventario',
        'ajuste',
        $semDesde,
        $semHasta,
        $sucFiltro,
        $diccionario,
        $cascadeMap,
        $altMap,
        $bal
    );

    // ── COMPRAS ───────────────────────────────────────────────────────
    aplicarKardexSimple(
        $conn,
        'msaccess_masivo_Compras',
        'CodIngresoAlmacen',
        'compras',
        $semDesde,
        $semHasta,
        $sucFiltro,
        $diccionario,
        $cascadeMap,
        $altMap,
        $bal
    );

    // ── MERMA ─────────────────────────────────────────────────────────
    aplicarKardexSimple(
        $conn,
        'msaccess_masivo_MermaCotizacion',
        'CodMermaUnidad',
        'merma',
        $semDesde,
        $semHasta,
        $sucFiltro,
        $diccionario,
        $cascadeMap,
        $altMap,
        $bal
    );

    // ── DESPACHO (PreIngreso × SubPreIngreso, filtro por Destino) ─────
    $stmtDesp = $conn->prepare("
        SELECT sub.CodCotizacion, pre.Destino, SUM(sub.Cantidad) AS total
        FROM msaccess_masivo_SubPreIngresosPitaya sub
        INNER JOIN msaccess_masivo_PreIngresoPitaya pre
            ON pre.CodPreIngresoPitaya = sub.CodPreIngresoPitaya
        INNER JOIN SemanasSistema ss ON pre.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin
        WHERE ss.numero_semana BETWEEN :d AND :h
          AND pre.Destino REGEXP '^[Pp]itaya[[:space:]]+[0-9]+'
        GROUP BY sub.CodCotizacion, pre.Destino
    ");
    $stmtDesp->execute([':d' => $semDesde, ':h' => $semHasta]);
    foreach ($stmtDesp->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!preg_match('/[Pp]itaya\s+(\d+)/', $row['Destino'], $m))
            continue;
        $suc = (int) $m[1];
        if (!in_array($suc, $sucFiltro))
            continue;
        $res = resolverCodCot((int) $row['CodCotizacion'], (float) $row['total'], $diccionario, $cascadeMap, $altMap);
        if (!$res)
            continue;
        [$bid, $qty] = $res;
        if (isset($bal[$bid][$suc]))
            $bal[$bid][$suc]['despacho'] += $qty;
    }

    // ── CONSUMO TEÓRICO (lógica P1/P2/P3 de dashboard_consumo) ───────
    $consumoTeorico = []; // [base_pp_id][suc] => float

    // Agregado de ventas × SubReceta
    $paramsSql = [':fd' => $fechaIni, ':fh' => $fechaFin, ':sd' => $semDesde, ':sh' => $semHasta];
    $whereSucV = '';
    if (!empty($sucsPost)) {
        $phV = [];
        foreach ($sucsPost as $i => $sv) {
            $k = ':sv' . $i;
            $phV[] = $k;
            $paramsSql[$k] = $sv;
        }
        $whereSucV = ' AND v.local IN (' . implode(',', $phV) . ')';
    }
    $sqlAgg = "
        SELECT v.local AS suc, sr.CodIngrediente AS cod_ing, sr.codporcion,
               SUM(v.Cantidad * sr.Cantidad) AS cant_total
        FROM VentasGlobalesAccessCSV v
        INNER JOIN SubReceta sr ON sr.CodBatido = v.CodProducto
        WHERE v.Anulado=0
          AND v.Fecha BETWEEN :fd AND :fh
          AND v.Semana BETWEEN :sd AND :sh
          AND v.CodProducto IS NOT NULL
          AND (sr.codporcion IS NULL OR sr.codporcion NOT IN (
              SELECT CodCotizacionPorcion FROM MezclaPorcionesAccess WHERE CodCotizacionPorcion IS NOT NULL
          ))
          {$whereSucV}
        GROUP BY v.local, sr.CodIngrediente, sr.codporcion
    ";
    $stmtAgg = $conn->prepare($sqlAgg);
    $stmtAgg->execute($paramsSql);
    $filasVenta = $stmtAgg->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($filasVenta)) {
        // Pre-cargar cotizaciones para P2/P3
        $codsIng = array_unique(array_column($filasVenta, 'cod_ing'));
        $phI = implode(',', array_fill(0, count($codsIng), '?'));
        $stmtCot = $conn->prepare("
            SELECT CodIngrediente,CodCotizacion,Conversion,Prioridad
            FROM Cotizaciones
            WHERE CodIngrediente IN ({$phI})
              AND (Subproducto IS NULL OR Subproducto!=1)
              AND (Marca IS NULL OR Marca!='Almacen Global')
            ORDER BY CodIngrediente,Conversion DESC,Prioridad ASC
        ");
        $stmtCot->execute(array_values($codsIng));
        $cotMap = [];
        foreach ($stmtCot->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $ci = $c['CodIngrediente'];
            if (!isset($cotMap[$ci]))
                $cotMap[$ci] = ['p2' => null, 'p3' => null];
            if ($c['Conversion'] == 1 && $c['Prioridad'] == 1 && !$cotMap[$ci]['p2'])
                $cotMap[$ci]['p2'] = $c['CodCotizacion'];
            if (!$cotMap[$ci]['p3'])
                $cotMap[$ci]['p3'] = $c['CodCotizacion'];
        }
        // Pre-cargar ingredientes para unidades
        $stmtIng = $conn->prepare("SELECT CodIngrediente,Unidad FROM DBIngredientes WHERE CodIngrediente IN ({$phI})");
        $stmtIng->execute(array_values($codsIng));
        $dbIng = [];
        foreach ($stmtIng->fetchAll(PDO::FETCH_ASSOC) as $row)
            $dbIng[$row['CodIngrediente']] = $row;
        // Pre-cargar diccionario para consumo
        // Paso A: mapeo directo (presentacion_basica_inventario = 1)
        $diccionarioConsumo = [];
        $r7 = $conn->prepare("
            SELECT d.CodCotizacion,pp.id AS pp_id,pp.cantidad AS pp_cant,
                   pp.id_unidad_producto AS id_unid,pp.Id_receta_producto,
                   pp.id_producto_maestro AS id_mae
            FROM diccionario_productos_legado d
            INNER JOIN producto_presentacion pp ON pp.id=d.id_producto_presentacion
            WHERE pp.Activo='SI' AND pp.presentacion_basica_inventario=1
        ");
        $r7->execute();
        foreach ($r7->fetchAll(PDO::FETCH_ASSOC) as $row)
            $diccionarioConsumo[(int) $row['CodCotizacion']] = $row;

        // Paso B: rastreo AUTO por maestro — replica el comportamiento "AUTO" del visor de recetas.
        // Para los CodCotizacion de P1/P2/P3 que NO quedaron en el Paso A
        // (su presentación mapeada en el diccionario es de despacho u otro tipo sin basica_inventario),
        // obtenemos el id_producto_maestro de esa presentación y buscamos la presentación básica
        // del mismo maestro (ej: el pote 1.36kg → maestro Chocolate → oz con basica_inventario=1).
        $todosCodsVenta = array_unique(array_filter(array_merge(
            array_column($filasVenta, 'codporcion'),
            array_column($cotMap, 'p2'),
            array_column($cotMap, 'p3')
        )));
        $codsNoResueltos = array_values(array_filter($todosCodsVenta, function ($c) use (&$diccionarioConsumo) {
            return $c !== null && $c !== '' && !isset($diccionarioConsumo[(int) $c]);
        }));
        if (!empty($codsNoResueltos)) {
            $phNR2 = implode(',', array_fill(0, count($codsNoResueltos), '?'));
            $stmtAuto2 = $conn->prepare("
                SELECT
                    d.CodCotizacion,
                    pp_base.id               AS pp_id,
                    pp_base.cantidad         AS pp_cant,
                    pp_base.id_unidad_producto AS id_unid,
                    pp_base.Id_receta_producto,
                    pp_base.id_producto_maestro AS id_mae
                FROM diccionario_productos_legado d
                INNER JOIN producto_presentacion pp_orig ON pp_orig.id = d.id_producto_presentacion
                INNER JOIN producto_presentacion pp_base
                        ON pp_base.id_producto_maestro = pp_orig.id_producto_maestro
                       AND pp_base.presentacion_basica_inventario = 1
                       AND pp_base.Activo = 'SI'
                       AND pp_base.Id_receta_producto IS NULL
                WHERE d.CodCotizacion IN ($phNR2)
                  AND pp_orig.Activo = 'SI'
                  AND pp_orig.id_producto_maestro IS NOT NULL
                GROUP BY d.CodCotizacion
            ");
            $stmtAuto2->execute(array_values($codsNoResueltos));
            foreach ($stmtAuto2->fetchAll(PDO::FETCH_ASSOC) as $row)
                $diccionarioConsumo[(int) $row['CodCotizacion']] = $row;
        }

        // Paso C: fallback via CodIngrediente en Cotizaciones.
        // Cubre productos donde pp_orig.id_producto_maestro es NULL (ej: Mani 1lb).
        // Traza: CodCotizacion -> CodIngrediente -> todas sus cotizaciones -> diccionario
        //        -> cualquier presentacion con maestro -> presentacion basica del mismo maestro.
        $codsSinResolverC = array_values(array_filter($todosCodsVenta, function ($c) use (&$diccionarioConsumo) {
            return $c !== null && $c !== '' && !isset($diccionarioConsumo[(int) $c]);
        }));
        if (!empty($codsSinResolverC)) {
            $phC2 = implode(',', array_fill(0, count($codsSinResolverC), '?'));
            $stmtC2 = $conn->prepare("
                SELECT
                    c_src.CodCotizacion      AS CodCotizacion,
                    pp_base.id               AS pp_id,
                    pp_base.cantidad         AS pp_cant,
                    pp_base.id_unidad_producto AS id_unid,
                    pp_base.Id_receta_producto,
                    pp_base.id_producto_maestro AS id_mae
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
                       AND pp_base.Id_receta_producto IS NULL
                WHERE c_src.CodCotizacion IN ($phC2)
                GROUP BY c_src.CodCotizacion
            ");
            $stmtC2->execute(array_values($codsSinResolverC));
            foreach ($stmtC2->fetchAll(PDO::FETCH_ASSOC) as $row)
                $diccionarioConsumo[(int) $row['CodCotizacion']] = $row;
        }
        // Unidades + conversiones
        $stmtU = $conn->prepare("SELECT id,nombre,abreviado,nombres_opcionales FROM unidad_producto");
        $stmtU->execute();
        $uPorNom = [];
        foreach ($stmtU->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $uid = (int) $u['id'];
            $uPorNom[strtolower(trim($u['nombre']))] = $uid;
            if ($u['abreviado'])
                $uPorNom[strtolower(trim($u['abreviado']))] = $uid;
            if (!empty($u['nombres_opcionales']))
                foreach (preg_split('/[,;|]+/', $u['nombres_opcionales']) as $al) {
                    $ak = strtolower(trim($al));
                    if ($ak)
                        $uPorNom[$ak] = $uid;
                }
        }
        // Reutilizar $convIndex ya cargado al inicio (no recargar)
        $convIdx = &$convIndex;
        // Presentaciones por maestro
        $idsMaestros = array_unique(array_filter(array_column($diccionarioConsumo, 'id_mae')));
        $ppPorMaestro = [];
        if (!empty($idsMaestros)) {
            $phM = implode(',', array_fill(0, count($idsMaestros), '?'));
            $stmtPP = $conn->prepare("SELECT pp.id,pp.id_producto_maestro AS mae,pp.cantidad AS pp_cant,pp.id_unidad_producto AS id_u FROM producto_presentacion pp WHERE pp.id_producto_maestro IN({$phM}) AND pp.Id_receta_producto IS NULL AND pp.Activo='SI' AND pp.presentacion_basica_inventario=1");
            $stmtPP->execute(array_values($idsMaestros));
            foreach ($stmtPP->fetchAll(PDO::FETCH_ASSOC) as $pp)
                $ppPorMaestro[(int) $pp['mae']][(int) $pp['id_u']] = $pp;
        }
        // Loop resolución P1/P2/P3
        foreach ($filasVenta as $fila) {
            $codIng = $fila['cod_ing'];
            $codPor = $fila['codporcion'];
            $suc = (int) $fila['suc'];
            $cantTotal = (float) $fila['cant_total'];
            $mapeo = null;
            $esP1 = false;
            if (!empty($codPor) && isset($diccionarioConsumo[(int) $codPor])) {
                $mapeo = $diccionarioConsumo[(int) $codPor];
                $esP1 = true;
            }
            if (!$mapeo && isset($cotMap[$codIng]['p2'])) {
                $ck = (int) $cotMap[$codIng]['p2'];
                if (isset($diccionarioConsumo[$ck]))
                    $mapeo = $diccionarioConsumo[$ck];
            }
            if (!$mapeo && isset($cotMap[$codIng]['p3'])) {
                $ck = (int) $cotMap[$codIng]['p3'];
                if (isset($diccionarioConsumo[$ck]))
                    $mapeo = $diccionarioConsumo[$ck];
            }
            if (!$mapeo)
                continue;
            $esGlobal = !empty($mapeo['Id_receta_producto']);
            $idPP = (int) $mapeo['pp_id'];
            $ppCant = max((float) $mapeo['pp_cant'], 0.001);
            $idUnidERP = (int) $mapeo['id_unid'];
            if ($esGlobal) {
                $consumido = $cantTotal;
            } else {
                $unAcc = $dbIng[$codIng]['Unidad'] ?? '';
                $idUnAcc = isset($uPorNom[strtolower(trim($unAcc))]) ? $uPorNom[strtolower(trim($unAcc))] : null;
                $factor = 1.0;
                if ($idUnAcc && $idUnAcc !== $idUnidERP) {
                    if (isset($convIdx[$idUnAcc][$idUnidERP])) {
                        $factor = $convIdx[$idUnAcc][$idUnidERP];
                    } elseif (isset($ppPorMaestro[(int) $mapeo['id_mae']][$idUnAcc])) {
                        $ppAlt = $ppPorMaestro[(int) $mapeo['id_mae']][$idUnAcc];
                        $idPP = (int) $ppAlt['id'];
                        $ppCant = max((float) $ppAlt['pp_cant'], 0.001);
                    } elseif (isset($convIdx[$idUnAcc])) {
                        foreach ($convIdx[$idUnAcc] as $idD => $fac) {
                            if (isset($ppPorMaestro[(int) $mapeo['id_mae']][$idD])) {
                                $ppC = $ppPorMaestro[(int) $mapeo['id_mae']][$idD];
                                $idPP = (int) $ppC['id'];
                                $ppCant = max((float) $ppC['pp_cant'], 0.001);
                                $factor = $fac;
                                break;
                            }
                        }
                    }
                }
                $consumido = ($cantTotal * $factor) / $ppCant;
                if ($esP1)
                    $consumido = round($consumido * 2) / 2;
            }
            if (!isset($consumoTeorico[$idPP]))
                $consumoTeorico[$idPP] = [];
            if (!isset($consumoTeorico[$idPP][$suc]))
                $consumoTeorico[$idPP][$suc] = 0;
            $consumoTeorico[$idPP][$suc] += $consumido;
        }
    }

    // ── CONSTRUIR RESPUESTA ───────────────────────────────────────────
    $resultado = [];
    foreach ($idsBase as $pid) {
        $meta = $productosMeta[$pid];
        $porSuc = [];
        $totales = ['inv_inicial' => 0, 'ajuste' => 0, 'despacho' => 0, 'compras' => 0, 'merma' => 0, 'inv_final' => 0, 'consumo_real' => 0, 'consumo_teorico' => 0, 'varianza' => 0];
        foreach ($sucFiltro as $suc) {
            $b = $bal[$pid][$suc] ?? ['inv_inicial' => 0, 'ajuste' => 0, 'despacho' => 0, 'compras' => 0, 'merma' => 0, 'inv_final' => 0];
            $cr = round($b['inv_inicial'] + $b['ajuste'] + $b['despacho'] + $b['compras'] - $b['merma'] - $b['inv_final'], 4);
            $ct = round($consumoTeorico[$pid][$suc] ?? 0, 4);
            $var = round($cr - $ct, 4);
            $pct = ($ct != 0) ? round($var / $ct * 100, 2) : null;
            $porSuc[$suc] = [
                'inv_inicial' => round($b['inv_inicial'], 4),
                'ajuste' => round($b['ajuste'], 4),
                'despacho' => round($b['despacho'], 4),
                'compras' => round($b['compras'], 4),
                'merma' => round($b['merma'], 4),
                'inv_final' => round($b['inv_final'], 4),
                'consumo_real' => $cr,
                'consumo_teorico' => $ct,
                'varianza' => $var,
                'pct_varianza' => $pct,
            ];
            $totales['inv_inicial'] += $b['inv_inicial'];
            $totales['ajuste'] += $b['ajuste'];
            $totales['despacho'] += $b['despacho'];
            $totales['compras'] += $b['compras'];
            $totales['merma'] += $b['merma'];
            $totales['inv_final'] += $b['inv_final'];
            $totales['consumo_real'] += $cr;
            $totales['consumo_teorico'] += $ct;
            $totales['varianza'] += $var;
        }
        foreach ($totales as $k => $v)
            $totales[$k] = round($v, 4);
        $totales['pct_varianza'] = $totales['consumo_teorico'] != 0 ? round($totales['varianza'] / $totales['consumo_teorico'] * 100, 2) : null;
        $resultado[] = [
            'id' => $pid,
            'nombre' => $meta['Nombre'],
            'maestro' => $meta['maestro'],
            'unidad' => $meta['unidad'],
            'categoria' => $meta['categoria_insumo'],
            'por_sucursal' => $porSuc,
            'totales' => $totales,
        ];
    }

    // Sucursales presentes
    $sucPresentes = [];
    foreach ($sucFiltro as $s)
        $sucPresentes[] = ['codigo' => $s, 'nombre' => $sucNombres[$s] ?? $s];

    echo json_encode([
        'ok' => true,
        'productos' => $resultado,
        'sucursales' => $sucPresentes,
        'semanas' => $semsRango,
        'semana_anterior' => $semAnterior,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
}
