<?php
/* ============================================================
   AJAX: Stock Mín y Stock Máx Final para un producto
   modulos/productos/ajax/balance_inventario_get_stock_minmax.php

   Calcula stock_minimo y stock_max_final usando la misma lógica
   que pedido_sugerido_calcular.php pero para un solo id_pp y
   usando siempre las 5 semanas completas más recientes.

   Params POST:
     id_pp        int   — id de producto_presentacion
     sem_analisis int   — semana que se está analizando
     sem_actual   int   — semana actual del sistema
     cod_sucursal str   — código de sucursal (puede ser una)
   ============================================================ */
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');
set_time_limit(0);

$usuario = obtenerUsuarioActual();
if (!tienePermiso('balance_inventario_access_host', 'vista', $usuario['CodNivelesCargos'])) {
    echo json_encode(['ok' => false, 'msg' => 'Sin permiso.']);
    exit();
}

$idPP       = isset($_POST['id_pp'])        ? (int)$_POST['id_pp']        : 0;
$semAnalisis= isset($_POST['sem_analisis']) ? (int)$_POST['sem_analisis'] : 0;
$semActual  = isset($_POST['sem_actual'])   ? (int)$_POST['sem_actual']   : 0;
$codSuc     = isset($_POST['cod_sucursal']) ? trim($_POST['cod_sucursal']) : '';

if (!$idPP || !$semAnalisis || !$semActual) {
    echo json_encode(['ok' => false, 'msg' => 'Faltan parámetros.']);
    exit();
}

/* ── Calcular ventana de 5 semanas ──────────────────────────────────────
   Si la semana analizada ES la semana actual, retrocedemos 1 semana para
   asegurarnos de usar solo semanas con datos completos (7 días).
   Siempre tomamos 5 semanas: [base-4 .. base].
   ──────────────────────────────────────────────────────────────────── */
$retrocedido = false;
if ($semAnalisis >= $semActual) {
    // Semana actual o futura: retroceder 1 para tener datos completos
    $semBase     = $semActual - 1;
    $retrocedido = true;
} else {
    $semBase = $semAnalisis;
}

$numHasta = $semBase;
$numDesde = $semBase - 4;   // 5 semanas: [base-4 .. base]

/* ── Helpers (igual que pedido_sugerido_calcular.php) ────────────────── */
function desviacionEstandarMuestra_SMM(array $valores): float {
    $n = count($valores);
    if ($n <= 1) return 0.0;
    $media   = array_sum($valores) / $n;
    $varianza= array_sum(array_map(fn($v) => ($v - $media) ** 2, $valores)) / ($n - 1);
    return sqrt($varianza);
}

function resolverFactorConv_SMM(int $o, int $d, array &$idx): ?float {
    if ($o === $d) return 1.0;
    return $idx[$o][$d] ?? null;
}

function cerrarTransitivas_SMM(array &$convIndex): void {
    $units = array_unique(array_merge(
        array_keys($convIndex),
        array_merge(...array_map('array_keys', array_values($convIndex)))
    ));
    foreach ($units as $k)
        foreach ($units as $i) {
            if (!isset($convIndex[$i][$k])) continue;
            foreach ($units as $j) {
                if (!isset($convIndex[$k][$j])) continue;
                if (!isset($convIndex[$i][$j]))
                    $convIndex[$i][$j] = $convIndex[$i][$k] * $convIndex[$k][$j];
            }
        }
}

