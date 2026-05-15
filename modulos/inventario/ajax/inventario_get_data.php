<?php
/* ============================================================
   AJAX: Obtener datos para Inventario Semanal
   Ruta: modulos/inventario/ajax/inventario_get_data.php

   El cálculo de consumo es IDÉNTICO a pedido_sugerido_calcular.php:
   - Mismo pipeline de ventas × SubReceta
   - Mismo fallback codporcion → Cotizaciones p2 → Cotizaciones p3
   - Misma conversión de unidades
   - Misma fórmula stockMin/Max y factor congelados
   ============================================================ */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');
set_time_limit(0);

$usuario = obtenerUsuarioActual();
$cargo   = $usuario['CodNivelesCargos'];

if (!tienePermiso('inventario_semanal', 'vista', $cargo)) {
    echo json_encode(['ok' => false, 'msg' => 'Sin permisos.']);
    exit();
}

$codSucursal        = $_GET['cod_sucursal'] ?? '';
$numSemanaInv       = isset($_GET['semana_inv'])              ? (int)$_GET['semana_inv']              : 0;
$numDesde           = isset($_GET['semana_desde'])            ? (int)$_GET['semana_desde']            : 0;
$numHasta           = isset($_GET['semana_hasta'])            ? (int)$_GET['semana_hasta']            : 0;
$semCortePron       = isset($_GET['semana_corte_pronostico']) ? (int)$_GET['semana_corte_pronostico'] : max(1, $numSemanaInv - 1);

if (empty($codSucursal) || !$numSemanaInv) {
    echo json_encode(['ok' => false, 'msg' => 'Sucursal y semana de inventario requeridas.']);
    exit();
}

/* ── Helpers idénticos a pedido_sugerido_calcular.php ────── */
function desviacionEstandarMuestra(array $vals): float
{
    $n = count($vals);
    if ($n <= 1) return 0.0;
    $media = array_sum($vals) / $n;
    return sqrt(array_sum(array_map(fn($v) => ($v - $media) ** 2, $vals)) / ($n - 1));
}
function resolverUnidadId_PS(string $nombre, array &$unidadPorNombre): ?int
{
    return $unidadPorNombre[strtolower(trim($nombre))] ?? null;
}
function resolverFactorConversion_PS(int $idOrigen, int $idDestino, array &$convIndex): ?float
{
    if ($idOrigen === $idDestino) return 1.0;
    return $convIndex[$idOrigen][$idDestino] ?? null;
}
/**
 * Cierre transitivo de conversiones (Floyd-Warshall).
 * Resuelve cadenas oz → gr → kg sin necesidad de fila directa en la BD.
 * Llama DESPUÉS de poblar $convIndex con las filas de conversion_unidad_producto.
 */
function cerrarConversionesTransitivas(array &$convIndex): void
{
    $units = array_unique(
        array_merge(
            array_keys($convIndex),
            array_merge(...array_map('array_keys', array_values($convIndex)))
        )
    );
    foreach ($units as $k) {
        foreach ($units as $i) {
            if (!isset($convIndex[$i][$k])) continue;
            foreach ($units as $j) {
                if (!isset($convIndex[$k][$j])) continue;
                $nuevo = $convIndex[$i][$k] * $convIndex[$k][$j];
                if (!isset($convIndex[$i][$j])) {
                    $convIndex[$i][$j] = $nuevo;
                }
            }
        }
    }
}
function buscarPresentacionEnMaestro_PS(int $idMaestro, int $idUnidad, array &$presentPorMaestro): ?array
{
    return $presentPorMaestro[$idMaestro][$idUnidad] ?? null;
}

