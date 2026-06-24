<?php
/**
 * Obtener datos de tareas y reuniones agrupados
 */

require_once '../../../core/auth/auth.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $codOperario = $usuario['CodOperario'];
    $codCargo = $usuario['CodNivelesCargos'];

    $agrupacion = $_POST['agrupacion'] ?? 'mes';

    // Obtener todas las tareas y reuniones relevantes para el usuario
    $items = obtenerItemsUsuario($conn, $codCargo);

    // Agrupar según el tipo solicitado
    $grupos     = [];
    $finalizados = [];
    $hoyStr = date('Y-m-d');

    $sinFecha = [];
    switch ($agrupacion) {
        case 'mes':
            $resultado = agruparPorMes($items);
            $grupos    = $resultado['grupos'];
            $sinFecha  = $resultado['sin_fecha'];
            // Historial: tareas finalizadas/canceladas + reuniones cuya fecha ya pasó
            foreach ($items as $it) {
                if ($it['tipo'] === 'tarea' && in_array($it['estado'], ['finalizado', 'cancelado'])) {
                    $finalizados[] = $it;
                } elseif ($it['tipo'] === 'reunion') {
                    $fechaR = substr($it['fecha_reunion'] ?? '', 0, 10);
                    if ($fechaR < $hoyStr || $it['estado'] === 'finalizado') {
                        $finalizados[] = $it;
                    }
                }
            }
            // Ordenar historial: más reciente primero
            usort($finalizados, function($a, $b) {
                $fa = $a['tipo'] === 'reunion' ? ($a['fecha_reunion'] ?? '') : ($a['fecha_meta'] ?? '');
                $fb = $b['tipo'] === 'reunion' ? ($b['fecha_reunion'] ?? '') : ($b['fecha_meta'] ?? '');
                return strcmp($fb, $fa);
            });
            break;
        case 'semana':
            $grupos = agruparPorSemana($items, $conn);
            break;
        case 'cargo':
            $grupos = agruparPorCargo($items, $conn);
            break;
        case 'estado':
            $grupos = agruparPorEstado($items);
            break;
    }

    echo json_encode([
        'success'     => true,
        'grupos'      => $grupos,
        'sin_fecha'   => $sinFecha,
        'finalizados' => $finalizados
    ]);

} catch (Exception $e) {
    error_log("Error en get_datos: " . $e->getMessage());
    file_put_contents(__DIR__ . '/error_debug.txt', $e->getMessage() . "\n" . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Obtener items relevantes para el usuario
 */
function obtenerItemsUsuario($conn, $codCargo)
{
    // Obtener tareas asignadas al cargo del usuario o creadas por él
    // Obtener reuniones donde el cargo del usuario es participante

    $sql = "SELECT 
                i.*,
                nc.Nombre as nombre_cargo_asignado,
                -- Lógica de avatar: Si es reunión, el creador. Si es tarea, el asignado.
                CASE 
                    WHEN i.tipo = 'reunion' THEN o_creador.foto_perfil
                    ELSE o_asignado.foto_perfil
                END as avatar_url,
                CASE 
                    WHEN i.tipo = 'reunion' THEN CONCAT(o_creador.Nombre, ' ', o_creador.Apellido)
                    ELSE COALESCE(CONCAT(o_asignado.Nombre, ' ', o_asignado.Apellido), nc.Nombre)
                END as nombre_responsable,
                (SELECT COUNT(*) FROM gestion_tareas_reuniones_items sub 
                 WHERE sub.id_padre = i.id AND sub.tipo = 'subtarea') as total_subtareas,
                (SELECT COUNT(*) FROM gestion_tareas_reuniones_items sub 
                 WHERE sub.id_padre = i.id AND sub.tipo = 'subtarea' 
                 AND sub.estado = 'finalizado') as subtareas_completadas,
                (SELECT COUNT(*) FROM gestion_tareas_reuniones_participantes p 
                 WHERE p.id_item = i.id) as total_invitados,
                (SELECT COUNT(*) FROM gestion_tareas_reuniones_participantes p 
                 WHERE p.id_item = i.id AND p.confirmacion != 'pendiente') as confirmados
            FROM gestion_tareas_reuniones_items i
            LEFT JOIN NivelesCargos nc ON i.cod_cargo_asignado = nc.CodNivelesCargos
            LEFT JOIN Operarios o_creador ON i.cod_operario_creador = o_creador.CodOperario
            LEFT JOIN Operarios o_asignado ON o_asignado.CodOperario = (
                SELECT anc.CodOperario 
                FROM AsignacionNivelesCargos anc 
                WHERE anc.CodNivelesCargos = i.cod_cargo_asignado 
                AND anc.Fecha <= CURDATE() 
                AND (anc.Fin >= CURDATE() OR anc.Fin IS NULL)
                ORDER BY anc.Fecha DESC 
                LIMIT 1
            )
            WHERE i.tipo IN ('tarea', 'reunion')
            AND (
                i.cod_cargo_asignado = :cod_cargo_asignado
                OR i.cod_cargo_creador = :cod_cargo_creador
                OR EXISTS (
                    SELECT 1 FROM gestion_tareas_reuniones_participantes p 
                    WHERE p.id_item = i.id AND p.cod_cargo = :cod_cargo_participante
                )
            )
            ORDER BY 
                CASE 
                    WHEN i.tipo = 'reunion' THEN i.fecha_reunion
                    WHEN i.fecha_meta IS NULL THEN '9999-12-31'
                    ELSE i.fecha_meta
                END ASC, i.id ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':cod_cargo_asignado' => $codCargo,
        ':cod_cargo_creador' => $codCargo,
        ':cod_cargo_participante' => $codCargo
    ]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular progreso para cada item
    foreach ($items as &$item) {
        if ($item['tipo'] == 'tarea' && $item['total_subtareas'] > 0) {
            $item['progreso'] = ($item['subtareas_completadas'] / $item['total_subtareas']) * 100;
        } else {
            $item['progreso'] = $item['estado'] == 'finalizado' ? 100 : 0;
        }
    }

    return $items;
}

/**
 * Agrupar por día:
 * - Pasados (cualquier fecha): mostrar SOLO si tienen pendientes (sin límite de antigüedad)
 * - Hoy + días restantes del mes actual + al menos 6 días después de hoy: siempre mostrar
 */
function agruparPorMes($items)
{
    $hoy    = new DateTime('today');
    $hoyStr = $hoy->format('Y-m-d');

    $meses      = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    $diasSemana = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];

    // Helper para nombre de día
    $nombreDia = function(DateTime $d) use ($meses, $diasSemana): string {
        return $diasSemana[(int)$d->format('w')] . ' ' .
               $d->format('d') . ' ' .
               $meses[(int)$d->format('n') - 1];
    };

    // Separar tareas sin fecha_meta (pendientes de asignar)
    $sinFecha = [];
    $itemsByDate = [];
    foreach ($items as $item) {
        // Tareas sin fecha_meta → panel especial
        if ($item['tipo'] === 'tarea' && empty($item['fecha_meta'])
            && !in_array($item['estado'], ['finalizado', 'cancelado'])) {
            $sinFecha[] = $item;
            continue;
        }
        $fecha = $item['tipo'] === 'reunion'
            ? substr($item['fecha_reunion'] ?? '', 0, 10)
            : ($item['fecha_meta'] ?? '');
        if (!$fecha) continue;
        $itemsByDate[$fecha][] = $item;
    }

    // Ordenar items de cada día: PRIMERO activos por hora, LUEGO finalizados/cancelados por hora
    $prioridadWeight = function ($p) {
        switch ($p) {
            case 'alta': return 1;
            case 'media': return 2;
            case 'baja': return 3;
            default: return 4;
        }
    };

    $getHora = function ($item) {
        return $item['tipo'] === 'reunion'
            ? substr($item['fecha_reunion'] ?? '', 11, 8)
            : ($item['hora_tarea'] ?? '08:00:00'); // NULL hora = 08:00 (igual que el JS)
    };

    $sortByHora = function ($a, $b) use ($prioridadWeight, $getHora) {
        $ha = $getHora($a);
        $hb = $getHora($b);
        if ($ha !== $hb) return strcmp($ha, $hb);
        $wa = $prioridadWeight($a['prioridad'] ?? 'media');
        $wb = $prioridadWeight($b['prioridad'] ?? 'media');
        if ($wa !== $wb) return $wa - $wb;
        return $a['id'] - $b['id'];
    };

    foreach ($itemsByDate as &$itemsDia) {
        $activos     = array_values(array_filter($itemsDia, fn($i) => !in_array($i['estado'], ['finalizado', 'cancelado'])));
        $terminados  = array_values(array_filter($itemsDia, fn($i) =>  in_array($i['estado'], ['finalizado', 'cancelado'])));
        usort($activos,    $sortByHora);
        usort($terminados, $sortByHora);
        $itemsDia = array_merge($activos, $terminados);
    }
    unset($itemsDia);

    $grupos = [];

    // ── PASADOS: solo tareas con estado pendiente (reuniones pasadas = concluidas, van al historial) ──
    foreach ($itemsByDate as $fechaStr => $itemsDelDia) {
        if ($fechaStr >= $hoyStr) continue;

        // Filtrar solo TAREAS activas (en_progreso / solicitado)
        $tareasPendientes = array_values(array_filter($itemsDelDia, function($it) {
            return $it['tipo'] === 'tarea'
                && !in_array($it['estado'], ['finalizado', 'cancelado']);
        }));

        if (empty($tareasPendientes)) continue;

        $diaObj = new DateTime($fechaStr);
        $grupos[$fechaStr] = [
            'nombre'           => $nombreDia($diaObj) . ' ' . $diaObj->format('Y'),
            'fecha_referencia' => $fechaStr,
            'clase_header'     => 'vencido',
            'items'            => $tareasPendientes,
        ];
    }

    // ── HOY + resto del mes actual + al menos 6 días más (buffer futuro) ──
    $hasta = new DateTime('first day of next month');
    $seisDiasMas = (clone $hoy)->modify('+6 days');
    if ($seisDiasMas > $hasta) {
        $hasta = $seisDiasMas;
    }
    $diaIterado = clone $hoy;

    while ($diaIterado <= $hasta) {
        $fechaStr = $diaIterado->format('Y-m-d');
        $esHoy    = ($fechaStr === $hoyStr);

        $grupos[$fechaStr] = [
            'nombre'           => $esHoy ? ('HOY — ' . $nombreDia($diaIterado)) : $nombreDia($diaIterado),
            'fecha_referencia' => $fechaStr,
            'clase_header'     => $esHoy ? 'hoy' : '',
            'items'            => $itemsByDate[$fechaStr] ?? [],
        ];

        $diaIterado->modify('+1 day');
    }

    ksort($grupos);
    return ['grupos' => array_values($grupos), 'sin_fecha' => $sinFecha];
}

