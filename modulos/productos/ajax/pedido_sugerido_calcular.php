<?php
/* ============================================================
   AJAX: Calcular pedido sugerido
   modulos/productos/ajax/pedido_sugerido_calcular.php

   Cadena de fórmulas:
   1. consumo_por_semana (VentasGlobalesAccessCSV × SubReceta)
   2. promedio = SUM / N
   3. desv_estandar_muestra (N-1)
   4. cons_semanal = prom + desv
   5. cons_diario = (cons_sem * (1 + ajuste)) / 7
   ...
   ============================================================ */
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');
set_time_limit(0);

$usuario = obtenerUsuarioActual();
if (!tienePermiso('pedido_sugerido', 'vista', $usuario['CodNivelesCargos'])) {
    echo json_encode(['ok' => false, 'msg' => 'Sin permiso para calcular.']);
    exit();
}

$numDesde    = isset($_POST['semana_desde_num']) ? (int)$_POST['semana_desde_num'] : 0;
$numHasta    = isset($_POST['semana_hasta_num']) ? (int)$_POST['semana_hasta_num'] : 0;
$codSucursal = trim($_POST['cod_sucursal'] ?? '');

if (!$numDesde || !$numHasta || !$codSucursal) {
    echo json_encode(['ok' => false, 'msg' => 'Error: Faltan parámetros (semanas o sucursal) para el cálculo.']);
    exit();
}

$numDesde = min($numDesde, $numHasta);
$numHasta = max($numDesde, $numHasta);
$nSemanas = $numHasta - $numDesde + 1;

function desviacionEstandarMuestra(array $valores): float {
    $n = count($valores);
    if ($n <= 1) return 0.0;
    $media = array_sum($valores) / $n;
    $varianza = array_sum(array_map(fn($v) => ($v - $media) ** 2, $valores)) / ($n - 1);
    return sqrt($varianza);
}

function resolverUnidadId_PS(string $nombre, array &$unidadPorNombre): ?int {
    $k = strtolower(trim($nombre));
    return $unidadPorNombre[$k] ?? null;
}

function resolverFactorConversion_PS(int $idOrigen, int $idDestino, array &$convIndex): ?float {
    if ($idOrigen === $idDestino) return 1.0;
    return $convIndex[$idOrigen][$idDestino] ?? null;
}

function buscarPresentacionEnMaestro_PS(int $idMaestro, int $idUnidad, array &$presentPorMaestro): ?array {
    return $presentPorMaestro[$idMaestro][$idUnidad] ?? null;
}

