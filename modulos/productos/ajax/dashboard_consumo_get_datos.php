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
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');
set_time_limit(0);    // Sin límite: dejamos que MySQL haga el trabajo pesado
ini_set('memory_limit', '256M');

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('dashboard_consumo_insumos', 'vista', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Sin permiso']);
    exit();
}

/* ── Parámetros ────────────────────────────────────────────── */
$numDesde = isset($_POST['semana_desde_num']) ? (int) $_POST['semana_desde_num'] : 0;
$numHasta = isset($_POST['semana_hasta_num']) ? (int) $_POST['semana_hasta_num'] : 0;
$sucursalesPost = isset($_POST['sucursales']) ? (array) $_POST['sucursales'] : [];
$idInsumo = isset($_POST['id_insumo']) ? (int) $_POST['id_insumo'] : 0;

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
    $semanasMap = [];
    foreach ($semanasRango as $s) {
        $semanasMap[(int) $s['numero_semana']] = $s;
    }

    /* ══════════════════════════════════════════════════════════
       PASO 2: Agregación principal en SQL
       Una sola query que une Ventas × SubReceta y devuelve
       consumo por (CodIngrediente, codporcion, sucursal, semana)
       ══════════════════════════════════════════════════════════ */
    // ── Detectar semana actual incompleta ────────────────────────────────
    $hoy = date('Y-m-d');
    $ayer = date('Y-m-d', strtotime('-1 day'));
    $semanaHastaIncompleta = ($rango['fecha_hasta'] > $ayer);

    $fechaFinQuery = $semanaHastaIncompleta ? $ayer : $rango['fecha_hasta'];

    $whereSuc = '';
    $paramsSql = [
        ':fecha_desde' => $rango['fecha_desde'],
        ':fecha_hasta' => $fechaFinQuery,
        ':sem_desde' => $numDesde,
        ':sem_hasta' => $numHasta,
    ];
    if (!empty($sucursalesPost)) {
        $phSuc = [];
        foreach ($sucursalesPost as $i => $s) {
            $k = ':suc' . $i;
            $phSuc[] = $k;
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
          AND (sr.codporcion IS NULL OR sr.codporcion NOT IN (
              SELECT CodCotizacionPorcion
              FROM MezclaPorcionesAccess
              WHERE CodCotizacionPorcion IS NOT NULL
          ))
          $whereSuc
        GROUP BY v.local, v.Semana, sr.CodIngrediente, sr.codporcion
        ORDER BY v.Semana ASC
    ";
    $stmtAgg = $conn->prepare($sqlAgregado);
    $stmtAgg->execute($paramsSql);
    $filas = $stmtAgg->fetchAll(PDO::FETCH_ASSOC);

    if (empty($filas)) {
        echo json_encode([
            'ok' => true,
            'msg' => 'Sin ventas válidas en el período.',
            'consumo' => [],
            'sin_mapeo' => [],
            'semanas' => array_values($semanasRango),
            'sucursales' => [],
        ]);
        exit();
    }

    /* ══════════════════════════════════════════════════════════
       PASO 3: Pre-cargar todos los mapeos en BULK
       ══════════════════════════════════════════════════════════ */

    // 3a. Ingredientes únicos
    $codsIng = array_unique(array_column($filas, 'cod_ingrediente'));
    $phIng = implode(',', array_fill(0, count($codsIng), '?'));

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
        if (!isset($cotMap[$ci]))
            $cotMap[$ci] = ['p2' => null, 'p3' => null];
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

        /*
         * Estrategia de resolución del diccionario (replica el comportamiento "AUTO" del visor):
         *
         * 1. Primero intentamos el mapeo directo con presentacion_basica_inventario = 1
         *    (caso normal: el CodCotizacion ya apunta a la presentación de uso).
         *
         * 2. Si el CodCotizacion del diccionario apunta a una presentación que NO es básica
         *    (ej: el pote de despacho 1.36kg), obtenemos el id_producto_maestro de esa
         *    presentación y desde ahí buscamos la presentación básica del mismo maestro —
         *    exactamente el rastreo "AUTO" que hace el visor de recetas.
         */
        // Paso A: mapeo directo (presentacion_basica_inventario = 1)
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
                pm.Nombre           AS nombre_maestro,
                pp.categoria_insumo
            FROM diccionario_productos_legado d
            INNER JOIN producto_presentacion pp ON pp.id = d.id_producto_presentacion
            LEFT  JOIN unidad_producto u         ON u.id  = pp.id_unidad_producto
            LEFT  JOIN producto_maestro pm       ON pm.id = pp.id_producto_maestro
            WHERE d.CodCotizacion IN ($phCot)
              AND pp.Activo = 'SI'
              AND pp.presentacion_basica_inventario = 1
        ");
        $stmtDic->execute(array_values($codCotBuscar));
        foreach ($stmtDic->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $diccionarioMap[(string) $row['CodCotizacion']] = $row;
        }

        // Paso B: rastreo directo por maestro de la presentación mapeada
        // Funciona cuando pp_orig tiene id_producto_maestro asignado.
        $codNoResueltos = array_values(array_filter($codCotBuscar, function ($c) use (&$diccionarioMap) {
            return !isset($diccionarioMap[(string) $c]);
        }));
        if (!empty($codNoResueltos)) {
            $phNR = implode(',', array_fill(0, count($codNoResueltos), '?'));
            $stmtAuto = $conn->prepare("
                SELECT
                    d.CodCotizacion,
                    pp_base.id               AS id_presentacion,
                    pp_base.cantidad         AS pp_cantidad,
                    pp_base.Id_receta_producto,
                    pp_base.id_producto_maestro AS id_maestro,
                    pp_base.Nombre           AS nombre_presentacion,
                    u_base.id                AS id_unidad_erp,
                    u_base.nombre            AS unidad_erp,
                    u_base.abreviado         AS unidad_erp_abrev,
                    u_base.nombres_opcionales,
                    pm.Nombre                AS nombre_maestro,
                    pp_base.categoria_insumo
                FROM diccionario_productos_legado d
                INNER JOIN producto_presentacion pp_orig ON pp_orig.id = d.id_producto_presentacion
                INNER JOIN producto_presentacion pp_base
                        ON pp_base.id_producto_maestro = pp_orig.id_producto_maestro
                       AND pp_base.presentacion_basica_inventario = 1
                       AND pp_base.Activo = 'SI'
                       AND pp_base.Id_receta_producto IS NULL
                LEFT  JOIN unidad_producto u_base ON u_base.id = pp_base.id_unidad_producto
                LEFT  JOIN producto_maestro pm    ON pm.id = pp_base.id_producto_maestro
                WHERE d.CodCotizacion IN ($phNR)
                  AND pp_orig.Activo = 'SI'
                  AND pp_orig.id_producto_maestro IS NOT NULL
                GROUP BY d.CodCotizacion
            ");
            $stmtAuto->execute(array_values($codNoResueltos));
            foreach ($stmtAuto->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $diccionarioMap[(string) $row['CodCotizacion']] = $row;
            }
        }

        // Paso C: fallback vía CodIngrediente (replica exacta del AUTO del visor).
        // Cubre el caso donde pp_orig.id_producto_maestro es NULL (ej: Maní 1lb sin maestro FK).
        // Traza: CodCotizacion → Cotizaciones.CodIngrediente → todas las cotizaciones del mismo
        // ingrediente → diccionario → cualquier presentación → id_producto_maestro → basica.
        $codAunSinResolver = array_values(array_filter($codCotBuscar, function ($c) use (&$diccionarioMap) {
            return !isset($diccionarioMap[(string) $c]);
        }));
        if (!empty($codAunSinResolver)) {
            $phC = implode(',', array_fill(0, count($codAunSinResolver), '?'));
            $stmtC = $conn->prepare("
                SELECT
                    c_src.CodCotizacion      AS CodCotizacion,
                    pp_base.id               AS id_presentacion,
                    pp_base.cantidad         AS pp_cantidad,
                    pp_base.Id_receta_producto,
                    pp_base.id_producto_maestro AS id_maestro,
                    pp_base.Nombre           AS nombre_presentacion,
                    u_base.id                AS id_unidad_erp,
                    u_base.nombre            AS unidad_erp,
                    u_base.abreviado         AS unidad_erp_abrev,
                    u_base.nombres_opcionales,
                    pm.Nombre                AS nombre_maestro,
                    pp_base.categoria_insumo
                FROM Cotizaciones c_src
                INNER JOIN Cotizaciones c_all  ON c_all.CodIngrediente = c_src.CodIngrediente
                INNER JOIN diccionario_productos_legado d2 ON d2.CodCotizacion = c_all.CodCotizacion
                INNER JOIN producto_presentacion pp_any    ON pp_any.id = d2.id_producto_presentacion
                                                          AND pp_any.Activo = 'SI'
                                                          AND pp_any.id_producto_maestro IS NOT NULL
                INNER JOIN producto_presentacion pp_base
                        ON pp_base.id_producto_maestro = pp_any.id_producto_maestro
                       AND pp_base.presentacion_basica_inventario = 1
                       AND pp_base.Activo = 'SI'
                       AND pp_base.Id_receta_producto IS NULL
                LEFT  JOIN unidad_producto u_base ON u_base.id = pp_base.id_unidad_producto
                LEFT  JOIN producto_maestro pm    ON pm.id = pp_base.id_producto_maestro
                WHERE c_src.CodCotizacion IN ($phC)
                GROUP BY c_src.CodCotizacion
            ");
            $stmtC->execute(array_values($codAunSinResolver));
            foreach ($stmtC->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $diccionarioMap[(string) $row['CodCotizacion']] = $row;
            }
        }
    }

    // 3e. Pre-cargar TODAS las unidades (tabla pequeña, < 50 filas normalmente)
    $stmtAllU = $conn->prepare("SELECT id, nombre, abreviado, nombres_opcionales FROM unidad_producto");
    $stmtAllU->execute();
    $todasUnidades = $stmtAllU->fetchAll(PDO::FETCH_ASSOC);


    // Crear índice: nombre/abreviado/alias → id
    $unidadPorNombre = [];
    foreach ($todasUnidades as $u) {
        $uid = (int) $u['id'];
        $unom = strtolower(trim($u['nombre']));
        $uabr = strtolower(trim($u['abreviado'] ?? ''));
        $unidadPorNombre[$unom] = $uid;
        if ($uabr)
            $unidadPorNombre[$uabr] = $uid;
        if (!empty($u['nombres_opcionales'])) {
            foreach (preg_split('/[,;|]+/', $u['nombres_opcionales']) as $alias) {
                $ak = strtolower(trim($alias));
                if ($ak)
                    $unidadPorNombre[$ak] = $uid;
            }
        }
    }
    // Índice por id para lookup rápido
    $unidadPorId = [];
    foreach ($todasUnidades as $u) {
        $unidadPorId[(int) $u['id']] = $u;
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
        $ini = (int) $c['id_unidad_producto_inicio'];
        $fin = (int) $c['id_unidad_producto_final'];
        $fac = (float) $c['cantidad'];
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
              AND pp.presentacion_basica_inventario = 1
        ");
        $stmtPP->execute(array_values($idMaestrosDict));
        foreach ($stmtPP->fetchAll(PDO::FETCH_ASSOC) as $pp) {
            $presentPorMaestro[(int) $pp['id_producto_maestro']][(int) $pp['id_unidad_producto']] = $pp;
        }
    }


    /* ══════════════════════════════════════════════════════════
       PASO 4: Funciones auxiliares (PHP puro)
       Solo usa los arrays pre-cargados — sin queries
       ══════════════════════════════════════════════════════════ */
    function resolverUnidadId($nombreAccess, &$unidadPorNombre)
    {
        $k = strtolower(trim($nombreAccess));
        return $unidadPorNombre[$k] ?? null;
    }

    function resolverFactorConversion($idOrigen, $idDestino, &$convIndex)
    {
        if ($idOrigen === $idDestino)
            return 1.0;
        return $convIndex[$idOrigen][$idDestino] ?? null;
    }

    function buscarPresentacionEnMaestro($idMaestro, $idUnidad, &$presentPorMaestro)
    {
        return $presentPorMaestro[$idMaestro][$idUnidad] ?? null;
    }

    function calcularProyeccionWLS(array $valores): array
    {
        $n = count($valores);
        if ($n === 0)
            return ['promedio' => 0.0, 'm' => 0.0, 'b' => 0.0, 'n' => 0, 'w1' => 0.0, 'w2' => 0.0, 'w3' => 0.0];
        if ($n === 1) {
            $v = max(0.0, (float) $valores[0]);
            return ['promedio' => $v, 'm' => 0.0, 'b' => $v, 'n' => 1, 'w1' => $v, 'w2' => $v, 'w3' => $v];
        }

        $sum_w = 0.0;
        $sum_wx = 0.0;
        $sum_wy = 0.0;
        $sum_wxx = 0.0;
        $sum_wxy = 0.0;
        foreach ($valores as $i => $y) {
            $x = $i + 1;
            $w = $x;
            $sum_w += $w;
            $sum_wx += $w * $x;
            $sum_wy += $w * $y;
            $sum_wxx += $w * $x * $x;
            $sum_wxy += $w * $x * $y;
        }

        $denominator = ($sum_w * $sum_wxx) - ($sum_wx * $sum_wx);
        if (abs($denominator) < 0.0001) {
            $prom = array_sum($valores) / $n;
            return ['promedio' => $prom, 'm' => 0.0, 'b' => $prom, 'n' => $n, 'w1' => $prom, 'w2' => $prom, 'w3' => $prom];
        }

        $slope = (($sum_w * $sum_wxy) - ($sum_wx * $sum_wy)) / $denominator;
        $intercept = ($sum_wy - $slope * $sum_wx) / $sum_w;

        // Tendencia Ajustada: si la tendencia es negativa, anulamos la pendiente y usamos el máximo de las 2 últimas semanas.
        if ($slope < 0) {
            $ultimas2 = array_slice($valores, -2);
            $max_ultimas2 = !empty($ultimas2) ? max($ultimas2) : 0.0;
            $slope = 0.0;
            $intercept = (float)$max_ultimas2;
        }

        $w1 = max(0.0, $slope * ($n + 1) + $intercept);
        $w2 = max(0.0, $slope * ($n + 2) + $intercept);
        $w3 = max(0.0, $slope * ($n + 3) + $intercept);

        return [
            'promedio' => ($w1 + $w2 + $w3) / 3.0,
            'm' => $slope,
            'b' => $intercept,
            'n' => $n,
            'w1' => $w1,
            'w2' => $w2,
            'w3' => $w3
        ];
    }


    function calcularDesviacionEstandar(array $valores): float
    {
        $n = count($valores);
        if ($n <= 1)
            return 0.0;
        $media = array_sum($valores) / $n;
        $varianza = array_sum(array_map(fn($v) => ($v - $media) ** 2, $valores)) / ($n - 1);
        return sqrt($varianza);
    }

    /* ══════════════════════════════════════════════════════════
       PASO 5: Loop de resolución — PHP puro (sin DB)
       ══════════════════════════════════════════════════════════ */
    $consumoAgg = [];   // [id_pp][sucursal][semana] => float
    $metaPP = [];   // [id_pp] => {nombre, maestro, unidad, es_global}
    $esP1Map = [];   // [id_pp] => bool  (true si TODOS sus mapeos vinieron de P1)
    $sinMapeo = [];
    $sinMapeoSet = [];
    $sucursalesSet = [];

    // ── Mapeo de Sucursales ───────────────────────────────────────────
    // VentasGlobalesAccessCSV.local puede contener el NOMBRE o el CÓDIGO de la sucursal.
    // Construimos índices para asegurar encontrar el código y nombre.
    $nombresSucursales = []; // [codigo] => nombre
    $idsSucursales = []; // [nombre_lower] => codigo  +  [codigo_lower] => codigo

    $stmtSuc = $conn->query("SELECT id, codigo, nombre FROM sucursales");
    foreach ($stmtSuc->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cod = $row['codigo'];
        $nombresSucursales[$cod] = $row['nombre'];
        $idsSucursales[strtolower(trim($row['nombre']))] = $cod;
        if (!empty($cod)) {
            $idsSucursales[strtolower(trim($cod))] = $cod;
        }
    }

    foreach ($filas as $fila) {
        $codIng = $fila['cod_ingrediente'];
        $codporcion = $fila['codporcion'];
        $sucursal = $fila['sucursal'];
        $semana = (int) $fila['semana'];
        $cantTotal = (float) $fila['cant_total'];

        $sucursalesSet[$sucursal] = true;

        // ── Resolver mapeo (P1 / P2 / P3) ──
        $mapeo = null;
        $esP1 = false;   // P1 = porción mapeada → redondear al 0.5 más cercano

        // P1: codporcion directo
        if (!empty($codporcion) && isset($diccionarioMap[(string) $codporcion])) {
            $mapeo = $diccionarioMap[(string) $codporcion];
            $esP1 = true;
        }
        // P2: cotización base
        if (!$mapeo && isset($cotMap[$codIng]['p2'])) {
            $ck = (string) $cotMap[$codIng]['p2'];
            if (isset($diccionarioMap[$ck]))
                $mapeo = $diccionarioMap[$ck];
        }
        // P3: fallback
        if (!$mapeo && isset($cotMap[$codIng]['p3'])) {
            $ck = (string) $cotMap[$codIng]['p3'];
            if (isset($diccionarioMap[$ck]))
                $mapeo = $diccionarioMap[$ck];
        }

        // Sin mapeo
        if (!$mapeo) {
            if (!isset($sinMapeoSet[$codIng])) {
                $sinMapeoSet[$codIng] = true;
                $sinMapeo[] = [
                    'cod_ingrediente' => $codIng,
                    'nombre' => $dbIngredientes[$codIng]['Nombre'] ?? 'Desconocido',
                    'unidad_access' => $dbIngredientes[$codIng]['Unidad'] ?? '',
                    'num_productos' => 1,
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
        $esGlobal = !empty($mapeo['Id_receta_producto']);
        $idPP = (int) $mapeo['id_presentacion'];
        $ppCant = max((float) $mapeo['pp_cantidad'], 0.001);
        $idMaestro = (int) $mapeo['id_maestro'];
        $idUnidERP = (int) $mapeo['id_unidad_erp'];

        // Filtro opcional por insumo
        if ($idInsumo > 0 && $idPP !== $idInsumo)
            continue;

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
                        $idPP = (int) $ppAlt['id'];
                        $ppCant = max((float) $ppAlt['pp_cantidad'], 0.001);
                        $idUnidERP = (int) $ppAlt['id_unidad_producto'];
                        $factor = 1.0;
                    } else {
                        // Nivel 3: buscar via conversiones disponibles para el maestro
                        if (isset($convIndex[$idUnidAccess])) {
                            foreach ($convIndex[$idUnidAccess] as $idDestino => $factorConv) {
                                $ppConv = buscarPresentacionEnMaestro($idMaestro, $idDestino, $presentPorMaestro);
                                if ($ppConv) {
                                    $idPP = (int) $ppConv['id'];
                                    $ppCant = max((float) $ppConv['pp_cantidad'], 0.001);
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
                'id' => $idPP,
                'nombre' => $mapeo['nombre_presentacion'],
                'maestro' => $mapeo['nombre_maestro'],
                'unidad' => $unidadPorId[$idUnidERP]['nombre'] ?? $mapeo['unidad_erp'] ?? '',
                'categoria_insumo' => $mapeo['categoria_insumo'],
                'es_global' => $esGlobal,
            ];
            $esP1Map[$idPP] = $esP1;  // inicializar con el primer mapeo
        } else {
            // Si alguna fila del mismo idPP NO es P1, el item deja de ser puro P1
            if (!$esP1)
                $esP1Map[$idPP] = false;
        }

        if (!isset($consumoAgg[$idPP]))
            $consumoAgg[$idPP] = [];
        if (!isset($consumoAgg[$idPP][$sucursal]))
            $consumoAgg[$idPP][$sucursal] = [];
        if (!isset($consumoAgg[$idPP][$sucursal][$semana]))
            $consumoAgg[$idPP][$sucursal][$semana] = 0;
        $consumoAgg[$idPP][$sucursal][$semana] += $consumido;
    }

    /* ══════════════════════════════════════════════════════════
       PASO 6: Calcular estadísticas y construir respuesta
       ══════════════════════════════════════════════════════════ */
    $sucursalesPresentes = array_keys($sucursalesSet);
    $semanasNros = array_keys($semanasMap);

    // ── Mapeo de Sucursales para Configuración Logística ──
    $sucIdsPresentes = [];
    foreach ($sucursalesPresentes as $suc) {
        $key = strtolower(trim($suc));
        if (isset($idsSucursales[$key]))
            $sucIdsPresentes[] = $idsSucursales[$key];
    }


    $configProductos = []; // [cod_sucursal][categoria] => {ajuste_demanda, ...}
    if (!empty($sucIdsPresentes)) {
        $phS = implode(',', array_fill(0, count($sucIdsPresentes), '?'));
        $stmtCP = $conn->prepare("SELECT cod_sucursal, codigo_insumo, ajuste_demanda, dias_ciclo, dias_desfase FROM configuracion_logistica_producto WHERE cod_sucursal IN ($phS)");
        $stmtCP->execute($sucIdsPresentes);
        foreach ($stmtCP->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $configProductos[$row['cod_sucursal']][$row['codigo_insumo']] = [
                'ajuste' => (float) $row['ajuste_demanda'],
                'ciclo' => (float) $row['dias_ciclo'],
                'desfase' => (float) $row['dias_desfase']
            ];
        }
    }

    $listaConsumo = [];
    foreach ($consumoAgg as $idPP => $porSuc) {
        $meta = $metaPP[$idPP];
        $itemEsP1 = !empty($esP1Map[$idPP]);   // ¿todas las filas de este idPP son P1?

        // Función local de redondeo: P1 → 0.5, resto → 4 decimales
        $rnd = fn($v) => $itemEsP1 ? (round($v * 2) / 2) : round($v, 4);

        $totalGeneral = 0;
        $consPorSem = [];
        $porSucRes = [];

        foreach ($porSuc as $suc => $porSem) {
            $totSuc = 0;
            foreach ($porSem as $sem => $c) {
                $totalGeneral += $c;
                $totSuc += $c;
                $consPorSem[$sem] = ($consPorSem[$sem] ?? 0) + $c;
            }
            $porSucRes[$suc] = $rnd($totSuc);
        }

        // Redondear acumulados por semana al 0.5 si es P1
        foreach ($consPorSem as $s => $c) {
            $consPorSem[$s] = $rnd($c);
        }

        $semanaPico = null;
        $semanaLow = null;
        $maxC = -1;
        $minC = PHP_INT_MAX;
        foreach ($consPorSem as $s => $c) {
            if ($c > $maxC) {
                $maxC = $c;
                $semanaPico = $s;
            }
            if ($c < $minC) {
                $minC = $c;
                $semanaLow = $s;
            }
        }

        $totalGeneral = $rnd($totalGeneral);
        $n = count($consPorSem);
        $promSemana = $n > 0 ? $totalGeneral / $n : 0;

        // ── Cálculo de Stock Máximo Profesional ──
        $stockMaxPorSucursal = [];
        $stockMaxTotalSum = 0;

        foreach ($sucursalesPresentes as $suc) {
            $valsSuc = [];
            foreach ($semanasNros as $sem) {
                $valsSuc[] = (float) ($consumoAgg[$idPP][$suc][$sem] ?? 0);
            }

            // Ventana Activa (mismo algoritmo que pedido_sugerido_calcular.php)
            $valsParaVentana = $valsSuc;
            if ($semanaHastaIncompleta) {
                array_pop($valsParaVentana); // Eliminar última semana (incompleta) para evitar sesgo en la regresión
            }

            $nonZeroVals = array_filter($valsParaVentana, fn($v) => $v > 0);
            if (empty($nonZeroVals)) {
                $stockMaxPorSucursal[$suc] = 0;
                continue;
            }

            $meanNonZero = array_sum($nonZeroVals) / count($nonZeroVals);
            $umbral = max(0.01, $meanNonZero * 0.10);

            $firstIdx = null;
            $lastIdx = null;
            foreach ($valsParaVentana as $i => $v) {
                if ($v >= $umbral) {
                    if ($firstIdx === null)
                        $firstIdx = $i;
                    $lastIdx = $i;
                }
            }

            if ($firstIdx === null) {
                $stockMaxPorSucursal[$suc] = 0;
                continue;
            }

            $nActiva = $lastIdx - $firstIdx + 1;
            $valsActivo = array_slice($valsSuc, $firstIdx, $nActiva);

            $n_vals = count($valsActivo);
            $valsActivo = array_slice($valsParaVentana, $firstIdx, $nActiva);

            $wlsResSuc = calcularProyeccionWLS($valsActivo);
            $semC = $wlsResSuc['promedio'];

            $sucCod = $idsSucursales[strtolower(trim($suc))] ?? null;
            $cat = $meta['categoria_insumo'];
            $cP = $sucCod ? ($configProductos[$sucCod][$cat] ?? null) : null;
            $adj = $cP ? (float) $cP['ajuste'] : 0;
            $ciclo = $cP ? (float) $cP['ciclo'] : 7;

            $diaC = ($semC * (1 + $adj)) / 7;
            
            // Stock Maximo: sin Stock Minimo
            $sMax = ($diaC * $ciclo);

            $valMax = round($sMax, 4);

            $stockMaxPorSucursal[$suc] = $valMax;

            $stockMaxTotalSum += $valMax;
        }

        // Tendencia
        $tendencia = 'flat';
        if ($n >= 2) {
            $ks = array_keys($consPorSem);
            sort($ks);
            $mid = (int) ($n / 2);
            $p1 = array_sum(array_intersect_key($consPorSem, array_flip(array_slice($ks, 0, $mid)))) / max($mid, 1);
            $p2 = array_sum(array_intersect_key($consPorSem, array_flip(array_slice($ks, $mid)))) / max($n - $mid, 1);
            if ($p2 > $p1 * 1.05)
                $tendencia = 'up';
            elseif ($p2 < $p1 * 0.95)
                $tendencia = 'down';
        }

        // --- CÁLCULO WLS GLOBAL PARA EL GRÁFICO ---
        $valsGlobal = [];
        foreach ($semanasNros as $sem) {
            $valsGlobal[] = (float) ($consPorSem[$sem] ?? 0);
        }
        $globalSemC = 0;
        $globalWlsM = 0;
        $globalWlsB = 0;
        $globalWlsN = 0;
        $globalWlsFirstIdx = 0;

        $valsParaVentanaGlobal = $valsGlobal;
        if ($semanaHastaIncompleta) {
            array_pop($valsParaVentanaGlobal);
        }

        $nonZeroGlobal = array_filter($valsParaVentanaGlobal, fn($v) => $v > 0);
        if (!empty($nonZeroGlobal)) {
            $meanNonZeroG = array_sum($nonZeroGlobal) / count($nonZeroGlobal);
            $umbralG = max(0.01, $meanNonZeroG * 0.10);

            $firstIdxG = null;
            $lastIdxG = null;
            foreach ($valsParaVentanaGlobal as $i => $v) {
                if ($v >= $umbralG) {
                    if ($firstIdxG === null)
                        $firstIdxG = $i;
                    $lastIdxG = $i;
                }
            }
            if ($firstIdxG !== null && $lastIdxG !== null && $lastIdxG >= $firstIdxG) {
                $nActivaG = $lastIdxG - $firstIdxG + 1;
                $valsActivoG = array_slice($valsParaVentanaGlobal, $firstIdxG, $nActivaG);
                $wlsResG = calcularProyeccionWLS($valsActivoG);
                $globalSemC = $wlsResG['promedio'];
                $globalWlsM = $wlsResG['m'];
                $globalWlsB = $wlsResG['b'];
                $globalWlsN = $wlsResG['n'];
                $globalWlsFirstIdx = $firstIdxG;
            }
        }


        // Desglose semana × sucursal (redondeado al 0.5 si es P1)
        $desgloseSemsuc = [];
        foreach ($semanasNros as $sem) {
            $desgloseSemsuc[$sem] = [];
            foreach ($sucursalesPresentes as $suc) {
                $desgloseSemsuc[$sem][$suc] = $rnd((float) ($consumoAgg[$idPP][$suc][$sem] ?? 0));
            }
        }

        $listaConsumo[] = [
            'id' => $idPP,
            'nombre' => $meta['nombre'],
            'maestro' => $meta['maestro'],
            'unidad' => $meta['unidad'],
            'categoria_insumo' => $meta['categoria_insumo'],
            'es_global' => (bool) $meta['es_global'],
            'es_p1' => $itemEsP1,
            'total' => $totalGeneral,
            'prom_semana' => round($promSemana, $itemEsP1 ? 1 : 4),
            'proyeccion_3sem' => round($semC * 3, $itemEsP1 ? 1 : 4),
            'stock_max' => round($stockMaxTotalSum, 4),
            'stock_max_suc' => $stockMaxPorSucursal,
            'semana_pico_num' => $semanaPico,
            'semana_low_num' => $semanaLow,
            'max_consumo_sem' => $rnd($maxC),
            'tendencia' => $tendencia,
            'por_semana' => $consPorSem,
            'por_sucursal' => $porSucRes,
            'desglose_semxsuc' => $desgloseSemsuc,
            'wls_m' => $globalWlsM,
            'wls_b' => $globalWlsB,
            'wls_n' => $globalWlsN,
            'wls_first_idx' => $globalWlsFirstIdx,
        ];
    }

    // Ordenar por categoría (ASC) y luego por nombre (ASC)
    usort($listaConsumo, function ($a, $b) {
        $catA = (string) ($a['categoria_insumo'] ?? '');
        $catB = (string) ($b['categoria_insumo'] ?? '');
        $cmp = strcasecmp($catA, $catB);
        if ($cmp !== 0)
            return $cmp;
        return strcasecmp((string) $a['nombre'], (string) $b['nombre']);
    });

    // Estadísticas globales
    $totalGeneral = array_sum(array_column($listaConsumo, 'total'));
    $proyTotal = array_sum(array_column($listaConsumo, 'proyeccion_3sem'));
    $sumasPorSem = [];
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
        'ok' => true,
        'consumo' => $listaConsumo,
        'sin_mapeo' => $sinMapeo,
        'semanas' => array_values($semanasRango),
        'sucursales' => $sucursalesPresentes,
        'sucursales_nombres' => $sucursalesNombresMap,
        'total_general' => round($totalGeneral, 4),
        'proyeccion_total' => round($proyTotal, 4),
        'semana_pico_global' => $picoGlobal,
        'num_sin_mapeo' => count($sinMapeo),
        'num_insumos' => count($listaConsumo),
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error al calcular consumo: ' . $e->getMessage()]);
}

