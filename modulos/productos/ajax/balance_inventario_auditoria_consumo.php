<?php
/* ============================================================
   AJAX: Auditoría de consumo teórico — detalle venta × venta
   modulos/productos/ajax/balance_inventario_auditoria_consumo.php

   Usa la misma lógica de resolución robusta (4 niveles) que
   balance_inventario_get_detalle.php para construir el codMap.

   Parámetros POST:
     id_presentacion  : id de producto_presentacion a auditar
     semana_desde_num : número de semana inicio
     semana_hasta_num : número de semana fin
     sucursales[]     : (opcional) filtro de sucursales
   ============================================================ */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');
set_time_limit(0);
ini_set('memory_limit', '256M');
error_reporting(E_ALL);
ini_set('display_errors', '0');

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) http_response_code(500);
        echo json_encode(['ok' => false, 'msg' => 'Fatal: ' . $e['message']]);
    }
});

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('balance_inventario_access_host', 'vista', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Sin permiso']);
    exit();
}

$idPP     = isset($_POST['id_presentacion'])  ? (int)$_POST['id_presentacion']  : 0;
$numDesde = isset($_POST['semana_desde_num']) ? (int)$_POST['semana_desde_num'] : 0;
$numHasta = isset($_POST['semana_hasta_num']) ? (int)$_POST['semana_hasta_num'] : 0;
$sucursalesPost = isset($_POST['sucursales']) ? (array)$_POST['sucursales'] : [];

if (!$idPP || !$numDesde || !$numHasta) {
    echo json_encode(['ok' => false, 'msg' => 'Parámetros incompletos.']);
    exit();
}
$numDesde = min($numDesde, $numHasta);
$numHasta = max($numDesde, $numHasta);