/**
 * Agrupar por semana
 */
function agruparPorSemana($items, $conn)
{
    $grupos = [];
    $hoy = new DateTime();

    // Obtener la semana actual del sistema
    $sqlSemanaActual = "SELECT numero_semana, anio FROM SemanasSistema 
                        WHERE fecha_inicio <= CURDATE() AND fecha_fin >= CURDATE() LIMIT 1";
    $stmtActual = $conn->query($sqlSemanaActual);
    $semanaActualData = $stmtActual ? $stmtActual->fetch(PDO::FETCH_ASSOC) : null;
    $numSemanaActual = $semanaActualData ? (int)$semanaActualData['numero_semana'] : 0;
    $anioSemanaActual = $semanaActualData ? (int)$semanaActualData['anio'] : 0;

    // Grupo de tareas vencidas (antes de la semana actual)
    $vencidas = [];

    foreach ($items as $item) {
        $fecha = $item['tipo'] == 'reunion' ? $item['fecha_reunion'] : $item['fecha_meta'];

        if (!$fecha)
            continue;

        // Obtener la fila de SemanasSistema que contiene esta fecha
        $sqlSemana = "SELECT numero_semana, anio, fecha_inicio, fecha_fin 
                      FROM SemanasSistema 
                      WHERE fecha_inicio <= DATE(:fecha) AND fecha_fin >= DATE(:fecha) LIMIT 1";
        $stmt = $conn->prepare($sqlSemana);
        $stmt->execute([':fecha' => $fecha]);
        $semanaData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$semanaData)
            continue;

        $numSemana = (int)$semanaData['numero_semana'];
        $anioSemana = (int)$semanaData['anio'];
        // Orden cronológico: AAAA + Semana (ej. 202626 para semana 26 de 2026)
        $ordenSemana = $anioSemana * 100 + $numSemana;
        $ordenSemanaActual = $anioSemanaActual * 100 + $numSemanaActual;

        // Si es tarea activa y su semana ya pasó → vencidas
        if ($item['tipo'] == 'tarea'
            && $ordenSemanaActual > 0
            && $ordenSemana < $ordenSemanaActual
            && $item['estado'] != 'finalizado'
            && $item['estado'] != 'cancelado') {
            $vencidas[] = $item;
            continue;
        }

        $mesesCortos = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        $fechaInicio = new DateTime($semanaData['fecha_inicio']);
        $fechaFin    = new DateTime($semanaData['fecha_fin']);
        $nombreSemana = "Semana #" . $numSemana . " (" .
            $fechaInicio->format('d') . "-" . $mesesCortos[$fechaInicio->format('n') - 1] . " al " .
            $fechaFin->format('d')   . "-" . $mesesCortos[$fechaFin->format('n')   - 1] . ")";

        // Agregar fecha_referencia para drag & drop
        if (!isset($grupos[$ordenSemana])) {
            $grupos[$ordenSemana] = [
                'nombre'           => $nombreSemana,
                'orden'            => $ordenSemana,
                'fecha_referencia' => $semanaData['fecha_fin'], // última fecha de la semana
                'items'            => []
            ];
        }

        $grupos[$ordenSemana]['items'][] = $item;
    }

    // Ordenar grupos por orden cronológico de semana
    uasort($grupos, function ($a, $b) {
        return $a['orden'] - $b['orden'];
    });

    // Agregar grupo de vencidas al inicio si hay
    if (!empty($vencidas)) {
        array_unshift($grupos, [
            'nombre'           => 'Tareas Vencidas',
            'orden'            => 0,
            'fecha_referencia' => '',
            'items'            => $vencidas
        ]);
    }

    return array_values($grupos);
}

