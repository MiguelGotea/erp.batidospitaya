<?php
/* ============================================================
   AJAX: Pronóstico de Despacho D-1 por producto
   modulos/productos/ajax/pedido_sugerido_pronostico_despacho.php

   Parámetros POST:
     id_pp          int    — id de producto_presentacion
     cod_sucursal   str    — código de sucursal
     sem_corte      int    — número de semana de referencia (inventario real del domingo)
     fecha_despacho str    — fecha ISO (YYYY-MM-DD) del próximo despacho
     cons_diario    float  — (opcional) consumo diario ya calculado por v2
     despacho_factor float — (opcional) factor de conversión a paquetes
     stock_max_final float — (opcional) stock máximo final en paquetes

   Respuesta:
     {ok, id_pp, sem_corte, domingo_corte, stock_domingo, cons_diario,
      fecha_despacho, fecha_D1, dias_transcurridos, stock_D1_uso,
      stock_D1_paquetes, stock_max_final_paquetes, despacho_sugerido_pronostico,
      despacho_factor, sin_inventario}
   ============================================================ */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');

$usuario = obtenerUsuarioActual();
if (!tienePermiso('pedido_sugerido', 'vista', $usuario['CodNivelesCargos'])) {
    echo json_encode(['ok' => false, 'msg' => 'Sin permiso.']);
    exit();
}

// ── Leer parámetros ──────────────────────────────────────────
$idPP          = isset($_POST['id_pp'])          ? (int)$_POST['id_pp']               : 0;
$codSucursal   = trim($_POST['cod_sucursal']     ?? '');
$semCorte      = isset($_POST['sem_corte'])      ? (int)$_POST['sem_corte']            : 0;
$fechaDespacho = trim($_POST['fecha_despacho']   ?? '');

// Parámetros opcionales — si el frontend ya los tiene del cálculo v2
$consDiario    = isset($_POST['cons_diario'])     ? (float)$_POST['cons_diario']       : null;
$despFactor    = isset($_POST['despacho_factor']) ? (float)$_POST['despacho_factor']   : null;
$stockMaxFinal = isset($_POST['stock_max_final']) ? (float)$_POST['stock_max_final']   : null;

// ── Validaciones básicas ─────────────────────────────────────
if (!$idPP || !$codSucursal || !$semCorte || !$fechaDespacho) {
    echo json_encode(['ok' => false, 'msg' => 'Faltan parámetros requeridos (id_pp, cod_sucursal, sem_corte, fecha_despacho).']);
    exit();
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDespacho)) {
    echo json_encode(['ok' => false, 'msg' => 'Formato de fecha_despacho inválido. Use YYYY-MM-DD.']);
    exit();
}

