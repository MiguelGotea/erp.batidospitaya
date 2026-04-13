<?php
/* ============================================================
   AJAX: Obtener datos de consumo — Núcleo del Dashboard
   modulos/productos/ajax/dashboard_consumo_get_datos.php

   Parámetros POST:
     semana_desde_id  (int)   - id en SemanasSistema
     semana_hasta_id  (int)   - id en SemanasSistema
     sucursales       (array) - códigos de sucursales (vacío = todas)
     id_insumo        (int)   - opcional, filtrar un insumo ERP específico

   Algoritmo:
     1. Obtener rango de fechas de las semanas seleccionadas
     2. Obtener ventas del período (Anulado=0)
     3. Por cada CodProducto, resolver SubReceta
     4. Por cada ingrediente, traducir a ERP (P1/P2/P3/AUTO)
     5. Calcular consumo: (SubReceta.Cantidad × factor) / pp.cantidad × ventas
     6. Agregar por {id_presentacion_erp, semana, sucursal}
   ============================================================ */
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');
set_time_limit(120); // El cálculo puede tardar

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('dashboard_consumo_insumos', 'vista', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Sin permiso']);
    exit();
}

// ── Parámetros ────────────────────────────────────────────────────────
$semanaDesdeId = isset($_POST['semana_desde_id']) ? (int)$_POST['semana_desde_id'] : 0;
$semanaHastaId = isset($_POST['semana_hasta_id']) ? (int)$_POST['semana_hasta_id'] : 0;
$sucursalesPost = isset($_POST['sucursales']) ? (array)$_POST['sucursales'] : [];
$idInsumo      = isset($_POST['id_insumo']) ? (int)$_POST['id_insumo'] : 0;

if (!$semanaDesdeId || !$semanaHastaId) {
    echo json_encode(['ok' => false, 'msg' => 'Parámetros de semana requeridos.']);
    exit();
}

