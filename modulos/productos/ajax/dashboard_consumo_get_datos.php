<?php
/* ============================================================
   AJAX: Obtener datos de consumo — Versión Optimizada (bulk)
   modulos/productos/ajax/dashboard_consumo_get_datos.php

   Estrategia anti-timeout:
     1. Una sola query SQL agrega el consumo por
        (CodIngrediente, codporcion, sucursal, semana).
     2. Se pre-cargan todos los mapeos en bulk (8-10 queries).
     3. El loop PHP solo hace lookups en arrays en memoria.
   ============================================================ */
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');
set_time_limit(0);    // Sin límite: dejamos que MySQL haga el trabajo pesado
ini_set('memory_limit', '256M');

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('dashboard_consumo_insumos', 'vista', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Sin permiso']);
    exit();
}

/* ── Parámetros ────────────────────────────────────────────── */
$numDesde       = isset($_POST['semana_desde_num']) ? (int)$_POST['semana_desde_num'] : 0;
$numHasta       = isset($_POST['semana_hasta_num']) ? (int)$_POST['semana_hasta_num'] : 0;
$sucursalesPost = isset($_POST['sucursales'])       ? (array)$_POST['sucursales']     : [];
$idInsumo       = isset($_POST['id_insumo'])        ? (int)$_POST['id_insumo']        : 0;

if (!$numDesde || !$numHasta) {
    echo json_encode(['ok' => false, 'msg' => 'Ingresa los números de semana de inicio y fin.']);
    exit();
}
$numDesde = min($numDesde, $numHasta);
$numHasta = max($numDesde, $numHasta);