try {
    // ── 1. Obtener fecha_fin (domingo) de la semana de corte ─────────────
    $stmtS = $conn->prepare(
        "SELECT fecha_fin FROM SemanasSistema WHERE numero_semana = ? LIMIT 1"
    );
    $stmtS->execute([$semCorte]);
    $semRow = $stmtS->fetch(PDO::FETCH_ASSOC);

    if (!$semRow) {
        echo json_encode(['ok' => false, 'msg' => "Semana de corte {$semCorte} no encontrada en SemanasSistema."]);
        exit();
    }
    $domingoCorte = $semRow['fecha_fin']; // ej: '2026-05-11'

    // ── 2. Obtener stock inventariado del domingo de la semana de corte ──
    // La tabla inventario tiene: id, cod_sucursal, id_producto_presentacion,
    // cantidad, fecha_inventario (DATE).  No tiene columna "semana".
    // Se filtra por el rango de fechas de la semana de corte.
    $stmtFechas = $conn->prepare(
        "SELECT fecha_inicio FROM SemanasSistema WHERE numero_semana = ? LIMIT 1"
    );
    $stmtFechas->execute([$semCorte]);
    $fechaInicioSem = $stmtFechas->fetchColumn();

    $stmtInv = $conn->prepare("
        SELECT i.cantidad
        FROM inventario i
        WHERE i.cod_sucursal            = ?
          AND i.id_producto_presentacion = ?
          AND i.fecha_inventario BETWEEN ? AND ?
        ORDER BY i.id DESC
        LIMIT 1
    ");
    $stmtInv->execute([$codSucursal, $idPP, $fechaInicioSem ?: $domingoCorte, $domingoCorte]);
    $invRow = $stmtInv->fetch(PDO::FETCH_ASSOC);
    $stockDomingo = $invRow ? (float)$invRow['cantidad'] : null;

    $sinInventario = ($stockDomingo === null);

    // ── 3. Si no vienen los parámetros del cálculo v2, recalcular ────────
    //    cons_diario: se necesita para proyectar. Si no viene del frontend, 
    //    calcularlo desde el motor de consumo usando las 5 semanas anteriores.
    //    En la práctica el frontend SIEMPRE lo envía, pero manejamos el fallback.
    if ($consDiario === null || $consDiario <= 0) {
        // Fallback: obtener consumo semanal de las 5 semanas previas al corte
        $semDesde = $semCorte - 5;
        $semHasta = $semCorte - 1;

        $stmtRango = $conn->prepare(
            "SELECT MIN(fecha_inicio) as f1, MAX(fecha_fin) as f2
             FROM SemanasSistema
             WHERE numero_semana BETWEEN ? AND ?"
        );
        $stmtRango->execute([$semDesde, $semHasta]);
        $rango = $stmtRango->fetch(PDO::FETCH_ASSOC);

        if ($rango && $rango['f1']) {
            // Ventas de esas semanas para este producto (vía diccionario + SubReceta)
            $stmtVen = $conn->prepare("
                SELECT v.Semana as sem, SUM(v.Cantidad * sr.Cantidad) as cant
                FROM VentasGlobalesAccessCSV v
                INNER JOIN SubReceta sr ON sr.CodBatido = v.CodProducto
                INNER JOIN diccionario_productos_legado d ON d.CodCotizacion = sr.codporcion
                WHERE v.Anulado = 0
                  AND v.local   = ?
                  AND v.Semana BETWEEN ? AND ?
                  AND v.Fecha BETWEEN ? AND ?
                  AND d.id_producto_presentacion = ?
                GROUP BY v.Semana
            ");
            $stmtVen->execute([
                $codSucursal, $semDesde, $semHasta,
                $rango['f1'], $rango['f2'], $idPP
            ]);
            $ventasSem = $stmtVen->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($ventasSem)) {
                $vals = [];
                for ($s = $semDesde; $s <= $semHasta; $s++) {
                    $found = array_filter($ventasSem, fn($r) => (int)$r['sem'] === $s);
                    $vals[] = $found ? (float)array_values($found)[0]['cant'] : 0.0;
                }
                $nonZero = array_filter($vals, fn($v) => $v > 0);
                if (!empty($nonZero)) {
                    $mean    = array_sum($vals) / count($vals);
                    $n       = count($vals);
                    $varianza = $n > 1
                        ? array_sum(array_map(fn($v) => ($v - $mean) ** 2, $vals)) / ($n - 1)
                        : 0;
                    $desv = sqrt($varianza);
                    $semC = $mean + $desv;

                    // Obtener ajuste_demanda del producto
                    $stmtAdj = $conn->prepare("
                        SELECT clp.ajuste_demanda, pp.categoria_insumo
                        FROM producto_presentacion pp
                        LEFT JOIN configuracion_logistica_producto clp
                               ON clp.codigo_insumo = pp.categoria_insumo
                              AND clp.cod_sucursal   = ?
                        WHERE pp.id = ?
                        LIMIT 1
                    ");
                    $stmtAdj->execute([$codSucursal, $idPP]);
                    $adjRow = $stmtAdj->fetch(PDO::FETCH_ASSOC);
                    $adj    = $adjRow ? (float)$adjRow['ajuste_demanda'] : 0.0;

                    $consDiario = ($semC * (1 + $adj)) / 7.0;
                }
            }
        }

        // Si aún no se pudo calcular, usar 0 (el pronóstico quedará igual al stock domingo)
        if ($consDiario === null) $consDiario = 0.0;
    }

    // ── 4. Si no viene despacho_factor, intentar obtenerlo ──────────────
    if ($despFactor === null || $despFactor <= 0) {
        $despFactor = 1.0; // Default: 1 a 1 (sin conversión)

        // Paso B: receta-paquete cuyo único componente es este id_pp
        $stmtDB = $conn->prepare("
            SELECT crp.cantidad AS factor
            FROM producto_presentacion ppd
            INNER JOIN componentes_receta_producto crp
                   ON crp.id_receta_producto_global = ppd.Id_receta_producto
            WHERE ppd.presentacion_despacho = 1
              AND ppd.Activo = 'SI'
              AND crp.id_presentacion_producto = ?
              AND (
                  SELECT COUNT(*) FROM componentes_receta_producto crp2
                  WHERE crp2.id_receta_producto_global = ppd.Id_receta_producto
              ) = 1
            ORDER BY ppd.id ASC
            LIMIT 1
        ");
        $stmtDB->execute([$idPP]);
        $dfRow = $stmtDB->fetch(PDO::FETCH_ASSOC);

        if ($dfRow && (float)$dfRow['factor'] > 0) {
            $despFactor = (float)$dfRow['factor'];
        } else {
            // Paso A: por maestro
            $stmtDA = $conn->prepare("
                SELECT ppd.cantidad AS d_cant, ppd.id_unidad_producto AS d_uid,
                       pp.cantidad AS pp_cant, pp.id_unidad_producto AS pp_uid
                FROM producto_presentacion pp
                INNER JOIN producto_presentacion ppd
                       ON ppd.id_producto_maestro = pp.id_producto_maestro
                      AND ppd.presentacion_despacho = 1
                      AND ppd.Activo = 'SI'
                      AND pp.id_producto_maestro IS NOT NULL
                WHERE pp.id = ? AND pp.Activo = 'SI'
                ORDER BY ppd.id ASC
                LIMIT 1
            ");
            $stmtDA->execute([$idPP]);
            $daRow = $stmtDA->fetch(PDO::FETCH_ASSOC);
            if ($daRow && (float)$daRow['pp_cant'] > 0 && $daRow['d_uid'] === $daRow['pp_uid']) {
                $despFactor = (float)$daRow['d_cant'] / (float)$daRow['pp_cant'];
            }
        }
    }

    // ── 5. Si no viene stock_max_final, no podemos calcularlo sin el motor completo ─
    //    El frontend DEBE enviarlo (viene de la respuesta de v2). Si no viene → null.
    $stockMaxFinalPaq = $stockMaxFinal; // ya viene en paquetes desde v2

    // ── 6. Proyección D-1 ────────────────────────────────────────────────
    $fechaD1 = date('Y-m-d', strtotime($fechaDespacho . ' -1 day'));

    // días desde el domingo de corte hasta D-1
    $diasTranscurridos = 0;
    if (!$sinInventario) {
        $tsD1     = strtotime($fechaD1);
        $tsDomingo = strtotime($domingoCorte);
        $diasTranscurridos = max(0, (int)(($tsD1 - $tsDomingo) / 86400));
    }

    // Stock en unidades de uso proyectado a D-1
    $stockD1Uso = $sinInventario
        ? null
        : max(0.0, $stockDomingo - ($consDiario * $diasTranscurridos));

    // Convertir a paquetes de despacho
    $dfSafe = ($despFactor > 0) ? $despFactor : 1.0;
    $stockD1Paquetes = ($stockD1Uso !== null)
        ? ($stockD1Uso / $dfSafe)
        : null;

    // ── 7. Despacho sugerido por pronóstico ──────────────────────────────
    $despachoPron = null;
    if (!$sinInventario && $stockMaxFinalPaq !== null && $stockD1Paquetes !== null) {
        $despachoPron = max(0, (int)ceil($stockMaxFinalPaq - $stockD1Paquetes));
    }

    // ── 8. Respuesta ────────────────────────────────────────────────────
    echo json_encode([
        'ok'                         => true,
        'id_pp'                      => $idPP,
        'sem_corte'                  => $semCorte,
        'domingo_corte'              => $domingoCorte,
        'stock_domingo'              => $sinInventario ? null : round($stockDomingo, 4),
        'cons_diario'                => round($consDiario, 6),
        'fecha_despacho'             => $fechaDespacho,
        'fecha_D1'                   => $fechaD1,
        'dias_transcurridos'         => $diasTranscurridos,
        'stock_D1_uso'               => $stockD1Uso !== null  ? round($stockD1Uso, 4)      : null,
        'stock_D1_paquetes'          => $stockD1Paquetes !== null ? round($stockD1Paquetes, 4) : null,
        'stock_max_final_paquetes'   => $stockMaxFinalPaq !== null ? round($stockMaxFinalPaq, 4) : null,
        'despacho_sugerido_pronostico' => $despachoPron,
        'despacho_factor'            => round($dfSafe, 6),
        'sin_inventario'             => $sinInventario,
    ]);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'Error interno: ' . $e->getMessage()]);
}
