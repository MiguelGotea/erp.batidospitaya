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

if (empty($codSucursal) || !$numSemanaInv) {
    echo json_encode(['ok' => false, 'msg' => 'Sucursal y semana de inventario requeridas.']);
    exit();
}

/* ── Helpers idénticos a pedido_sugerido_calcular.php ────── */

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


try {
    /* ── 1. Semana de inventario ──────────────────────────── */
    $stmtS = $conn->prepare("SELECT fecha_inicio, fecha_fin FROM SemanasSistema WHERE numero_semana = ?");
    $stmtS->execute([$numSemanaInv]);
    $semInv = $stmtS->fetch();
    if (!$semInv) throw new Exception("Semana de inventario no válida.");

    /* ── 2. Configuración de porcentajes de la sucursal ───── */
    // La herramienta original de porcentajes fue eliminada. Usamos 0 por defecto.
    $configPct = ['porcentaje_congelados' => 0, 'porcentaje_frescos' => 0];



    /* ── 5. Inventario guardado de la semana seleccionada ─── */
    $stmtInv = $conn->prepare("
        SELECT id_producto_presentacion, cantidad_unidades, cantidad_presentacion
        FROM inventario_semanal
        WHERE cod_sucursal = ? AND fecha_inventario BETWEEN ? AND ?
        ORDER BY fecha_inventario ASC
    ");
    $stmtInv->execute([$codSucursal, $semInv['fecha_inicio'], $semInv['fecha_fin']]);
    $inventarioSemana = [];
    foreach ($stmtInv->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $inventarioSemana[$row['id_producto_presentacion']] = $row;
    }


        $convIndex = [];
        foreach ($conn->query("SELECT id_unidad_producto_inicio as i, id_unidad_producto_final as f, cantidad as c FROM conversion_unidad_producto")->fetchAll() as $c) {
            $convIndex[(int)$c['i']][(int)$c['f']] = (float)$c['c'];
            $convIndex[(int)$c['f']][(int)$c['i']] = $c['c'] != 0 ? 1 / $c['c'] : 0;
        }
        cerrarConversionesTransitivas($convIndex); // oz→gr→kg, etc.

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

    /* ── 9. Asignar variables en 0 según requerimiento ──── */
    foreach ($productos as &$p) {
        $idPP = $p['id'];
        $invRow  = $inventarioSemana[$idPP] ?? null;

        $p['_inv_pres']       = $invRow ? (float)$invRow['cantidad_presentacion'] : null;
        $p['_inv_unidades']   = $invRow ? (float)$invRow['cantidad_unidades']     : null;

        $p['es_ajustado']     = false;
        
        $p['_stock_pronostico'] = 0;
        $p['_stock_min']        = 0;
        $p['stock_max_final']   = 0;
        
        $p['pedido_sugerido']   = 0;
        $p['p1']                = 0;
        $p['p2']                = 0;
    }
    unset($p);

    echo json_encode([
        'ok'                     => true,
        'rango_fechas_inv'       => $semInv,
        'n_semanas'              => 1,
        'factor_c'               => null,
        'capacidad_c'            => null,
        'sum_smax_b'             => 0,
        'porcentajes'            => $configPct,
        'productos'              => $productos,
    ]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
}
