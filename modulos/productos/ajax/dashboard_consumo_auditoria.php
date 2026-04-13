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
set_time_limit(60);

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('dashboard_consumo_insumos', 'vista', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Sin permiso']);
    exit();
}

$idPP        = isset($_POST['id_presentacion'])  ? (int)$_POST['id_presentacion']  : 0;
$numDesde    = isset($_POST['semana_desde_num']) ? (int)$_POST['semana_desde_num'] : 0;
$numHasta    = isset($_POST['semana_hasta_num']) ? (int)$_POST['semana_hasta_num'] : 0;
$sucursalesPost = isset($_POST['sucursales'])    ? (array)$_POST['sucursales']     : [];

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
        WHERE numero_semana BETWEEN :d AND :h
    ");
    $stmtRango->execute([':d' => $numDesde, ':h' => $numHasta]);
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
               u.nombre AS unidad_erp
        FROM producto_presentacion pp
        LEFT JOIN unidad_producto u ON u.id = pp.id_unidad_producto
        WHERE pp.id = :id
    ");
    $stmtPP->execute([':id' => $idPP]);
    $ppDat = $stmtPP->fetch(PDO::FETCH_ASSOC);

    if (!$ppDat) {
        echo json_encode(['ok' => false, 'msg' => 'Presentación no encontrada.']);
        exit();
    }

    $ppCantBase  = max((float)$ppDat['pp_cantidad'], 0.001);
    $esGlobal    = !empty($ppDat['Id_receta_producto']);
    $idMaestro   = (int)$ppDat['id_producto_maestro'];

    /* ── CodCotizacion(es) que apuntan a esta presentación ── */
    $stmtCods = $conn->prepare("
        SELECT d.CodCotizacion
        FROM diccionario_productos_legado d
        WHERE d.id_producto_presentacion = :id
    ");
    $stmtCods->execute([':id' => $idPP]);
    $codCots = array_column($stmtCods->fetchAll(PDO::FETCH_ASSOC), 'CodCotizacion');

    if (empty($codCots)) {
        echo json_encode(['ok' => false, 'msg' => 'Esta presentación no tiene CodCotizacion mapeado.']);
        exit();
    }

    /* ── Query ventas individuales (sin GROUP BY) ─────────── */
    $whereSuc = '';
    $params = [
        ':fecha_desde' => $rango['fecha_desde'],
        ':fecha_hasta' => $rango['fecha_hasta'],
        ':sem_desde'   => $numDesde,
        ':sem_hasta'   => $numHasta,
    ];
    if (!empty($sucursalesPost)) {
        $phSuc = [];
        foreach ($sucursalesPost as $i => $s) {
            $k = ':suc' . $i;
            $phSuc[]    = $k;
            $params[$k] = $s;
        }
        $whereSuc = ' AND v.local IN (' . implode(',', $phSuc) . ')';
    }

    // Construir IN para CodCotizacion
    $phCod = implode(',', array_fill(0, count($codCots), '?'));
    // Necesitamos mezclar params: usamos statement separado

    $sqlVentas = "
        SELECT
            v.Folio,
            v.Fecha,
            v.Semana       AS semana,
            v.local        AS sucursal,
            b.Nombre       AS nombre_batido,
            v.CodProducto,
            v.Cantidad     AS ventas,
            sr.CodIngrediente,
            ing.Nombre     AS nombre_ingrediente,
            ing.Unidad     AS unidad_access,
            sr.Cantidad    AS cant_receta,
            sr.codporcion,
            (v.Cantidad * sr.Cantidad) AS cant_total_raw
        FROM VentasGlobalesAccessCSV v
        INNER JOIN SubReceta sr ON sr.CodBatido = v.CodProducto
        INNER JOIN DBIngredientes ing ON ing.CodIngrediente = sr.CodIngrediente
        LEFT  JOIN Batidos b ON b.CodBatido = v.CodProducto
        WHERE v.Anulado = 0
          AND v.Fecha BETWEEN :fecha_desde AND :fecha_hasta
          AND v.Semana BETWEEN :sem_desde AND :sem_hasta
          AND v.CodProducto IS NOT NULL
          $whereSuc
          AND (
              sr.codporcion IN ($phCod)
              OR (
                  sr.codporcion IS NULL
                  AND sr.CodIngrediente IN (
                      SELECT c.CodIngrediente
                      FROM Cotizaciones c
                      WHERE c.CodCotizacion IN ($phCod)
                  )
              )
          )
        ORDER BY v.Semana ASC, v.local ASC, v.Fecha ASC, v.Folio ASC
        LIMIT 5000
    ";

    // Preparar con parámetros mixtos (named + positional no mezclan en PDO)
    // Convertir todo a positional
    $sqlPos  = "
        SELECT
            v.Folio,
            v.Fecha,
            v.Semana       AS semana,
            v.local        AS sucursal,
            b.Nombre       AS nombre_batido,
            v.CodProducto,
            v.Cantidad     AS ventas,
            sr.CodIngrediente,
            ing.Nombre     AS nombre_ingrediente,
            ing.Unidad     AS unidad_access,
            sr.Cantidad    AS cant_receta,
            sr.codporcion,
            (v.Cantidad * sr.Cantidad) AS cant_total_raw
        FROM VentasGlobalesAccessCSV v
        INNER JOIN SubReceta sr ON sr.CodBatido = v.CodProducto
        INNER JOIN DBIngredientes ing ON ing.CodIngrediente = sr.CodIngrediente
        LEFT  JOIN Batidos b ON b.CodBatido = v.CodProducto
        WHERE v.Anulado = 0
          AND v.Fecha BETWEEN ? AND ?
          AND v.Semana BETWEEN ? AND ?
          AND v.CodProducto IS NOT NULL
          {$whereSuc}
          AND (
              sr.codporcion IN ($phCod)
              OR (
                  sr.codporcion IS NULL
                  AND sr.CodIngrediente IN (
                      SELECT c.CodIngrediente
                      FROM Cotizaciones c
                      WHERE c.CodCotizacion IN ($phCod)
                  )
              )
          )
        ORDER BY v.Semana ASC, v.local ASC, v.Fecha ASC, v.Folio ASC
        LIMIT 5000
    ";

    // Armar array positional
    $positional = [
        $rango['fecha_desde'],
        $rango['fecha_hasta'],
        $numDesde,
        $numHasta,
    ];
    if (!empty($sucursalesPost)) {
        foreach ($sucursalesPost as $s) $positional[] = $s;
    }
    // codporcion IN
    foreach ($codCots as $c) $positional[] = $c;
    // CodIngrediente IN (subquery)
    foreach ($codCots as $c) $positional[] = $c;

    $stmtV = $conn->prepare($sqlPos);
    $stmtV->execute($positional);
    $ventas = $stmtV->fetchAll(PDO::FETCH_ASSOC);

    /* ── Cargar conversiones y unidades (igual que get_datos) */
    $stmtAllU = $conn->prepare("SELECT id, nombre, abreviado, nombres_opcionales FROM unidad_producto");
    $stmtAllU->execute();
    $todasUnidades = $stmtAllU->fetchAll(PDO::FETCH_ASSOC);

    $unidadPorNombre = [];
    foreach ($todasUnidades as $u) {
        $uid = (int)$u['id'];
        $unidadPorNombre[strtolower(trim($u['nombre']))]    = $uid;
        $ak = strtolower(trim($u['abreviado'] ?? ''));
        if ($ak) $unidadPorNombre[$ak] = $uid;
        if (!empty($u['nombres_opcionales'])) {
            foreach (preg_split('/[,;|]+/', $u['nombres_opcionales']) as $alias) {
                $ak2 = strtolower(trim($alias));
                if ($ak2) $unidadPorNombre[$ak2] = $uid;
            }
        }
    }
    $unidadPorId = [];
    foreach ($todasUnidades as $u) $unidadPorId[(int)$u['id']] = $u;

    $stmtConv = $conn->prepare("SELECT id_unidad_producto_inicio, id_unidad_producto_final, cantidad FROM conversion_unidad_producto");
    $stmtConv->execute();
    $convIndex = [];
    foreach ($stmtConv->fetchAll(PDO::FETCH_ASSOC) as $cv) {
        $ini = (int)$cv['id_unidad_producto_inicio'];
        $fin = (int)$cv['id_unidad_producto_final'];
        $fac = (float)$cv['cantidad'];
        $convIndex[$ini][$fin] = $fac;
        $convIndex[$fin][$ini] = ($fac != 0) ? 1 / $fac : 0;
    }

    /* ── Unidad ERP de la presentación ───────────────────── */
    $stmtUid = $conn->prepare("SELECT id_unidad_producto FROM producto_presentacion WHERE id = ?");
    $stmtUid->execute([$idPP]);
    $idUnidERP = (int)($stmtUid->fetchColumn() ?: 0);

    /* ── Presentaciones del maestro para nivel 2/3 ───────── */
    $presentPorMaestro = [];
    if ($idMaestro) {
        $stmtPM = $conn->prepare("
            SELECT pp.id, pp.id_producto_maestro, pp.cantidad AS pp_cantidad,
                   pp.id_unidad_producto, u.nombre AS unidad_nombre
            FROM producto_presentacion pp
            LEFT JOIN unidad_producto u ON u.id = pp.id_unidad_producto
            WHERE pp.id_producto_maestro = ?
              AND pp.Id_receta_producto IS NULL
              AND pp.Activo = 'SI'
        ");
        $stmtPM->execute([$idMaestro]);
        foreach ($stmtPM->fetchAll(PDO::FETCH_ASSOC) as $pp) {
            $presentPorMaestro[$idMaestro][(int)$pp['id_unidad_producto']] = $pp;
        }
    }

    /* ── Calcular detalle por fila ───────────────────────── */
    $filas = [];
    $codCotSet = array_flip(array_map('strval', $codCots));

    foreach ($ventas as $v) {
        $unidAcc  = $v['unidad_access'] ?? '';
        $codporc  = $v['codporcion'];
        $esP1     = isset($codCotSet[(string)$codporc]);

        // Resolver factor
        $factor   = 1.0;
        $ppCant   = $ppCantBase;
        $nivelUsado = 'mismo';

        if (!$esGlobal) {
            $idUnidAcc = $unidadPorNombre[strtolower(trim($unidAcc))] ?? null;
            if ($idUnidAcc && $idUnidAcc !== $idUnidERP) {
                $factorDir = $convIndex[$idUnidAcc][$idUnidERP] ?? null;
                if ($factorDir !== null) {
                    $factor     = $factorDir;
                    $nivelUsado = 'conversion_directa';
                } else {
                    // Nivel 2
                    $ppAlt = $presentPorMaestro[$idMaestro][$idUnidAcc] ?? null;
                    if ($ppAlt) {
                        $ppCant     = max((float)$ppAlt['pp_cantidad'], 0.001);
                        $factor     = 1.0;
                        $nivelUsado = 'nivel2_maestro';
                    } else {
                        // Nivel 3
                        if (isset($convIndex[$idUnidAcc])) {
                            foreach ($convIndex[$idUnidAcc] as $idDest => $fconv) {
                                $ppC = $presentPorMaestro[$idMaestro][$idDest] ?? null;
                                if ($ppC) {
                                    $ppCant     = max((float)$ppC['pp_cantidad'], 0.001);
                                    $factor     = $fconv;
                                    $nivelUsado = 'nivel3_conversion';
                                    break;
                                }
                            }
                        }
                        if ($nivelUsado === 'mismo') $nivelUsado = 'factor1_fallback';
                    }
                }
            }

            $cantTotal    = (float)$v['cant_total_raw'];
            $consumoCrudo = ($cantTotal * $factor) / $ppCant;
            $consumoFinal = $esP1 ? (round($consumoCrudo * 2) / 2) : $consumoCrudo;
        } else {
            $cantTotal    = (float)$v['cant_total_raw'];
            $consumoCrudo = $cantTotal;
            $consumoFinal = $cantTotal;
            $nivelUsado   = 'global';
        }

        $filas[] = [
            'folio'           => $v['Folio'] ?? '—',
            'fecha'           => $v['Fecha'],
            'semana'          => (int)$v['semana'],
            'sucursal'        => $v['sucursal'],
            'nombre_batido'   => $v['nombre_batido'] ?? $v['CodProducto'],
            'nombre_ingrediente' => $v['nombre_ingrediente'],
            'unidad_access'   => $unidAcc,
            'codporcion'      => $codporc,
            'cant_receta'     => (float)$v['cant_receta'],
            'ventas'          => (float)$v['ventas'],
            'cant_total'      => round($cantTotal, 4),
            'factor'          => round($factor, 6),
            'pp_cantidad'     => $ppCant,
            'consumo_crudo'   => round($consumoCrudo, 4),
            'consumo_final'   => $consumoFinal,
            'es_p1'           => $esP1,
            'nivel'           => $nivelUsado,
            'genera_decimal'  => $esP1 && (abs($consumoCrudo - $consumoFinal) > 0.001),
        ];
    }

    echo json_encode([
        'ok'         => true,
        'presentacion' => [
            'id'       => $idPP,
            'nombre'   => $ppDat['Nombre'],
            'unidad'   => $ppDat['unidad_erp'],
            'pp_cant'  => $ppCantBase,
            'es_global'=> $esGlobal,
        ],
        'filas'      => $filas,
        'total_filas'=> count($filas),
        'total_consumo' => round(array_sum(array_column($filas, 'consumo_final')), 4),
        'cod_cots'   => $codCots,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
}