try {
    /* ── 1. Semana de inventario ──────────────────────────── */
    $stmtS = $conn->prepare("SELECT fecha_inicio, fecha_fin FROM SemanasSistema WHERE numero_semana = ?");
    $stmtS->execute([$numSemanaInv]);
    $semInv = $stmtS->fetch();
    if (!$semInv) throw new Exception("Semana de inventario no válida.");

    /* ── 2. Configuración de porcentajes de la sucursal ───── */
    $stmtConf = $conn->prepare("SELECT porcentaje_congelados, porcentaje_frescos FROM inventario_configuracion_sucursal WHERE cod_sucursal = ?");
    $stmtConf->execute([$codSucursal]);
    $configPct = $stmtConf->fetch(PDO::FETCH_ASSOC) ?: ['porcentaje_congelados' => 0, 'porcentaje_frescos' => 0];

    /* ── 3. Logística sucursal ────────────────────────────── */
    $stmtLogSuc = $conn->prepare("SELECT dias_stock_minimo, capacidad_congelados FROM configuracion_logistica_sucursal WHERE cod_sucursal = ?");
    $stmtLogSuc->execute([$codSucursal]);
    $logSuc = $stmtLogSuc->fetch(PDO::FETCH_ASSOC) ?: ['dias_stock_minimo' => 0, 'capacidad_congelados' => null];
    $dSM  = (float)$logSuc['dias_stock_minimo'];
    $capC = $logSuc['capacidad_congelados'] !== null ? (float)$logSuc['capacidad_congelados'] : null;

    /* ── 4. Logística por categoría ───────────────────────── */
    $stmtLogCat = $conn->prepare("SELECT codigo_insumo, dias_ciclo, dias_desfase, ajuste_demanda FROM configuracion_logistica_producto WHERE cod_sucursal = ?");
    $stmtLogCat->execute([$codSucursal]);
    $logCats = [];
    foreach ($stmtLogCat->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $logCats[$row['codigo_insumo']] = $row;
    }

    /* ── 5. Inventario guardado de la semana seleccionada ─── */
    $stmtInv = $conn->prepare("
        SELECT id_producto_presentacion, cantidad_unidades, cantidad_presentacion
        FROM inventario_semanal
        WHERE cod_sucursal = ? AND fecha_inventario BETWEEN ? AND ?
    ");
    $stmtInv->execute([$codSucursal, $semInv['fecha_inicio'], $semInv['fecha_fin']]);
    $inventarioSemana = [];
    foreach ($stmtInv->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $inventarioSemana[$row['id_producto_presentacion']] = $row;
    }

    /* ── 6. Consumo histórico (IDÉNTICO a pedido_sugerido_calcular.php) */
    $conAgg  = [];   // [id_pp][semana] = consumo acumulado
    $metaPP  = [];   // [id_pp] = ['cat' => ..., 'n' => ...]
    $nSemanas = 1;

    if ($numDesde > 0 && $numHasta > 0) {
        $numDesde = min($numDesde, $numHasta);
        $numHasta = max($numDesde, $numHasta);
        $nSemanas = $numHasta - $numDesde + 1;

        $stmtR = $conn->prepare("SELECT MIN(fecha_inicio) as f1, MAX(fecha_fin) as f2 FROM SemanasSistema WHERE numero_semana BETWEEN ? AND ?");
        $stmtR->execute([$numDesde, $numHasta]);
        $r = $stmtR->fetch();

        if ($r && $r['f1']) {
            // Ventas × SubReceta (igual que en el script de referencia)
            $stmtV = $conn->prepare("
                SELECT v.Semana as sem, sr.CodIngrediente as cod_ing, sr.codporcion,
                       SUM(v.Cantidad * sr.Cantidad) as cant
                FROM VentasGlobalesAccessCSV v
                INNER JOIN SubReceta sr ON sr.CodBatido = v.CodProducto
                WHERE v.Anulado = 0 AND v.local = ? AND v.Semana BETWEEN ? AND ?
                  AND v.Fecha BETWEEN ? AND ?
                GROUP BY v.Semana, sr.CodIngrediente, sr.codporcion
            ");
            $stmtV->execute([$codSucursal, $numDesde, $numHasta, $r['f1'], $r['f2']]);
            $filas = $stmtV->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($filas)) {
                // Pre-cargar mapeos (Bulk) — idéntico al script de referencia
                $codsIng = array_unique(array_column($filas, 'cod_ing'));
                $ph = implode(',', array_fill(0, count($codsIng), '?'));

                $dbIng = [];
                $stmtI = $conn->prepare("SELECT CodIngrediente, Nombre, Unidad FROM DBIngredientes WHERE CodIngrediente IN ($ph)");
                $stmtI->execute(array_values($codsIng));
                foreach ($stmtI->fetchAll() as $row) $dbIng[$row['CodIngrediente']] = $row;

                // Cotizaciones: fallback p2 (Conversion=1, Prioridad=1) y p3 (cualquiera)
                $cotMap = [];
                $stmtC = $conn->prepare("SELECT CodIngrediente, CodCotizacion, Conversion, Prioridad FROM Cotizaciones WHERE CodIngrediente IN ($ph) AND (Subproducto IS NULL OR Subproducto!=1) AND (Marca IS NULL OR Marca!='Almacen Global') ORDER BY Conversion DESC, Prioridad ASC");
                $stmtC->execute(array_values($codsIng));
                foreach ($stmtC->fetchAll() as $c) {
                    $ci = $c['CodIngrediente'];
                    if (!isset($cotMap[$ci])) $cotMap[$ci] = ['p2' => null, 'p3' => null];
                    if ($c['Conversion'] == 1 && $c['Prioridad'] == 1 && !$cotMap[$ci]['p2']) $cotMap[$ci]['p2'] = $c['CodCotizacion'];
                    if (!$cotMap[$ci]['p3']) $cotMap[$ci]['p3'] = $c['CodCotizacion'];
                }

                // Diccionario de productos legado
                $codCotBuscar = array_unique(array_filter(array_merge(
                    array_column($filas, 'codporcion'),
                    array_column($cotMap, 'p2'),
                    array_column($cotMap, 'p3')
                )));
                $diccionarioMap = [];
                if (!empty($codCotBuscar)) {
                    $phC = implode(',', array_fill(0, count($codCotBuscar), '?'));
                    
                    // Paso A: resolución directa
                    $stmtD = $conn->prepare("
                        SELECT d.CodCotizacion, pp.id, pp.cantidad as pp_cant, pp.Id_receta_producto,
                               pp.id_producto_maestro as id_m, pp.Nombre as n, pp.categoria_insumo as cat,
                               pp.presentacion_basica_inventario,
                               u.id as uid, u.abreviado as uab
                        FROM diccionario_productos_legado d
                        INNER JOIN producto_presentacion pp ON pp.id = d.id_producto_presentacion
                        LEFT JOIN unidad_producto u ON u.id = pp.id_unidad_producto
                        WHERE d.CodCotizacion IN ($phC) AND pp.Activo = 'SI'
                    ");
                    $stmtD->execute(array_values($codCotBuscar));
                    foreach ($stmtD->fetchAll() as $row) $diccionarioMap[(string)$row['CodCotizacion']] = $row;

                    // Paso B: rastreo por maestro de la presentación mapeada (para redirigir a la básica)
                    $sinResolverB = array_values(array_filter($codCotBuscar, fn($c) => !isset($diccionarioMap[(string)$c])));
                    if (!empty($sinResolverB)) {
                        $phB = implode(',', array_fill(0, count($sinResolverB), '?'));
                        $stmtB = $conn->prepare("
                            SELECT d.CodCotizacion, pp_base.id, pp_base.cantidad as pp_cant, pp_base.Id_receta_producto,
                                   pp_base.id_producto_maestro as id_m, pp_base.Nombre as n, pp_base.categoria_insumo as cat,
                                   pp_base.presentacion_basica_inventario,
                                   u_base.id as uid, u_base.abreviado as uab
                            FROM diccionario_productos_legado d
                            INNER JOIN producto_presentacion pp_orig ON pp_orig.id = d.id_producto_presentacion
                            INNER JOIN producto_presentacion pp_base ON pp_base.id_producto_maestro = pp_orig.id_producto_maestro
                                   AND pp_base.presentacion_basica_inventario = 1 AND pp_base.Activo = 'SI' AND pp_base.Id_receta_producto IS NULL
                            LEFT JOIN unidad_producto u_base ON u_base.id = pp_base.id_unidad_producto
                            WHERE d.CodCotizacion IN ($phB) AND pp_orig.Activo = 'SI' AND pp_orig.id_producto_maestro IS NOT NULL
                            GROUP BY d.CodCotizacion
                        ");
                        $stmtB->execute(array_values($sinResolverB));
                        foreach ($stmtB->fetchAll() as $row) $diccionarioMap[(string)$row['CodCotizacion']] = $row;
                    }

                    // Paso C: fallback vía CodIngrediente -> todas sus cotizaciones -> maestro -> básica
                    $sinResolverC = array_values(array_filter($codCotBuscar, fn($c) => !isset($diccionarioMap[(string)$c])));
                    if (!empty($sinResolverC)) {
                        $phC2 = implode(',', array_fill(0, count($sinResolverC), '?'));
                        $stmtPC = $conn->prepare("
                            SELECT c_src.CodCotizacion, pp_base.id, pp_base.cantidad as pp_cant, pp_base.Id_receta_producto,
                                   pp_base.id_producto_maestro as id_m, pp_base.Nombre as n, pp_base.categoria_insumo as cat,
                                   pp_base.presentacion_basica_inventario,
                                   u_base.id as uid, u_base.abreviado as uab
                            FROM Cotizaciones c_src
                            INNER JOIN Cotizaciones c_all ON c_all.CodIngrediente = c_src.CodIngrediente
                            INNER JOIN diccionario_productos_legado d2 ON d2.CodCotizacion = c_all.CodCotizacion
                            INNER JOIN producto_presentacion pp_any ON pp_any.id = d2.id_producto_presentacion AND pp_any.Activo = 'SI' AND pp_any.id_producto_maestro IS NOT NULL
                            INNER JOIN producto_presentacion pp_base ON pp_base.id_producto_maestro = pp_any.id_producto_maestro
                                   AND pp_base.presentacion_basica_inventario = 1 AND pp_base.Activo = 'SI' AND pp_base.Id_receta_producto IS NULL
                            LEFT JOIN unidad_producto u_base ON u_base.id = pp_base.id_unidad_producto
                            WHERE c_src.CodCotizacion IN ($phC2)
                            GROUP BY c_src.CodCotizacion
                        ");
                        $stmtPC->execute(array_values($sinResolverC));
                        foreach ($stmtPC->fetchAll() as $row) $diccionarioMap[(string)$row['CodCotizacion']] = $row;
                    }
                }

                // Unidades y conversiones
                $unidadPorNombre = [];
                $unidadPorId = [];
                foreach ($conn->query("SELECT id, nombre, abreviado, nombres_opcionales FROM unidad_producto")->fetchAll() as $u) {
                    $uid = (int)$u['id'];
                    $unidadPorId[$uid] = $u;
                    $unidadPorNombre[strtolower(trim($u['nombre']))] = $uid;
                    if ($u['abreviado']) $unidadPorNombre[strtolower(trim($u['abreviado']))] = $uid;
                    if ($u['nombres_opcionales']) foreach (preg_split('/[,;|]+/', $u['nombres_opcionales']) as $a) if ($ak = strtolower(trim($a))) $unidadPorNombre[$ak] = $uid;
                }

                $convIndex = [];
                foreach ($conn->query("SELECT id_unidad_producto_inicio as i, id_unidad_producto_final as f, cantidad as c FROM conversion_unidad_producto")->fetchAll() as $c) {
                    $convIndex[(int)$c['i']][(int)$c['f']] = (float)$c['c'];
                    $convIndex[(int)$c['f']][(int)$c['i']] = $c['c'] != 0 ? 1 / $c['c'] : 0;
                }
                cerrarConversionesTransitivas($convIndex); // oz→gr→kg, etc.

                $presentPorMaestro = [];
                $idMs = array_unique(array_filter(array_column($diccionarioMap, 'id_m')));
                if (!empty($idMs)) {
                    $phM = implode(',', array_fill(0, count($idMs), '?'));
                    $stmtPP = $conn->prepare("SELECT id, id_producto_maestro, cantidad, id_unidad_producto FROM producto_presentacion WHERE id_producto_maestro IN ($phM) AND Id_receta_producto IS NULL AND Activo='SI' AND presentacion_basica_inventario = 1");
                    $stmtPP->execute(array_values($idMs));
                    foreach ($stmtPP->fetchAll() as $pp) {
                        $presentPorMaestro[(int)$pp['id_producto_maestro']][(int)$pp['id_unidad_producto']] = $pp;
                    }
                }

                // Proceso de consumo con fallback triple (idéntico al script de referencia)
                foreach ($filas as $f) {
                    $ci = $f['cod_ing'];
                    $cp = $f['codporcion'];
                    $sem = (int)$f['sem'];
                    $cant = (float)$f['cant'];
                    $m = null;
                    $esP1 = false;

                    // Nivel 1: codporcion directo
                    if (!empty($cp) && isset($diccionarioMap[(string)$cp])) {
                        $m = $diccionarioMap[(string)$cp];
                        $esP1 = true;
                    }
                    // Nivel 2: Cotizaciones p2
                    if (!$m && isset($cotMap[$ci]['p2']) && isset($diccionarioMap[(string)$cotMap[$ci]['p2']])) $m = $diccionarioMap[(string)$cotMap[$ci]['p2']];
                    // Nivel 3: Cotizaciones p3
                    if (!$m && isset($cotMap[$ci]['p3']) && isset($diccionarioMap[(string)$cotMap[$ci]['p3']])) $m = $diccionarioMap[(string)$cotMap[$ci]['p3']];
                    if (!$m) continue;

                    // Redirección a presentación básica si el mapeo resolvió a otra presentación del mismo maestro
                    if (empty($m['Id_receta_producto']) && ($m['presentacion_basica_inventario'] ?? 1) != 1 && !empty($m['id_m'])) {
                        $basicasMaestro = $presentPorMaestro[(int)$m['id_m']] ?? [];
                        if (!empty($basicasMaestro)) {
                            $ppBasica = reset($basicasMaestro);
                            $uidBasica = (int)$ppBasica['id_unidad_producto'];
                            $m = array_merge($m, [
                                'id' => $ppBasica['id'],
                                'pp_cant' => $ppBasica['cantidad'],
                                'uid' => $uidBasica,
                                'uab' => $unidadPorId[$uidBasica]['abreviado'] ?? $m['uab'],
                                'presentacion_basica_inventario' => 1
                            ]);
                        }
                    }

                    $idPP = (int)$m['id'];
                    $ppC  = max((float)$m['pp_cant'], 0.001);
                    $uidERP = (int)$m['uid'];

                    if ($m['Id_receta_producto']) {
                        $cons = $cant;
                    } else {
                        $uAcc   = $dbIng[$ci]['Unidad'] ?? '';
                        $uidAcc = resolverUnidadId_PS($uAcc, $unidadPorNombre);
                        $fac    = 1.0;
                        if ($uidAcc && $uidAcc !== $uidERP) {
                            $fDir = resolverFactorConversion_PS($uidAcc, $uidERP, $convIndex);
                            if ($fDir) {
                                $fac = $fDir;
                            } else {
                                $alt = buscarPresentacionEnMaestro_PS((int)$m['id_m'], $uidAcc, $presentPorMaestro);
                                if ($alt) {
                                    $idPP = (int)$alt['id'];
                                    $ppC = max((float)$alt['cantidad'], 0.001);
                                    $uidERP = (int)$alt['id_unidad_producto'];
                                    $fac = 1.0;
                                }
                            }
                        }
                        $cons = ($cant * $fac) / $ppC;
                        if ($esP1) $cons = round($cons * 2) / 2;
                    }

                    if (!isset($metaPP[$idPP])) $metaPP[$idPP] = ['cat' => $m['cat'], 'n' => $m['n']];
                    $conAgg[$idPP][$sem] = ($conAgg[$idPP][$sem] ?? 0) + $cons;
                }
            }
        }
    }

    /* ── 7. Stats de consumo por id_pp — Ventana Activa ─────
       Idéntico a pedido_sugerido_calcular.php:
       Excluye ceros estructurales del inicio/fin con umbral relativo 10%.
       Solo promedia sobre las semanas dentro de la ventana activa.
    ─────────────────────────────────────────────────────── */
    $consumoStats = [];
    foreach ($conAgg as $idPP => $semanas) {
        $vals = [];
        for ($s = $numDesde; $s <= $numHasta; $s++) $vals[] = (float)($semanas[$s] ?? 0);

        // Paso 1: media de semanas con consumo > 0 (para calcular umbral)
        $nonZeroVals = array_filter($vals, fn($v) => $v > 0);
        if (empty($nonZeroVals)) continue; // Sin consumo real → descartar
        $meanNonZero = array_sum($nonZeroVals) / count($nonZeroVals);

        // Umbral: 10% de la media real (mín. 0.01)
        $umbral = max(0.01, $meanNonZero * 0.10);

        // Paso 2: detectar ventana activa
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
        $consumoStats[$idPP] = ['promedio' => $prom, 'desviacion' => $desv, 'cons_semanal' => $prom + $desv];
    }

    /* ── 7.5 Índice de conversiones (garantizado para factor despacho) */
    if (!isset($convIndex)) {
        $convIndex = [];
        foreach ($conn->query("SELECT id_unidad_producto_inicio as i, id_unidad_producto_final as f, cantidad as c FROM conversion_unidad_producto")->fetchAll() as $c) {
            $convIndex[(int)$c['i']][(int)$c['f']] = (float)$c['c'];
            $convIndex[(int)$c['f']][(int)$c['i']] = $c['c'] != 0 ? 1 / $c['c'] : 0;
        }
        cerrarConversionesTransitivas($convIndex); // oz→gr→kg, etc.
    }

    /* ── 8. Productos para inventario (con lógica de despacho mejorada) ──── */
    $stmtP = $conn->prepare("
        SELECT pp.id, pp.Nombre, pp.presentacion, pp.categoria_insumo,
               pp.categoria_nombre,
               pp.presentacion_basica_inventario, pp.presentacion_despacho,
               u.abreviado as unidad, pp.cantidad as cant_pres,
               pp.id_unidad_producto as uid_uso,
               -- Caso A: Despacho por Maestro
               ppd_a.id          as d_id_a,
               ppd_a.Nombre      as d_nom_a,
               ppd_a.presentacion as d_pres_a,
               ppd_a.cantidad    as d_cant_a,
               ppd_a.id_unidad_producto as d_uid_a,
               ud_a.abreviado    as d_uni_a,
               -- Caso B: Despacho por Receta (componente = esta presentación exacta)
               ppd_b.id          as d_id_b,
               ppd_b.Nombre      as d_nom_b,
               ppd_b.presentacion as d_pres_b,
               ppd_b.cantidad    as d_cant_b,
               ppd_b.id_unidad_producto as d_uid_b,
               ud_b.abreviado    as d_uni_b,
               crp_b.cantidad    as d_receta_cant_b,
               -- Caso C: Despacho por Receta (componente = cualquier presentación del mismo maestro)
               ppd_c.id          as d_id_c,
               ppd_c.Nombre      as d_nom_c,
               ppd_c.presentacion as d_pres_c,
               ud_c.abreviado    as d_uni_c,
               crp_c.cantidad    as d_receta_cant_c
        FROM producto_presentacion pp
        LEFT JOIN unidad_producto u ON u.id = pp.id_unidad_producto
        -- JOIN A: Por Maestro (Mismo producto_maestro con flag despacho=1)
        LEFT JOIN producto_presentacion ppd_a
               ON ppd_a.id = (
                   SELECT ppd_sub_a.id FROM producto_presentacion ppd_sub_a
                   WHERE ppd_sub_a.id_producto_maestro = pp.id_producto_maestro
                     AND ppd_sub_a.presentacion_despacho = 1
                     AND ppd_sub_a.Activo = 'SI'
                     AND pp.id_producto_maestro IS NOT NULL
                   ORDER BY ppd_sub_a.id ASC LIMIT 1
               )
        LEFT JOIN unidad_producto ud_a ON ud_a.id = ppd_a.id_unidad_producto
        -- JOIN B: Receta-paquete cuyo único componente es esta presentación exacta
        LEFT JOIN producto_presentacion ppd_b
               ON ppd_b.id = (
                   SELECT ppd_sub_b.id FROM producto_presentacion ppd_sub_b
                   INNER JOIN componentes_receta_producto crp_sub_b ON crp_sub_b.id_receta_producto_global = ppd_sub_b.Id_receta_producto
                   WHERE ppd_sub_b.presentacion_despacho = 1
                     AND ppd_sub_b.Activo = 'SI'
                     AND crp_sub_b.id_presentacion_producto = pp.id
                     AND (
                         SELECT COUNT(DISTINCT crp_cnt.id_presentacion_producto)
                         FROM componentes_receta_producto crp_cnt
                         WHERE crp_cnt.id_receta_producto_global = ppd_sub_b.Id_receta_producto
                     ) = 1
                   ORDER BY ppd_sub_b.id ASC LIMIT 1
               )
        LEFT JOIN componentes_receta_producto crp_b ON crp_b.id_receta_producto_global = ppd_b.Id_receta_producto AND crp_b.id_presentacion_producto = pp.id
        LEFT JOIN unidad_producto ud_b ON ud_b.id = ppd_b.id_unidad_producto
        -- JOIN C: Receta-paquete cuyo componente es cualquier presentación del mismo maestro
        --         (cubre el caso: Naranja oz → Cajilla cuya receta tiene componente Naranja Unidad)
        LEFT JOIN producto_presentacion ppd_c
               ON ppd_c.id = (
                   SELECT ppd_sub_c.id FROM producto_presentacion ppd_sub_c
                   INNER JOIN componentes_receta_producto crp_sub_c ON crp_sub_c.id_receta_producto_global = ppd_sub_c.Id_receta_producto
                   INNER JOIN producto_presentacion pp_comp_c ON pp_comp_c.id = crp_sub_c.id_presentacion_producto
                   WHERE ppd_sub_c.presentacion_despacho = 1
                     AND ppd_sub_c.Activo = 'SI'
                     AND ppd_sub_c.Id_receta_producto IS NOT NULL
                     AND pp_comp_c.id_producto_maestro = pp.id_producto_maestro
                     AND pp.id_producto_maestro IS NOT NULL
                   ORDER BY ppd_sub_c.id ASC LIMIT 1
               )
        LEFT JOIN componentes_receta_producto crp_c ON crp_c.id_receta_producto_global = ppd_c.Id_receta_producto
                   AND crp_c.id_presentacion_producto = (
                       SELECT pp_comp2.id FROM producto_presentacion pp_comp2
                       INNER JOIN componentes_receta_producto crp2 ON crp2.id_presentacion_producto = pp_comp2.id
                       WHERE crp2.id_receta_producto_global = ppd_c.Id_receta_producto
                         AND pp_comp2.id_producto_maestro = pp.id_producto_maestro
                       LIMIT 1
                   )
        LEFT JOIN unidad_producto ud_c ON ud_c.id = ppd_c.id_unidad_producto
        WHERE pp.presentacion_basica_inventario = 1
          AND pp.Activo = 'SI'
        ORDER BY pp.categoria_insumo ASC, pp.Nombre ASC
    ");
    $stmtP->execute();
    $productos = $stmtP->fetchAll(PDO::FETCH_ASSOC);

    /* ── 8.5 Calcular factor despacho por producto ────────── */
    foreach ($productos as &$p) {
        // ── Prioridad: B (receta componente exacto) > A (presentación maestro) > C (receta mismo maestro).
        //
        // El orden B > A > C es equivalente al de pedido_sugerido_calcular.php:
        //   B exacto tiene prioridad → si no, la presentación despacho directa por maestro (A)
        //   → solo como último recurso, la receta-paquete cuyo componente comparte maestro (C).
        //
        // Esto evita que presentaciones como "Fresa 1oz" hereden incorrectamente el paquete de
        // "Fresa 2oz" via maestro del componente, cuando existe la Bandeja 400gr resolvible por A.
        // El caso Naranja sigue funcionando: la Cajilla no tiene id_producto_maestro propio,
        // por lo que A falla y C la encuentra correctamente via el maestro del componente.
        $usarC = !empty($p['d_id_c']) && empty($p['d_id_b']) && empty($p['d_id_a']);

        $p['despacho_id']     = $p['d_id_b']   ?? $p['d_id_a']   ?? ($usarC ? $p['d_id_c']   : null) ?? null;
        $p['despacho_nombre'] = $p['d_pres_b'] ?? $p['d_pres_a'] ?? ($usarC ? $p['d_pres_c'] : null) ?? null;
        $p['despacho_unidad'] = $p['d_uni_b']  ?? $p['d_uni_a']  ?? ($usarC ? $p['d_uni_c']  : null) ?? null;
        $p['despacho_cant']   = $p['d_cant_b'] ?? null;

        $despFactor = null;

        // Caso B: componente exacto — usar cantidad del componente en la receta
        if (!empty($p['d_id_b']) && (float)$p['d_receta_cant_b'] > 0) {
            $despFactor = (float)$p['d_receta_cant_b'];
        }
        // Caso A: por maestro — calcular factor con conversión de unidades si es necesario
        elseif (!empty($p['d_id_a']) && (float)$p['d_cant_a'] > 0 && (float)$p['cant_pres'] > 0) {
            $uidUso  = (int)$p['uid_uso'];
            $uidDesp = (int)$p['d_uid_a'];
            if ($uidUso === $uidDesp) {
                $despFactor = (float)$p['d_cant_a'] / (float)$p['cant_pres'];
            } else {
                $facConv = resolverFactorConversion_PS($uidUso, $uidDesp, $convIndex);
                if ($facConv !== null && $facConv != 0) {
                    $despFactor = (float)$p['d_cant_a'] / ((float)$p['cant_pres'] * $facConv);
                }
            }
        }
        // Caso C: último recurso — receta-paquete cuyo componente comparte maestro (sin maestro propio en ppd)
        elseif ($usarC && (float)$p['d_receta_cant_c'] > 0) {
            $despFactor = (float)$p['d_receta_cant_c'];
        }

        $p['despacho_factor'] = $despFactor !== null ? round($despFactor, 6) : null;

        // Limpiar campos temporales del query
        unset(
            $p['uid_uso'],
            $p['d_id_a'],
            $p['d_nom_a'],
            $p['d_pres_a'],
            $p['d_cant_a'],
            $p['d_uid_a'],
            $p['d_uni_a'],
            $p['d_id_b'],
            $p['d_nom_b'],
            $p['d_pres_b'],
            $p['d_cant_b'],
            $p['d_uid_b'],
            $p['d_uni_b'],
            $p['d_receta_cant_b'],
            $p['d_id_c'],
            $p['d_nom_c'],
            $p['d_pres_c'],
            $p['d_uni_c'],
            $p['d_receta_cant_c']
        );
    }
    unset($p);

    /* ── 9. Calcular stocks — fórmula idéntica al script de referencia */
    $sumSMaxB = 0.0;
    foreach ($productos as &$p) {
        $idPP = $p['id'];
        $cat  = $p['categoria_insumo'];
        $lc   = $logCats[$cat] ?? null;
        
        $adj  = $lc ? (float)$lc['ajuste_demanda'] : 0;
        $dC   = $lc ? (float)$lc['dias_ciclo']     : 0;
        $dD   = $lc ? (float)$lc['dias_desfase']   : 0;

        $cs    = $consumoStats[$idPP] ?? null;
        $semC  = $cs ? (float)$cs['cons_semanal'] : 0.0;
        $prom  = $cs ? (float)$cs['promedio']     : 0.0;
        $desv  = $cs ? (float)$cs['desviacion']   : 0.0;

        $diaC = ($semC * (1 + $adj)) / 7;
        $sMin = $diaC * $dSM;
        $sMax = $diaC * ($dC + $dD + $dSM);

        $invRow  = $inventarioSemana[$idPP] ?? null;

        $p['_cons_semanal']   = round($semC, 4);
        $p['_promedio']       = round($prom, 4);
        $p['_desviacion']     = round($desv, 4);
        $p['_cons_diario']    = round($diaC, 6);
        $p['_stock_min_u']    = round($sMin, 4);   // En unidades de uso
        $p['_stock_max_u']    = round($sMax, 4);   // En unidades de uso
        $p['_tiene_config']   = ($lc !== null);
        $p['_inv_pres']       = $invRow ? (float)$invRow['cantidad_presentacion'] : null;
        $p['_inv_unidades']   = $invRow ? (float)$invRow['cantidad_unidades']     : null;

        // Para el cálculo del factorC (congelados), solo sumamos productos que tengan consumo (cons_semanal > 0)
        // Esto replica exactamente el universo de productos de pedido_sugerido_calcular.php
        if ($cat === 'B' && $semC > 0) $sumSMaxB += $sMax;
    }
    unset($p);

    // Factor de congelados
    $factorC = ($capC !== null && $sumSMaxB > 0) ? min(1.0, $capC / $sumSMaxB) : null;

    $pctCong  = (float)$configPct['porcentaje_congelados'];
    $pctFresc = (float)$configPct['porcentaje_frescos'];

    foreach ($productos as &$p) {
        $cat  = $p['categoria_insumo'];
        $sMaxU = $p['_stock_max_u'];
        $df   = (float)($p['despacho_factor'] ?? 1);
        if ($df <= 0) $df = 1;

        if ($cat === 'B' && $factorC !== null) {
            $sMaxFinalU = round($sMaxU * $factorC, 4);
            $esAjustado = true;
        } else {
            $sMaxFinalU = $p['_tiene_config'] ? $sMaxU : null;
            $esAjustado = false;
        }

        // Inventario actual (A) ya viene en unidades de despacho desde la BD
        $invPres = $p['_inv_pres'];
        $pedido  = null;

        // Stock Máximo Final (B) - Debe mostrarse siempre que haya configuración
        if ($sMaxFinalU !== null) {
            $sMaxFinalDesp = round($sMaxFinalU / $df, 4);
            $p['stock_max_final'] = $sMaxFinalDesp;
            
            // El pedido solo se calcula si tenemos inventario (A)
            if ($invPres !== null) {
                $pedido = max(0.0, $sMaxFinalDesp - $invPres);
            }
        } else {
            $p['stock_max_final'] = null;
        }

        // Stock Mínimo (Uso -> Despacho) - Debe mostrarse siempre que haya configuración
        if ($p['_tiene_config']) {
            $p['_stock_min'] = round($p['_stock_min_u'] / $df, 4);
        } else {
            $p['_stock_min'] = null;
        }

        $p1 = $p2 = 0.0;
        if ($pedido !== null) {
            if (in_array($cat, ['B', 'D', 'F'])) {
                $p1 = $pedido * $pctCong;
                $p2 = $pedido - $p1;
            } elseif (in_array($cat, ['A', 'C'])) {
                $p1 = $pedido * $pctFresc;
                $p2 = $pedido - $p1;
            } else {
                $p1 = $pedido;
            }
        }

        $p['es_ajustado']     = $esAjustado;
        $p['pedido_sugerido'] = $pedido !== null ? round($pedido, 4) : null;
        $p['p1']              = round($p1, 4);
        $p['p2']              = round($p2, 4);

        unset($p['_stock_max_u'], $p['_stock_min_u'], $p['_tiene_config']);
    }
    unset($p);

    /* ── 10. Stock Pronóstico ─────────────────────────────────────────────────
       Fórmula: inv_fisico(semCortePron) + movimientos(semCortePron+1..semInv)
                                         - consumo_teo(semCortePron+1..semInv)
       Resultado en presentación de despacho (mismas unidades que stock mín).
    ──────────────────────────────────────────────────────────────────────── */
    $semCortePron = max(1, $semCortePron);
    // Inicializar pronóstico como null
    foreach ($productos as &$p) { $p['_stock_pronostico'] = null; }
    unset($p);

    $semPronDesde = $semCortePron + 1; // primer día a acumular movimientos
    $semPronHasta = $numSemanaInv;

    if ($semPronDesde <= $semPronHasta) {
        // ── Fechas ──────────────────────────────────────────────────────────
        $rFAll = $conn->prepare("SELECT MIN(fecha_inicio) as fi, MAX(fecha_fin) as ff FROM SemanasSistema WHERE numero_semana BETWEEN ? AND ?");
        $rFAll->execute([$semCortePron, $semPronHasta]);
        $fdAll = $rFAll->fetch(PDO::FETCH_ASSOC);
        $fCorteIni = $fdAll['fi'];  // inicio de la semana de corte (para el inv físico)
        $fPronFin  = $fdAll['ff'];  // fin de semana_inv

        // Inicio/fin exactos de la semana de corte (para query de inv físico)
        $rFCor = $conn->prepare("SELECT fecha_inicio, fecha_fin FROM SemanasSistema WHERE numero_semana = ?");
        $rFCor->execute([$semCortePron]);
        $fdCor = $rFCor->fetch(PDO::FETCH_ASSOC);
        $fCorIni = $fdCor['fecha_inicio'];
        $fCorFin = $fdCor['fecha_fin'];

        // Inicio/fin del período de movimientos (semCortePron+1..semInv)
        $rFMov = $conn->prepare("SELECT MIN(fecha_inicio) as fi, MAX(fecha_fin) as ff FROM SemanasSistema WHERE numero_semana BETWEEN ? AND ?");
        $rFMov->execute([$semPronDesde, $semPronHasta]);
        $fdMov = $rFMov->fetch(PDO::FETCH_ASSOC);
        $fMovIni = $fdMov['fi'];
        $fMovFin = $fdMov['ff'];

        // ── Build Global Code Map: CodCotizacion → [id_pp, factor] ──────────
        $allPPIds  = array_column($productos, 'id');
        $phPP      = implode(',', array_fill(0, count($allPPIds), '?'));

        // A: Direct — CodCotizacion que apunta directamente a una presentación básica
        $rDir = $conn->prepare("
            SELECT d.CodCotizacion, pp.id as id_pp
            FROM diccionario_productos_legado d
            INNER JOIN producto_presentacion pp ON pp.id = d.id_producto_presentacion
            WHERE pp.presentacion_basica_inventario = 1 AND pp.Activo = 'SI'
              AND pp.id IN ($phPP)
        ");
        $rDir->execute(array_values($allPPIds));
        $gCodeMap = []; // [cod => ['id_pp' => int, 'factor' => float]]
        foreach ($rDir->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $gCodeMap[(int)$row['CodCotizacion']] = ['id_pp' => (int)$row['id_pp'], 'factor' => 1.0];
        }

        // B: Cascade — paquete con exactamente 1 componente básico
        $rCas = $conn->prepare("
            SELECT d.CodCotizacion, crp.id_presentacion_producto as base_id, crp.cantidad as factor
            FROM producto_presentacion pp_pkg
            INNER JOIN diccionario_productos_legado d ON d.id_producto_presentacion = pp_pkg.id
            INNER JOIN componentes_receta_producto crp ON crp.id_receta_producto_global = pp_pkg.Id_receta_producto
            INNER JOIN producto_presentacion pp_base ON pp_base.id = crp.id_presentacion_producto
                AND pp_base.presentacion_basica_inventario = 1 AND pp_base.Activo = 'SI'
                AND pp_base.id IN ($phPP)
            WHERE pp_pkg.presentacion_receta = 1 AND pp_pkg.Activo = 'SI'
              AND (SELECT COUNT(DISTINCT id_presentacion_producto)
                   FROM componentes_receta_producto
                   WHERE id_receta_producto_global = pp_pkg.Id_receta_producto) = 1
        ");
        $rCas->execute(array_values($allPPIds));
        foreach ($rCas->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cod = (int)$row['CodCotizacion'];
            if (!isset($gCodeMap[$cod]))
                $gCodeMap[$cod] = ['id_pp' => (int)$row['base_id'], 'factor' => (float)$row['factor']];
        }

        // C: Maestro fallback — presentación no básica, no receta → base vía maestro
        $rAlt = $conn->prepare("
            SELECT d.CodCotizacion,
                   pp_alt.id_unidad_producto as alt_unid, pp_alt.cantidad as alt_cant,
                   pp_base.id as base_id, pp_base.id_unidad_producto as base_unid,
                   pp_base.cantidad as base_cant
            FROM diccionario_productos_legado d
            INNER JOIN producto_presentacion pp_alt ON pp_alt.id = d.id_producto_presentacion
                AND pp_alt.Activo = 'SI'
                AND pp_alt.presentacion_basica_inventario = 0
                AND pp_alt.presentacion_receta = 0
                AND pp_alt.id_producto_maestro IS NOT NULL
            INNER JOIN producto_presentacion pp_base ON pp_base.id_producto_maestro = pp_alt.id_producto_maestro
                AND pp_base.presentacion_basica_inventario = 1 AND pp_base.Activo = 'SI'
                AND pp_base.id IN ($phPP)
        ");
        $rAlt->execute(array_values($allPPIds));
        foreach ($rAlt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cod = (int)$row['CodCotizacion'];
            if (isset($gCodeMap[$cod])) continue;
            $altUnid  = (int)$row['alt_unid'];
            $baseUnid = (int)$row['base_unid'];
            $altCant  = max((float)$row['alt_cant'], 0.001);
            $baseCant = max((float)$row['base_cant'], 0.001);
            if ($altUnid === $baseUnid) {
                $factor = $altCant / $baseCant;
            } elseif (isset($convIndex[$altUnid][$baseUnid])) {
                $factor = ($altCant * $convIndex[$altUnid][$baseUnid]) / $baseCant;
            } else {
                continue;
            }
            $gCodeMap[$cod] = ['id_pp' => (int)$row['base_id'], 'factor' => $factor];
        }

        if (!empty($gCodeMap)) {
            $allCodsPron = array_keys($gCodeMap);
            $phCods      = implode(',', array_fill(0, count($allCodsPron), '?'));
            $sucInt      = (int)$codSucursal;

            // ── Inventario físico de la semana de corte (punto de partida) ──────
            $pronInv = []; // [id_pp => cantidad_en_unidades]
            $stmtInvF = $conn->prepare("
                SELECT k.CodCotizacion, SUM(k.Cantidad) as qty
                FROM msaccess_masivo_InventarioCotizacion k
                INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin
                WHERE ss.numero_semana = ? AND k.CodCotizacion IN ($phCods)
                  AND k.Sucursal = ?
                GROUP BY k.CodCotizacion
            ");
            $stmtInvF->execute(array_merge([$semCortePron], $allCodsPron, [$sucInt]));
            foreach ($stmtInvF->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $cod = (int)$r['CodCotizacion'];
                if (!isset($gCodeMap[$cod])) continue;
                $idPP  = $gCodeMap[$cod]['id_pp'];
                $fac   = $gCodeMap[$cod]['factor'];
                $pronInv[$idPP] = ($pronInv[$idPP] ?? 0) + (float)$r['qty'] * $fac;
            }

            // ── Movimientos del período (semCortePron+1 → semInv) ────────────
            $pronMov = []; // [id_pp => delta_en_unidades] (+entradas -salidas)

            if ($fMovIni && $fMovFin) {
                // Ajustes (+)
                $stmtA = $conn->prepare("
                    SELECT k.CodCotizacion, SUM(k.Cantidad) as qty
                    FROM msaccess_masivo_AjustesInventario k
                    INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin
                    WHERE ss.numero_semana BETWEEN ? AND ? AND k.CodCotizacion IN ($phCods)
                      AND k.Sucursal = ?
                    GROUP BY k.CodCotizacion
                ");
                $stmtA->execute(array_merge([$semPronDesde, $semPronHasta], $allCodsPron, [$sucInt]));
                foreach ($stmtA->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $cod = (int)$r['CodCotizacion'];
                    if (!isset($gCodeMap[$cod])) continue;
                    $idPP = $gCodeMap[$cod]['id_pp'];
                    $pronMov[$idPP] = ($pronMov[$idPP] ?? 0) + (float)$r['qty'] * $gCodeMap[$cod]['factor'];
                }

                // Mermas (-)
                $stmtM = $conn->prepare("
                    SELECT k.CodCotizacion, SUM(k.Cantidad) as qty
                    FROM msaccess_masivo_MermaCotizacion k
                    INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin
                    WHERE ss.numero_semana BETWEEN ? AND ? AND k.CodCotizacion IN ($phCods)
                      AND k.Sucursal = ?
                    GROUP BY k.CodCotizacion
                ");
                $stmtM->execute(array_merge([$semPronDesde, $semPronHasta], $allCodsPron, [$sucInt]));
                foreach ($stmtM->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $cod = (int)$r['CodCotizacion'];
                    if (!isset($gCodeMap[$cod])) continue;
                    $idPP = $gCodeMap[$cod]['id_pp'];
                    $pronMov[$idPP] = ($pronMov[$idPP] ?? 0) - (float)$r['qty'] * $gCodeMap[$cod]['factor'];
                }

                // Despachos (+) — solo los destinados a esta sucursal
                $stmtD = $conn->prepare("
                    SELECT sub.CodCotizacion, SUM(sub.Cantidad) as qty
                    FROM msaccess_masivo_SubPreIngresosPitaya sub
                    INNER JOIN msaccess_masivo_PreIngresoPitaya pre ON pre.CodPreIngresoPitaya = sub.CodPreIngresoPitaya
                    INNER JOIN SemanasSistema ss ON pre.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin
                    WHERE ss.numero_semana BETWEEN ? AND ? AND sub.CodCotizacion IN ($phCods)
                      AND pre.Destino REGEXP CONCAT('^[Pp]itaya[[:space:]]+', ?)
                    GROUP BY sub.CodCotizacion
                ");
                $stmtD->execute(array_merge([$semPronDesde, $semPronHasta], $allCodsPron, [$sucInt]));
                foreach ($stmtD->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $cod = (int)$r['CodCotizacion'];
                    if (!isset($gCodeMap[$cod])) continue;
                    $idPP = $gCodeMap[$cod]['id_pp'];
                    $pronMov[$idPP] = ($pronMov[$idPP] ?? 0) + (float)$r['qty'] * $gCodeMap[$cod]['factor'];
                }

                // Compras (+)
                $stmtC2 = $conn->prepare("
                    SELECT k.CodCotizacion, SUM(k.Cantidad) as qty
                    FROM msaccess_masivo_Compras k
                    INNER JOIN SemanasSistema ss ON k.Fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin
                    WHERE ss.numero_semana BETWEEN ? AND ? AND k.CodCotizacion IN ($phCods)
                      AND k.Sucursal = ?
                    GROUP BY k.CodCotizacion
                ");
                $stmtC2->execute(array_merge([$semPronDesde, $semPronHasta], $allCodsPron, [$sucInt]));
                foreach ($stmtC2->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $cod = (int)$r['CodCotizacion'];
                    if (!isset($gCodeMap[$cod])) continue;
                    $idPP = $gCodeMap[$cod]['id_pp'];
                    $pronMov[$idPP] = ($pronMov[$idPP] ?? 0) + (float)$r['qty'] * $gCodeMap[$cod]['factor'];
                }
            }

            // ── Consumo teórico del período de pronóstico ───────────────────
            // Usar $conAgg para semanas ya calculadas; semana_inv = separate pass
            $pronCons = []; // [id_pp => consumo_total_unidades]
            for ($s = $semPronDesde; $s <= $semPronHasta; $s++) {
                if (isset($conAgg) && $s >= $numDesde && $s <= $numHasta) {
                    // ya calculado en step 6
                    foreach ($conAgg as $idPPc => $semMap) {
                        if (isset($semMap[$s])) {
                            $pronCons[$idPPc] = ($pronCons[$idPPc] ?? 0) + $semMap[$s];
                        }
                    }
                } else {
                    // semana fuera del rango histórico (ej: semana_inv)
                    // Usamos consumo diario promedio calculado en step 9
                    foreach ($productos as $p2) {
                        $idPPc  = (int)$p2['id'];
                        $cdDia  = (float)($p2['_cons_diario'] ?? 0);
                        // 7 días de consumo diario (semana completa)
                        $pronCons[$idPPc] = ($pronCons[$idPPc] ?? 0) + $cdDia * 7;
                    }
                }
            }

            // ── Calcular pronóstico por producto ─────────────────────────────
            foreach ($productos as &$p) {
                $idPP = (int)$p['id'];
                $df   = (float)($p['despacho_factor'] ?? 1);
                if ($df <= 0) $df = 1;

                $invFisico = $pronInv[$idPP]  ?? 0.0; // unidades básicas
                $movTotal  = $pronMov[$idPP]  ?? 0.0;
                $consTotal = $pronCons[$idPP] ?? 0.0;

                $pronUnid = $invFisico + $movTotal - $consTotal;
                $p['_stock_pronostico'] = round($pronUnid / $df, 4);
            }
            unset($p);
        }
    }

    echo json_encode([
        'ok'                     => true,
        'rango_fechas_inv'       => $semInv,
        'n_semanas'              => $nSemanas,
        'factor_c'               => $factorC,
        'capacidad_c'            => $capC,
        'sum_smax_b'             => round($sumSMaxB, 4),
        'porcentajes'            => $configPct,
        'semana_corte_pronostico'=> $semCortePron,
        'productos'              => $productos,
    ]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
}
