<?php
/* ============================================================
   AJAX: Calcular pedido sugerido — v2 (Plan de Despacho integrado)
   modulos/productos/ajax/pedido_sugerido_calcular_v2.php

   Cadena de fórmulas:
   1. consumo_por_semana (VentasGlobalesAccessCSV × SubReceta)
   2. promedio = SUM / N
   3. desv_estandar_muestra (N-1)
   4. cons_semanal = prom + desv
   5. cons_diario = (cons_sem * (1 + ajuste)) / 7
   ...
   ============================================================ */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');
set_time_limit(0);

$usuario = obtenerUsuarioActual();
if (!tienePermiso('pedido_sugerido', 'vista', $usuario['CodNivelesCargos'])) {
    echo json_encode(['ok' => false, 'msg' => 'Sin permiso para calcular.']);
    exit();
}

$numDesde = isset($_POST['semana_desde_num']) ? (int) $_POST['semana_desde_num'] : 0;
$numHasta = isset($_POST['semana_hasta_num']) ? (int) $_POST['semana_hasta_num'] : 0;
$codSucursal = trim($_POST['cod_sucursal'] ?? '');

if (!$numDesde || !$numHasta || !$codSucursal) {
    echo json_encode(['ok' => false, 'msg' => 'Error: Faltan parámetros (semanas o sucursal) para el cálculo.']);
    exit();
}

$numDesde = min($numDesde, $numHasta);
$numHasta = max($numDesde, $numHasta);
$nSemanas = $numHasta - $numDesde + 1;

/**
 * Calcula la proyección de consumo usando regresión lineal Weighted Least Squares (WLS).
 * Asigna mayor peso a los datos más recientes (w = i).
 * Retorna el promedio proyectado de las próximas 3 semanas.
 */
function calcularProyeccionWLS(array $valores): array
{
    $n = count($valores);
    if ($n === 0) return ['promedio' => 0.0, 'm' => 0.0, 'b' => 0.0, 'n' => 0];
    if ($n === 1) return ['promedio' => max(0.0, (float)$valores[0]), 'm' => 0.0, 'b' => max(0.0, (float)$valores[0]), 'n' => 1];

    $sum_w = 0.0;
    $sum_wx = 0.0;
    $sum_wy = 0.0;
    $sum_wxx = 0.0;
    $sum_wxy = 0.0;

    // x = 1, 2, ..., n
    foreach ($valores as $i => $y) {
        $x = $i + 1;
        $w = $x; // Pesos lineales decrecientes hacia el pasado (más reciente = mayor peso)
        
        $sum_w += $w;
        $sum_wx += $w * $x;
        $sum_wy += $w * $y;
        $sum_wxx += $w * $x * $x;
        $sum_wxy += $w * $x * $y;
    }

    $denominator = ($sum_w * $sum_wxx) - ($sum_wx * $sum_wx);
    if (abs($denominator) < 0.0001) {
        $prom = array_sum($valores) / $n;
        return ['promedio' => $prom, 'm' => 0.0, 'b' => $prom, 'n' => $n];
    }

    $slope = (($sum_w * $sum_wxy) - ($sum_wx * $sum_wy)) / $denominator;
    $intercept = ($sum_wy - $slope * $sum_wx) / $sum_w;

    $w1 = max(0.0, $slope * ($n + 1) + $intercept);
    $w2 = max(0.0, $slope * ($n + 2) + $intercept);
    $w3 = max(0.0, $slope * ($n + 3) + $intercept);

    return [
        'promedio' => ($w1 + $w2 + $w3) / 3.0,
        'm' => $slope,
        'b' => $intercept,
        'n' => $n
    ];
}

function resolverUnidadId_PS(string $nombre, array &$unidadPorNombre): ?int
{
    $k = strtolower(trim($nombre));
    return $unidadPorNombre[$k] ?? null;
}

function resolverFactorConversion_PS(int $idOrigen, int $idDestino, array &$convIndex): ?float
{
    if ($idOrigen === $idDestino)
        return 1.0;
    return $convIndex[$idOrigen][$idDestino] ?? null;
}

/**
 * Cierre transitivo de conversiones (Floyd-Warshall).
 * Permite resolver cadenas como oz → gr → kg sin necesidad de
 * tener una fila directa oz→kg en conversion_unidad_producto.
 * Llama DESPUÉS de poblar $convIndex con las filas de la BD.
 */
function cerrarConversionesTransitivas(array &$convIndex): void
{
    $units = array_unique(
        array_merge(
            array_keys($convIndex),
            array_merge(...array_map('array_keys', array_values($convIndex)))
        )
    );
    // Floyd-Warshall: para cada «nodo puente» k, propagar i→k→j
    foreach ($units as $k) {
        foreach ($units as $i) {
            if (!isset($convIndex[$i][$k])) continue;
            foreach ($units as $j) {
                if (!isset($convIndex[$k][$j])) continue;
                $nuevo = $convIndex[$i][$k] * $convIndex[$k][$j];
                // Solo agregar si la ruta transitiva es nueva (no sobreescribir directas)
                if (!isset($convIndex[$i][$j])) {
                    $convIndex[$i][$j] = $nuevo;
                }
            }
        }
    }
}

function buscarPresentacionEnMaestro_PS(int $idMaestro, int $idUnidad, array &$presentPorMaestro): ?array
{
    return $presentPorMaestro[$idMaestro][$idUnidad] ?? null;
}