try {
    // 1. Rango de fechas
    $stmtR = $conn->prepare("SELECT MIN(fecha_inicio) as f1, MAX(fecha_fin) as f2 FROM SemanasSistema WHERE numero_semana BETWEEN ? AND ?");
    $stmtR->execute([$numDesde, $numHasta]);
    $r = $stmtR->fetch();
    if (!$r || !$r['f1']) {
        echo json_encode(['ok' => false, 'msg' => 'Rango de semanas no encontrado.']);
        exit();
    }

    // 2. Ventas Agregadas
    $sql = "SELECT v.Semana as sem, sr.CodIngrediente as cod_ing, sr.codporcion, SUM(v.Cantidad * sr.Cantidad) as cant
            FROM VentasGlobalesAccessCSV v
            INNER JOIN SubReceta sr ON sr.CodBatido = v.CodProducto
            WHERE v.Anulado = 0 AND v.local = ? AND v.Semana BETWEEN ? AND ? AND v.Fecha BETWEEN ? AND ?
            GROUP BY v.Semana, sr.CodIngrediente, sr.codporcion";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$codSucursal, $numDesde, $numHasta, $r['f1'], $r['f2']]);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($filas)) {
        echo json_encode(['ok' => true, 'productos' => [], 'n_semanas' => $nSemanas, 'factor_congelados' => null, 'capacidad_congelados' => null]);
        exit();
    }

    // 3. Pre-cargar mapeos (Bulk)
    $codsIng = array_unique(array_column($filas, 'cod_ing'));
    $ph = implode(',', array_fill(0, count($codsIng), '?'));

    $dbIng = [];
    $stmtI = $conn->prepare("SELECT CodIngrediente, Nombre, Unidad FROM DBIngredientes WHERE CodIngrediente IN ($ph)");
    $stmtI->execute(array_values($codsIng));
    foreach ($stmtI->fetchAll() as $row) $dbIng[$row['CodIngrediente']] = $row;

    $cotMap = [];
    $stmtC = $conn->prepare("SELECT CodIngrediente, CodCotizacion, Conversion, Prioridad FROM Cotizaciones WHERE CodIngrediente IN ($ph) AND (Subproducto IS NULL OR Subproducto!=1) AND (Marca IS NULL OR Marca!='Almacen Global') ORDER BY Conversion DESC, Prioridad ASC");
    $stmtC->execute(array_values($codsIng));
    foreach ($stmtC->fetchAll() as $c) {
        $ci = $c['CodIngrediente'];
        if (!isset($cotMap[$ci])) $cotMap[$ci] = ['p2'=>null, 'p3'=>null];
        if ($c['Conversion']==1 && $c['Prioridad']==1 && !$cotMap[$ci]['p2']) $cotMap[$ci]['p2'] = $c['CodCotizacion'];
        if (!$cotMap[$ci]['p3']) $cotMap[$ci]['p3'] = $c['CodCotizacion'];
    }

    $codCotBuscar = array_unique(array_filter(array_merge(array_column($filas, 'codporcion'), array_column($cotMap, 'p2'), array_column($cotMap, 'p3'))));
    $codCotBuscar = array_values(array_filter($codCotBuscar, fn($v) => $v !== null && $v !== ''));

    $diccionarioMap = [];
    if (!empty($codCotBuscar)) {
        $phC = implode(',', array_fill(0, count($codCotBuscar), '?'));

        // Paso A: resolución directa — presentacion_basica_inventario = 1
        $stmtD = $conn->prepare("
            SELECT d.CodCotizacion,
                   pp.id                  AS id,
                   pp.cantidad            AS pp_cant,
                   pp.Id_receta_producto,
                   pp.id_producto_maestro AS id_m,
                   pp.Nombre              AS n,
                   pp.categoria_insumo    AS cat,
                   pp.presentacion        AS presentacion,
                   u.id                   AS uid,
                   u.abreviado            AS uab,
                   pm.Nombre              AS mn
            FROM diccionario_productos_legado d
            INNER JOIN producto_presentacion pp ON pp.id = d.id_producto_presentacion
            LEFT  JOIN unidad_producto u        ON u.id  = pp.id_unidad_producto
            LEFT  JOIN producto_maestro pm      ON pm.id = pp.id_producto_maestro
            WHERE d.CodCotizacion IN ($phC)
              AND pp.Activo = 'SI'
              AND pp.presentacion_basica_inventario = 1
        ");
        $stmtD->execute(array_values($codCotBuscar));
        foreach ($stmtD->fetchAll() as $row)
            $diccionarioMap[(string)$row['CodCotizacion']] = $row;

        // Paso B: rastreo por maestro de la presentación mapeada.
        // Cubre el caso donde el CodCotizacion mapea a una presentación de despacho/compra
        // (ej: Pote Chocolate 1.36kg) que tiene id_producto_maestro pero no es la básica.
        $sinResolverB = array_values(array_filter($codCotBuscar,
            fn($c) => !isset($diccionarioMap[(string)$c])
        ));
        if (!empty($sinResolverB)) {
            $phB = implode(',', array_fill(0, count($sinResolverB), '?'));
            $stmtB = $conn->prepare("
                SELECT d.CodCotizacion,
                       pp_base.id                  AS id,
                       pp_base.cantidad            AS pp_cant,
                       pp_base.Id_receta_producto,
                       pp_base.id_producto_maestro AS id_m,
                       pp_base.Nombre              AS n,
                       pp_base.categoria_insumo    AS cat,
                       pp_base.presentacion        AS presentacion,
                       u_base.id                   AS uid,
                       u_base.abreviado            AS uab,
                       pm.Nombre                   AS mn
                FROM diccionario_productos_legado d
                INNER JOIN producto_presentacion pp_orig
                        ON pp_orig.id = d.id_producto_presentacion
                INNER JOIN producto_presentacion pp_base
                        ON pp_base.id_producto_maestro = pp_orig.id_producto_maestro
                       AND pp_base.presentacion_basica_inventario = 1
                       AND pp_base.Activo = 'SI'
                       AND pp_base.Id_receta_producto IS NULL
                LEFT  JOIN unidad_producto u_base ON u_base.id = pp_base.id_unidad_producto
                LEFT  JOIN producto_maestro pm    ON pm.id = pp_base.id_producto_maestro
                WHERE d.CodCotizacion IN ($phB)
                  AND pp_orig.Activo = 'SI'
                  AND pp_orig.id_producto_maestro IS NOT NULL
                GROUP BY d.CodCotizacion
            ");
            $stmtB->execute(array_values($sinResolverB));
            foreach ($stmtB->fetchAll() as $row)
                $diccionarioMap[(string)$row['CodCotizacion']] = $row;
        }

        // Paso C: fallback vía CodIngrediente → todas sus cotizaciones → maestro → básica.
        // Cubre casos donde pp_orig.id_producto_maestro es NULL.
        $sinResolverC = array_values(array_filter($codCotBuscar,
            fn($c) => !isset($diccionarioMap[(string)$c])
        ));
        if (!empty($sinResolverC)) {
            $phNR = implode(',', array_fill(0, count($sinResolverC), '?'));
            $stmtPC = $conn->prepare("
                SELECT c_src.CodCotizacion,
                       pp_base.id                  AS id,
                       pp_base.cantidad            AS pp_cant,
                       pp_base.Id_receta_producto,
                       pp_base.id_producto_maestro AS id_m,
                       pp_base.Nombre              AS n,
                       pp_base.categoria_insumo    AS cat,
                       pp_base.presentacion        AS presentacion,
                       u_base.id                   AS uid,
                       u_base.abreviado            AS uab,
                       pm.Nombre                   AS mn
                FROM Cotizaciones c_src
                INNER JOIN Cotizaciones c_all   ON c_all.CodIngrediente = c_src.CodIngrediente
                INNER JOIN diccionario_productos_legado d2
                        ON d2.CodCotizacion = c_all.CodCotizacion
                INNER JOIN producto_presentacion pp_any
                        ON pp_any.id = d2.id_producto_presentacion
                       AND pp_any.Activo = 'SI'
                       AND pp_any.id_producto_maestro IS NOT NULL
                INNER JOIN producto_presentacion pp_base
                        ON pp_base.id_producto_maestro = pp_any.id_producto_maestro
                       AND pp_base.presentacion_basica_inventario = 1
                       AND pp_base.Activo = 'SI'
                       AND pp_base.Id_receta_producto IS NULL
                LEFT  JOIN unidad_producto u_base ON u_base.id = pp_base.id_unidad_producto
                LEFT  JOIN producto_maestro pm    ON pm.id = pp_base.id_producto_maestro
                WHERE c_src.CodCotizacion IN ($phNR)
                GROUP BY c_src.CodCotizacion
            ");
            $stmtPC->execute(array_values($sinResolverC));
            foreach ($stmtPC->fetchAll() as $row)
                $diccionarioMap[(string)$row['CodCotizacion']] = $row;
        }
    }

    $unidadPorNombre = []; $unidadPorId = [];
    foreach ($conn->query("SELECT id, nombre, abreviado, nombres_opcionales FROM unidad_producto")->fetchAll() as $u) {
        $uid = (int)$u['id']; $unidadPorId[$uid] = $u;
        $unidadPorNombre[strtolower(trim($u['nombre']))] = $uid;
        if ($u['abreviado']) $unidadPorNombre[strtolower(trim($u['abreviado']))] = $uid;
        if ($u['nombres_opcionales']) foreach (preg_split('/[,;|]+/', $u['nombres_opcionales']) as $a) if ($ak=strtolower(trim($a))) $unidadPorNombre[$ak] = $uid;
    }

    $convIndex = [];
    foreach ($conn->query("SELECT id_unidad_producto_inicio as i, id_unidad_producto_final as f, cantidad as c FROM conversion_unidad_producto")->fetchAll() as $c) {
        $convIndex[(int)$c['i']][(int)$c['f']] = (float)$c['c'];
        $convIndex[(int)$c['f']][(int)$c['i']] = $c['c']!=0 ? 1/$c['c'] : 0;
    }

    $presentPorMaestro = [];
    $idMs = array_unique(array_filter(array_column($diccionarioMap, 'id_m')));
    if (!empty($idMs)) {
        $phM = implode(',', array_fill(0, count($idMs), '?'));
        $stmtPP = $conn->prepare("SELECT id, id_producto_maestro, cantidad, id_unidad_producto, presentacion FROM producto_presentacion WHERE id_producto_maestro IN ($phM) AND Id_receta_producto IS NULL AND Activo='SI' AND presentacion_basica_inventario = 1");
        $stmtPP->execute(array_values($idMs));
        foreach ($stmtPP->fetchAll() as $pp) {
             $presentPorMaestro[(int)$pp['id_producto_maestro']][(int)$pp['id_unidad_producto']] = $pp;
        }
    }

    // 4. Proceso de Consumo
    $conAgg = []; $metaPP = [];
    foreach ($filas as $f) {
        $ci = $f['cod_ing']; $cp = $f['codporcion']; $sem = (int)$f['sem']; $cant = (float)$f['cant'];
        $m = null; $esP1 = false;
        if (!empty($cp) && isset($diccionarioMap[(string)$cp])) { $m=$diccionarioMap[(string)$cp]; $esP1=true; }
        if (!$m && isset($cotMap[$ci]['p2']) && isset($diccionarioMap[(string)$cotMap[$ci]['p2']])) $m=$diccionarioMap[(string)$cotMap[$ci]['p2']];
        if (!$m && isset($cotMap[$ci]['p3']) && isset($diccionarioMap[(string)$cotMap[$ci]['p3']])) $m=$diccionarioMap[(string)$cotMap[$ci]['p3']];
        if (!$m) continue;

        $idPP = (int)$m['id']; $ppC = max((float)$m['pp_cant'], 0.001); $uidERP = (int)$m['uid'];
        $presentacionFinal = $m['presentacion'] ?? null; // valor por defecto del mapeo principal
        if ($m['Id_receta_producto']) { $cons = $cant; } else {
            $uAcc = $dbIng[$ci]['Unidad'] ?? ''; $uidAcc = resolverUnidadId_PS($uAcc, $unidadPorNombre); $fac = 1.0;
            if ($uidAcc && $uidAcc !== $uidERP) {
                $fDir = resolverFactorConversion_PS($uidAcc, $uidERP, $convIndex);
                if ($fDir) $fac=$fDir; else {
                    $alt = buscarPresentacionEnMaestro_PS((int)$m['id_m'], $uidAcc, $presentPorMaestro);
                    if ($alt) {
                        $idPP = (int)$alt['id'];
                        $ppC  = max((float)$alt['cantidad'], 0.001);
                        $uidERP = (int)$alt['id_unidad_producto'];
                        $fac  = 1.0;
                        // Usar la presentacion de la presentacion alternativa si la tiene
                        $presentacionFinal = $alt['presentacion'] ?? $presentacionFinal;
                    }
                }
            }
            $cons = ($cant * $fac) / $ppC;
            if ($esP1) $cons = round($cons * 2) / 2;
        }
        if (!isset($metaPP[$idPP])) $metaPP[$idPP] = ['n'=>$m['n'], 'u'=>$presentacionFinal ?? ($unidadPorId[$uidERP]['abreviado'] ?? $m['uab']), 'cat'=>$m['cat']];
        $conAgg[$idPP][$sem] = ($conAgg[$idPP][$sem] ?? 0) + $cons;
    }

    // 5. Config Logística
    $stmtS = $conn->prepare("SELECT dias_stock_minimo, capacidad_congelados FROM configuracion_logistica_sucursal WHERE cod_sucursal = ?");
    $stmtS->execute([$codSucursal]);
    $cS = $stmtS->fetch();
    $dSM = $cS ? (float)$cS['dias_stock_minimo'] : 0;
    $capC = $cS ? (float)$cS['capacidad_congelados'] : null;

    $stmtP = $conn->prepare("SELECT codigo_insumo, dias_ciclo, dias_desfase, ajuste_demanda FROM configuracion_logistica_producto WHERE cod_sucursal = ?");
    $stmtP->execute([$codSucursal]);
    $cPs = []; foreach($stmtP->fetchAll() as $row) $cPs[$row['codigo_insumo']] = $row;

    // 6. Inventario
    $stmtV = $conn->prepare("SELECT i.id_producto_presentacion, i.cantidad FROM inventario i INNER JOIN (SELECT id_producto_presentacion, MAX(id) as mid FROM inventario WHERE cod_sucursal=? GROUP BY id_producto_presentacion) l ON i.id=l.mid");
    $stmtV->execute([$codSucursal]);
    $invA = []; foreach($stmtV->fetchAll() as $row) $invA[(int)$row['id_producto_presentacion']] = (int)$row['cantidad'];

    // 7. Cálculos finales
    $res = []; $sumB = 0;
    foreach ($conAgg as $idP => $sems) {
        $vals = []; for($s=$numDesde;$s<=$numHasta;$s++) $vals[] = (float)($sems[$s]??0);

        // ── Ventana Activa con umbral relativo ───────────────────────────────
        // Paso 1: media de semanas con consumo estrictamente > 0
        $nonZeroVals = array_filter($vals, fn($v) => $v > 0);
        if (empty($nonZeroVals)) continue; // Sin consumo real → descartar producto
        $meanNonZero = array_sum($nonZeroVals) / count($nonZeroVals);

        // Umbral: 10% de la media real (mín. 0.01 para productos de muy bajo volumen).
        // Valores por debajo del umbral en los extremos se tratan como ceros estructurales
        // (artefactos de redondeo, conversión, cambio de insumo).
        $umbral = max(0.01, $meanNonZero * 0.10);

        // Paso 2: detectar ventana activa con umbral relativo
        $firstIdx = null; $lastIdx = null;
        foreach ($vals as $i => $v) {
            if ($v >= $umbral) {
                if ($firstIdx === null) $firstIdx = $i;
                $lastIdx = $i;
            }
        }
        if ($firstIdx === null) continue; // Todo por debajo del umbral → descartar

        $nActiva    = $lastIdx - $firstIdx + 1;
        $valsActivo = array_slice($vals, $firstIdx, $nActiva);
        $prom = array_sum($valsActivo) / $nActiva;
        $desv = desviacionEstandarMuestra($valsActivo);
        $semC = $prom + $desv;
        $m = $metaPP[$idP]; $cat = $m['cat']; $cP = $cat ? ($cPs[$cat] ?? null) : null;
        $adj = $cP ? (float)$cP['ajuste_demanda'] : 0; $dC = $cP ? (float)$cP['dias_ciclo'] : 0; $dD = $cP ? (float)$cP['dias_desfase'] : 0;
        $diaC = ($semC * (1+$adj)) / 7; $sMin = $diaC * $dSM; $sMax = $diaC * ($dC + $dD + $dSM);
        if ($cat === 'B') $sumB += $sMax;
        $res[$idP] = [
            'id_pp'=>$idP, 'nombre'=>$m['n'], 'unidad'=>$m['u'], 'categoria_insumo'=>$cat,
            'prom_consumo'=>round($prom,4), 'desv_estandar'=>round($desv,4), 'cons_semanal'=>round($semC,4),
            'ajuste_demanda'=>$adj, 'dias_ciclo'=>$dC, 'dias_desfase'=>$dD, 'dias_stock_min'=>$dSM,
            'cons_diario'=>round($diaC,6), 'stock_minimo'=>round($sMin,4), 'stock_maximo'=>round($sMax,4),
            'stock_max_final'=>null, 'es_ajustado'=>false, 'inventario_actual'=>$invA[$idP]??null, 'pedido_sugerido'=>null, '_tc'=>$cP!==null
        ];
    }

    // ── Despacho factor por id_pp ─────────────────────────────
    // Idéntico a inventario_get_data.php (sección 8.5):
    // Caso A: presentación de despacho del mismo producto_maestro.
    // Caso B: receta de despacho que contiene la presentación básica.
    $idsPP    = array_keys($res);
    $despFMap = []; // id_pp => ['factor'=>float, 'nombre'=>string, 'unidad'=>string]
    if (!empty($idsPP)) {
        $phPP = implode(',', array_fill(0, count($idsPP), '?'));

        // ── Paso B primero: receta-paquete (presentación de despacho cuyo único componente es la presentación básica)
        // Si existe un paquete configurado explícitamente, ese debe tener prioridad sobre cualquier
        // presentación de despacho genérica por maestro.
        $stmtDB = $conn->prepare("
            SELECT crp.id_presentacion_producto AS id_pp,
                   crp.cantidad                 AS d_receta_cant,
                   ppd.Nombre                   AS d_nombre,
                   ud.abreviado                 AS d_unidad
            FROM producto_presentacion ppd
            INNER JOIN componentes_receta_producto crp
                   ON crp.id_receta_producto_global = ppd.Id_receta_producto
            LEFT  JOIN unidad_producto ud ON ud.id = ppd.id_unidad_producto
            WHERE ppd.presentacion_despacho = 1 AND ppd.Activo = 'SI'
              AND crp.id_presentacion_producto IN ($phPP)
              AND (
                  SELECT COUNT(DISTINCT crp2.id_presentacion_producto)
                  FROM componentes_receta_producto crp2
                  WHERE crp2.id_receta_producto_global = ppd.Id_receta_producto
              ) = 1
            GROUP BY crp.id_presentacion_producto
            ORDER BY ppd.id ASC
        ");
        $stmtDB->execute(array_values($idsPP));
        foreach ($stmtDB->fetchAll(PDO::FETCH_ASSOC) as $row)
            $despFMap[(int)$row['id_pp']] = ['factor' => (float)$row['d_receta_cant'], 'nombre' => $row['d_nombre'], 'unidad' => $row['d_unidad']];

        // ── Paso A: por maestro (fallback para los que no resolvieron con B)
        $sinDF = array_values(array_filter($idsPP, fn($id) => !isset($despFMap[$id])));
        if (!empty($sinDF)) {
            $phSin = implode(',', array_fill(0, count($sinDF), '?'));
            $stmtDA = $conn->prepare("
                SELECT pp.id                  AS id_pp,
                       ppd.cantidad           AS d_cant,
                       ppd.id_unidad_producto AS d_uid,
                       pp.cantidad            AS pp_cant,
                       pp.id_unidad_producto  AS pp_uid,
                       ppd.Nombre             AS d_nombre,
                       ud.abreviado           AS d_unidad
                FROM producto_presentacion pp
                INNER JOIN producto_presentacion ppd
                       ON ppd.id_producto_maestro = pp.id_producto_maestro
                      AND ppd.presentacion_despacho = 1
                      AND ppd.Activo = 'SI'
                      AND pp.id_producto_maestro IS NOT NULL
                LEFT  JOIN unidad_producto ud ON ud.id = ppd.id_unidad_producto
                WHERE pp.id IN ($phSin) AND pp.Activo = 'SI'
                GROUP BY pp.id
                ORDER BY ppd.id ASC
            ");
            $stmtDA->execute(array_values($sinDF));
            foreach ($stmtDA->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $uidPP = (int)$row['pp_uid']; $uidD = (int)$row['d_uid']; $df = null;
                if ($uidPP === $uidD) {
                    $df = (float)$row['d_cant'] / max((float)$row['pp_cant'], 0.001);
                } else {
                    $facConv = resolverFactorConversion_PS($uidPP, $uidD, $convIndex);
                    if ($facConv !== null && $facConv != 0)
                        $df = (float)$row['d_cant'] / (max((float)$row['pp_cant'], 0.001) * $facConv);
                }
                if ($df !== null)
                    $despFMap[(int)$row['id_pp']] = ['factor' => round($df, 6), 'nombre' => $row['d_nombre'], 'unidad' => $row['d_unidad']];
            }
        }
    }

    $facC = ($capC!==null && $sumB>0) ? min(1.0, $capC/$sumB) : null;
    foreach ($res as &$p) {
        $idP    = $p['id_pp'];
        $dfInfo = $despFMap[$idP] ?? null;
        $df     = ($dfInfo && $dfInfo['factor'] > 0) ? $dfInfo['factor'] : 1.0;

        $p['despacho_factor'] = $dfInfo ? $dfInfo['factor'] : null;
        $p['despacho_nombre'] = $dfInfo ? $dfInfo['nombre'] : null;
        $p['despacho_unidad'] = $dfInfo ? $dfInfo['unidad'] : null;

        // Stock máximo final en unidades de USO (con factor congelados si aplica)
        $sMaxUso = $p['stock_maximo']; // aún en unidades de uso
        if ($p['categoria_insumo']==='B' && $facC!==null) { $sMaxFinalUso = round($sMaxUso * $facC, 4); $p['es_ajustado'] = true; }
        else { $sMaxFinalUso = $p['_tc'] ? round($sMaxUso, 4) : null; }

        // Convertir a unidades de despacho para mostrar (÷ despacho_factor)
        $p['stock_minimo']    = $p['_tc'] ? round($p['stock_minimo'] / $df, 4) : null;
        $p['stock_maximo']    = $p['_tc'] ? round($sMaxUso           / $df, 4) : null;
        $p['stock_max_final'] = $sMaxFinalUso !== null ? round($sMaxFinalUso / $df, 4) : null;

        // Pedido sugerido e inventario actual también en despacho
        if ($p['stock_max_final'] !== null && $p['inventario_actual'] !== null) {
            $invDesp = round($p['inventario_actual'] / $df, 4);
            $p['inventario_actual']  = $invDesp;
            $p['pedido_sugerido']    = round(max(0, $p['stock_max_final'] - $invDesp), 4);
        }
        unset($p['_tc']);
    }

    usort($res, function($a,$b){ $ca=$a['categoria_insumo']??'Z'; $cb=$b['categoria_insumo']??'Z'; return ($ca!==$cb)?strcmp($ca,$cb):strcmp($a['nombre'],$b['nombre']); });

    echo json_encode(['ok'=>true, 'productos'=>array_values($res), 'n_semanas'=>$nSemanas, 'factor_congelados'=>$facC, 'capacidad_congelados'=>$capC, 'sum_stock_max_b'=>$sumB]);
} catch (Exception $e) {
    echo json_encode(['ok'=>false, 'msg'=>'Error: ' . $e->getMessage()]);
}
