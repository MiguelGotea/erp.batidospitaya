<?php
/* ============================================================
   AJAX: Auditoría de consumo — detalle venta × venta
   modulos/productos/ajax/dashboard_consumo_auditoria.php

   Parámetros POST:
     id_presentacion  : id de producto_presentacion a auditar
     semana_desde_num : número de semana inicio
     semana_hasta_num : número de semana fin
     sucursales[]     : (opcional) filtro de sucursales
   ============================================================ */
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');
set_time_limit(0);       // sin límite: la query pesada la hace MySQL
ini_set('memory_limit', '256M');
error_reporting(E_ALL);
ini_set('display_errors', '0');  // no mezclar HTML con JSON

// Convertir warnings/errors a excepciones atrapables
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

// Capturar errores fatales que no lanza exception
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent())
            http_response_code(500);
        echo json_encode(['ok' => false, 'msg' => 'Fatal: ' . $e['message'] . ' en ' . $e['file'] . ':' . $e['line']]);
    }
});

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('dashboard_consumo_insumos', 'vista', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Sin permiso']);
    exit();
}

$idPP = isset($_POST['id_presentacion']) ? (int) $_POST['id_presentacion'] : 0;
$numDesde = isset($_POST['semana_desde_num']) ? (int) $_POST['semana_desde_num'] : 0;
$numHasta = isset($_POST['semana_hasta_num']) ? (int) $_POST['semana_hasta_num'] : 0;
$sucursalesPost = isset($_POST['sucursales']) ? (array) $_POST['sucursales'] : [];

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
               u.nombre AS unidad_erp
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

    $ppCantBase = max((float) $ppDat['pp_cantidad'], 0.001);
    $esGlobal = !empty($ppDat['Id_receta_producto']);
    $idMaestro = (int) $ppDat['id_producto_maestro'];
    $idUnidERP = (int) $ppDat['id_unidad_producto'];

    /* ── CodCotizacion(es) que apuntan a esta presentación ── */
    $stmtCods = $conn->prepare("
        SELECT d.CodCotizacion
        FROM diccionario_productos_legado d
        WHERE d.id_producto_presentacion = ?
    ");
    $stmtCods->execute([$idPP]);
    $codCots = array_column($stmtCods->fetchAll(PDO::FETCH_ASSOC), 'CodCotizacion');

    if (empty($codCots)) {
        echo json_encode(['ok' => false, 'msg' => 'Esta presentación no tiene CodCotizacion mapeado en el diccionario.']);
        exit();
    }

    /* ── PASO A: CodIngredientes vía Cotizaciones (path P2) ─ */
    $codIngs = [];
    if (!empty($codCots)) {
        $phC2    = implode(',', array_fill(0, count($codCots), '?'));
        $stmtCI  = $conn->prepare("
            SELECT DISTINCT CodIngrediente
            FROM Cotizaciones
            WHERE CodCotizacion IN ($phC2)
        ");
        $stmtCI->execute(array_values($codCots));
        $codIngs = array_column($stmtCI->fetchAll(PDO::FETCH_ASSOC), 'CodIngrediente');
    }

    /* ── PASO B: CodBatidos desde SubReceta ─────────────────
       SubReceta es pequeña → consulta rápida.
       Obtenemos todos los CodBatido que usan esta porción
       o ingrediente, para luego filtrar VentasGlobalesAccessCSV
       por CodProducto IN (...) usando su índice idx_codproducto.
    ---------------------------------------------------------- */
    $whereIngSR = '';
    $ingParamsSR = [];

    if (!empty($codCots)) {
        $phCod = implode(',', array_fill(0, count($codCots), '?'));
        $whereIngSR    = "sr.codporcion IN ($phCod)";
        $ingParamsSR   = array_values($codCots);
    }
    if (!empty($codIngs)) {
        $phIng = implode(',', array_fill(0, count($codIngs), '?'));
        $whereIngSR   .= ($whereIngSR ? ' OR ' : '') .
                         "(sr.codporcion IS NULL AND sr.CodIngrediente IN ($phIng))";
        $ingParamsSR   = array_merge($ingParamsSR, array_values($codIngs));
    }

    $codBatidos = [];
    if ($whereIngSR) {
        $stmtSR = $conn->prepare("
            SELECT DISTINCT sr.CodBatido
            FROM SubReceta sr
            WHERE $whereIngSR
        ");
        $stmtSR->execute($ingParamsSR);
        $codBatidos = array_column($stmtSR->fetchAll(PDO::FETCH_ASSOC), 'CodBatido');
    }

    if (empty($codBatidos)) {
        echo json_encode(['ok' => false, 'msg' => 'No hay batidos que usen este ingrediente/porción.']);
        exit();
    }

    /* ── PASO C: Query principal — usa idx_codproducto ──────
       Al filtrar v.CodProducto IN (...codBatidos...) el motor
       usa el índice de VentasGlobalesAccessCSV en lugar de
       hacer full scan. Luego el JOIN con SubReceta aplica solo
       sobre los productos relevantes.
    ---------------------------------------------------------- */
    $phBat    = implode(',', array_fill(0, count($codBatidos), '?'));
    $whereSuc = '';
    $sucParams = [];
    if (!empty($sucursalesPost)) {
        $whereSuc  = ' AND v.local IN (' . implode(',', array_fill(0, count($sucursalesPost), '?')) . ')';
        $sucParams = array_values($sucursalesPost);
    }

    // Reconstruir WHERE ingrediente para el JOIN (igual que arriba)
    $whereIngJoin = $whereIngSR;   // reutiliza la misma condición

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
                                     AND ($whereIngJoin)
        INNER JOIN DBIngredientes ing ON ing.CodIngrediente = sr.CodIngrediente
        WHERE v.Anulado = 0
          AND v.Semana  BETWEEN ? AND ?
          AND v.CodProducto IN ($phBat)
          {$whereSuc}
        GROUP BY v.Semana, v.local, v.Fecha, v.CodProducto,
                 sr.CodIngrediente, sr.Cantidad, sr.codporcion
        ORDER BY v.Semana ASC, v.local ASC, v.Fecha ASC
        LIMIT 5000
    ";

    $positional = [
        $numDesde,
        $numHasta,
    ];
    foreach ($codBatidos  as $b) $positional[] = $b;   // CodProducto IN
    foreach ($sucParams   as $s) $positional[] = $s;   // sucursales IN
    foreach ($ingParamsSR as $p) $positional[] = $p;  // ON ingrediente JOIN

    $stmtV = $conn->prepare($sql);
    $stmtV->execute($positional);
    $ventas = $stmtV->fetchAll(PDO::FETCH_ASSOC);



    /* ── Pre-cargar unidades y conversiones ─────────────── */
    $stmtAllU = $conn->prepare("SELECT id, nombre, abreviado, nombres_opcionales FROM unidad_producto");
    $stmtAllU->execute();
    $todasUnidades = $stmtAllU->fetchAll(PDO::FETCH_ASSOC);

    $unidadPorNombre = [];
    foreach ($todasUnidades as $u) {
        $uid = (int) $u['id'];
        $unidadPorNombre[strtolower(trim($u['nombre']))] = $uid;
        $abr = strtolower(trim($u['abreviado'] ?? ''));
        if ($abr)
            $unidadPorNombre[$abr] = $uid;
        if (!empty($u['nombres_opcionales'])) {
            foreach (preg_split('/[,;|]+/', $u['nombres_opcionales']) as $alias) {
                $ak = strtolower(trim($alias));
                if ($ak)
                    $unidadPorNombre[$ak] = $uid;
            }
        }
    }

    $stmtConv = $conn->prepare("
        SELECT id_unidad_producto_inicio, id_unidad_producto_final, cantidad
        FROM conversion_unidad_producto
    ");
    $stmtConv->execute();
    $convIndex = [];
    foreach ($stmtConv->fetchAll(PDO::FETCH_ASSOC) as $cv) {
        $ini = (int) $cv['id_unidad_producto_inicio'];
        $fin = (int) $cv['id_unidad_producto_final'];
        $fac = (float) $cv['cantidad'];
        $convIndex[$ini][$fin] = $fac;
        $convIndex[$fin][$ini] = ($fac != 0) ? 1 / $fac : 0;
    }

    /* ── Presentaciones del maestro para niveles 2/3 ─────── */
    $presentPorMaestro = [];
    if ($idMaestro) {
        $stmtPM = $conn->prepare("
            SELECT pp.id, pp.cantidad AS pp_cantidad, pp.id_unidad_producto
            FROM producto_presentacion pp
            WHERE pp.id_producto_maestro = ?
              AND pp.Id_receta_producto IS NULL
              AND pp.Activo = 'SI'
        ");
        $stmtPM->execute([$idMaestro]);
        foreach ($stmtPM->fetchAll(PDO::FETCH_ASSOC) as $pp) {
            $presentPorMaestro[(int) $pp['id_unidad_producto']] = $pp;
        }
    }

    /* ── Set de codCots para detectar P1 ─────────────────── */
    $codCotSet = array_flip(array_map('strval', $codCots));

    /* ── Calcular detalle fila por fila ─────────────────── */
    $filas = [];

    foreach ($ventas as $v) {
        $unidAcc = $v['unidad_access'] ?? '';
        $codporc = $v['codporcion'];
        $cantTotal = (float) $v['cant_total_raw'];

        // ¿Es P1? (codporcion apunta directamente a esta presentación)
        $esP1 = !empty($codporc) && isset($codCotSet[(string) $codporc]);

        $factor = 1.0;
        $ppCant = $ppCantBase;
        $nivelUsado = 'mismo';
        $consumoCrudo = 0.0;
        $consumoFinal = 0.0;

        if ($esGlobal) {
            $consumoCrudo = $cantTotal;
            $consumoFinal = $cantTotal;
            $nivelUsado = 'global';
        } else {
            // Resolver factor de conversión de unidad
            $idUnidAcc = $unidadPorNombre[strtolower(trim($unidAcc))] ?? null;

            if ($idUnidAcc && $idUnidAcc !== $idUnidERP) {
                $factorDir = $convIndex[$idUnidAcc][$idUnidERP] ?? null;
                if ($factorDir !== null) {
                    $factor = $factorDir;
                    $nivelUsado = 'conversion_directa';
                } else {
                    // Nivel 2: presentación del maestro con la unidad de Access
                    $ppAlt = $presentPorMaestro[$idUnidAcc] ?? null;
                    if ($ppAlt) {
                        $ppCant = max((float) $ppAlt['pp_cantidad'], 0.001);
                        $factor = 1.0;
                        $nivelUsado = 'nivel2_maestro';
                    } else {
                        // Nivel 3: buscar via conversiones disponibles
                        if (isset($convIndex[$idUnidAcc])) {
                            foreach ($convIndex[$idUnidAcc] as $idDest => $fconv) {
                                $ppC = $presentPorMaestro[$idDest] ?? null;
                                if ($ppC) {
                                    $ppCant = max((float) $ppC['pp_cantidad'], 0.001);
                                    $factor = $fconv;
                                    $nivelUsado = 'nivel3_conversion';
                                    break;
                                }
                            }
                        }
                        if ($nivelUsado === 'mismo')
                            $nivelUsado = 'factor1_fallback';
                    }
                }
            }

            $consumoCrudo = ($cantTotal * $factor) / $ppCant;
            // P1: redondear al 0.5 más cercano
            $consumoFinal = $esP1 ? (round($consumoCrudo * 2) / 2) : $consumoCrudo;
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
            'consumo_final' => $consumoFinal,
            'es_p1' => $esP1,
            'nivel' => $nivelUsado,
            'genera_decimal' => $esP1 && (abs($consumoCrudo - $consumoFinal) > 0.001),
        ];
    }

    echo json_encode([
        'ok' => true,
        'presentacion' => [
            'id' => $idPP,
            'nombre' => $ppDat['Nombre'],
            'unidad' => $ppDat['unidad_erp'],
            'pp_cant' => $ppCantBase,
            'es_global' => $esGlobal,
        ],
        'filas' => $filas,
        'total_filas' => count($filas),
        'total_consumo' => round(array_sum(array_column($filas, 'consumo_final')), 4),
        'cod_cots' => $codCots,
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'msg' => 'Error: ' . $e->getMessage()
            . ' en ' . basename($e->getFile()) . ':' . $e->getLine(),
    ]);
}