/**
 * Calcula el ciclo real en días a partir de una fila de plan_despacho_sucursal.
 *
 * Para tipo 'n_semanas':
 *   ciclo = intervalo × 7 días (sin cambios).
 *
 * Para tipo 'dias_semana':
 *   El ciclo ya NO es el promedio 7/N.  Cada despacho debe abastecer
 *   exactamente los días que transcurren hasta el despacho SIGUIENTE.
 *   Ej. Lun+Mié →  despacho del Lunes cubre 3 días (L→M→X),
 *                   despacho del Miércoles cubre 4 días (X→J→V→S→L).
 *
 *   Algoritmo:
 *     1. Determinar el día de la semana de HOY.
 *     2. Buscar el próximo día de despacho (nextDow).
 *     3. Contar los días desde nextDow hasta el despacho SUBSIGUIENTE.
 *     4. Ese conteo es el ciclo real del próximo despacho.
 *
 * @param string $hoy  Fecha actual 'Y-m-d'; si está vacía se usa 7/N como fallback.
 * Retorna null si no hay plan o la fila no está activa.
 */
function calcularCicloRealDias(?array $planCat, string $hoy = ''): ?float {
    if (!$planCat || !($planCat['activo'] ?? 0)) return null;

    if ($planCat['tipo_frecuencia'] === 'n_semanas') {
        $intervalo = (int)($planCat['intervalo_semanas'] ?? 1);
        return $intervalo * 7.0;
    }

    if ($planCat['tipo_frecuencia'] === 'dias_semana') {
        $dias = $planCat['dias_semana'];
        if (is_string($dias)) $dias = json_decode($dias, true);
        if (empty($dias) || !is_array($dias)) return null;
        sort($dias); // 0=Lun, …, 6=Dom
        $n = count($dias);
        if ($n === 0) return null;
        if ($n === 1) return 7.0; // Un solo despacho por semana → ciclo completo

        // Sin fecha base: fallback al promedio (no debería ocurrir en producción)
        if (!$hoy) return 7.0 / $n;

        $hoyTs  = strtotime($hoy);
        $hoyDow = (int)date('N', $hoyTs) - 1; // 0=Lun, …, 6=Dom

        // 1. Encontrar el próximo día de despacho (nextDow)
        $nextDow = null;
        for ($d = 1; $d <= 7; $d++) {
            $checkDow = ($hoyDow + $d) % 7;
            if (in_array($checkDow, $dias)) {
                $nextDow = $checkDow;
                break;
            }
        }
        if ($nextDow === null) return 7.0 / $n;

        // 2. Contar días desde nextDow hasta el despacho subsiguiente
        for ($d = 1; $d <= 7; $d++) {
            $checkDow = ($nextDow + $d) % 7;
            if (in_array($checkDow, $dias)) {
                return (float)$d; // Ciclo real del próximo despacho
            }
        }

        return 7.0 / $n; // Fallback de seguridad
    }

    return null;
}

/**
 * Obtiene los días de preparación de una fila del plan.
 * Retorna null si no hay plan.
 */
function calcularDiasPreparacion(?array $planCat): ?float {
    if (!$planCat || !($planCat['activo'] ?? 0)) return null;
    return (float)($planCat['dias_preparacion'] ?? 1);
}

/**
 * Calcula la fecha del próximo despacho para una categoría.
 *
 * @param array|null $planCat   Fila de plan_despacho_sucursal
 * @param string     $hoy      Fecha actual 'Y-m-d'
 * @param PDO        $conn     Conexión BD (para consultar SemanasSistema)
 * @return string|null         Fecha 'Y-m-d' del próximo despacho, o null si no hay plan
 */