try {
    /* ── Rango de fechas ─────────────────────────────────── */
    $stmtRango = $conn->prepare("
        SELECT MIN(fecha_inicio) AS fecha_desde, MAX(fecha_fin) AS fecha_hasta
        FROM SemanasSistema
        WHERE numero_semana BETWEEN ? AND ?
    ");
    $stmtRango->execute([$numDesde, $numHasta]);
    $rango = $stmtRango->fetch(PDO::FETCH_ASSOC);

    if (!$rango || !$rango['fecha_desde']) {
        echo json_encode(['ok' => false, 'msg' => "No hay semanas {$numDesde}–{$numHasta}."]);
        exit();
    }

    /* ── Datos de la presentación destino ────────────────── */
    $stmtPP = $conn->prepare("
        SELECT pp.id, pp.Nombre, pp.cantidad AS pp_cantidad,
               pp.Id_receta_producto,
               pp.id_producto_maestro,
               pp.id_unidad_producto,
               u.nombre AS unidad_erp,
               pp.categoria_insumo
        FROM producto_presentacion pp
        LEFT JOIN unidad_producto u ON u.id = pp.id_unidad_producto
        WHERE pp.id = ?
    ");
    $stmtPP->execute([$idPP]);
    $ppDat = $stmtPP->fetch(PDO::FETCH_ASSOC);

    if (!$ppDat) {
        echo json_encode(['ok' => false, 'msg' => 'Presentación no encontrada.']);
        exit();
    }

    $ppCantBase = max((float)$ppDat['pp_cantidad'], 0.001);
    $esGlobal   = !empty($ppDat['Id_receta_producto']);
    $idMaestro  = (int)$ppDat['id_producto_maestro'];
    $idUnidERP  = (int)$ppDat['id_unidad_producto'];

    /* ── Conversiones ────────────────────────────────────── */
    $convIndex = [];
    $rConv = $conn->prepare("SELECT id_unidad_producto_inicio AS i, id_unidad_producto_final AS f, cantidad AS fac FROM conversion_unidad_producto");
    $rConv->execute();
    foreach ($rConv->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $convIndex[(int)$c['i']][(int)$c['f']] = (float)$c['fac'];
        $convIndex[(int)$c['f']][(int)$c['i']] = $c['fac'] != 0 ? 1 / (float)$c['fac'] : 0;
    }

    /* ── Cascade Map (paquetes) ──────────────────────────── */
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

    /* ── Lógica robusta de 4 niveles para construir codMap ─ */
    // Igual que balance_inventario_get_detalle.php
    $codMap  = []; // CodCotizacion => {factor, nombre, tipo, pp_id, id_unid, pp_cant, id_mae}
    $ingsBase = [];

    // Nivel 1: ingredientes vía diccionario directo + maestro
    $stmtRel = $conn->prepare("
        SELECT DISTINCT c.CodIngrediente
        FROM Cotizaciones c
        INNER JOIN diccionario_productos_legado d ON d.CodCotizacion = c.CodCotizacion
        INNER JOIN producto_presentacion pp ON pp.id = d.id_producto_presentacion
        WHERE pp.id = :id
           OR (pp.id_producto_maestro = :mid AND pp.Activo='SI' AND :mid2 > 0)
    ");
    $stmtRel->execute([':id' => $idPP, ':mid' => $idMaestro, ':mid2' => $idMaestro]);
    $ingsBase = $stmtRel->fetchAll(PDO::FETCH_COLUMN);

    // Nivel 2: ingredientes vía Step C (mismo maestro, transitividad)
    if ($idMaestro > 0) {
        $stmtStepC = $conn->prepare("
            SELECT DISTINCT c_src.CodIngrediente
            FROM Cotizaciones c_src
            INNER JOIN Cotizaciones c_all ON c_all.CodIngrediente = c_src.CodIngrediente
            INNER JOIN diccionario_productos_legado d2 ON d2.CodCotizacion = c_all.CodCotizacion
            INNER JOIN producto_presentacion pp_any ON pp_any.id = d2.id_producto_presentacion
            WHERE pp_any.id_producto_maestro = :mid AND pp_any.Activo='SI'
        ");
        $stmtStepC->execute([':mid' => $idMaestro]);
        $ingsBase = array_unique(array_merge($ingsBase, $stmtStepC->fetchAll(PDO::FETCH_COLUMN)));
    }

    // Fallback: mapeo directo básico si nada encontrado aún
    if (empty($ingsBase)) {
        $stmtF = $conn->prepare("SELECT DISTINCT CodIngrediente FROM Cotizaciones c INNER JOIN diccionario_productos_legado d ON d.CodCotizacion = c.CodCotizacion WHERE d.id_producto_presentacion = ?");
        $stmtF->execute([$idPP]);
        $ingsBase = $stmtF->fetchAll(PDO::FETCH_COLUMN);
    }

    if (!empty($ingsBase)) {
        $phI = implode(',', array_fill(0, count($ingsBase), '?'));

        // Todas las cotizaciones de estos ingredientes
        $stmtAllCots = $conn->prepare("SELECT CodCotizacion, CodIngrediente, Conversion, Prioridad FROM Cotizaciones WHERE CodIngrediente IN ($phI)");
        $stmtAllCots->execute($ingsBase);
        $allCots = $stmtAllCots->fetchAll(PDO::FETCH_ASSOC);

        // Diccionario para estas cotizaciones
        $phC_list = array_column($allCots, 'CodCotizacion');
        if (!empty($phC_list)) {
            $phC = implode(',', array_fill(0, count($phC_list), '?'));
            $stmtDic = $conn->prepare("
                SELECT d.CodCotizacion, pp.id AS pp_id, pp.Nombre, pp.id_unidad_producto, pp.cantidad, pp.id_producto_maestro,
                       pp.presentacion_basica_inventario, pp.presentacion_receta
                FROM diccionario_productos_legado d
                INNER JOIN producto_presentacion pp ON pp.id = d.id_producto_presentacion
                WHERE d.CodCotizacion IN ($phC) AND pp.Activo='SI'
            ");
            $stmtDic->execute($phC_list);
            $dicMap = [];
            foreach ($stmtDic->fetchAll(PDO::FETCH_ASSOC) as $r) $dicMap[(int)$r['CodCotizacion']] = $r;

            foreach ($allCots as $cot) {
                $cid     = (int)$cot['CodCotizacion'];
                $mapeoRow = null;
                $type    = 'alternativa';
                $fac     = null;

                if (isset($dicMap[$cid])) {
                    $row = $dicMap[$cid];
                    if ((int)$row['pp_id'] === $idPP) {
                        $fac  = 1.0;
                        $type = 'base';
                    } elseif ($idMaestro > 0 && (int)$row['id_producto_maestro'] === $idMaestro) {
                        $u = (int)$row['id_unidad_producto'];
                        $c = max((float)$row['cantidad'], 0.001);
                        if ($u === $idUnidERP) $fac = $c / $ppCantBase;
                        elseif (isset($convIndex[$u][$idUnidERP])) $fac = ($c * $convIndex[$u][$idUnidERP]) / $ppCantBase;
                        if ($row['presentacion_basica_inventario']) $type = 'base';
                    } elseif (isset($cascadeMap[(int)$row['pp_id']]) && $cascadeMap[(int)$row['pp_id']]['base_id'] === $idPP) {
                        $fac  = $cascadeMap[(int)$row['pp_id']]['factor'];
                        $type = 'cascada';
                    }
                    $mapeoRow = $row;
                }

                // Fallback auto_ingrediente
                if ($fac === null && in_array($cot['CodIngrediente'], $ingsBase)) {
                    $fac  = 1.0;
                    $type = 'auto_ingrediente';
                }

                if ($fac !== null) {
                    $codMap[$cid] = [
                        'factor'  => $fac,
                        'nombre'  => $mapeoRow['Nombre'] ?? ('Cod: '.$cid),
                        'tipo'    => $type,
                        'pp_id'   => $mapeoRow['pp_id'] ?? $idPP,
                        'id_unid' => $mapeoRow['id_unidad_producto'] ?? $idUnidERP,
                        'pp_cant' => max((float)($mapeoRow['cantidad'] ?? $ppCantBase), 0.001),
                        'id_mae'  => $mapeoRow['id_producto_maestro'] ?? $idMaestro,
                    ];
                }
            }
        }
    }

    // codCots = todas las CodCotizacion resueltas en el codMap
    $codCots = array_keys($codMap);

    if (empty($codCots)) {
        echo json_encode(['ok' => false, 'msg' => 'No se encontraron cotizaciones mapeadas para esta presentación (ni directo, ni vía maestro).']);
        exit();
    }

    /* ── CodIngredientes de las cotizaciones resueltas ───── */
    $phCM = implode(',', array_fill(0, count($codCots), '?'));
    $stmtCI = $conn->prepare("SELECT DISTINCT CodIngrediente FROM Cotizaciones WHERE CodCotizacion IN ($phCM)");
    $stmtCI->execute(array_values($codCots));
    $codIngs = array_column($stmtCI->fetchAll(PDO::FETCH_ASSOC), 'CodIngrediente');

    /* ── CodBatidos desde SubReceta ──────────────────────── */
    $whereIngSR  = '';
    $ingParamsSR = [];

    // Porción directa (codporcion)
    $whereIngSR   = "sr.codporcion IN ($phCM)";
    $ingParamsSR  = array_values($codCots);

    // Ingrediente (sin porción)
    if (!empty($codIngs)) {
        $phIng = implode(',', array_fill(0, count($codIngs), '?'));
        $whereIngSR  .= " OR (sr.codporcion IS NULL AND sr.CodIngrediente IN ($phIng))";
        $ingParamsSR  = array_merge($ingParamsSR, array_values($codIngs));
    }

    $stmtSR = $conn->prepare("SELECT DISTINCT sr.CodBatido FROM SubReceta sr WHERE $whereIngSR");
    $stmtSR->execute($ingParamsSR);
    $codBatidos = array_column($stmtSR->fetchAll(PDO::FETCH_ASSOC), 'CodBatido');

    if (empty($codBatidos)) {
        echo json_encode(['ok' => false, 'msg' => 'No hay batidos que usen este ingrediente/porción.']);
        exit();
    }

    /* ── Pre-cargar unidades y conversiones ──────────────── */
    $stmtAllU = $conn->prepare("SELECT id, nombre, abreviado, nombres_opcionales FROM unidad_producto");
    $stmtAllU->execute();
    $unidadPorNombre = [];
    foreach ($stmtAllU->fetchAll(PDO::FETCH_ASSOC) as $u) {
        $uid = (int)$u['id'];
        $unidadPorNombre[strtolower(trim($u['nombre']))] = $uid;
        $abr = strtolower(trim($u['abreviado'] ?? ''));
        if ($abr) $unidadPorNombre[$abr] = $uid;
        if (!empty($u['nombres_opcionales'])) {
            foreach (preg_split('/[,;|]+/', $u['nombres_opcionales']) as $alias) {
                $ak = strtolower(trim($alias));
                if ($ak) $unidadPorNombre[$ak] = $uid;
            }
        }
    }

    /* ── Pre-cargar DBIngredientes (unidades Access) ─────── */
    $dbIng = [];
    if (!empty($codIngs)) {
        $phIngDb = implode(',', array_fill(0, count($codIngs), '?'));
        $stmtIng = $conn->prepare("SELECT CodIngrediente, Unidad, Nombre FROM DBIngredientes WHERE CodIngrediente IN ($phIngDb)");
        $stmtIng->execute(array_values($codIngs));
        foreach ($stmtIng->fetchAll(PDO::FETCH_ASSOC) as $row) $dbIng[$row['CodIngrediente']] = $row;
    }

    /* ── PASO C: Query principal ──────────────────────────── */
    $phBat    = implode(',', array_fill(0, count($codBatidos), '?'));
    $whereSuc = '';
    $sucParams = [];
    if (!empty($sucursalesPost)) {
        $whereSuc  = ' AND v.local IN (' . implode(',', array_fill(0, count($sucursalesPost), '?')) . ')';
        $sucParams = array_values($sucursalesPost);
    }

    $sql = "
        SELECT
            v.Fecha,
            v.Semana             AS semana,
            v.local              AS sucursal,
            v.DBBatidos_Nombre   AS nombre_batido,
            v.CodProducto,
            sr.CodIngrediente,
            ing.Nombre           AS nombre_ingrediente,
            ing.Unidad           AS unidad_access,
            sr.Cantidad          AS cant_receta,
            sr.codporcion,
            SUM(v.Cantidad)               AS ventas_sum,
            SUM(v.Cantidad * sr.Cantidad) AS cant_total_raw
        FROM VentasGlobalesAccessCSV v
        INNER JOIN SubReceta sr       ON sr.CodBatido       = v.CodProducto
        INNER JOIN DBIngredientes ing ON ing.CodIngrediente = sr.CodIngrediente
        WHERE v.Anulado = 0
          AND v.Fecha   BETWEEN ? AND ?
          AND v.Semana  BETWEEN ? AND ?
          AND v.CodProducto IN ($phBat)
          AND (sr.codporcion IS NULL OR sr.codporcion NOT IN (
              SELECT CodCotizacionPorcion
              FROM MezclaPorcionesAccess
              WHERE CodCotizacionPorcion IS NOT NULL
          ))
          {$whereSuc}
          AND ($whereIngSR)
        GROUP BY v.Fecha, v.Semana, v.local, v.DBBatidos_Nombre,
                 v.CodProducto, sr.CodIngrediente, ing.Nombre,
                 ing.Unidad, sr.Cantidad, sr.codporcion
        ORDER BY v.Semana ASC, v.local ASC, v.Fecha ASC
        LIMIT 5000
    ";

    $positional = [
        $rango['fecha_desde'],
        $rango['fecha_hasta'],
        $numDesde,
        $numHasta,
    ];
    foreach ($codBatidos  as $b) $positional[] = $b;
    foreach ($sucParams   as $s) $positional[] = $s;
    foreach ($ingParamsSR as $p) $positional[] = $p;

    $stmtV = $conn->prepare($sql);
    $stmtV->execute($positional);
    $ventas = $stmtV->fetchAll(PDO::FETCH_ASSOC);

    /* ── Calcular detalle fila por fila ──────────────────── */
    // Usamos codMap para resolver cada fila (misma lógica que get_detalle)
    $codCotSet = array_flip(array_map('strval', $codCots)); // lookup rápido

    // Pre-cargar P2/P3 para tipo_mapeo badge
    $cotP2P3Map = [];

    // Detectar si el producto tiene presentacion_receta=1 para desactivar P2/P3
    // (evita absorber consumo de ingredientes compartidos con otras presentaciones).
    $esRecetaTarget = false;
    if (!empty($phC_list)) {
        // Buscar el entry del diccionario cuyo pp_id === $idPP
        foreach ($dicMap as $row) {
            if ((int)$row['pp_id'] === $idPP) {
                $esRecetaTarget = (bool)$row['presentacion_receta'];
                break;
            }
        }
    }
    if (!empty($codIngs)) {
        $phCot2 = implode(',', array_fill(0, count($codIngs), '?'));
        $stmtCot2 = $conn->prepare("
            SELECT CodIngrediente, CodCotizacion, Conversion, Prioridad
            FROM Cotizaciones
            WHERE CodIngrediente IN ($phCot2)
              AND (Subproducto IS NULL OR Subproducto != 1)
              AND (Marca IS NULL OR Marca != 'Almacen Global')
            ORDER BY CodIngrediente, Conversion DESC, Prioridad ASC
        ");
        $stmtCot2->execute(array_values($codIngs));
        foreach ($stmtCot2->fetchAll(PDO::FETCH_ASSOC) as $ct) {
            $ci = $ct['CodIngrediente'];
            if (!isset($cotP2P3Map[$ci])) $cotP2P3Map[$ci] = ['p2' => null, 'p3' => null];
            if ($ct['Conversion'] == 1 && $ct['Prioridad'] == 1 && !$cotP2P3Map[$ci]['p2'])
                $cotP2P3Map[$ci]['p2'] = (string)$ct['CodCotizacion'];
            if (!$cotP2P3Map[$ci]['p3'])
                $cotP2P3Map[$ci]['p3'] = (string)$ct['CodCotizacion'];
        }
    }

    $filas = [];

    foreach ($ventas as $v) {
        $unidAcc   = $v['unidad_access'] ?? '';
        $codporc   = $v['codporcion'];
        $ci        = $v['CodIngrediente'];
        $cantTotal = (float)$v['cant_total_raw'];

        // Resolver mapeo: P1 (porción directa) > P2/P3 (ingrediente)
        $mapeo = null;
        $esP1  = false;
        $cp    = $codporc ? (int)$codporc : null;

        if ($cp && isset($codMap[$cp])) {
            $mapeo = $codMap[$cp];
            $esP1  = true;
        } elseif (!$esRecetaTarget && $ci && isset($cotP2P3Map[$ci])) {
            // P2/P3 solo aplica si el producto NO es presentacion_receta
            $p2 = $cotP2P3Map[$ci]['p2'];
            $p3 = $cotP2P3Map[$ci]['p3'];
            if ($p2 && isset($codMap[(int)$p2])) $mapeo = $codMap[(int)$p2];
            elseif ($p3 && isset($codMap[(int)$p3])) $mapeo = $codMap[(int)$p3];
        }

        if (!$mapeo) continue; // fila no aplica a esta presentación

        $factor       = 1.0;
        $ppCant       = $mapeo['pp_cant'];
        $nivelUsado   = $mapeo['tipo'];
        $consumoCrudo = 0.0;
        $consumoFinal = 0.0;

        if ($esGlobal) {
            $consumoCrudo = $cantTotal;
            $consumoFinal = $cantTotal;
            $nivelUsado   = 'global';
        } else {
            $idUnidAcc = $unidadPorNombre[strtolower(trim($unidAcc))] ?? null;
            $idUnidMap = (int)$mapeo['id_unid'];

            if ($idUnidAcc && $idUnidAcc !== $idUnidMap) {
                if (isset($convIndex[$idUnidAcc][$idUnidMap])) {
                    $factor     = $convIndex[$idUnidAcc][$idUnidMap];
                    $nivelUsado = 'conversion_directa';
                }
            }

            $consumoCrudo = ($cantTotal * $factor) / $ppCant;
            if ($esP1) {
                $consumoFinal = round($consumoCrudo * 2) / 2;
            } else {
                $consumoFinal = round($consumoCrudo, 4);
            }
        }

        // Tipo mapeo badge
        $codIngFila = $v['CodIngrediente'];
        if ($esP1) {
            $tipoMapeo = 'P1';
        } elseif (
            isset($cotP2P3Map[$codIngFila]['p2']) &&
            isset($codMap[(int)$cotP2P3Map[$codIngFila]['p2']]) &&
            $codMap[(int)$cotP2P3Map[$codIngFila]['p2']]['tipo'] === 'base'
        ) {
            $tipoMapeo = 'P2';
        } else {
            $tipoMapeo = 'P3';
        }

        $filas[] = [
            'fecha'              => $v['Fecha'],
            'semana'             => (int)$v['semana'],
            'sucursal'           => $v['sucursal'],
            'nombre_batido'      => $v['nombre_batido'] ?? $v['CodProducto'],
            'nombre_ingrediente' => $v['nombre_ingrediente'],
            'unidad_access'      => $unidAcc,
            'codporcion'         => $codporc,
            'cant_receta'        => (float)$v['cant_receta'],
            'ventas'             => round((float)$v['ventas_sum'], 2),
            'cant_total'         => round($cantTotal, 4),
            'factor'             => round($factor, 6),
            'pp_cantidad'        => $ppCant,
            'consumo_crudo'      => round($consumoCrudo, 4),
            'consumo_final'      => $consumoFinal,
            'es_p1'              => $esP1,
            'tipo_mapeo'         => $tipoMapeo,
            'nivel'              => $nivelUsado,
            'genera_decimal'     => $esP1 && (abs($consumoCrudo - $consumoFinal) > 0.001),
        ];
    }

    echo json_encode([
        'ok'           => true,
        'presentacion' => [
            'id'               => $idPP,
            'nombre'           => $ppDat['Nombre'],
            'unidad'           => $ppDat['unidad_erp'],
            'categoria_insumo' => $ppDat['categoria_insumo'],
            'pp_cant'          => $ppCantBase,
            'es_global'        => $esGlobal,
        ],
        'filas'         => $filas,
        'total_filas'   => count($filas),
        'total_consumo' => round(array_sum(array_column($filas, 'consumo_final')), 4),
        'cod_cots'      => $codCots,
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'  => false,
        'msg' => 'Error: ' . $e->getMessage() . ' en ' . basename($e->getFile()) . ':' . $e->getLine(),
    ]);
}