try {
    /* ══════════════════════════════════════════════════════════
       PASO 1: Rango de fechas y semanas (solo 2 queries)
       ══════════════════════════════════════════════════════════ */
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

    $stmtSems = $conn->prepare("
        SELECT id, numero_semana, anio, fecha_inicio, fecha_fin
        FROM SemanasSistema
        WHERE numero_semana BETWEEN :d AND :h
        ORDER BY numero_semana ASC
    ");
    $stmtSems->execute([':d' => $numDesde, ':h' => $numHasta]);
    $semanasRango = $stmtSems->fetchAll(PDO::FETCH_ASSOC);
    $semanasMap   = [];
    foreach ($semanasRango as $s) {
        $semanasMap[(int)$s['numero_semana']] = $s;
    }

    /* ══════════════════════════════════════════════════════════
       PASO 2: Agregación principal en SQL
       Una sola query que une Ventas × SubReceta y devuelve
       consumo por (CodIngrediente, codporcion, sucursal, semana)
       ══════════════════════════════════════════════════════════ */
    $whereSuc = '';
    $paramsSql = [
        ':fecha_desde' => $rango['fecha_desde'],
        ':fecha_hasta' => $rango['fecha_hasta'],
        ':sem_desde'   => $numDesde,
        ':sem_hasta'   => $numHasta,
    ];
    if (!empty($sucursalesPost)) {
        $phSuc = [];
        foreach ($sucursalesPost as $i => $s) {
            $k = ':suc' . $i;
            $phSuc[]      = $k;
            $paramsSql[$k] = $s;
        }
        $whereSuc = ' AND v.local IN (' . implode(',', $phSuc) . ')';
    }

    $sqlAgregado = "
        SELECT
            v.local              AS sucursal,
            v.Semana             AS semana,
            sr.CodIngrediente    AS cod_ingrediente,
            sr.codporcion        AS codporcion,
            SUM(v.Cantidad * sr.Cantidad) AS cant_total
        FROM VentasGlobalesAccessCSV v
        INNER JOIN SubReceta sr ON sr.CodBatido = v.CodProducto
        WHERE v.Anulado = 0
          AND v.Fecha BETWEEN :fecha_desde AND :fecha_hasta
          AND v.Semana BETWEEN :sem_desde AND :sem_hasta
          AND v.CodProducto IS NOT NULL
          $whereSuc
        GROUP BY v.local, v.Semana, sr.CodIngrediente, sr.codporcion
        ORDER BY v.Semana ASC
    ";
    $stmtAgg = $conn->prepare($sqlAgregado);
    $stmtAgg->execute($paramsSql);
    $filas = $stmtAgg->fetchAll(PDO::FETCH_ASSOC);

    if (empty($filas)) {
        echo json_encode([
            'ok'        => true,
            'msg'       => 'Sin ventas válidas en el período.',
            'consumo'   => [],
            'sin_mapeo' => [],
            'semanas'   => array_values($semanasRango),
            'sucursales'=> [],
        ]);
        exit();
    }

    /* ══════════════════════════════════════════════════════════
       PASO 3: Pre-cargar todos los mapeos en BULK
       ══════════════════════════════════════════════════════════ */

    // 3a. Ingredientes únicos
    $codsIng = array_unique(array_column($filas, 'cod_ingrediente'));
    $phIng   = implode(',', array_fill(0, count($codsIng), '?'));

    // 3b. Unidades de ingredientes (DBIngredientes)
    $stmtUnd = $conn->prepare("
        SELECT CodIngrediente, Nombre, Unidad
        FROM DBIngredientes
        WHERE CodIngrediente IN ($phIng)
    ");
    $stmtUnd->execute(array_values($codsIng));
    $dbIngredientes = [];
    foreach ($stmtUnd->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $dbIngredientes[$row['CodIngrediente']] = $row;
    }

    // 3c. Cotizaciones (P2 = Conversion+Prioridad, P3 = fallback)
    $stmtCot = $conn->prepare("
        SELECT CodIngrediente, CodCotizacion, Conversion, Prioridad, Subproducto, Marca
        FROM Cotizaciones
        WHERE CodIngrediente IN ($phIng)
          AND (Subproducto IS NULL OR Subproducto != 1)
          AND (Marca IS NULL OR Marca != 'Almacen Global')
        ORDER BY CodIngrediente, Conversion DESC, Prioridad ASC
    ");
    $stmtCot->execute(array_values($codsIng));
    // Indexar: $cotMap[CodIngrediente] = ['p2' => CodCotizacion, 'p3' => CodCotizacion]
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

    // 3d. Recolectar todos los CodCotizacion a buscar en el diccionario
    $codCotBuscar = array_unique(array_filter(array_merge(
        array_column($filas, 'codporcion'),         // P1
        array_column($cotMap, 'p2'),                 // P2
        array_column($cotMap, 'p3')                  // P3
    )));
    $codCotBuscar = array_filter($codCotBuscar, fn($v) => $v !== null && $v !== '');

    $diccionarioMap = []; // [CodCotizacion] => {id, cantidad, Id_receta_producto, unidad_erp, id_maestro, id_unidad_erp, ...}
    if (!empty($codCotBuscar)) {
        $phCot = implode(',', array_fill(0, count($codCotBuscar), '?'));
        $stmtDic = $conn->prepare("
            SELECT
                d.CodCotizacion,
                pp.id               AS id_presentacion,
                pp.cantidad         AS pp_cantidad,
                pp.Id_receta_producto,
                pp.id_producto_maestro AS id_maestro,
                pp.Nombre           AS nombre_presentacion,
                u.id                AS id_unidad_erp,
                u.nombre            AS unidad_erp,
                u.abreviado         AS unidad_erp_abrev,
                u.nombres_opcionales,
                pm.Nombre           AS nombre_maestro
            FROM diccionario_productos_legado d
            INNER JOIN producto_presentacion pp ON pp.id = d.id_producto_presentacion
            LEFT  JOIN unidad_producto u         ON u.id  = pp.id_unidad_producto
            LEFT  JOIN producto_maestro pm       ON pm.id = pp.id_producto_maestro
            WHERE d.CodCotizacion IN ($phCot)
              AND pp.Activo = 'SI'
        ");
        $stmtDic->execute(array_values($codCotBuscar));
        foreach ($stmtDic->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $diccionarioMap[(string)$row['CodCotizacion']] = $row;
        }
    }

    // 3e. Pre-cargar TODAS las unidades (tabla pequeña, < 50 filas normalmente)
    $stmtAllU = $conn->prepare("SELECT id, nombre, abreviado, nombres_opcionales FROM unidad_producto");
    $stmtAllU->execute();
    $todasUnidades = $stmtAllU->fetchAll(PDO::FETCH_ASSOC);

    // Crear índice: nombre/abreviado/alias → id
    $unidadPorNombre = [];
    foreach ($todasUnidades as $u) {
        $uid  = (int)$u['id'];
        $unom = strtolower(trim($u['nombre']));
        $uabr = strtolower(trim($u['abreviado'] ?? ''));
        $unidadPorNombre[$unom] = $uid;
        if ($uabr) $unidadPorNombre[$uabr] = $uid;
        if (!empty($u['nombres_opcionales'])) {
            foreach (preg_split('/[,;|]+/', $u['nombres_opcionales']) as $alias) {
                $ak = strtolower(trim($alias));
                if ($ak) $unidadPorNombre[$ak] = $uid;
            }
        }
    }
    // Índice por id para lookup rápido
    $unidadPorId = [];
    foreach ($todasUnidades as $u) {
        $unidadPorId[(int)$u['id']] = $u;
    }

    // 3f. Pre-cargar TODAS las conversiones (tabla pequeña)
    $stmtConv = $conn->prepare("
        SELECT id_unidad_producto_inicio, id_unidad_producto_final, cantidad
        FROM conversion_unidad_producto
    ");
    $stmtConv->execute();
    // Índice: [inicio][final] => factor, [final][inicio] => 1/factor
    $convIndex = [];
    foreach ($stmtConv->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $ini = (int)$c['id_unidad_producto_inicio'];
        $fin = (int)$c['id_unidad_producto_final'];
        $fac = (float)$c['cantidad'];
        $convIndex[$ini][$fin] = $fac;
        $convIndex[$fin][$ini] = ($fac != 0) ? 1 / $fac : 0;
    }

    // 3g. Pre-cargar presentaciones por maestro (Nivel AUTO)
    // Todas las presentaciones simples (no globales) de los maestros relevantes
    $idMaestrosDict = array_unique(array_filter(array_column($diccionarioMap, 'id_maestro')));
    $presentPorMaestro = []; // [id_maestro][id_unidad] => {id_presentacion, pp_cantidad, unidad}
    if (!empty($idMaestrosDict)) {
        $phMa = implode(',', array_fill(0, count($idMaestrosDict), '?'));
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

    /* ══════════════════════════════════════════════════════════
       PASO 4: Función de resolución de unidades (PHP puro)
       Solo usa los arrays pre-cargados — sin queries
       ══════════════════════════════════════════════════════════ */
    function resolverUnidadId($nombreAccess, &$unidadPorNombre) {
        $k = strtolower(trim($nombreAccess));
        return $unidadPorNombre[$k] ?? null;
    }

    function resolverFactorConversion($idOrigen, $idDestino, &$convIndex) {
        if ($idOrigen === $idDestino) return 1.0;
        return $convIndex[$idOrigen][$idDestino] ?? null;
    }

    function buscarPresentacionEnMaestro($idMaestro, $idUnidad, &$presentPorMaestro) {
        return $presentPorMaestro[$idMaestro][$idUnidad] ?? null;
    }

    /* ══════════════════════════════════════════════════════════
       PASO 5: Loop de resolución — PHP puro (sin DB)
       ══════════════════════════════════════════════════════════ */
    $consumoAgg    = [];   // [id_pp][sucursal][semana] => float
    $metaPP        = [];   // [id_pp] => {nombre, maestro, unidad, es_global}
    $esP1Map       = [];   // [id_pp] => bool  (true si TODOS sus mapeos vinieron de P1)
    $sinMapeo      = [];
    $sinMapeoSet   = [];
    $sucursalesSet = [];

    // Pre-cargar nombres de sucursales (codigo => nombre)
    $stmtNombresSuc = $conn->prepare("
        SELECT codigo, nombre FROM sucursales
    ");
    $stmtNombresSuc->execute();
    $nombresSucursales = [];  // [codigo] => nombre
    foreach ($stmtNombresSuc->fetchAll(PDO::FETCH_ASSOC) as $sRow) {
        $nombresSucursales[$sRow['codigo']] = $sRow['nombre'];
    }

    foreach ($filas as $fila) {
        $codIng    = $fila['cod_ingrediente'];
        $codporcion = $fila['codporcion'];
        $sucursal  = $fila['sucursal'];
        $semana    = (int)$fila['semana'];
        $cantTotal = (float)$fila['cant_total'];

        $sucursalesSet[$sucursal] = true;

        // ── Resolver mapeo (P1 / P2 / P3) ──
        $mapeo  = null;
        $esP1   = false;   // P1 = porción mapeada → redondear al 0.5 más cercano

        // P1: codporcion directo
        if (!empty($codporcion) && isset($diccionarioMap[(string)$codporcion])) {
            $mapeo = $diccionarioMap[(string)$codporcion];
            $esP1  = true;
        }
        // P2: cotización base
        if (!$mapeo && isset($cotMap[$codIng]['p2'])) {
            $ck = (string)$cotMap[$codIng]['p2'];
            if (isset($diccionarioMap[$ck])) $mapeo = $diccionarioMap[$ck];
        }
        // P3: fallback
        if (!$mapeo && isset($cotMap[$codIng]['p3'])) {
            $ck = (string)$cotMap[$codIng]['p3'];
            if (isset($diccionarioMap[$ck])) $mapeo = $diccionarioMap[$ck];
        }

        // Sin mapeo
        if (!$mapeo) {
            if (!isset($sinMapeoSet[$codIng])) {
                $sinMapeoSet[$codIng] = true;
                $sinMapeo[] = [
                    'cod_ingrediente'  => $codIng,
                    'nombre'           => $dbIngredientes[$codIng]['Nombre'] ?? 'Desconocido',
                    'unidad_access'    => $dbIngredientes[$codIng]['Unidad'] ?? '',
                    'num_productos'    => 1,
                    'ventas_afectadas' => round($cantTotal, 2),
                ];
            } else {
                foreach ($sinMapeo as &$sm) {
                    if ($sm['cod_ingrediente'] === $codIng) {
                        $sm['ventas_afectadas'] += $cantTotal;
                        break;
                    }
                }
                unset($sm);
            }
            continue;
        }

        // ── ¿Receta global? ─────────────────────────────────
        $esGlobal  = !empty($mapeo['Id_receta_producto']);
        $idPP      = (int)$mapeo['id_presentacion'];
        $ppCant    = max((float)$mapeo['pp_cantidad'], 0.001);
        $idMaestro = (int)$mapeo['id_maestro'];
        $idUnidERP = (int)$mapeo['id_unidad_erp'];

        // Filtro opcional por insumo
        if ($idInsumo > 0 && $idPP !== $idInsumo) continue;

        if ($esGlobal) {
            // Fórmula global: consumo = cant_total (ya es sum(ventas * cantidad_receta))
            $consumido = $cantTotal;
        } else {
            // ── Resolver conversión de unidad ────────────────
            $unidadAccess = $dbIngredientes[$codIng]['Unidad'] ?? '';
            $idUnidAccess = resolverUnidadId($unidadAccess, $unidadPorNombre);
            $factor = 1.0;

            if ($idUnidAccess && $idUnidAccess !== $idUnidERP) {
                // Nivel 1: conversión directa
                $factorDir = resolverFactorConversion($idUnidAccess, $idUnidERP, $convIndex);
                if ($factorDir !== null) {
                    $factor = $factorDir;
                } else {
                    // Nivel 2: buscar presentación del maestro con unidad de access
                    $ppAlt = buscarPresentacionEnMaestro($idMaestro, $idUnidAccess, $presentPorMaestro);
                    if ($ppAlt) {
                        $idPP   = (int)$ppAlt['id'];
                        $ppCant = max((float)$ppAlt['pp_cantidad'], 0.001);
                        $idUnidERP = (int)$ppAlt['id_unidad_producto'];
                        $factor = 1.0;
                    } else {
                        // Nivel 3: buscar via conversiones disponibles para el maestro
                        if (isset($convIndex[$idUnidAccess])) {
                            foreach ($convIndex[$idUnidAccess] as $idDestino => $factorConv) {
                                $ppConv = buscarPresentacionEnMaestro($idMaestro, $idDestino, $presentPorMaestro);
                                if ($ppConv) {
                                    $idPP   = (int)$ppConv['id'];
                                    $ppCant = max((float)$ppConv['pp_cantidad'], 0.001);
                                    $idUnidERP = $idDestino;
                                    $factor = $factorConv;
                                    break;
                                }
                            }
                        }
                        // Si no hay conversión, continúa con factor=1 (mejor que nada)
                    }
                }
            }

            // Fórmula: consumo = (cant_total * factor) / pp_cantidad
            $consumido = ($cantTotal * $factor) / $ppCant;

            // P1: porciones físicas → solo enteros o mitades (redondear al 0.5 más cercano)
            if ($esP1) {
                $consumido = round($consumido * 2) / 2;
            }
        }

        // ── Guardar metadata ──────────────────────────────────
        if (!isset($metaPP[$idPP])) {
            $metaPP[$idPP] = [
                'id'       => $idPP,
                'nombre'   => $mapeo['nombre_presentacion'],
                'maestro'  => $mapeo['nombre_maestro'],
                'unidad'   => $unidadPorId[$idUnidERP]['nombre'] ?? $mapeo['unidad_erp'] ?? '',
                'es_global'=> $esGlobal,
            ];
            $esP1Map[$idPP] = $esP1;  // inicializar con el primer mapeo
        } else {
            // Si alguna fila del mismo idPP NO es P1, el item deja de ser puro P1
            if (!$esP1) $esP1Map[$idPP] = false;
        }

        if (!isset($consumoAgg[$idPP]))             $consumoAgg[$idPP] = [];
        if (!isset($consumoAgg[$idPP][$sucursal]))  $consumoAgg[$idPP][$sucursal] = [];
        if (!isset($consumoAgg[$idPP][$sucursal][$semana])) $consumoAgg[$idPP][$sucursal][$semana] = 0;
        $consumoAgg[$idPP][$sucursal][$semana] += $consumido;
    }

    /* ══════════════════════════════════════════════════════════
       PASO 6: Calcular estadísticas y construir respuesta
       ══════════════════════════════════════════════════════════ */
    $sucursalesPresentes = array_keys($sucursalesSet);
    $semanasNros         = array_keys($semanasMap);

    $listaConsumo = [];
    foreach ($consumoAgg as $idPP => $porSuc) {
        $meta  = $metaPP[$idPP];
        $itemEsP1 = !empty($esP1Map[$idPP]);   // ¿todas las filas de este idPP son P1?

        // Función local de redondeo: P1 → 0.5, resto → 4 decimales
        $rnd = fn($v) => $itemEsP1 ? (round($v * 2) / 2) : round($v, 4);

        $totalGeneral  = 0;
        $consPorSem    = [];
        $porSucRes     = [];

        foreach ($porSuc as $suc => $porSem) {
            $totSuc = 0;
            foreach ($porSem as $sem => $c) {
                $totalGeneral += $c;
                $totSuc       += $c;
                $consPorSem[$sem] = ($consPorSem[$sem] ?? 0) + $c;
            }
            $porSucRes[$suc] = $rnd($totSuc);
        }

        // Redondear acumulados por semana al 0.5 si es P1
        foreach ($consPorSem as $s => $c) {
            $consPorSem[$s] = $rnd($c);
        }

        $semanaPico = null; $semanaLow = null;
        $maxC = -1; $minC = PHP_INT_MAX;
        foreach ($consPorSem as $s => $c) {
            if ($c > $maxC) { $maxC = $c; $semanaPico = $s; }
            if ($c < $minC) { $minC = $c; $semanaLow  = $s; }
        }

        $totalGeneral = $rnd($totalGeneral);
        $n          = count($consPorSem);
        $promSemana = $n > 0 ? $totalGeneral / $n : 0;

        // Tendencia
        $tendencia = 'flat';
        if ($n >= 2) {
            $ks  = array_keys($consPorSem); sort($ks);
            $mid = (int)($n / 2);
            $p1  = array_sum(array_intersect_key($consPorSem, array_flip(array_slice($ks, 0, $mid)))) / max($mid, 1);
            $p2  = array_sum(array_intersect_key($consPorSem, array_flip(array_slice($ks, $mid))))   / max($n - $mid, 1);
            if ($p2 > $p1 * 1.05) $tendencia = 'up';
            elseif ($p2 < $p1 * 0.95) $tendencia = 'down';
        }

        // Desglose semana × sucursal (redondeado al 0.5 si es P1)
        $desgloseSemsuc = [];
        foreach ($semanasNros as $sem) {
            $desgloseSemsuc[$sem] = [];
            foreach ($sucursalesPresentes as $suc) {
                $desgloseSemsuc[$sem][$suc] = $rnd((float)($consumoAgg[$idPP][$suc][$sem] ?? 0));
            }
        }

        $listaConsumo[] = [
            'id'               => $idPP,
            'nombre'           => $meta['nombre'],
            'maestro'          => $meta['maestro'],
            'unidad'           => $meta['unidad'],
            'es_global'        => (bool)$meta['es_global'],
            'es_p1'            => $itemEsP1,
            'total'            => $totalGeneral,
            'prom_semana'      => round($promSemana, $itemEsP1 ? 1 : 4),
            'proyeccion_4sem'  => round($promSemana * 4, $itemEsP1 ? 1 : 4),
            'stock_min'        => round($promSemana, $itemEsP1 ? 1 : 4),
            'stock_max'        => round($promSemana * 2, $itemEsP1 ? 1 : 4),
            'semana_pico_num'  => $semanaPico,
            'semana_low_num'   => $semanaLow,
            'max_consumo_sem'  => $rnd($maxC),
            'tendencia'        => $tendencia,
            'por_semana'       => $consPorSem,
            'por_sucursal'     => $porSucRes,
            'desglose_semxsuc' => $desgloseSemsuc,
        ];
    }

    usort($listaConsumo, fn($a, $b) => $b['total'] <=> $a['total']);

    // Estadísticas globales
    $totalGeneral = array_sum(array_column($listaConsumo, 'total'));
    $proyTotal    = array_sum(array_column($listaConsumo, 'proyeccion_4sem'));
    $sumasPorSem  = [];
    foreach ($listaConsumo as $it) {
        foreach ($it['por_semana'] as $s => $c) {
            $sumasPorSem[$s] = ($sumasPorSem[$s] ?? 0) + $c;
        }
    }
    $picoGlobal = !empty($sumasPorSem) ? array_search(max($sumasPorSem), $sumasPorSem) : null;

    // Construir mapa codigo => nombre para las sucursales presentes
    $sucursalesNombresMap = [];
    foreach ($sucursalesPresentes as $cod) {
        $sucursalesNombresMap[$cod] = $nombresSucursales[$cod] ?? $cod;
    }

    echo json_encode([
        'ok'                 => true,
        'consumo'            => $listaConsumo,
        'sin_mapeo'          => $sinMapeo,
        'semanas'            => array_values($semanasRango),
        'sucursales'         => $sucursalesPresentes,
        'sucursales_nombres' => $sucursalesNombresMap,
        'total_general'      => round($totalGeneral, 4),
        'proyeccion_total'   => round($proyTotal, 4),
        'semana_pico_global' => $picoGlobal,
        'num_sin_mapeo'      => count($sinMapeo),
        'num_insumos'        => count($listaConsumo),
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error al calcular consumo: ' . $e->getMessage()]);
}
