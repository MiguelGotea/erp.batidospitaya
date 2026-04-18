<?php
/**
 * AJAX — Pitaya OPS Lab · Datos de Ingeniería de Operaciones
 * modulos/gerencia/ajax/pitaya_ops_get_datos.php
 *
 * Parámetros POST:
 *   accion       : 'llegadas' | 'mix_estaciones' | 'cycle_times' | 'multi_estacion'
 *                  | 'config' | 'sucursales' | 'resumen_mes'
 *   cod_sucursal : código de sucursal (varchar, campo 'codigo' de tabla sucursales)
 *   ini          : fecha inicio (Y-m-d)
 *   fin          : fecha fin    (Y-m-d)
 *   tipo_dia     : 'todos' | 'entre_semana' | 'fin_semana'
 *   turno        : 'todos' | 'manana' | 'tarde'  (mañana: <=14:00, tarde: >14:00)
 */

ob_start();
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');

$usuario    = obtenerUsuarioActual();
$cargo      = $usuario['CodNivelesCargos'];

if (!tienePermiso('pitaya_ops_lab', 'vista', $cargo)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Sin acceso']);
    exit;
}

$accion      = $_POST['accion']       ?? 'sucursales';
$codSucursal = $_POST['cod_sucursal'] ?? null;
$ini         = $_POST['ini']          ?? date('Y-m-01', strtotime('-1 month'));
$fin         = $_POST['fin']          ?? date('Y-m-t', strtotime('-1 month'));
$tipoDia     = $_POST['tipo_dia']     ?? 'todos';
$turno       = $_POST['turno']        ?? 'todos';

// ── Helper: filtros de día y turno ────────────────────────────────────────────
// tipoDia: 1=Domingo,2=Lunes,...,7=Sábado  → fin_semana = 1,6,7
$filtroDia  = '';
if ($tipoDia === 'fin_semana')    $filtroDia = "AND DAYOFWEEK(v.Fecha) IN (1, 6, 7)";
if ($tipoDia === 'entre_semana')  $filtroDia = "AND DAYOFWEEK(v.Fecha) NOT IN (1, 6, 7)";

// Turno: mañana = HoraCreado <= '14:00:00', tarde = HoraCreado > '14:00:00'
$filtroTurno = '';
if ($turno === 'manana') $filtroTurno = "AND TIME(v.HoraCreado) <= '14:00:00'";
if ($turno === 'tarde')  $filtroTurno = "AND TIME(v.HoraCreado) > '14:00:00'";

// Filtro de sucursal — si no viene, todas
$filtroSuc   = $codSucursal ? "AND v.local = :cod_suc" : "";
$paramsSuc   = $codSucursal ? [':cod_suc' => $codSucursal] : [];