try {
    /* 1. Verificar que el id_pp existe y obtener su maestro + cat */
    $stmtPP = $conn->prepare("
        SELECT pp.id, pp.Nombre, pp.cantidad AS pp_cant, pp.id_producto_maestro AS id_m,
               pp.id_unidad_producto AS uid, pp.categoria_insumo AS cat,
               pp.presentacion, u.abreviado AS uab
        FROM producto_presentacion pp
        LEFT JOIN unidad_producto u ON u.id = pp.id_unidad_producto
        WHERE pp.id = ? AND pp.Activo = 'SI'
    ");
    $stmtPP->execute([$idPP]);
    $ppRow = $stmtPP->fetch(PDO::FETCH_ASSOC);
    if (!$ppRow) {
        echo json_encode(['ok' => false, 'msg' => 'Producto no encontrado.']);
        exit();
    }

    $cat   = $ppRow['cat'];
    $idM   = $ppRow['id_m'] ? (int)$ppRow['id_m'] : null;

    /* 2. Rango de fechas para las 5 semanas */
    $stmtR = $conn->prepare("SELECT MIN(fecha_inicio) as f1, MAX(fecha_fin) as f2
                              FROM SemanasSistema WHERE numero_semana BETWEEN ? AND ?");
    $stmtR->execute([$numDesde, $numHasta]);
    $rDates = $stmtR->fetch();
    if (!$rDates || !$rDates['f1']) {
        echo json_encode(['ok' => false, 'msg' => 'Semanas no encontradas en el sistema.']);
        exit();
    }

    /* 3. Config logística de la sucursal (o primera disponible si no se especificó) */
    if ($codSuc) {
        $stmtS = $conn->prepare("SELECT dias_stock_minimo, capacidad_congelados
                                  FROM configuracion_logistica_sucursal WHERE cod_sucursal = ?");
        $stmtS->execute([$codSuc]);
    } else {
        $stmtS = $conn->query("SELECT dias_stock_minimo, capacidad_congelados
                               FROM configuracion_logistica_sucursal LIMIT 1");
    }
    $cS  = $stmtS->fetch();
    $dSM = $cS ? (float)$cS['dias_stock_minimo'] : 0;
    $capC= $cS ? (float)$cS['capacidad_congelados'] : null;

    /* Config logística del producto por categoría */
    $cP   = null;
    if ($cat) {
        $params = $codSuc ? [$cat, $codSuc] : [$cat];
        $sql    = $codSuc
            ? "SELECT dias_ciclo, dias_desfase, ajuste_demanda FROM configuracion_logistica_producto WHERE codigo_insumo = ? AND cod_sucursal = ? LIMIT 1"
            : "SELECT dias_ciclo, dias_desfase, ajuste_demanda FROM configuracion_logistica_producto WHERE codigo_insumo = ? LIMIT 1";
        $stmtCLP = $conn->prepare($sql);
        $stmtCLP->execute($params);
        $cP = $stmtCLP->fetch(PDO::FETCH_ASSOC);
    }

    if (!$cP) {
        /* Sin config logística → no podemos calcular */
        echo json_encode([
            'ok'           => true,
            'stock_minimo' => null,
            'stock_max_final' => null,
            'sem_desde'    => $numDesde,
            'sem_hasta'    => $numHasta,
            'retrocedido'  => $retrocedido,
            'msg'          => 'Sin configuración logística para esta categoría/sucursal.'
        ]);
        exit();
    }

    $adj = (float)$cP['ajuste_demanda'];
    $dC  = (float)$cP['dias_ciclo'];
    $dD  = (float)$cP['dias_desfase'];

    /* 4. Consumo por semana del producto en la ventana activa
          Usamos el mismo approach que pedido_sugerido: ventas × subreceta → mapeo → presentacion básica */
    
    /* Primero necesitamos los CodCotizacion que mapean al id_pp */
    $stmtDic = $conn->prepare("
        SELECT d.CodCotizacion
        FROM diccionario_productos_legado d
        INNER JOIN producto_presentacion pp ON pp.id = d.id_producto_presentacion
        WHERE pp.Activo = 'SI' AND (pp.id = ? OR pp.id_producto_maestro = ?)
    ");
    $stmtDic->execute([$idPP, $idM ?? -1]);
    $codCots = array_column($stmtDic->fetchAll(PDO::FETCH_ASSOC), 'CodCotizacion');

    /* También buscar por maestro */
    if ($idM) {
        $stmtDicM = $conn->prepare("
            SELECT d.CodCotizacion
            FROM diccionario_productos_legado d
            INNER JOIN producto_presentacion pp ON pp.id = d.id_producto_presentacion
            WHERE pp.Activo = 'SI' AND pp.id_producto_maestro = ?
        ");
        $stmtDicM->execute([$idM]);
        $codCots = array_unique(array_merge($codCots, array_column($stmtDicM->fetchAll(PDO::FETCH_ASSOC), 'CodCotizacion')));
    }

    $consumoPorSem = [];
    for ($s = $numDesde; $s <= $numHasta; $s++) $consumoPorSem[$s] = 0.0;

    if (!empty($codCots)) {
        $ph = implode(',', array_fill(0, count($codCots), '?'));
        /* Consumo real del Kardex para el producto: despachos + mermas */
        /* Usamos directamente la tabla KardexBalance o bien VentasGlobalesAccessCSV × SubReceta */
        /* Para consistencia usamos el mismo método del pedido sugerido */

        /* Unidades y conversiones */
        $unidadPorNombre = [];
        $unidadPorId     = [];
        foreach ($conn->query("SELECT id, nombre, abreviado, nombres_opcionales FROM unidad_producto")->fetchAll() as $u) {
            $uid = (int)$u['id'];
            $unidadPorId[$uid] = $u;
            $unidadPorNombre[strtolower(trim($u['nombre']))] = $uid;
            if ($u['abreviado']) $unidadPorNombre[strtolower(trim($u['abreviado']))] = $uid;
            if ($u['nombres_opcionales'])
                foreach (preg_split('/[,;|]+/', $u['nombres_opcionales']) as $a)
                    if ($ak = strtolower(trim($a))) $unidadPorNombre[$ak] = $uid;
        }
        $convIndex = [];
        foreach ($conn->query("SELECT id_unidad_producto_inicio as i, id_unidad_producto_final as f, cantidad as c FROM conversion_unidad_producto")->fetchAll() as $cv) {
            $convIndex[(int)$cv['i']][(int)$cv['f']] = (float)$cv['c'];
            $convIndex[(int)$cv['f']][(int)$cv['i']] = $cv['c'] != 0 ? 1/(float)$cv['c'] : 0;
        }
        cerrarTransitivas_SMM($convIndex);

        /* Ventas × SubReceta agrupadas por semana para los CodCotizacion del producto */
        $stmtV = $conn->prepare("
            SELECT v.Semana as sem, sr.CodIngrediente as cod_ing, sr.codporcion,
                   SUM(v.Cantidad * sr.Cantidad) as cant
            FROM VentasGlobalesAccessCSV v
            INNER JOIN SubReceta sr ON sr.CodBatido = v.CodProducto
            WHERE v.Anulado = 0 AND v.Semana BETWEEN ? AND ?
              AND v.Fecha BETWEEN ? AND ?
              AND sr.codporcion IN ($ph)
            GROUP BY v.Semana, sr.CodIngrediente, sr.codporcion
        ");
        $paramsV = array_merge([$numDesde, $numHasta, $rDates['f1'], $rDates['f2']], array_values($codCots));
        $stmtV->execute($paramsV);
        $filasV = $stmtV->fetchAll(PDO::FETCH_ASSOC);

        /* Mapeo diccionario para los codporcion */
        $dicMap = [];
        if (!empty($codCots)) {
            $stmtDM = $conn->prepare("
                SELECT d.CodCotizacion,
                       pp.id AS id, pp.cantidad AS pp_cant, pp.Id_receta_producto,
                       pp.id_producto_maestro AS id_m, pp.Nombre AS n,
                       pp.categoria_insumo AS cat, pp.presentacion,
                       pp.presentacion_basica_inventario,
                       u.id AS uid, u.abreviado AS uab
                FROM diccionario_productos_legado d
                INNER JOIN producto_presentacion pp ON pp.id = d.id_producto_presentacion
                LEFT  JOIN unidad_producto u        ON u.id  = pp.id_unidad_producto
                WHERE d.CodCotizacion IN ($ph) AND pp.Activo = 'SI'
            ");
            $stmtDM->execute(array_values($codCots));
            foreach ($stmtDM->fetchAll(PDO::FETCH_ASSOC) as $row)
                $dicMap[(string)$row['CodCotizacion']] = $row;
        }

        $dbIng = [];
        $codsIng = array_unique(array_column($filasV, 'cod_ing'));
        if (!empty($codsIng)) {
            $phI = implode(',', array_fill(0, count($codsIng), '?'));
            $stmtI = $conn->prepare("SELECT CodIngrediente, Nombre, Unidad FROM DBIngredientes WHERE CodIngrediente IN ($phI)");
            $stmtI->execute(array_values($codsIng));
            foreach ($stmtI->fetchAll() as $row) $dbIng[$row['CodIngrediente']] = $row;
        }

        foreach ($filasV as $f) {
            $cp   = $f['codporcion'];
            $ci   = $f['cod_ing'];
            $sem  = (int)$f['sem'];
            $cant = (float)$f['cant'];
            $m    = $dicMap[(string)$cp] ?? null;
            if (!$m) continue;

            $ppId  = (int)$m['id'];
            $ppC   = max((float)$m['pp_cant'], 0.001);
            $uidERP= (int)$m['uid'];

            if ($m['Id_receta_producto']) {
                $cons = $cant;
            } else {
                $uAcc  = $dbIng[$ci]['Unidad'] ?? '';
                $uidAcc= isset($unidadPorNombre[strtolower(trim($uAcc))]) ? $unidadPorNombre[strtolower(trim($uAcc))] : null;
                $fac   = 1.0;
                if ($uidAcc && $uidAcc !== $uidERP) {
                    $fDir = resolverFactorConv_SMM($uidAcc, $uidERP, $convIndex);
                    if ($fDir) $fac = $fDir;
                }
                $cons = ($cant * $fac) / $ppC;
                /* P1: redondear al 0.5 más cercano si la pp es la básica */
                if (($m['presentacion_basica_inventario'] ?? 0) == 1)
                    $cons = round($cons * 2) / 2;
            }
            /* Solo acumular si el ppId resuelto corresponde al mismo maestro o id */
            if ($ppId === $idPP || ($idM && $m['id_m'] == $idM)) {
                $consumoPorSem[$sem] = ($consumoPorSem[$sem] ?? 0) + $cons;
            }
        }
    }

    /* 5. Estadísticas con ventana activa */
    $vals = [];
    for ($s = $numDesde; $s <= $numHasta; $s++) $vals[] = (float)($consumoPorSem[$s] ?? 0);

    $nonZero = array_filter($vals, fn($v) => $v > 0);
    if (empty($nonZero)) {
        echo json_encode([
            'ok'              => true,
            'stock_minimo'    => null,
            'stock_max_final' => null,
            'sem_desde'       => $numDesde,
            'sem_hasta'       => $numHasta,
            'retrocedido'     => $retrocedido,
            'msg'             => 'Sin consumo registrado en el período.'
        ]);
        exit();
    }

    $meanNZ  = array_sum($nonZero) / count($nonZero);
    $umbral  = max(0.01, $meanNZ * 0.10);
    $firstIdx= null; $lastIdx = null;
    foreach ($vals as $i => $v) {
        if ($v >= $umbral) {
            if ($firstIdx === null) $firstIdx = $i;
            $lastIdx = $i;
        }
    }
    if ($firstIdx === null) {
        echo json_encode(['ok' => true, 'stock_minimo' => null, 'stock_max_final' => null,
                          'sem_desde' => $numDesde, 'sem_hasta' => $numHasta, 'retrocedido' => $retrocedido]);
        exit();
    }

    $nActiva   = $lastIdx - $firstIdx + 1;
    $valsActivo= array_slice($vals, $firstIdx, $nActiva);
    $prom      = array_sum($valsActivo) / $nActiva;
    $desv      = desviacionEstandarMuestra_SMM($valsActivo);
    $semC      = $prom + $desv;
    $diaC      = ($semC * (1 + $adj)) / 7;
    $sMin      = $diaC * $dSM;
    $sMax      = $diaC * ($dC + $dD + $dSM);

    /* Factor congelados (cat B) */
    $sMaxFinal = $sMax;
    if ($cat === 'B' && $capC !== null && $sMax > 0) {
        /* Calcular suma de stock_maximo de todos los B para esta sucursal
           (aproximación: usamos solo el producto actual para el factor) */
        $facC = min(1.0, $capC / $sMax);
        $sMaxFinal = $sMax * $facC;
    }

    /* Factor de despacho para convertir a unidades de despacho */
    $df = 1.0;
    $despNombre = null;
    /* Paso B: receta-paquete cuyo único componente es este id_pp */
    $stmtDB = $conn->prepare("
        SELECT crp.cantidad AS d_receta_cant, ppd.Nombre AS d_nombre
        FROM producto_presentacion ppd
        INNER JOIN componentes_receta_producto crp
               ON crp.id_receta_producto_global = ppd.Id_receta_producto
        WHERE ppd.presentacion_despacho = 1 AND ppd.Activo = 'SI'
          AND crp.id_presentacion_producto = ?
          AND (SELECT COUNT(DISTINCT crp2.id_presentacion_producto)
               FROM componentes_receta_producto crp2
               WHERE crp2.id_receta_producto_global = ppd.Id_receta_producto) = 1
        ORDER BY ppd.id ASC LIMIT 1
    ");
    $stmtDB->execute([$idPP]);
    $dfRow = $stmtDB->fetch(PDO::FETCH_ASSOC);
    if ($dfRow && $dfRow['d_receta_cant'] > 0) {
        $df = (float)$dfRow['d_receta_cant'];
        $despNombre = $dfRow['d_nombre'];
    } elseif ($idM) {
        /* Paso A: presentación de despacho del mismo maestro */
        $stmtDA = $conn->prepare("
            SELECT ppd.cantidad AS d_cant, ppd.id_unidad_producto AS d_uid,
                   pp.cantidad AS pp_cant_base, pp.id_unidad_producto AS pp_uid,
                   ppd.Nombre AS d_nombre
            FROM producto_presentacion pp
            INNER JOIN producto_presentacion ppd
                   ON ppd.id_producto_maestro = pp.id_producto_maestro
                  AND ppd.presentacion_despacho = 1 AND ppd.Activo = 'SI'
            WHERE pp.id = ? AND pp.Activo = 'SI'
            ORDER BY ppd.id ASC LIMIT 1
        ");
        $stmtDA->execute([$idPP]);
        $daRow = $stmtDA->fetch(PDO::FETCH_ASSOC);
        if ($daRow) {
            $uidPP = (int)$daRow['pp_uid'];
            $uidD  = (int)$daRow['d_uid'];
            if ($uidPP === $uidD) {
                $df = (float)$daRow['d_cant'] / max((float)$daRow['pp_cant_base'], 0.001);
            } else {
                $facConv = resolverFactorConv_SMM($uidPP, $uidD, $convIndex);
                if ($facConv !== null && $facConv != 0)
                    $df = (float)$daRow['d_cant'] / (max((float)$daRow['pp_cant_base'], 0.001) * $facConv);
            }
            $despNombre = $daRow['d_nombre'];
        }
    }

    $stockMinDesp    = $df > 0 ? round($sMin    / $df, 4) : round($sMin, 4);
    $stockMaxFinDesp = $df > 0 ? round($sMaxFinal / $df, 4) : round($sMaxFinal, 4);

    echo json_encode([
        'ok'              => true,
        'stock_minimo'    => $stockMinDesp,
        'stock_max_final' => $stockMaxFinDesp,
        'despacho_nombre' => $despNombre,
        'cons_diario'     => round($diaC, 6),
        'sem_desde'       => $numDesde,
        'sem_hasta'       => $numHasta,
        'retrocedido'     => $retrocedido,
        'sem_actual'      => $semActual,
    ]);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
}