/**
 * Agrupar por cargo
 */
function agruparPorCargo($items, $conn)
{
    $grupos = [];

    foreach ($items as $item) {
        if ($item['tipo'] == 'tarea') {
            // Agrupar por cargo asignado
            $codCargo = $item['cod_cargo_asignado'];
            $nombreCargo = $item['nombre_cargo_asignado'];

            if (!isset($grupos[$codCargo])) {
                $grupos[$codCargo] = [
                    'nombre' => $nombreCargo,
                    'orden' => $nombreCargo,
                    'items' => []
                ];
            }

            $grupos[$codCargo]['items'][] = $item;
        } else {
            // Reunión: agregar a todos los cargos invitados
            $sqlParticipantes = "SELECT p.cod_cargo, nc.Nombre 
                                 FROM gestion_tareas_reuniones_participantes p
                                 INNER JOIN NivelesCargos nc ON p.cod_cargo = nc.CodNivelesCargos
                                 WHERE p.id_item = :id_item";
            $stmt = $conn->prepare($sqlParticipantes);
            $stmt->execute([':id_item' => $item['id']]);
            $participantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($participantes as $participante) {
                $codCargo = $participante['cod_cargo'];
                $nombreCargo = $participante['Nombre'];

                if (!isset($grupos[$codCargo])) {
                    $grupos[$codCargo] = [
                        'nombre' => $nombreCargo,
                        'orden' => $nombreCargo,
                        'items' => []
                    ];
                }

                $grupos[$codCargo]['items'][] = $item;
            }
        }
    }

    // Ordenar grupos alfabéticamente
    uasort($grupos, function ($a, $b) {
        return strcmp($a['orden'], $b['orden']);
    });

    // Ordenar items dentro de cada grupo por prioridad
    $prioridadWeight = function ($p) {
        switch ($p) {
            case 'alta': return 1;
            case 'media': return 2;
            case 'baja': return 3;
            default: return 4;
        }
    };

    foreach ($grupos as &$g) {
        usort($g['items'], function ($a, $b) use ($prioridadWeight) {
            $wa = $prioridadWeight($a['prioridad'] ?? 'media');
            $wb = $prioridadWeight($b['prioridad'] ?? 'media');
            return $wa - $wb;
        });
    }
    unset($g);

    return array_values($grupos);
}

/**
 * Agrupar por estado
 */
function agruparPorEstado($items)
{
    $estados = [
        'solicitado' => 'Solicitados',
        'en_progreso' => 'En Progreso',
        'finalizado' => 'Finalizados',
        'cancelado' => 'Cancelados'
    ];

    $grupos = [];

    foreach ($estados as $estado => $nombre) {
        $grupos[$estado] = [
            'nombre' => $nombre,
            'orden' => $estado,
            'items' => []
        ];
    }

    foreach ($items as $item) {
        $estado = $item['estado'];
        if (isset($grupos[$estado])) {
            $grupos[$estado]['items'][] = $item;
        }
    }

    return array_values($grupos);
}

/**
 * Obtener nombre del mes
 */
function obtenerNombreMes($fecha)
{
    $meses = [
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre'
    ];

    $mes = intval($fecha->format('n'));
    $anio = $fecha->format('Y');

    return $meses[$mes] . ' ' . $anio;
}
?>