try {
    // ════════════════════════════════════════════════════════════════════════
    // ACCIÓN: sucursales — lista de tiendas activas
    // ════════════════════════════════════════════════════════════════════════
    if ($accion === 'sucursales') {
        $rows = $conn->query(
            "SELECT codigo, nombre FROM sucursales
             WHERE sucursal = 1 AND activa = 1
             ORDER BY nombre ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        ob_clean();
        echo json_encode(['success' => true, 'sucursales' => $rows]);
        exit;
    }

    // ════════════════════════════════════════════════════════════════════════
    // ACCIÓN: config — parámetros operativos editables
    // ════════════════════════════════════════════════════════════════════════
    if ($accion === 'config') {
        $rows = $conn->query(
            "SELECT tipo_estacion, parametro, valor, descripcion
             FROM ops_config_estaciones
             ORDER BY FIELD(tipo_estacion,'Batido','Waffle','Bowl','General'), parametro"
        )->fetchAll(PDO::FETCH_ASSOC);

        // Convertir a mapa { tipo_estacion: { parametro: valor } }
        $config = [];
        foreach ($rows as $r) {
            $config[$r['tipo_estacion']][$r['parametro']] = [
                'valor'       => (float) $r['valor'],
                'descripcion' => $r['descripcion'],
            ];
        }

        ob_clean();
        echo json_encode(['success' => true, 'config' => $config]);
        exit;
    }

    // ════════════════════════════════════════════════════════════════════════
    // ACCIÓN: guardar_config — actualizar un parámetro
    // ════════════════════════════════════════════════════════════════════════
    if ($accion === 'guardar_config') {
        $tipoEst  = $_POST['tipo_estacion'] ?? null;
        $param    = $_POST['parametro']     ?? null;
        $valor    = $_POST['valor']         ?? null;

        if (!$tipoEst || !$param || $valor === null) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Parámetros incompletos']);
            exit;
        }

        $st = $conn->prepare(
            "UPDATE ops_config_estaciones
             SET valor = :valor, actualizado_por = :usr
             WHERE tipo_estacion = :tipo AND parametro = :param"
        );
        $st->execute([
            ':valor' => (float) $valor,
            ':usr'   => $usuario['CodOperario'] ?? null,
            ':tipo'  => $tipoEst,
            ':param' => $param,
        ]);

        ob_clean();
        echo json_encode(['success' => true, 'updated' => $st->rowCount()]);
        exit;
    }

    // ════════════════════════════════════════════════════════════════════════
    // ACCIÓN: llegadas — distribución de pedidos por hora
    // λ (tasa de llegada Poisson) por hora, día semana, tipo día
    // ════════════════════════════════════════════════════════════════════════
    if ($accion === 'llegadas') {
        $sql = "
            SELECT
                HOUR(v.HoraCreado)          AS hora,
                DAYOFWEEK(v.Fecha)          AS dia_semana,
                CASE WHEN DAYOFWEEK(v.Fecha) IN (1,6,7)
                     THEN 'fin_semana' ELSE 'entre_semana' END AS tipo_dia,
                COUNT(DISTINCT v.CodPedido) AS pedidos,
                SUM(v.Cantidad)             AS unidades_total,
                AVG(v.Cantidad)             AS unidades_prom_pedido,
                COUNT(DISTINCT v.Fecha)     AS dias_observados
            FROM VentasGlobalesAccessCSV v
            INNER JOIN sucursales s ON s.codigo = v.local
            WHERE v.Anulado = 0
              AND v.Fecha BETWEEN :ini AND :fin
              AND v.HoraCreado IS NOT NULL
              AND s.sucursal = 1
              $filtroSuc
              $filtroDia
              $filtroTurno
            GROUP BY HOUR(v.HoraCreado), DAYOFWEEK(v.Fecha)
            ORDER BY hora ASC, dia_semana ASC
        ";

        $params = array_merge([':ini' => $ini, ':fin' => $fin], $paramsSuc);
        $st = $conn->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        // Calcular λ promedio por hora (pedidos / días observados)
        $byHora = [];
        foreach ($rows as $r) {
            $hora = (int) $r['hora'];
            if (!isset($byHora[$hora])) {
                $byHora[$hora] = ['pedidos_total' => 0, 'dias_obs' => 0, 'unidades' => 0];
            }
            $byHora[$hora]['pedidos_total'] += (int) $r['pedidos'];
            $byHora[$hora]['dias_obs']      += (int) $r['dias_observados'];
            $byHora[$hora]['unidades']      += (float) $r['unidades_total'];
        }

        $llegadasPorHora = [];
        for ($h = 6; $h <= 22; $h++) {
            $d = $byHora[$h] ?? ['pedidos_total' => 0, 'dias_obs' => 1, 'unidades' => 0];
            $diasObs = max(1, $d['dias_obs']);
            $llegadasPorHora[] = [
                'hora'          => $h,
                'lambda'        => round($d['pedidos_total'] / $diasObs, 2),  // pedidos/hora promedio
                'pedidos_total' => (int) $d['pedidos_total'],
                'dias_obs'      => $diasObs,
                'unidades_prom' => round($d['unidades'] / $diasObs, 2),
            ];
        }

        ob_clean();
        echo json_encode([
            'success'         => true,
            'llegadas_por_hora' => $llegadasPorHora,
            'raw'             => $rows,
        ]);
        exit;
    }

    // ════════════════════════════════════════════════════════════════════════
    // ACCIÓN: mix_estaciones — % por estación por hora
    // ════════════════════════════════════════════════════════════════════════
    if ($accion === 'mix_estaciones') {
        $sql = "
            SELECT
                HOUR(v.HoraCreado)          AS hora,
                CASE
                    WHEN g.Tipo IN ('Batido','Limonada') THEN 'Batido'
                    WHEN g.Tipo = 'Waffle'               THEN 'Waffle'
                    WHEN g.Tipo = 'Bowl'                 THEN 'Bowl'
                    ELSE 'Otro'
                END                          AS estacion,
                COUNT(DISTINCT v.CodPedido) AS pedidos,
                SUM(v.Cantidad)             AS unidades,
                COUNT(DISTINCT v.Fecha)     AS dias_obs
            FROM VentasGlobalesAccessCSV v
            INNER JOIN sucursales s ON s.codigo = v.local
            INNER JOIN DBBatidos b ON b.CodBatido = v.CodProducto
            INNER JOIN GrupoProductosVenta g ON g.CodGrupo = b.CodGrupo
            WHERE v.Anulado = 0
              AND v.Fecha BETWEEN :ini AND :fin
              AND v.HoraCreado IS NOT NULL
              AND s.sucursal = 1
              $filtroSuc
              $filtroDia
              $filtroTurno
            GROUP BY HOUR(v.HoraCreado),
                     CASE
                         WHEN g.Tipo IN ('Batido','Limonada') THEN 'Batido'
                         WHEN g.Tipo = 'Waffle'               THEN 'Waffle'
                         WHEN g.Tipo = 'Bowl'                 THEN 'Bowl'
                         ELSE 'Otro'
                     END
            ORDER BY hora ASC
        ";

        $params = array_merge([':ini' => $ini, ':fin' => $fin], $paramsSuc);
        $st = $conn->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        // Estructurar por hora → {estacion: {pedidos, unidades}}
        $mixPorHora = [];
        foreach ($rows as $r) {
            $h = (int) $r['hora'];
            if (!isset($mixPorHora[$h])) {
                $mixPorHora[$h] = ['hora' => $h, 'Batido' => 0, 'Waffle' => 0, 'Bowl' => 0, 'Otro' => 0, 'total' => 0];
            }
            $diasObs = max(1, (int) $r['dias_obs']);
            $pedidosProm = round((int) $r['pedidos'] / $diasObs, 2);
            $est = $r['estacion'];
            $mixPorHora[$h][$est]  += $pedidosProm;
            $mixPorHora[$h]['total'] += $pedidosProm;
        }

        // Calcular % por estación
        $resultado = [];
        foreach ($mixPorHora as $h => $data) {
            $total = max(1, $data['total']);
            $resultado[] = [
                'hora'        => $h,
                'Batido'      => round($data['Batido'], 2),
                'Waffle'      => round($data['Waffle'], 2),
                'Bowl'        => round($data['Bowl'], 2),
                'Otro'        => round($data['Otro'], 2),
                'total'       => round($total, 2),
                'pct_Batido'  => round($data['Batido'] / $total * 100, 1),
                'pct_Waffle'  => round($data['Waffle'] / $total * 100, 1),
                'pct_Bowl'    => round($data['Bowl']   / $total * 100, 1),
            ];
        }

        ob_clean();
        echo json_encode(['success' => true, 'mix_por_hora' => array_values($resultado)]);
        exit;
    }

    // ════════════════════════════════════════════════════════════════════════
    // ACCIÓN: cycle_times — lead/cycle time proxy desde BD real
    // ════════════════════════════════════════════════════════════════════════
    if ($accion === 'cycle_times') {
        $sql = "
            SELECT
                CASE
                    WHEN g.Tipo IN ('Batido','Limonada') THEN 'Batido'
                    WHEN g.Tipo = 'Waffle'               THEN 'Waffle'
                    WHEN g.Tipo = 'Bowl'                 THEN 'Bowl'
                    ELSE 'Otro'
                END AS estacion,
                COUNT(*)                                                                  AS registros,
                AVG(TIMESTAMPDIFF(SECOND, v.HoraCreado, v.HoraImpreso))                  AS lead_time_prom_seg,
                AVG(TIMESTAMPDIFF(SECOND, v.HoraIngresoProducto, v.HoraImpreso))          AS cycle_time_prom_seg,
                MIN(TIMESTAMPDIFF(SECOND, v.HoraCreado, v.HoraImpreso))                  AS lead_min_seg,
                MAX(TIMESTAMPDIFF(SECOND, v.HoraCreado, v.HoraImpreso))                  AS lead_max_seg,
                -- Percentil aproximado vía subconsulta no disponible en todas versiones,
                -- usamos desviación estándar como proxy de variabilidad
                STDDEV(TIMESTAMPDIFF(SECOND, v.HoraCreado, v.HoraImpreso))               AS lead_stddev_seg
            FROM VentasGlobalesAccessCSV v
            INNER JOIN sucursales s ON s.codigo = v.local
            INNER JOIN DBBatidos b ON b.CodBatido = v.CodProducto
            INNER JOIN GrupoProductosVenta g ON g.CodGrupo = b.CodGrupo
            WHERE v.Anulado = 0
              AND v.HoraCreado IS NOT NULL
              AND v.HoraImpreso IS NOT NULL
              AND TIMESTAMPDIFF(SECOND, v.HoraCreado, v.HoraImpreso) > 0
              AND TIMESTAMPDIFF(SECOND, v.HoraCreado, v.HoraImpreso) < 7200 -- excluir outliers (>2h)
              AND v.Fecha BETWEEN :ini AND :fin
              AND s.sucursal = 1
              $filtroSuc
              $filtroDia
              $filtroTurno
            GROUP BY estacion
            HAVING estacion != 'Otro'
        ";

        $params = array_merge([':ini' => $ini, ':fin' => $fin], $paramsSuc);
        $st = $conn->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $cycleTimes = array_map(function ($r) {
            return [
                'estacion'             => $r['estacion'],
                'registros'            => (int) $r['registros'],
                'lead_time_prom_min'   => round((float) $r['lead_time_prom_seg'] / 60, 2),
                'cycle_time_prom_min'  => round((float) $r['cycle_time_prom_seg'] / 60, 2),
                'lead_min_min'         => round((float) $r['lead_min_seg'] / 60, 2),
                'lead_max_min'         => round((float) $r['lead_max_seg'] / 60, 2),
                'lead_stddev_min'      => round((float) $r['lead_stddev_seg'] / 60, 2),
                // Tiempo en cola = Lead Time − Cycle Time (tiempo esperando en fila)
                'queue_time_prom_min'  => round(((float) $r['lead_time_prom_seg'] - (float) $r['cycle_time_prom_seg']) / 60, 2),
            ];
        }, $rows);

        ob_clean();
        echo json_encode(['success' => true, 'cycle_times' => $cycleTimes]);
        exit;
    }

    // ════════════════════════════════════════════════════════════════════════
    // ACCIÓN: multi_estacion — análisis de pedidos que tocan varias estaciones
    // ════════════════════════════════════════════════════════════════════════
    if ($accion === 'multi_estacion') {
        $sql = "
            SELECT
                sub.num_estaciones,
                sub.estaciones_combo,
                COUNT(*)            AS num_pedidos,
                AVG(sub.total_items) AS items_prom
            FROM (
                SELECT
                    v.CodPedido,
                    COUNT(DISTINCT
                        CASE
                            WHEN g.Tipo IN ('Batido','Limonada') THEN 'Batido'
                            WHEN g.Tipo = 'Waffle'               THEN 'Waffle'
                            WHEN g.Tipo = 'Bowl'                 THEN 'Bowl'
                        END
                    ) AS num_estaciones,
                    GROUP_CONCAT(DISTINCT
                        CASE
                            WHEN g.Tipo IN ('Batido','Limonada') THEN 'Batido'
                            WHEN g.Tipo = 'Waffle'               THEN 'Waffle'
                            WHEN g.Tipo = 'Bowl'                 THEN 'Bowl'
                        END
                        ORDER BY g.Tipo
                    ) AS estaciones_combo,
                    SUM(v.Cantidad) AS total_items
                FROM VentasGlobalesAccessCSV v
                INNER JOIN sucursales s ON s.codigo = v.local
                INNER JOIN DBBatidos b ON b.CodBatido = v.CodProducto
                INNER JOIN GrupoProductosVenta g ON g.CodGrupo = b.CodGrupo
                WHERE v.Anulado = 0
                  AND v.Fecha BETWEEN :ini AND :fin
                  AND s.sucursal = 1
                  AND g.Tipo IN ('Batido','Limonada','Waffle','Bowl')
                  $filtroSuc
                  $filtroDia
                GROUP BY v.CodPedido
            ) sub
            GROUP BY sub.num_estaciones, sub.estaciones_combo
            ORDER BY num_pedidos DESC
        ";

        $params = array_merge([':ini' => $ini, ':fin' => $fin], $paramsSuc);
        $st = $conn->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $totalPedidos = array_sum(array_column($rows, 'num_pedidos'));
        $resultado = array_map(function ($r) use ($totalPedidos) {
            return [
                'num_estaciones'   => (int) $r['num_estaciones'],
                'estaciones_combo' => $r['estaciones_combo'],
                'num_pedidos'      => (int) $r['num_pedidos'],
                'items_prom'       => round((float) $r['items_prom'], 2),
                'pct'              => $totalPedidos > 0 ? round((int) $r['num_pedidos'] / $totalPedidos * 100, 1) : 0,
            ];
        }, $rows);

        ob_clean();
        echo json_encode([
            'success'       => true,
            'multi_estacion' => $resultado,
            'total_pedidos'  => $totalPedidos,
        ]);
        exit;
    }

    // ════════════════════════════════════════════════════════════════════════
    // ACCIÓN: resumen_mes — KPIs generales del período seleccionado
    // ════════════════════════════════════════════════════════════════════════
    if ($accion === 'resumen_mes') {
        // Total pedidos, ítems, días activos, ticket prom, distribución días semana
        $sqlResumen = "
            SELECT
                COUNT(DISTINCT v.CodPedido)                               AS total_pedidos,
                SUM(v.Cantidad)                                           AS total_unidades,
                COUNT(DISTINCT v.Fecha)                                   AS dias_activos,
                SUM(CASE WHEN v.Anulado=0 THEN v.Precio ELSE 0 END)      AS ventas_totales,
                AVG(v.Cantidad)                                           AS items_prom_pedido,
                CASE WHEN DAYOFWEEK(v.Fecha) IN (1,6,7) THEN 'fin_semana' ELSE 'entre_semana' END AS tipo_dia
            FROM VentasGlobalesAccessCSV v
            INNER JOIN sucursales s ON s.codigo = v.local
            WHERE v.Anulado = 0
              AND v.Fecha BETWEEN :ini AND :fin
              AND s.sucursal = 1
              $filtroSuc
            GROUP BY tipo_dia
        ";

        $params = array_merge([':ini' => $ini, ':fin' => $fin], $paramsSuc);
        $st = $conn->prepare($sqlResumen);
        $st->execute($params);
        $resumenRows = $st->fetchAll(PDO::FETCH_ASSOC);

        // Mix por estación global
        $sqlMixGlobal = "
            SELECT
                CASE
                    WHEN g.Tipo IN ('Batido','Limonada') THEN 'Batido'
                    WHEN g.Tipo = 'Waffle'               THEN 'Waffle'
                    WHEN g.Tipo = 'Bowl'                 THEN 'Bowl'
                    ELSE 'Otro'
                END AS estacion,
                COUNT(DISTINCT v.CodPedido) AS pedidos,
                SUM(v.Cantidad)             AS unidades,
                SUM(v.Precio)               AS monto
            FROM VentasGlobalesAccessCSV v
            INNER JOIN sucursales s ON s.codigo = v.local
            INNER JOIN DBBatidos b ON b.CodBatido = v.CodProducto
            INNER JOIN GrupoProductosVenta g ON g.CodGrupo = b.CodGrupo
            WHERE v.Anulado = 0
              AND v.Fecha BETWEEN :ini AND :fin
              AND s.sucursal = 1
              $filtroSuc
            GROUP BY estacion
            ORDER BY pedidos DESC
        ";
        $stMix = $conn->prepare($sqlMixGlobal);
        $stMix->execute($params);
        $mixGlobal = $stMix->fetchAll(PDO::FETCH_ASSOC);

        // Hora pico (top 3)
        $sqlHoraPico = "
            SELECT
                HOUR(v.HoraCreado) AS hora,
                COUNT(DISTINCT v.CodPedido) AS pedidos
            FROM VentasGlobalesAccessCSV v
            INNER JOIN sucursales s ON s.codigo = v.local
            WHERE v.Anulado = 0
              AND v.Fecha BETWEEN :ini AND :fin
              AND v.HoraCreado IS NOT NULL
              AND s.sucursal = 1
              $filtroSuc
            GROUP BY HOUR(v.HoraCreado)
            ORDER BY pedidos DESC
            LIMIT 3
        ";
        $stHP = $conn->prepare($sqlHoraPico);
        $stHP->execute($params);
        $horasPico = $stHP->fetchAll(PDO::FETCH_ASSOC);

        ob_clean();
        echo json_encode([
            'success'     => true,
            'resumen'     => $resumenRows,
            'mix_global'  => $mixGlobal,
            'horas_pico'  => $horasPico,
        ]);
        exit;
    }

    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Acción no reconocida: ' . htmlspecialchars($accion)]);

} catch (\Throwable $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
    ]);
}
