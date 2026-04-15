<?php
/* ============================================================
   AJAX: Calcular pedido sugerido
   modulos/productos/ajax/pedido_sugerido_calcular.php

   Cadena de fórmulas:
   1. consumo_por_semana (VentasGlobalesAccessCSV × SubReceta, P1/P2/P3)
   2. promedio = SUM(consumos_N_semanas) / N
   3. desv_estandar_muestra (N-1)  → si N=1, desv=0
   4. consumo_semanal = promedio + desv_estandar
   5. consumo_diario  = (consumo_semanal × (1 + ajuste_demanda)) / 7
   6. stock_minimo    = consumo_diario × dias_stock_minimo
   7. stock_maximo    = consumo_diario × (dias_ciclo + dias_desfase + dias_stock_minimo)
   8. factor_congelados = capacidad_congelados / SUM(stock_maximo cat B)
   9. stock_maximo_ajustado (cat B) = stock_maximo × factor_congelados
   10. pedido_sugerido = stock_max_final − inventario_actual
   ============================================================ */

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');
set_time_limit(0);
ini_set('memory_limit', '256M');

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('pedido_sugerido', 'vista', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Sin permiso.']);
    exit();
}

/* ── Parámetros ────────────────────────────────────────────── */
$numDesde    = isset($_POST['semana_desde_num']) ? (int)$_POST['semana_desde_num'] : 0;
$numHasta    = isset($_POST['semana_hasta_num']) ? (int)$_POST['semana_hasta_num'] : 0;
$codSucursal = trim($_POST['cod_sucursal'] ?? '');

if (!$numDesde || !$numHasta || !$codSucursal) {
    echo json_encode(['ok' => false, 'msg' => 'Semanas y sucursal son obligatorias.']);
    exit();
}
$numDesde = min($numDesde, $numHasta);
$numHasta = max($numDesde, $numHasta);
$nSemanas = $numHasta - $numDesde + 1;   // Semanas totales en el filtro

/* ── Funciones auxiliares ───────────────────────────────────── */
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

function desviacionEstandarMuestra(array $valores): float {
    $n = count($valores);
    if ($n <= 1) return 0.0;
    $media    = array_sum($valores) / $n;
    $varianza = array_sum(array_map(fn($v) => ($v - $media) ** 2, $valores)) / ($n - 1);
    return sqrt($varianza);
}