function calcularProximoDespacho(?array $planCat, string $hoy, PDO $conn): ?string {
    if (!$planCat || !($planCat['activo'] ?? 0)) return null;

    $tipo = $planCat['tipo_frecuencia'];

    if ($tipo === 'dias_semana') {
        $diasSemana = $planCat['dias_semana'];
        if (is_string($diasSemana)) $diasSemana = json_decode($diasSemana, true);
        if (empty($diasSemana)) return null;
        sort($diasSemana);  // 0=Lun,...,6=Dom

        $hoyTs = strtotime($hoy);
        for ($d = 1; $d <= 14; $d++) {
            $ts  = strtotime("+{$d} days", $hoyTs);
            $dow = (int)date('N', $ts) - 1; // 0=Lun,...,6=Dom
            if (in_array($dow, $diasSemana)) {
                return date('Y-m-d', $ts);
            }
        }
        return null;
    }

    if ($tipo === 'n_semanas') {
        $intervalo = (int)($planCat['intervalo_semanas'] ?? 1);
        $diaFijo   = (int)($planCat['dia_despacho']     ?? 0); // 0=Lun,...,6=Dom
        $semAncla  = (int)($planCat['semana_ancla']     ?? 0);

        if ($intervalo <= 0) return null;
        // Solo requerimos semana ancla si el intervalo es mayor a 1 semana
        if ($intervalo > 1 && !$semAncla) return null;

        $stmtSem = $conn->prepare("
            SELECT numero_semana
            FROM SemanasSistema
            WHERE fecha_inicio <= ? AND fecha_fin >= ?
            LIMIT 1
        ");
        $stmtSem->execute([$hoy, $hoy]);
        $semActual = (int)($stmtSem->fetchColumn() ?: 0);
        
        // Fallback si 'hoy' no está en SemanasSistema (ej: calendario no generado)
        if (!$semActual) {
            $stmtSemMax = $conn->prepare("SELECT numero_semana FROM SemanasSistema WHERE fecha_inicio <= ? ORDER BY numero_semana DESC LIMIT 1");
            $stmtSemMax->execute([$hoy]);
            $semActual = (int)($stmtSemMax->fetchColumn() ?: 0);
        }
        // Fallback absoluto si 'hoy' es muy antiguo
        if (!$semActual) {
            $stmtSemMax = $conn->query("SELECT MIN(numero_semana) FROM SemanasSistema");
            $semActual = (int)($stmtSemMax->fetchColumn() ?: 0);
        }

        if (!$semActual) return null;

        $delta      = ($semActual - $semAncla) % $intervalo;
        if ($delta < 0) $delta += $intervalo; // Corrección para deltas negativos
        
        $semProximo = ($delta === 0) ? $semActual : $semActual + ($intervalo - $delta);

        $stmtFecha = $conn->prepare("SELECT fecha_inicio FROM SemanasSistema WHERE numero_semana = ? LIMIT 1");
        
        $maxIter = 10; // Evitar loop infinito
        while ($maxIter > 0) {
            $stmtFecha->execute([$semProximo]);
            $inicioSem = $stmtFecha->fetchColumn();
            
            // Si la semana no existe en el sistema, salimos (no podemos proyectar más)
            if (!$inicioSem) break; 
            
            $fechaDespacho = date('Y-m-d', strtotime($inicioSem . " +{$diaFijo} days"));
            if ($fechaDespacho > $hoy) {
                return $fechaDespacho;
            }
            
            $semProximo += $intervalo;
            $maxIter--;
        }
        
        return null;
    }

    return null;
}

try {
    // 1. Rango de fechas
    $stmtR = $conn->prepare("SELECT MIN(fecha_inicio) as f1, MAX(fecha_fin) as f2 FROM SemanasSistema WHERE numero_semana BETWEEN ? AND ?");
    $stmtR->execute([$numDesde, $numHasta]);
    $r = $stmtR->fetch();
    if (!$r || !$r['f1']) {
        echo json_encode(['ok' => false, 'msg' => 'Rango de semanas no encontrado.']);
        exit();
    }

    // ── Detectar semana actual incompleta ────────────────────────────────
    // Si fecha_fin del rango (domingo de numHasta en la BD) está en el futuro,
    // la semana hasta es la semana en curso y no ha terminado.
    // Solución: Excluir completamente esta semana de la regresión estadística.
    $hoy  = date('Y-m-d');
    $ayer = date('Y-m-d', strtotime('-1 day'));
    $semanaHastaIncompleta = ($r['f2'] > $ayer);

    if ($semanaHastaIncompleta) {
        $numHasta = $numHasta - 1;
        $nSemanas = max(1, $numHasta - $numDesde + 1);

        // Recalcular la fecha fin de la nueva $numHasta
        $stmtR2 = $conn->prepare("SELECT fecha_fin FROM SemanasSistema WHERE numero_semana = ?");
        $stmtR2->execute([$numHasta]);
        $nuevaF2 = $stmtR2->fetchColumn();
        if ($nuevaF2) {
            $r['f2'] = $nuevaF2;
        }
    }

    // Fecha real de fin para la query de ventas
    $fechaFinQuery = $r['f2'];

    // 2. Ventas Agregadas (limitadas a $fechaFinQuery para excluir días futuros)
    $sql = "SELECT v.Semana as sem, sr.CodIngrediente as cod_ing, sr.codporcion, SUM(v.Cantidad * sr.Cantidad) as cant
            FROM VentasGlobalesAccessCSV v
            INNER JOIN SubReceta sr ON sr.CodBatido = v.CodProducto
            WHERE v.Anulado = 0 AND v.local = ? AND v.Semana BETWEEN ? AND ? AND v.Fecha BETWEEN ? AND ?
            GROUP BY v.Semana, sr.CodIngrediente, sr.codporcion";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$codSucursal, $numDesde, $numHasta, $r['f1'], $fechaFinQuery]);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($filas)) {
        echo json_encode(['ok' => true, 'productos' => [], 'n_semanas' => $nSemanas, 'factor_congelados' => null, 'capacidad_congelados' => null]);
        exit();
    }


    // 3. Pre-cargar mapeos (Bulk)
    $codsIng = array_unique(array_column($filas, 'cod_ing'));
    $ph = implode(',', array_fill(0, count($codsIng), '?'));

    $dbIng = [];
    $stmtI = $conn->prepare("SELECT CodIngrediente, Nombre, Unidad FROM DBIngredientes WHERE CodIngrediente IN ($ph)");
    $stmtI->execute(array_values($codsIng));
    foreach ($stmtI->fetchAll() as $row)
        $dbIng[$row['CodIngrediente']] = $row;

    $cotMap = [];
    $stmtC = $conn->prepare("SELECT CodIngrediente, CodCotizacion, Conversion, Prioridad FROM Cotizaciones WHERE CodIngrediente IN ($ph) AND (Subproducto IS NULL OR Subproducto!=1) AND (Marca IS NULL OR Marca!='Almacen Global') ORDER BY Conversion DESC, Prioridad ASC");
    $stmtC->execute(array_values($codsIng));
    foreach ($stmtC->fetchAll() as $c) {
        $ci = $c['CodIngrediente'];
        if (!isset($cotMap[$ci]))
            $cotMap[$ci] = ['p2' => null, 'p3' => null];
        if ($c['Conversion'] == 1 && $c['Prioridad'] == 1 && !$cotMap[$ci]['p2'])
            $cotMap[$ci]['p2'] = $c['CodCotizacion'];
        if (!$cotMap[$ci]['p3'])
            $cotMap[$ci]['p3'] = $c['CodCotizacion'];
    }

    $codCotBuscar = array_unique(array_filter(array_merge(array_column($filas, 'codporcion'), array_column($cotMap, 'p2'), array_column($cotMap, 'p3'))));
    $codCotBuscar = array_values(array_filter($codCotBuscar, fn($v) => $v !== null && $v !== ''));

    $diccionarioMap = [];
    if (!empty($codCotBuscar)) {
        $phC = implode(',', array_fill(0, count($codCotBuscar), '?'));

        // Paso A: resolución directa — sin filtro por presentacion_basica_inventario
        // (igual que inventario_get_data.php). Cuando la presentación resuelta no sea
        // la básica, se corrige más adelante en el loop de consumo via maestro.
        $stmtD = $conn->prepare("
            SELECT d.CodCotizacion,
                   pp.id                          AS id,
                   pp.cantidad                    AS pp_cant,
                   pp.Id_receta_producto,
                   pp.id_producto_maestro         AS id_m,
                   pp.Nombre                      AS n,
                   pp.categoria_insumo            AS cat,
                   pp.presentacion                AS presentacion,
                   pp.presentacion_basica_inventario,
                   u.id                           AS uid,
                   u.abreviado                    AS uab,
                   pm.Nombre                      AS mn
            FROM diccionario_productos_legado d
            INNER JOIN producto_presentacion pp ON pp.id = d.id_producto_presentacion
            LEFT  JOIN unidad_producto u        ON u.id  = pp.id_unidad_producto
            LEFT  JOIN producto_maestro pm      ON pm.id = pp.id_producto_maestro
            WHERE d.CodCotizacion IN ($phC)
              AND pp.Activo = 'SI'
        ");
        $stmtD->execute(array_values($codCotBuscar));
        foreach ($stmtD->fetchAll() as $row)
            $diccionarioMap[(string) $row['CodCotizacion']] = $row;

        // Paso B: rastreo por maestro de la presentación mapeada.
        // Cubre el caso donde el CodCotizacion mapea a una presentación de despacho/compra
        // (ej: Pote Chocolate 1.36kg) que tiene id_producto_maestro pero no es la básica.
        $sinResolverB = array_values(array_filter(
            $codCotBuscar,
            fn($c) => !isset($diccionarioMap[(string) $c])
        ));
        if (!empty($sinResolverB)) {
            $phB = implode(',', array_fill(0, count($sinResolverB), '?'));
            $stmtB = $conn->prepare("
                SELECT d.CodCotizacion,
                       pp_base.id                  AS id,
                       pp_base.cantidad            AS pp_cant,
                       pp_base.Id_receta_producto,
                       pp_base.id_producto_maestro AS id_m,
                       pp_base.Nombre              AS n,
                       pp_base.categoria_insumo    AS cat,
                       pp_base.presentacion        AS presentacion,
                       u_base.id                   AS uid,
                       u_base.abreviado            AS uab,
                       pm.Nombre                   AS mn
                FROM diccionario_productos_legado d
                INNER JOIN producto_presentacion pp_orig
                        ON pp_orig.id = d.id_producto_presentacion
                INNER JOIN producto_presentacion pp_base
                        ON pp_base.id_producto_maestro = pp_orig.id_producto_maestro
                       AND pp_base.presentacion_basica_inventario = 1
                       AND pp_base.Activo = 'SI'
                       AND pp_base.Id_receta_producto IS NULL
                LEFT  JOIN unidad_producto u_base ON u_base.id = pp_base.id_unidad_producto
                LEFT  JOIN producto_maestro pm    ON pm.id = pp_base.id_producto_maestro
                WHERE d.CodCotizacion IN ($phB)
                  AND pp_orig.Activo = 'SI'
                  AND pp_orig.id_producto_maestro IS NOT NULL
                GROUP BY d.CodCotizacion
            ");
            $stmtB->execute(array_values($sinResolverB));
            foreach ($stmtB->fetchAll() as $row)
                $diccionarioMap[(string) $row['CodCotizacion']] = $row;
        }

        // Paso C: fallback vía CodIngrediente → todas sus cotizaciones → maestro → básica.
        // Cubre casos donde pp_orig.id_producto_maestro es NULL.
        $sinResolverC = array_values(array_filter(
            $codCotBuscar,
            fn($c) => !isset($diccionarioMap[(string) $c])
        ));
        if (!empty($sinResolverC)) {
            $phNR = implode(',', array_fill(0, count($sinResolverC), '?'));
            $stmtPC = $conn->prepare("
                SELECT c_src.CodCotizacion,
                       pp_base.id                  AS id,
                       pp_base.cantidad            AS pp_cant,
                       pp_base.Id_receta_producto,
                       pp_base.id_producto_maestro AS id_m,
                       pp_base.Nombre              AS n,
                       pp_base.categoria_insumo    AS cat,
                       pp_base.presentacion        AS presentacion,
                       u_base.id                   AS uid,
                       u_base.abreviado            AS uab,
                       pm.Nombre                   AS mn
                FROM Cotizaciones c_src
                INNER JOIN Cotizaciones c_all   ON c_all.CodIngrediente = c_src.CodIngrediente
                INNER JOIN diccionario_productos_legado d2
                        ON d2.CodCotizacion = c_all.CodCotizacion
                INNER JOIN producto_presentacion pp_any
                        ON pp_any.id = d2.id_producto_presentacion
                       AND pp_any.Activo = 'SI'
                       AND pp_any.id_producto_maestro IS NOT NULL
                INNER JOIN producto_presentacion pp_base
                        ON pp_base.id_producto_maestro = pp_any.id_producto_maestro
                       AND pp_base.presentacion_basica_inventario = 1
                       AND pp_base.Activo = 'SI'
                       AND pp_base.Id_receta_producto IS NULL
                LEFT  JOIN unidad_producto u_base ON u_base.id = pp_base.id_unidad_producto
                LEFT  JOIN producto_maestro pm    ON pm.id = pp_base.id_producto_maestro
                WHERE c_src.CodCotizacion IN ($phNR)
                GROUP BY c_src.CodCotizacion
            ");
            $stmtPC->execute(array_values($sinResolverC));
            foreach ($stmtPC->fetchAll() as $row)
                $diccionarioMap[(string) $row['CodCotizacion']] = $row;
        }
    }

    $unidadPorNombre = [];
    $unidadPorId = [];
    foreach ($conn->query("SELECT id, nombre, abreviado, nombres_opcionales FROM unidad_producto")->fetchAll() as $u) {
        $uid = (int) $u['id'];
        $unidadPorId[$uid] = $u;
        $unidadPorNombre[strtolower(trim($u['nombre']))] = $uid;
        if ($u['abreviado'])
            $unidadPorNombre[strtolower(trim($u['abreviado']))] = $uid;
        if ($u['nombres_opcionales'])
            foreach (preg_split('/[,;|]+/', $u['nombres_opcionales']) as $a)
                if ($ak = strtolower(trim($a)))
                    $unidadPorNombre[$ak] = $uid;
    }

    $convIndex = [];
    foreach ($conn->query("SELECT id_unidad_producto_inicio as i, id_unidad_producto_final as f, cantidad as c FROM conversion_unidad_producto")->fetchAll() as $c) {
        $convIndex[(int) $c['i']][(int) $c['f']] = (float) $c['c'];
        $convIndex[(int) $c['f']][(int) $c['i']] = $c['c'] != 0 ? 1 / $c['c'] : 0;
    }
    cerrarConversionesTransitivas($convIndex); // oz→gr→kg, etc.

    $presentPorMaestro = [];
    $idMs = array_unique(array_filter(array_column($diccionarioMap, 'id_m')));
    if (!empty($idMs)) {
        $phM = implode(',', array_fill(0, count($idMs), '?'));
        $stmtPP = $conn->prepare("SELECT id, Nombre, id_producto_maestro, cantidad, id_unidad_producto, presentacion FROM producto_presentacion WHERE id_producto_maestro IN ($phM) AND Id_receta_producto IS NULL AND Activo='SI' AND presentacion_basica_inventario = 1");
        $stmtPP->execute(array_values($idMs));
        foreach ($stmtPP->fetchAll() as $pp) {
            $presentPorMaestro[(int) $pp['id_producto_maestro']][(int) $pp['id_unidad_producto']] = $pp;
        }
    }

    // 4. Proceso de Consumo
    $conAgg = [];
    $metaPP = [];
    foreach ($filas as $f) {
        $ci = $f['cod_ing'];
        $cp = $f['codporcion'];
        $sem = (int) $f['sem'];
        $cant = (float) $f['cant'];
        $m = null;
        $esP1 = false;
        if (!empty($cp) && isset($diccionarioMap[(string) $cp])) {
            $m = $diccionarioMap[(string) $cp];
            $esP1 = true;
        }
        if (!$m && isset($cotMap[$ci]['p2']) && isset($diccionarioMap[(string) $cotMap[$ci]['p2']]))
            $m = $diccionarioMap[(string) $cotMap[$ci]['p2']];
        if (!$m && isset($cotMap[$ci]['p3']) && isset($diccionarioMap[(string) $cotMap[$ci]['p3']]))
            $m = $diccionarioMap[(string) $cotMap[$ci]['p3']];
        if (!$m)
            continue;

        if (empty($m['Id_receta_producto']) && !empty($m['id_m'])) {
            // Ya NO forzamos la unificación a la presentación básica de inventario.
            // Se respeta la presentación exacta (igual que dashboard_consumo), pero sí
            // aseguramos que, si la unidad difiere de la unidad ERP de la presentación
            // original, se aplique el factor de conversión correspondiente.
        }

        $idPP = (int) $m['id'];
        $ppC = max((float) $m['pp_cant'], 0.001);
        $uidERP = (int) $m['uid'];
        $presentacionFinal = $m['presentacion'] ?? null; // valor por defecto del mapeo principal
        if ($m['Id_receta_producto']) {
            $cons = $cant;
        } else {
            $uAcc = $dbIng[$ci]['Unidad'] ?? '';
            $uidAcc = resolverUnidadId_PS($uAcc, $unidadPorNombre);
            $fac = 1.0;
            if ($uidAcc && $uidAcc !== $uidERP) {
                $fDir = resolverFactorConversion_PS($uidAcc, $uidERP, $convIndex);
                if ($fDir)
                    $fac = $fDir;
                else {
                    $alt = buscarPresentacionEnMaestro_PS((int) $m['id_m'], $uidAcc, $presentPorMaestro);
                    if ($alt) {
                        $idPP = (int) $alt['id'];
                        $ppC = max((float) $alt['cantidad'], 0.001);
                        $uidERP = (int) $alt['id_unidad_producto'];
                        $fac = 1.0;
                        // Usar la presentacion de la presentacion alternativa si la tiene
                        $presentacionFinal = $alt['presentacion'] ?? $presentacionFinal;
                    }
                }
            }
            $cons = ($cant * $fac) / $ppC;
            if ($esP1)
                $cons = round($cons * 2) / 2;
        }
        if (!isset($metaPP[$idPP]))
            // 'u' = campo presentacion de producto_presentacion (ej: 'rama', 'unid', 'oz', 'bolsa').
            // Si presentacion es NULL en la BD, se deja null → el JS muestra '—'.
            // No se usa fallback a la abreviatura de unidad para evitar mostrar datos no configurados.
            $metaPP[$idPP] = ['n' => $m['n'], 'u' => ($m['presentacion'] ?: null), 'cat' => $m['cat']];

        $conAgg[$idPP][$sem] = ($conAgg[$idPP][$sem] ?? 0) + $cons;
    }

    // 5. Config Logística
    $stmtS = $conn->prepare("
        SELECT dias_stock_minimo,
               capacidad_congelados,
               capacidad_congelados_paquetes
        FROM configuracion_logistica_sucursal
        WHERE cod_sucursal = ?
    ");
    $stmtS->execute([$codSucursal]);
    $cS  = $stmtS->fetch();
    $dSM = $cS ? (float)$cS['dias_stock_minimo'] : 0;
    $capC = $cS ? (float)$cS['capacidad_congelados'] : null;           // legacy (porciones)
    $capCPaquetes = ($cS && $cS['capacidad_congelados_paquetes'] !== null)
        ? (float)$cS['capacidad_congelados_paquetes']
        : null;

    $stmtP = $conn->prepare("SELECT codigo_insumo, dias_ciclo, dias_desfase, ajuste_demanda FROM configuracion_logistica_producto WHERE cod_sucursal = ?");
    $stmtP->execute([$codSucursal]);
    $cPs = [];
    foreach ($stmtP->fetchAll() as $row)
        $cPs[$row['codigo_insumo']] = $row;

    // 5b. Plan de despacho por categoría
    $stmtPD = $conn->prepare("
        SELECT categoria_insumo, tipo_frecuencia, intervalo_semanas, dia_despacho,
               semana_ancla, dias_semana, dias_preparacion, activo
        FROM plan_despacho_sucursal
        WHERE cod_sucursal = ? AND activo = 1
    ");
    $stmtPD->execute([$codSucursal]);
    $planDespacho = []; // [categoria => row]
    foreach ($stmtPD->fetchAll(PDO::FETCH_ASSOC) as $pdRow)
        $planDespacho[$pdRow['categoria_insumo']] = $pdRow;



    // 7. Cálculos finales
    $res = [];
    $sumB = 0;
    foreach ($conAgg as $idP => $sems) {
        $vals = [];
        for ($s = $numDesde; $s <= $numHasta; $s++)
            $vals[] = (float) ($sems[$s] ?? 0);

        // ── Ventana Activa con umbral relativo ───────────────────────────────
        // Paso 1: media de semanas con consumo estrictamente > 0
        $nonZeroVals = array_filter($vals, fn($v) => $v > 0);
        if (empty($nonZeroVals))
            continue; // Sin consumo real → descartar producto
        $meanNonZero = array_sum($nonZeroVals) / count($nonZeroVals);

        // Umbral: 10% de la media real (mín. 0.01 para productos de muy bajo volumen).
        // Valores por debajo del umbral en los extremos se tratan como ceros estructurales
        // (artefactos de redondeo, conversión, cambio de insumo).
        $umbral = max(0.01, $meanNonZero * 0.10);

        // Paso 2: detectar ventana activa con umbral relativo
        $firstIdx = null;
        $lastIdx = null;
        foreach ($vals as $i => $v) {
            if ($v >= $umbral) {
                if ($firstIdx === null)
                    $firstIdx = $i;
                $lastIdx = $i;
            }
        }
        if ($firstIdx === null)
            continue; // Todo por debajo del umbral → descartar

        $nActiva = $lastIdx - $firstIdx + 1;
        $valsActivo = array_slice($vals, $firstIdx, $nActiva);
        $prom = array_sum($valsActivo) / $nActiva;
        $wlsRes = calcularProyeccionWLS($valsActivo);
        $semC = $wlsRes['promedio'];
        $wls_m = $wlsRes['m'];
        $wls_b = $wlsRes['b'];
        $wls_n = $wlsRes['n'];
        $m = $metaPP[$idP];
        $cat = $m['cat'];
        $cP = $cat ? ($cPs[$cat] ?? null) : null;
        $adj = $cP ? (float)$cP['ajuste_demanda'] : 0;

        // Obtener ciclo desde el plan de despacho (si existe y está activo para esta cat).
        // Se pasa $hoy para que en tipo 'dias_semana' el ciclo refleje los días reales
        // entre el PRÓXIMO despacho y el subsiguiente (no el promedio 7/N).
        $planCat   = $planDespacho[$cat] ?? null;
        $cicloReal = calcularCicloRealDias($planCat, $hoy);
        $diasPrep  = calcularDiasPreparacion($planCat);

        // Fallback a configuracion_logistica_producto si no hay plan
        $dC = $cicloReal  ?? ($cP ? (float)$cP['dias_ciclo']   : 0);
        $dD = $diasPrep   ?? ($cP ? (float)$cP['dias_desfase']  : 0);

        $diaC = $semC / 7;
        $sMin = $diaC * $dSM;
        $sMax = ($diaC * $dC) + $sMin;
        // sumB sigue acumulando en unidades de uso (se convierte a paquetes después)
        if ($cat === 'B')
            $sumB += $sMax;
        // Calcular próximo despacho para la categoría
        $fechaProxDespacho = calcularProximoDespacho($planCat, date('Y-m-d'), $conn);
        $diasHastaDespacho = $fechaProxDespacho
            ? max(0, (int)((strtotime($fechaProxDespacho) - strtotime('today')) / 86400))
            : null;

        $res[$idP] = [
            'id_pp' => $idP,
            'nombre' => $m['n'],
            'unidad' => $m['u'],
            'categoria_insumo' => $cat,
            'prom_consumo' => round($prom, 4),
            'desv_estandar' => 0, // deprecado, mantenido para compatibilidad
            'cons_semanal' => round($semC, 4),
            'ajuste_demanda' => $adj,
            'dias_ciclo' => $dC,
            'dias_desfase' => $dD,
            'dias_stock_min' => $dSM,
            'cons_diario' => round($diaC, 6),
            'stock_minimo' => round($sMin, 4),
            'stock_maximo' => round($sMax, 4),
            'stock_max_final' => null,
            'es_ajustado' => false,

            'fecha_proximo_despacho'  => $fechaProxDespacho,
            'dias_hasta_despacho'     => $diasHastaDespacho,
            
            // WLS metadatos para proyección dinámica
            'wls_m' => $wls_m,
            'wls_b' => $wls_b,
            'wls_n' => $wls_n,

            // Metadatos del plan para que el JS calcule el ciclo real de cada ronda
            'plan_tipo_frecuencia'    => $planCat ? $planCat['tipo_frecuencia']  : null,
            'plan_dias_semana'        => $planCat ? (is_string($planCat['dias_semana']) ? json_decode($planCat['dias_semana'], true) : $planCat['dias_semana']) : null,
            'plan_intervalo_semanas'  => $planCat ? (int)($planCat['intervalo_semanas'] ?? 1) : null,
            '_tc' => $cP !== null
        ];
    }

    // ── Despacho factor por id_pp ─────────────────────────────
    // Idéntico a inventario_get_data.php (sección 8.5):
    // Caso A: presentación de despacho del mismo producto_maestro.
    // Caso B: receta de despacho que contiene la presentación básica.
    $idsPP = array_keys($res);
    $despFMap = []; // id_pp => ['factor'=>float, 'nombre'=>string, 'unidad'=>string]
    if (!empty($idsPP)) {
        $phPP = implode(',', array_fill(0, count($idsPP), '?'));

        // ── Paso B primero: receta-paquete (presentación de despacho cuyo único componente es la presentación básica)
        // Si existe un paquete configurado explícitamente, ese debe tener prioridad sobre cualquier
        // presentación de despacho genérica por maestro.
        $stmtDB = $conn->prepare("
            SELECT crp.id_presentacion_producto AS id_pp,
                   crp.cantidad                 AS d_receta_cant,
                   ppd.Nombre                   AS d_nombre,
                   ppd.presentacion             AS d_presentacion,
                   ud.abreviado                 AS d_unidad
            FROM producto_presentacion ppd
            INNER JOIN componentes_receta_producto crp
                   ON crp.id_receta_producto_global = ppd.Id_receta_producto
            LEFT  JOIN unidad_producto ud ON ud.id = ppd.id_unidad_producto
            WHERE ppd.presentacion_despacho = 1 AND ppd.Activo = 'SI'
              AND crp.id_presentacion_producto IN ($phPP)
              AND (
                  SELECT COUNT(DISTINCT crp2.id_presentacion_producto)
                  FROM componentes_receta_producto crp2
                  WHERE crp2.id_receta_producto_global = ppd.Id_receta_producto
              ) = 1
            GROUP BY crp.id_presentacion_producto
            ORDER BY ppd.id ASC
        ");
        $stmtDB->execute(array_values($idsPP));
        foreach ($stmtDB->fetchAll(PDO::FETCH_ASSOC) as $row)
            $despFMap[(int) $row['id_pp']] = ['factor' => (float) $row['d_receta_cant'], 'nombre' => $row['d_nombre'], 'presentacion' => $row['d_presentacion'], 'unidad' => $row['d_unidad']];

        // ── Paso A: por maestro (fallback para los que no resolvieron con B)
        $sinDF = array_values(array_filter($idsPP, fn($id) => !isset($despFMap[$id])));
        if (!empty($sinDF)) {
            $phSin = implode(',', array_fill(0, count($sinDF), '?'));
            $stmtDA = $conn->prepare("
                SELECT pp.id                  AS id_pp,
                       ppd.cantidad           AS d_cant,
                       ppd.id_unidad_producto AS d_uid,
                       pp.cantidad            AS pp_cant,
                       pp.id_unidad_producto  AS pp_uid,
                       ppd.Nombre             AS d_nombre,
                       ppd.presentacion       AS d_presentacion,
                       ud.abreviado           AS d_unidad
                FROM producto_presentacion pp
                INNER JOIN producto_presentacion ppd
                       ON ppd.id_producto_maestro = pp.id_producto_maestro
                      AND ppd.presentacion_despacho = 1
                      AND ppd.Activo = 'SI'
                      AND pp.id_producto_maestro IS NOT NULL
                LEFT  JOIN unidad_producto ud ON ud.id = ppd.id_unidad_producto
                WHERE pp.id IN ($phSin) AND pp.Activo = 'SI'
                GROUP BY pp.id
                ORDER BY ppd.id ASC
            ");
            $stmtDA->execute(array_values($sinDF));
            foreach ($stmtDA->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $uidPP = (int) $row['pp_uid'];
                $uidD = (int) $row['d_uid'];
                $df = null;
                if ($uidPP === $uidD) {
                    $df = (float) $row['d_cant'] / max((float) $row['pp_cant'], 0.001);
                } else {
                    $facConv = resolverFactorConversion_PS($uidPP, $uidD, $convIndex);
                    if ($facConv !== null && $facConv != 0)
                        $df = (float) $row['d_cant'] / (max((float) $row['pp_cant'], 0.001) * $facConv);
                }
                if ($df !== null)
                    $despFMap[(int) $row['id_pp']] = ['factor' => round($df, 6), 'nombre' => $row['d_nombre'], 'presentacion' => $row['d_presentacion'], 'unidad' => $row['d_unidad']];
            }
        }

        // ── Paso C: receta-paquete de despacho cuyo componente pertenece al mismo maestro
        //    (cubre cajillas registradas como receta con un componente diferente a la
        //     presentación básica pero del mismo maestro — ej: Naranja Cajilla 100u cuya
        //     receta contiene "Naranja Unidad" en vez de la presentación de oz).
        $sinDFC = array_values(array_filter($idsPP, fn($id) => !isset($despFMap[$id])));
        if (!empty($sinDFC)) {
            $phSinC = implode(',', array_fill(0, count($sinDFC), '?'));
            $stmtDC = $conn->prepare("
                SELECT pp.id                  AS id_pp,
                       ppd.Nombre             AS d_nombre,
                       ppd.presentacion       AS d_presentacion,
                       ud.abreviado           AS d_unidad,
                       crp.cantidad           AS d_receta_cant
                FROM producto_presentacion pp
                INNER JOIN producto_presentacion ppd
                       ON ppd.Id_receta_producto IS NOT NULL
                      AND ppd.presentacion_despacho = 1
                      AND ppd.Activo = 'SI'
                INNER JOIN componentes_receta_producto crp
                       ON crp.id_receta_producto_global = ppd.Id_receta_producto
                INNER JOIN producto_presentacion pp_comp
                       ON pp_comp.id = crp.id_presentacion_producto
                      AND pp_comp.id_producto_maestro = pp.id_producto_maestro
                      AND pp.id_producto_maestro IS NOT NULL
                LEFT  JOIN unidad_producto ud ON ud.id = ppd.id_unidad_producto
                WHERE pp.id IN ($phSinC)
                  AND pp.Activo = 'SI'
                GROUP BY pp.id
                ORDER BY ppd.id ASC
            ");
            $stmtDC->execute(array_values($sinDFC));
            foreach ($stmtDC->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $cant = (float) $row['d_receta_cant'];
                if ($cant > 0)
                    $despFMap[(int) $row['id_pp']] = ['factor' => round($cant, 6), 'nombre' => $row['d_nombre'], 'presentacion' => $row['d_presentacion'], 'unidad' => $row['d_unidad']];
            }
        }
    }

    // Recalcular sumB en PAQUETES (unidades de despacho) para comparar con capacidad
    $sumB_paquetes = 0.0;
    foreach ($res as $pTmp) {
        if ($pTmp['categoria_insumo'] === 'B') {
            $dfTmp = isset($despFMap[$pTmp['id_pp']]) && $despFMap[$pTmp['id_pp']]['factor'] > 0
                ? $despFMap[$pTmp['id_pp']]['factor']
                : 1.0;
            $sumB_paquetes += $pTmp['stock_maximo'] / $dfTmp;  // stock_maximo aún en uso
        }
    }

    // Preferir capacidad en paquetes (nueva) sobre la legacy en porciones
    if ($capCPaquetes !== null && $sumB_paquetes > 0) {
        $facC = min(1.0, $capCPaquetes / $sumB_paquetes);
    } elseif ($capC !== null && $sumB > 0) {
        // Fallback legacy (porciones) — se mantiene hasta que todas las sucursales
        // tengan configurado capacidad_congelados_paquetes
        $facC = min(1.0, $capC / $sumB);
    } else {
        $facC = null;
    }
    foreach ($res as &$p) {
        $idP = $p['id_pp'];
        $dfInfo = $despFMap[$idP] ?? null;
        $df = ($dfInfo && $dfInfo['factor'] > 0) ? $dfInfo['factor'] : 1.0;

        $p['despacho_factor'] = $dfInfo ? $dfInfo['factor'] : null;
        $p['despacho_nombre'] = $dfInfo ? $dfInfo['nombre'] : null;
        $p['despacho_presentacion'] = $dfInfo ? $dfInfo['presentacion'] : null;
        $p['despacho_unidad'] = $dfInfo ? $dfInfo['unidad'] : null;

        // Stock máximo final en unidades de USO (con factor congelados si aplica)
        $sMaxUso = $p['stock_maximo']; // aún en unidades de uso
        if ($p['categoria_insumo'] === 'B' && $facC !== null) {
            $sMaxFinalUso = round($sMaxUso * $facC, 4);
            $p['es_ajustado'] = true;
        } else {
            $sMaxFinalUso = $p['_tc'] ? round($sMaxUso, 4) : null;
        }

        // Convertir a unidades de despacho para mostrar (÷ despacho_factor)
        $p['stock_minimo'] = $p['_tc'] ? round($p['stock_minimo'] / $df, 4) : null;
        $p['stock_maximo'] = $p['_tc'] ? round($sMaxUso / $df, 4) : null;
        $p['stock_max_final'] = $sMaxFinalUso !== null ? round($sMaxFinalUso / $df, 4) : null;


        unset($p['_tc']);
    }

    usort($res, function ($a, $b) {
        $ca = $a['categoria_insumo'] ?? 'Z';
        $cb = $b['categoria_insumo'] ?? 'Z';
        return ($ca !== $cb) ? strcmp($ca, $cb) : strcmp($a['nombre'], $b['nombre']); });

    echo json_encode([
        'ok'                       => true,
        'productos'                => array_values($res),
        'n_semanas'                => $nSemanas,
        'factor_congelados'        => $facC,
        'capacidad_congelados'     => $capC,            // legacy porciones
        'capacidad_paquetes'       => $capCPaquetes,    // nuevo: paquetes
        'sum_stock_max_b'          => $sumB,            // en uso
        'sum_stock_max_b_paquetes' => $sumB_paquetes,   // en paquetes
        'usa_plan_despacho'        => !empty($planDespacho),
    ]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
}