try {
    // ── 1. Obtener rango de fechas de las semanas ──────────────────────
    $stmtSem = $conn->prepare("
        SELECT 
            MIN(ss.fecha_inicio)    AS fecha_desde,
            MAX(ss.fecha_fin)       AS fecha_hasta,
            MIN(ss.numero_semana)   AS sem_num_desde,
            MAX(ss.numero_semana)   AS sem_num_hasta
        FROM SemanasSistema ss
        WHERE ss.id BETWEEN :desde AND :hasta
    ");
    $stmtSem->execute([':desde' => min($semanaDesdeId, $semanaHastaId), ':hasta' => max($semanaDesdeId, $semanaHastaId)]);
    $rango = $stmtSem->fetch(PDO::FETCH_ASSOC);

    if (!$rango || !$rango['fecha_desde']) {
        echo json_encode(['ok' => false, 'msg' => 'Rango de semanas no encontrado.']);
        exit();
    }

    // ── 2. Obtener todas las semanas del rango con sus fechas ──────────
    $stmtSems = $conn->prepare("
        SELECT ss.id, ss.numero_semana, ss.anio, ss.fecha_inicio, ss.fecha_fin
        FROM SemanasSistema ss
        WHERE ss.id BETWEEN :desde AND :hasta
        ORDER BY ss.anio ASC, ss.numero_semana ASC
    ");
    $stmtSems->execute([':desde' => min($semanaDesdeId, $semanaHastaId), ':hasta' => max($semanaDesdeId, $semanaHastaId)]);
    $semanasRango = $stmtSems->fetchAll(PDO::FETCH_ASSOC);
    $semanasMap = []; // [numero_semana] => [id, label, fecha_inicio, fecha_fin]
    foreach ($semanasRango as $s) {
        $semanasMap[(int)$s['numero_semana']] = $s;
    }

    // ── 3. Obtener ventas del período ─────────────────────────────────
    // VentasGlobalesAccessCSV.Semana corresponde a SemanasSistema.numero_semana
    // Filtramos por Anulado=0 y fechas del rango

    $where_suc = '';
    $params    = [
        ':fecha_desde' => $rango['fecha_desde'],
        ':fecha_hasta' => $rango['fecha_hasta'],
    ];

    if (!empty($sucursalesPost)) {
        $placeholders = [];
        foreach ($sucursalesPost as $i => $suc) {
            $key = ':suc' . $i;
            $placeholders[] = $key;
            $params[$key]   = $suc;
        }
        $where_suc = ' AND v.local IN (' . implode(',', $placeholders) . ')';
    }

    $sqlVentas = "
        SELECT
            v.CodProducto,
            v.local         AS sucursal_codigo,
            v.Semana        AS numero_semana,
            SUM(v.Cantidad) AS total_vendido
        FROM VentasGlobalesAccessCSV v
        WHERE v.Anulado = 0
          AND v.Fecha BETWEEN :fecha_desde AND :fecha_hasta
          AND v.CodProducto IS NOT NULL
          AND v.Semana IS NOT NULL
          $where_suc
        GROUP BY v.CodProducto, v.local, v.Semana
        ORDER BY v.Semana ASC
    ";

    $stmtVentas = $conn->prepare($sqlVentas);
    $stmtVentas->execute($params);
    $ventas = $stmtVentas->fetchAll(PDO::FETCH_ASSOC);

    if (empty($ventas)) {
        echo json_encode([
            'ok' => true,
            'msg' => 'Sin ventas en el período seleccionado.',
            'consumo'    => [],
            'sin_mapeo'  => [],
            'semanas'    => array_values($semanasMap),
            'sucursales' => [],
        ]);
        exit();
    }

    // ── 4. Colectar CodProductos únicos ───────────────────────────────
    $codProductosUnicos = array_unique(array_column($ventas, 'CodProducto'));
    $sucursalesPresentes = array_unique(array_column($ventas, 'sucursal_codigo'));

    // ── 5. Cargar SubRecetas de todos los productos ───────────────────
    $placeholdersProd = implode(',', array_fill(0, count($codProductosUnicos), '?'));
    $stmtSub = $conn->prepare("
        SELECT sr.CodBatido, sr.CodIngrediente, sr.Cantidad, sr.codporcion, sr.ordenreceta
        FROM SubReceta sr
        WHERE sr.CodBatido IN ($placeholdersProd)
        ORDER BY sr.CodBatido, sr.ordenreceta ASC
    ");
    $stmtSub->execute(array_values($codProductosUnicos));
    $subRecetasRaw = $stmtSub->fetchAll(PDO::FETCH_ASSOC);

    // Indexar por CodBatido
    $subRecetasPorProd = [];
    foreach ($subRecetasRaw as $sr) {
        $subRecetasPorProd[$sr['CodBatido']][] = $sr;
    }

    // ── 6. Cargar unidades de DBIngredientes ─────────────────────────
    $codIngredientes = array_unique(array_column($subRecetasRaw, 'CodIngrediente'));
    $ingredientesMaestro = [];
    if (!empty($codIngredientes)) {
        $phIng = implode(',', array_fill(0, count($codIngredientes), '?'));
        $stmtIng = $conn->prepare("
            SELECT i.CodIngrediente, i.Nombre, i.Unidad
            FROM DBIngredientes i
            WHERE i.CodIngrediente IN ($phIng)
        ");
        $stmtIng->execute(array_values($codIngredientes));
        foreach ($stmtIng->fetchAll(PDO::FETCH_ASSOC) as $ing) {
            $ingredientesMaestro[$ing['CodIngrediente']] = $ing;
        }
    }

    // ── 7. Función: Resolver cotización (P1 / P2 / P3) ────────────────
    // Cachemos para no repetir queries iguales
    $cacheMapeo    = []; // [clave_cotizacion] => { id_presentacion, pp_cantidad, unidad_erp, es_global, id_maestro }
    $cacheUnidad   = []; // [unidad_access_lower] => { id_primaria, nombre, multi_directos, convertibles }
    $cachePresentacion = []; // [id_maestro.'_'.str_unidades] => { id_presentacion, pp_cantidad, unidad_erp, factor }

    /**
     * Resolver mapeo ERP desde CodCotizacion
     */
    function resolverMapeoERP($conn, $codCotizacion, &$cacheMapeo) {
        $key = (int)$codCotizacion;
        if (isset($cacheMapeo[$key])) return $cacheMapeo[$key];

        $stmt = $conn->prepare("
            SELECT
                pp.id               AS id_presentacion,
                pp.cantidad         AS pp_cantidad,
                pp.Id_receta_producto,
                pp.Activo,
                u.nombre            AS unidad_erp,
                u.abreviado         AS unidad_erp_abrev,
                pm.id               AS id_maestro,
                pm.Nombre           AS nombre_maestro,
                pp.Nombre           AS nombre_presentacion
            FROM diccionario_productos_legado d
            INNER JOIN producto_presentacion pp ON pp.id = d.id_producto_presentacion
            LEFT  JOIN unidad_producto u         ON u.id  = pp.id_unidad_producto
            LEFT  JOIN producto_maestro pm       ON pm.id = pp.id_producto_maestro
            WHERE d.CodCotizacion = ?
            LIMIT 1
        ");
        $stmt->execute([$key]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        $cacheMapeo[$key] = $r ?: null;
        return $cacheMapeo[$key];
    }

    /**
     * Obtener CodCotizacion base (P2: Conversion=1, Prioridad=1)
     */
    function obtenerCotizacionBase($conn, $codIngrediente) {
        $stmt = $conn->prepare("
            SELECT c.CodCotizacion
            FROM Cotizaciones c
            WHERE c.CodIngrediente = ?
              AND (c.Subproducto IS NULL OR c.Subproducto != 1)
              AND (c.Marca IS NULL OR c.Marca != 'Almacen Global')
              AND c.Conversion = 1
              AND c.Prioridad = 1
            LIMIT 1
        ");
        $stmt->execute([$codIngrediente]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ? $r['CodCotizacion'] : null;
    }

    /**
     * Obtener CodCotizacion fallback (P3)
     */
    function obtenerCotizacionFallback($conn, $codIngrediente) {
        $stmt = $conn->prepare("
            SELECT c.CodCotizacion
            FROM Cotizaciones c
            WHERE c.CodIngrediente = ?
              AND (c.Subproducto IS NULL OR c.Subproducto != 1)
              AND (c.Marca IS NULL OR c.Marca != 'Almacen Global')
            LIMIT 1
        ");
        $stmt->execute([$codIngrediente]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ? $r['CodCotizacion'] : null;
    }

    /**
     * AUTO: Rastreo por maestro + unidad cuando no hay mapeo por cotización
     */
    function rastreoAutoMaestro($conn, $codIngrediente) {
        $stmt = $conn->prepare("
            SELECT pp.id_producto_maestro
            FROM Cotizaciones c
            INNER JOIN diccionario_productos_legado d ON d.CodCotizacion = c.CodCotizacion
            INNER JOIN producto_presentacion pp ON pp.id = d.id_producto_presentacion
            WHERE c.CodIngrediente = ?
              AND pp.Id_receta_producto IS NULL
            LIMIT 1
        ");
        $stmt->execute([$codIngrediente]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ? $r['id_producto_maestro'] : null;
    }

    /**
     * Resolver unidad Access → ERP (primaria, multi_directos, convertibles)
     */
    function resolverUnidadERP($conn, $unidadAccess, &$cacheUnidad) {
        $u = strtolower(trim($unidadAccess));
        if (isset($cacheUnidad[$u])) return $cacheUnidad[$u];

        // Búsqueda primaria
        $stmt = $conn->prepare("
            SELECT id, nombre, abreviado
            FROM unidad_producto
            WHERE LOWER(abreviado) = :u
               OR LOWER(nombre)    = :u
               OR FIND_IN_SET(:u, LOWER(REPLACE(REPLACE(IFNULL(nombres_opcionales,''), ', ', ','), ' ,', ','))) > 0
            ORDER BY
                CASE
                    WHEN LOWER(abreviado) = :u THEN 1
                    WHEN LOWER(nombre)    = :u THEN 2
                    ELSE 3
                END
            LIMIT 1
        ");
        $stmt->execute([':u' => $u]);
        $primaria = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$primaria) {
            $cacheUnidad[$u] = null;
            return null;
        }

        $id_primaria = $primaria['id'];

        // Búsquedas secundarias (multi_directos)
        $stmt2 = $conn->prepare("
            SELECT nombre
            FROM unidad_producto
            WHERE id != ?
              AND (
                LOWER(abreviado) = :u
                OR LOWER(nombre) = :u
                OR FIND_IN_SET(:u, LOWER(REPLACE(REPLACE(IFNULL(nombres_opcionales,''), ', ', ','), ' ,', ','))) > 0
              )
        ");
        $stmt2->execute([$id_primaria, ':u' => $u, ':u' => $u, ':u' => $u]);
        $multiDirectos = array_column($stmt2->fetchAll(PDO::FETCH_ASSOC), 'nombre');
        $multiDirectos[] = $primaria['nombre']; // incluir la primaria

        // Unidades convertibles
        $stmt3 = $conn->prepare("
            SELECT
                CASE WHEN c.id_unidad_producto_inicio = ? THEN uf.nombre  ELSE ui.nombre  END AS nombre_relacionado,
                CASE WHEN c.id_unidad_producto_inicio = ? THEN c.cantidad ELSE (1/c.cantidad) END AS factor_conversion
            FROM conversion_unidad_producto c
            JOIN unidad_producto ui ON ui.id = c.id_unidad_producto_inicio
            JOIN unidad_producto uf ON uf.id = c.id_unidad_producto_final
            WHERE c.id_unidad_producto_inicio = ?
               OR c.id_unidad_producto_final  = ?
        ");
        $stmt3->execute([$id_primaria, $id_primaria, $id_primaria, $id_primaria]);
        $convertibles = $stmt3->fetchAll(PDO::FETCH_ASSOC);

        $result = [
            'id_primaria'   => $id_primaria,
            'nombre'        => $primaria['nombre'],
            'multi_directos'=> $multiDirectos,
            'convertibles'  => $convertibles,  // [{nombre_relacionado, factor_conversion}]
        ];
        $cacheUnidad[$u] = $result;
        return $result;
    }

    /**
     * Buscar presentación del maestro por lista de unidades (Nivel 1/2/3)
     */
    function buscarPresentacionPorUnidades($conn, $idMaestro, $listaUnidades) {
        if (empty($listaUnidades)) return null;
        $ph = implode(',', array_fill(0, count($listaUnidades), '?'));
        $stmt = $conn->prepare("
            SELECT
                pp.id       AS id_presentacion,
                pp.Nombre   AS nombre_erp,
                pp.cantidad AS pp_cantidad,
                u.nombre    AS unidad_erp
            FROM producto_presentacion pp
            LEFT JOIN unidad_producto u ON u.id = pp.id_unidad_producto
            WHERE pp.id_producto_maestro = ?
              AND u.nombre IN ($ph)
              AND pp.Id_receta_producto IS NULL
              AND pp.Activo = 'SI'
            ORDER BY
                CASE WHEN pp.cantidad = 1 THEN 0 ELSE 1 END ASC,
                pp.cantidad ASC
            LIMIT 1
        ");
        $params = array_merge([$idMaestro], $listaUnidades);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ── 8. Estructura de resultados ───────────────────────────────────
    // consumo[id_presentacion_erp][sucursal][numero_semana] = cantidad_consumida
    $consumoAgregado = [];
    $presentacionesMeta = []; // metadata de la presentación ERP
    $sinMapeo = [];           // ingredientes sin mapeo
    $sinMapeoSet = [];        // para no duplicar

    // ── 9. Procesar cada venta ────────────────────────────────────────
    foreach ($ventas as $venta) {
        $codProducto   = $venta['CodProducto'];
        $sucursalCod   = $venta['sucursal_codigo'];
        $numSemana     = (int)$venta['numero_semana'];
        $totalVendido  = (float)$venta['total_vendido'];

        if (!isset($subRecetasPorProd[$codProducto])) continue;

        foreach ($subRecetasPorProd[$codProducto] as $sr) {
            $codIng    = $sr['CodIngrediente'];
            $cantSR    = (float)$sr['Cantidad'];
            $codporcion = $sr['codporcion'];

            $id_presentacion_erp  = null;
            $pp_cantidad          = 1.0;
            $unidad_erp           = '';
            $es_global            = false;
            $factor               = 1.0;
            $nombre_presentacion  = '';
            $nombre_maestro       = '';

            // ── PRE: Resolver ID de cotización ──────────────────────
            $mapeo = null;

            // P1: codporcion directo
            if (!empty($codporcion)) {
                $mapeo = resolverMapeoERP($conn, $codporcion, $cacheMapeo);
            }

            // P2: Cotización base
            if (!$mapeo) {
                $codCot = obtenerCotizacionBase($conn, $codIng);
                if ($codCot) {
                    $mapeo = resolverMapeoERP($conn, $codCot, $cacheMapeo);
                }
            }

            // P3: Fallback cotización
            if (!$mapeo) {
                $codCot = obtenerCotizacionFallback($conn, $codIng);
                if ($codCot) {
                    $mapeo = resolverMapeoERP($conn, $codCot, $cacheMapeo);
                }
            }

            // ── Sin mapeo: registrar y saltar ─────────────────────
            if (!$mapeo) {
                $keyNoMapeo = $codIng;
                if (!isset($sinMapeoSet[$keyNoMapeo])) {
                    $sinMapeoSet[$keyNoMapeo] = true;
                    $ingData = $ingredientesMaestro[$codIng] ?? [];
                    $sinMapeo[] = [
                        'cod_ingrediente'   => $codIng,
                        'nombre'            => $ingData['Nombre'] ?? 'Desconocido',
                        'unidad_access'     => $ingData['Unidad'] ?? '',
                        'productos_afectados' => [],
                        'ventas_afectadas'    => 0,
                    ];
                }
                // Contar ventas afectadas (simple)
                foreach ($sinMapeo as &$sm) {
                    if ($sm['cod_ingrediente'] === $codIng) {
                        if (!in_array($codProducto, $sm['productos_afectados'])) {
                            $sm['productos_afectados'][] = $codProducto;
                        }
                        $sm['ventas_afectadas'] += $totalVendido;
                        break;
                    }
                }
                unset($sm);
                continue;
            }

            $id_presentacion_erp = $mapeo['id_presentacion'];
            $pp_cantidad         = max((float)$mapeo['pp_cantidad'], 0.001);
            $unidad_erp          = $mapeo['unidad_erp'] ?? '';
            $es_global           = !empty($mapeo['Id_receta_producto']);
            $id_maestro          = $mapeo['id_maestro'];
            $nombre_presentacion = $mapeo['nombre_presentacion'] ?? '';
            $nombre_maestro      = $mapeo['nombre_maestro'] ?? '';

            // ── PRE-CHECK: ¿Receta Global? ──────────────────────────
            if ($es_global) {
                // consumo = SubReceta.Cantidad × total_vendido (sin conversión)
                $cantidad_consumida = $cantSR * $totalVendido;
                $factor = 1.0;
            } else {
                // Resolver unidad Access → ERP
                $unidadAccess = $ingredientesMaestro[$codIng]['Unidad'] ?? '';
                $unidadResolta = resolverUnidadERP($conn, $unidadAccess, $cacheUnidad);

                if (!$unidadResolta) {
                    // Sin resolución de unidad → continuar con factor=1
                    $factor = 1.0;
                } else {
                    $factor = 1.0; // Nivel 1 por defecto

                    // ── AUTO: buscar presentación si el mapeo inicial no tiene el maestro correcto
                    if ($id_maestro) {
                        // Nivel 1: buscar por multi_directos
                        $presentacionN1 = buscarPresentacionPorUnidades($conn, $id_maestro, $unidadResolta['multi_directos']);
                        if ($presentacionN1) {
                            // Usar la presentación encontrada por unidad directa
                            $pp_cantidad = max((float)$presentacionN1['pp_cantidad'], 0.001);
                            $unidad_erp  = $presentacionN1['unidad_erp'];
                            $id_presentacion_erp = $presentacionN1['id_presentacion'];
                            $nombre_presentacion = $presentacionN1['nombre_erp'];
                            $factor = 1.0;
                        } else {
                            // Nivel 2: buscar con convertibles
                            foreach ($unidadResolta['convertibles'] as $conv) {
                                $presentacionN2 = buscarPresentacionPorUnidades($conn, $id_maestro, [$conv['nombre_relacionado']]);
                                if ($presentacionN2) {
                                    $pp_cantidad = max((float)$presentacionN2['pp_cantidad'], 0.001);
                                    $unidad_erp  = $presentacionN2['unidad_erp'];
                                    $id_presentacion_erp = $presentacionN2['id_presentacion'];
                                    $nombre_presentacion = $presentacionN2['nombre_erp'];
                                    $factor = (float)$conv['factor_conversion'];
                                    break;
                                }
                            }
                        }
                    }
                }

                // Fórmula: cantidad_erp = (Cantidad_subreceta × factor) / pp_cantidad × ventas
                $cantidad_consumida = ($cantSR * $factor / $pp_cantidad) * $totalVendido;
            }

            // ── Filtro por insumo específico ───────────────────────
            if ($idInsumo > 0 && (int)$id_presentacion_erp !== $idInsumo) {
                continue;
            }

            // ── Acumular ───────────────────────────────────────────
            $pid = (int)$id_presentacion_erp;

            // Guardar metadata de la presentación (solo una vez)
            if (!isset($presentacionesMeta[$pid])) {
                $presentacionesMeta[$pid] = [
                    'id'          => $pid,
                    'nombre'      => $nombre_presentacion,
                    'maestro'     => $nombre_maestro,
                    'unidad'      => $unidad_erp,
                    'es_global'   => $es_global,
                ];
            }

            if (!isset($consumoAgregado[$pid])) $consumoAgregado[$pid] = [];
            if (!isset($consumoAgregado[$pid][$sucursalCod])) $consumoAgregado[$pid][$sucursalCod] = [];
            if (!isset($consumoAgregado[$pid][$sucursalCod][$numSemana])) {
                $consumoAgregado[$pid][$sucursalCod][$numSemana] = 0;
            }

            $consumoAgregado[$pid][$sucursalCod][$numSemana] += $cantidad_consumida;
        }
    } // fin foreach ventas

    // ── 10. Construir respuesta ───────────────────────────────────────
    $listaConsumo = [];
    $semanasNros  = array_keys($semanasMap); // números de semana del rango

    foreach ($consumoAgregado as $pid => $porSucursal) {
        $meta = $presentacionesMeta[$pid];

        // Calcular totales y estadísticas
        $totalGeneral = 0;
        $consumoPorSemana = []; // [semana_num] => total_todas_sucursales
        $porSucursalResumen = [];

        foreach ($porSucursal as $suc => $porSemana) {
            $totalSuc = 0;
            foreach ($porSemana as $sem => $cant) {
                $totalGeneral += $cant;
                $totalSuc     += $cant;
                if (!isset($consumoPorSemana[$sem])) $consumoPorSemana[$sem] = 0;
                $consumoPorSemana[$sem] += $cant;
            }
            $porSucursalResumen[$suc] = round($totalSuc, 4);
        }

        // Semana pico y baja
        $semanaPico = null;
        $semanaLow  = null;
        $maxCons = -1;
        $minCons = PHP_INT_MAX;
        foreach ($consumoPorSemana as $sem => $cons) {
            if ($cons > $maxCons) { $maxCons = $cons; $semanaPico = $sem; }
            if ($cons < $minCons) { $minCons  = $cons; $semanaLow  = $sem; }
        }

        // Promedio por semana (semanas con venta, no el total del rango)
        $semanasConDatos = count($consumoPorSemana);
        $promSemana      = $semanasConDatos > 0 ? $totalGeneral / $semanasConDatos : 0;

        // Proyección 4 semanas (promedio ponderado simple)
        $proyeccion4sem = $promSemana * 4;

        // Stock mínimo = 1 semana, máximo = 2 semanas
        $stockMin = $promSemana;
        $stockMax = $promSemana * 2;

        // Tendencia (comparar primera vs última mitad del período)
        $tendencia = 'flat';
        if (count($consumoPorSemana) >= 2) {
            $semsOrden = array_keys($consumoPorSemana);
            sort($semsOrden);
            $mitad = (int)(count($semsOrden) / 2);
            $primMitad = array_slice($semsOrden, 0, $mitad);
            $segMitad  = array_slice($semsOrden, $mitad);
            $promPrim = array_sum(array_intersect_key($consumoPorSemana, array_flip($primMitad))) / max(count($primMitad), 1);
            $promSeg  = array_sum(array_intersect_key($consumoPorSemana, array_flip($segMitad)))  / max(count($segMitad),  1);
            if ($promSeg > $promPrim * 1.05)      $tendencia = 'up';
            elseif ($promSeg < $promPrim * 0.95)  $tendencia = 'down';
        }

        // Construir desglose semanal completo (para heatmap y gráfico)
        $desgloseSemanal = [];
        foreach ($semanasNros as $semNum) {
            $desgloseSemanal[$semNum] = [];
            foreach ($sucursalesPresentes as $suc) {
                $desgloseSemanal[$semNum][$suc] = round((float)($consumoAgregado[$pid][$suc][$semNum] ?? 0), 4);
            }
        }

        $listaConsumo[] = [
            'id'                => $pid,
            'nombre'            => $meta['nombre'],
            'maestro'           => $meta['maestro'],
            'unidad'            => $meta['unidad'],
            'es_global'         => (bool)$meta['es_global'],
            'total'             => round($totalGeneral, 4),
            'prom_semana'       => round($promSemana, 4),
            'proyeccion_4sem'   => round($proyeccion4sem, 4),
            'stock_min'         => round($stockMin, 4),
            'stock_max'         => round($stockMax, 4),
            'semana_pico_num'   => $semanaPico,
            'semana_low_num'    => $semanaLow,
            'max_consumo_sem'   => round($maxCons, 4),
            'tendencia'         => $tendencia,
            'por_semana'        => $consumoPorSemana,     // {semana_num: total}
            'por_sucursal'      => $porSucursalResumen,   // {suc_cod: total}
            'desglose_semxsuc'  => $desgloseSemanal,      // {semana: {suc: cant}}
        ];
    }

    // Ordenar por consumo total descendente
    usort($listaConsumo, fn($a, $b) => $b['total'] <=> $a['total']);

    // Limpiar sinMapeo: contar productos únicos afectados
    foreach ($sinMapeo as &$sm) {
        $sm['num_productos'] = count($sm['productos_afectados']);
        $sm['ventas_afectadas'] = round($sm['ventas_afectadas'], 2);
        unset($sm['productos_afectados']); // No serializar el array completo
    }
    unset($sm);

    // ── Estadísticas globales ─────────────────────────────────────────
    $consumoTotalGeneral = array_sum(array_column($listaConsumo, 'total'));
    $proySumaTotal = array_sum(array_column($listaConsumo, 'proyeccion_4sem'));

    // Semana pico global (la semana con mayor suma de todos los insumos)
    $sumasPorSemana = [];
    foreach ($listaConsumo as $item) {
        foreach ($item['por_semana'] as $sem => $cant) {
            if (!isset($sumasPorSemana[$sem])) $sumasPorSemana[$sem] = 0;
            $sumasPorSemana[$sem] += $cant;
        }
    }
    $semanaPicoGlobal = null;
    if (!empty($sumasPorSemana)) {
        $semanaPicoGlobal = array_search(max($sumasPorSemana), $sumasPorSemana);
    }

    echo json_encode([
        'ok'                  => true,
        'consumo'             => $listaConsumo,
        'sin_mapeo'           => $sinMapeo,
        'semanas'             => array_values(array_map(function($s) {
            return ['id' => $s['id'], 'numero_semana' => (int)$s['numero_semana'], 'anio' => (int)$s['anio'], 'fecha_inicio' => $s['fecha_inicio'], 'fecha_fin' => $s['fecha_fin']];
        }, $semanasRango)),
        'sucursales'          => $sucursalesPresentes,
        'total_general'       => round($consumoTotalGeneral, 4),
        'proyeccion_total'    => round($proySumaTotal, 4),
        'semana_pico_global'  => $semanaPicoGlobal,
        'num_sin_mapeo'       => count($sinMapeo),
        'num_insumos'         => count($listaConsumo),
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error al calcular consumo: ' . $e->getMessage()]);
}