try {
    /* ══════════════════════════════════════════════════════
       PASO 1: Validar rango de semanas en SemanasSistema
       ══════════════════════════════════════════════════════ */
    $stmtRango = $conn->prepare("
        SELECT MIN(fecha_inicio) AS fecha_desde, MAX(fecha_fin) AS fecha_hasta
        FROM SemanasSistema
        WHERE numero_semana BETWEEN :d AND :h
    ");
    $stmtRango->execute([':d' => $numDesde, ':h' => $numHasta]);
    $rango = $stmtRango->fetch(PDO::FETCH_ASSOC);

    if (!$rango || !$rango['fecha_desde']) {
        echo json_encode(['ok' => false, 'msg' => "No hay semanas {$numDesde}–{$numHasta} en el sistema."]);
        exit();
    }

    /* ══════════════════════════════════════════════════════
       PASO 2: Agregación de ventas × SubReceta (filtrado por
               una sola sucursal)
       ══════════════════════════════════════════════════════ */
    $sqlAgregado = "
        SELECT
            v.Semana             AS semana,
            sr.CodIngrediente    AS cod_ingrediente,
            sr.codporcion        AS codporcion,
            SUM(v.Cantidad * sr.Cantidad) AS cant_total
        FROM VentasGlobalesAccessCSV v
        INNER JOIN SubReceta sr ON sr.CodBatido = v.CodProducto
        WHERE v.Anulado = 0
          AND v.Fecha   BETWEEN :fecha_desde AND :fecha_hasta
          AND v.Semana  BETWEEN :sem_desde   AND :sem_hasta
          AND v.CodProducto IS NOT NULL
          AND v.local = :sucursal
        GROUP BY v.Semana, sr.CodIngrediente, sr.codporcion
        ORDER BY v.Semana ASC
    ";
    $stmtAgg = $conn->prepare($sqlAgregado);
    $stmtAgg->execute([
        ':fecha_desde' => $rango['fecha_desde'],
        ':fecha_hasta' => $rango['fecha_hasta'],
        ':sem_desde'   => $numDesde,
        ':sem_hasta'   => $numHasta,
        ':sucursal'    => $codSucursal,
    ]);
    $filas = $stmtAgg->fetchAll(PDO::FETCH_ASSOC);

    if (empty($filas)) {
        echo json_encode(['ok' => true, 'productos' => [], 'n_semanas' => $nSemanas,
            'factor_congelados' => null, 'capacidad_congelados' => null,
            'msg' => 'Sin ventas en el período para esta sucursal.']);
        exit();
    }

    /* ══════════════════════════════════════════════════════
       PASO 3: Pre-cargar todos los mapeos (bulk)
       ══════════════════════════════════════════════════════ */
    $codsIng = array_unique(array_column($filas, 'cod_ingrediente'));
    $phIng   = implode(',', array_fill(0, count($codsIng), '?'));

    // 3a. DBIngredientes (nombre + unidad)
    $stmtUnd = $conn->prepare("SELECT CodIngrediente, Nombre, Unidad FROM DBIngredientes WHERE CodIngrediente IN ($phIng)");
    $stmtUnd->execute(array_values($codsIng));
    $dbIngredientes = [];
    foreach ($stmtUnd->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $dbIngredientes[$row['CodIngrediente']] = $row;
    }

    // 3b. Cotizaciones (P2/P3)
    $stmtCot = $conn->prepare("
        SELECT CodIngrediente, CodCotizacion, Conversion, Prioridad, Subproducto, Marca
        FROM Cotizaciones
        WHERE CodIngrediente IN ($phIng)
          AND (Subproducto IS NULL OR Subproducto != 1)
          AND (Marca IS NULL OR Marca != 'Almacen Global')
        ORDER BY CodIngrediente, Conversion DESC, Prioridad ASC
    ");
    $stmtCot->execute(array_values($codsIng));
    $cotMap = [];
    foreach ($stmtCot->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $ci = $c['CodIngrediente'];
        if (!isset($cotMap[$ci])) $cotMap[$ci] = ['p2' => null, 'p3' => null];
        if ($c['Conversion'] == 1 && $c['Prioridad'] == 1 && !$cotMap[$ci]['p2']) {
            $cotMap[$ci]['p2'] = $c['CodCotizacion'];
        }
        if (!$cotMap[$ci]['p3']) {
            $cotMap[$ci]['p3'] = $c['CodCotizacion'];
        }
    }

    // 3c. Diccionario de productos (incluye categoria_insumo)
    $codCotBuscar = array_unique(array_filter(array_merge(
        array_column($filas, 'codporcion'),
        array_column($cotMap, 'p2'),
        array_column($cotMap, 'p3')
    )));
    $codCotBuscar = array_values(array_filter($codCotBuscar, fn($v) => $v !== null && $v !== ''));

    $diccionarioMap = [];
    if (!empty($codCotBuscar)) {
        $phCot   = implode(',', array_fill(0, count($codCotBuscar), '?'));
        $stmtDic = $conn->prepare("
            SELECT
                d.CodCotizacion,
                pp.id               AS id_presentacion,
                pp.cantidad         AS pp_cantidad,
                pp.Id_receta_producto,
                pp.id_producto_maestro AS id_maestro,
                pp.Nombre           AS nombre_presentacion,
                pp.categoria_insumo,
                u.id                AS id_unidad_erp,
                u.nombre            AS unidad_erp,
                u.abreviado         AS unidad_erp_abrev,
                u.nombres_opcionales,
                pm.Nombre           AS nombre_maestro
            FROM diccionario_productos_legado d
            INNER JOIN producto_presentacion pp ON pp.id = d.id_producto_presentacion
            LEFT  JOIN unidad_producto u        ON u.id  = pp.id_unidad_producto
            LEFT  JOIN producto_maestro pm      ON pm.id = pp.id_producto_maestro
            WHERE d.CodCotizacion IN ($phCot)
              AND pp.Activo = 'SI'
        ");
        $stmtDic->execute($codCotBuscar);
        foreach ($stmtDic->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $diccionarioMap[(string)$row['CodCotizacion']] = $row;
        }
    }

    // 3d. Todas las unidades
    $stmtAllU = $conn->prepare("SELECT id, nombre, abreviado, nombres_opcionales FROM unidad_producto");
    $stmtAllU->execute();
    $todasUnidades   = $stmtAllU->fetchAll(PDO::FETCH_ASSOC);
    $unidadPorNombre = [];
    $unidadPorId     = [];
    foreach ($todasUnidades as $u) {
        $uid = (int)$u['id'];
        $unidadPorNombre[strtolower(trim($u['nombre']))] = $uid;
        if ($u['abreviado']) $unidadPorNombre[strtolower(trim($u['abreviado']))] = $uid;
        if (!empty($u['nombres_opcionales'])) {
            foreach (preg_split('/[,;|]+/', $u['nombres_opcionales']) as $alias) {
                $ak = strtolower(trim($alias));
                if ($ak) $unidadPorNombre[$ak] = $uid;
            }
        }
        $unidadPorId[$uid] = $u;
    }

    // 3e. Conversiones de unidad
    $stmtConv  = $conn->prepare("SELECT id_unidad_producto_inicio, id_unidad_producto_final, cantidad FROM conversion_unidad_producto");
    $stmtConv->execute();
    $convIndex = [];
    foreach ($stmtConv->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $ini = (int)$c['id_unidad_producto_inicio'];
        $fin = (int)$c['id_unidad_producto_final'];
        $fac = (float)$c['cantidad'];
        $convIndex[$ini][$fin] = $fac;
        $convIndex[$fin][$ini] = ($fac != 0) ? 1 / $fac : 0;
    }

    // 3f. Presentaciones por maestro (para conversión Nivel 2)
    $idMaestrosDict    = array_unique(array_filter(array_column($diccionarioMap, 'id_maestro')));
    $presentPorMaestro = [];
    if (!empty($idMaestrosDict)) {
        $phMa   = implode(',', array_fill(0, count($idMaestrosDict), '?'));
        $stmtPP = $conn->prepare("
            SELECT pp.id, pp.id_producto_maestro, pp.cantidad AS pp_cantidad,
                   pp.id_unidad_producto, u.nombre AS unidad_nombre
            FROM producto_presentacion pp
            LEFT JOIN unidad_producto u ON u.id = pp.id_unidad_producto
            WHERE pp.id_producto_maestro IN ($phMa)
              AND pp.Id_receta_producto IS NULL
              AND pp.Activo = 'SI'
        ");
        $stmtPP->execute(array_values($idMaestrosDict));
        foreach ($stmtPP->fetchAll(PDO::FETCH_ASSOC) as $pp) {
            $presentPorMaestro[(int)$pp['id_producto_maestro']][(int)$pp['id_unidad_producto']] = $pp;
        }
    }

    /* ══════════════════════════════════════════════════════
       PASO 4: Resolver P1/P2/P3 y acumular consumo por [id_pp][semana]
       ══════════════════════════════════════════════════════ */
    $consumoAgg = [];   // [id_pp][semana] => float
    $metaPP     = [];   // [id_pp] => {nombre, unidad, categoria_insumo, ...}

    foreach ($filas as $fila) {
        $codIng    = $fila['cod_ingrediente'];
        $codporcion = $fila['codporcion'];
        $semana    = (int)$fila['semana'];
        $cantTotal = (float)$fila['cant_total'];

        // Resolver mapeo P1/P2/P3
        $mapeo = null;
        $esP1  = false;

        if (!empty($codporcion) && isset($diccionarioMap[(string)$codporcion])) {
            $mapeo = $diccionarioMap[(string)$codporcion];
            $esP1  = true;
        }
        if (!$mapeo && isset($cotMap[$codIng]['p2'])) {
            $ck = (string)$cotMap[$codIng]['p2'];
            if (isset($diccionarioMap[$ck])) $mapeo = $diccionarioMap[$ck];
        }
        if (!$mapeo && isset($cotMap[$codIng]['p3'])) {
            $ck = (string)$cotMap[$codIng]['p3'];
            if (isset($diccionarioMap[$ck])) $mapeo = $diccionarioMap[$ck];
        }
        if (!$mapeo) continue;  // Sin mapeo → omitir

        $esGlobal  = !empty($mapeo['Id_receta_producto']);
        $idPP      = (int)$mapeo['id_presentacion'];
        $ppCant    = max((float)$mapeo['pp_cantidad'], 0.001);
        $idMaestro = (int)$mapeo['id_maestro'];
        $idUnidERP = (int)$mapeo['id_unidad_erp'];

        if ($esGlobal) {
            $consumido = $cantTotal;
        } else {
            $unidadAccess = $dbIngredientes[$codIng]['Unidad'] ?? '';
            $idUnidAccess = resolverUnidadId_PS($unidadAccess, $unidadPorNombre);
            $factor = 1.0;

            if ($idUnidAccess && $idUnidAccess !== $idUnidERP) {
                $factorDir = resolverFactorConversion_PS($idUnidAccess, $idUnidERP, $convIndex);
                if ($factorDir !== null) {
                    $factor = $factorDir;
                } else {
                    $ppAlt = buscarPresentacionEnMaestro_PS($idMaestro, $idUnidAccess, $presentPorMaestro);
                    if ($ppAlt) {
                        $idPP   = (int)$ppAlt['id'];
                        $ppCant = max((float)$ppAlt['pp_cantidad'], 0.001);
                        $idUnidERP = (int)$ppAlt['id_unidad_producto'];
                        $factor = 1.0;
                    } elseif (isset($convIndex[$idUnidAccess])) {
                        foreach ($convIndex[$idUnidAccess] as $idDestino => $factorConv) {
                            $ppConv = buscarPresentacionEnMaestro_PS($idMaestro, $idDestino, $presentPorMaestro);
                            if ($ppConv) {
                                $idPP   = (int)$ppConv['id'];
                                $ppCant = max((float)$ppConv['pp_cantidad'], 0.001);
                                $idUnidERP = $idDestino;
                                $factor = $factorConv;
                                break;
                            }
                        }
                    }
                }
            }
            $consumido = ($cantTotal * $factor) / $ppCant;
            if ($esP1) $consumido = round($consumido * 2) / 2;
        }

        // Guardar metadata del producto
        if (!isset($metaPP[$idPP])) {
            $metaPP[$idPP] = [
                'nombre'          => $mapeo['nombre_presentacion'],
                'maestro'         => $mapeo['nombre_maestro'],
                'unidad'          => $unidadPorId[$idUnidERP]['abreviado'] ?? ($mapeo['unidad_erp_abrev'] ?? $mapeo['unidad_erp'] ?? ''),
                'categoria_insumo'=> $mapeo['categoria_insumo'] ?? null,
            ];
        }

        if (!isset($consumoAgg[$idPP]))           $consumoAgg[$idPP] = [];
        if (!isset($consumoAgg[$idPP][$semana]))  $consumoAgg[$idPP][$semana] = 0;
        $consumoAgg[$idPP][$semana] += $consumido;
    }

    /* ══════════════════════════════════════════════════════
       PASO 5: Cargar configuración logística de la sucursal
       ══════════════════════════════════════════════════════ */

    // 5a. Configuración de encabezado (dias_stock_minimo, capacidad_congelados)
    $stmtCLS = $conn->prepare("
        SELECT dias_stock_minimo, capacidad_congelados
        FROM configuracion_logistica_sucursal
        WHERE cod_sucursal = ?
        LIMIT 1
    ");
    $stmtCLS->execute([$codSucursal]);
    $configSuc = $stmtCLS->fetch(PDO::FETCH_ASSOC);

    $diasStockMinimo    = $configSuc ? (float)$configSuc['dias_stock_minimo']    : 0.0;
    $capacidadCongelados= $configSuc ? (float)$configSuc['capacidad_congelados'] : null;

    // 5b. Configuración por categoría (dias_ciclo, dias_desfase, ajuste_demanda)
    $stmtCLP = $conn->prepare("
        SELECT codigo_insumo, dias_ciclo, dias_desfase, dias_abastecimiento_despacho, ajuste_demanda
        FROM configuracion_logistica_producto
        WHERE cod_sucursal = ?
    ");
    $stmtCLP->execute([$codSucursal]);
    $configProd = [];  // [codigo_insumo] => {...}
    foreach ($stmtCLP->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $configProd[$row['codigo_insumo']] = $row;
    }

    /* ══════════════════════════════════════════════════════
       PASO 6: Cargar inventario actual (último registro por producto)
       ══════════════════════════════════════════════════════ */
    $stmtInv = $conn->prepare("
        SELECT i.id_producto_presentacion, i.cantidad, i.fecha_inventario
        FROM inventario i
        INNER JOIN (
            SELECT id_producto_presentacion, MAX(id) AS max_id
            FROM inventario
            WHERE cod_sucursal = ?
            GROUP BY id_producto_presentacion
        ) latest ON i.id = latest.max_id
        WHERE i.cod_sucursal = ?
    ");
    $stmtInv->execute([$codSucursal, $codSucursal]);
    $inventarioActual = [];
    foreach ($stmtInv->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $inventarioActual[(int)$row['id_producto_presentacion']] = (int)$row['cantidad'];
    }

    /* ══════════════════════════════════════════════════════
       PASO 7: Calcular estadísticas y fórmulas por producto
       ══════════════════════════════════════════════════════ */
    $listaProductos   = [];
    $sumStockMaxB     = 0.0;    // Para factor congelados (cat B)

    foreach ($consumoAgg as $idPP => $consumosPorSemana) {
        $meta = $metaPP[$idPP];

        // Construir array con N valores (incluye ceros para semanas sin consumo)
        $valoresSemanas = [];
        for ($s = $numDesde; $s <= $numHasta; $s++) {
            $valoresSemanas[] = (float)($consumosPorSemana[$s] ?? 0.0);
        }

        $promConsumo  = array_sum($valoresSemanas) / $nSemanas;
        $desvEstandar = desviacionEstandarMuestra($valoresSemanas);
        $consSemanal  = $promConsumo + $desvEstandar;

        // Parámetros logísticos de la categoría
        $catInsumo   = $meta['categoria_insumo'];
        $configCat   = $catInsumo ? ($configProd[$catInsumo] ?? null) : null;

        $ajusteDemanda = $configCat ? (float)$configCat['ajuste_demanda'] : 0.0;
        $diasCiclo     = $configCat ? (float)$configCat['dias_ciclo']     : 0.0;
        $diasDesfase   = $configCat ? (float)$configCat['dias_desfase']   : 0.0;

        $consDiario   = ($consSemanal * (1 + $ajusteDemanda)) / 7;
        $stockMinimo  = $consDiario * $diasStockMinimo;
        $stockMaximo  = $consDiario * ($diasCiclo + $diasDesfase + $diasStockMinimo);

        // Acumular para factor congelados (solo cat B)
        if ($catInsumo === 'B') {
            $sumStockMaxB += $stockMaximo;
        }

        $invActual = $inventarioActual[$idPP] ?? null;

        $listaProductos[$idPP] = [
            'id_pp'            => $idPP,
            'nombre'           => $meta['nombre'],
            'unidad'           => $meta['unidad'],
            'categoria_insumo' => $catInsumo,
            'prom_consumo'     => round($promConsumo, 4),
            'desv_estandar'    => round($desvEstandar, 4),
            'cons_semanal'     => round($consSemanal, 4),
            'ajuste_demanda'   => $ajusteDemanda,
            'cons_diario'      => round($consDiario, 6),
            'stock_minimo'     => round($stockMinimo, 4),
            'stock_maximo'     => round($stockMaximo, 4),
            // stock_max_final y es_ajustado se rellenan después de calcular el factor_congelados
            'stock_max_final'  => null,
            'es_ajustado'      => false,
            'inventario_actual'=> $invActual,
            'pedido_sugerido'  => null,
            '_tiene_config'    => $configCat !== null,
        ];
    }

    /* ══════════════════════════════════════════════════════
       PASO 8: Calcular factor_congelados y aplicar ajuste a cat B
       ══════════════════════════════════════════════════════ */
    $factorCongelados = null;
    if ($capacidadCongelados !== null && $sumStockMaxB > 0) {
        $factorCongelados = $capacidadCongelados / $sumStockMaxB;
    }

    foreach ($listaProductos as $idPP => &$prod) {
        $catInsumo = $prod['categoria_insumo'];
        $stockMax  = $prod['stock_maximo'];

        if ($catInsumo === 'B' && $factorCongelados !== null) {
            $prod['stock_max_final'] = round($stockMax * $factorCongelados, 4);
            $prod['es_ajustado']     = true;
        } else {
            // Para productos sin config o sin stock_maximo significativo, aún damos el valor base
            $prod['stock_max_final'] = $prod['_tiene_config'] ? round($stockMax, 4) : null;
            $prod['es_ajustado']     = false;
        }

        // Calcular pedido sugerido si tenemos todo
        if ($prod['stock_max_final'] !== null && $prod['inventario_actual'] !== null) {
            $prod['pedido_sugerido'] = round($prod['stock_max_final'] - $prod['inventario_actual'], 4);
        }

        unset($prod['_tiene_config']); // limpiar campo interno
    }
    unset($prod);

    // Ordenar: por categoria_insumo → por nombre
    usort($listaProductos, function ($a, $b) {
        $catA = $a['categoria_insumo'] ?? 'Z';
        $catB = $b['categoria_insumo'] ?? 'Z';
        if ($catA !== $catB) return strcmp($catA, $catB);
        return strcmp($a['nombre'], $b['nombre']);
    });

    /* ══════════════════════════════════════════════════════
       PASO 9: Respuesta final
       ══════════════════════════════════════════════════════ */
    echo json_encode([
        'ok'                   => true,
        'productos'            => array_values($listaProductos),
        'n_semanas'            => $nSemanas,
        'factor_congelados'    => $factorCongelados !== null ? round($factorCongelados, 6) : null,
        'capacidad_congelados' => $capacidadCongelados,
        'sum_stock_max_b'      => round($sumStockMaxB, 4),
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error al calcular: ' . $e->getMessage()]);
}
