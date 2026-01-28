<?php
/**
 * Obtener datos de tareas y reuniones agrupados
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/helpers/funciones.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $codOperario = $usuario['CodOperario'];
    $codCargo = $usuario['CodNivelesCargos'];

    $agrupacion = $_POST['agrupacion'] ?? 'mes';

    // Obtener todas las tareas y reuniones relevantes para el usuario
    $items = obtenerItemsUsuario($conn, $codCargo);

    // Agrupar según el tipo solicitado
    $grupos = [];

    switch ($agrupacion) {
        case 'mes':
            $grupos = agruparPorMes($items);
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
        'success' => true,
        'grupos' => $grupos
    ]);

} catch (Exception $e) {
    error_log("Error en get_datos: " . $e->getMessage());
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
                    ELSE i.fecha_meta
                END ASC";

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
 * Agrupar por mes
 */
function agruparPorMes($items)
{
    $grupos = [];
    $hoy = new DateTime();
    $mesActual = $hoy->format('Y-m');

    // Grupo de tareas vencidas
    $vencidas = [];

    foreach ($items as $item) {
        $fecha = $item['tipo'] == 'reunion' ? $item['fecha_reunion'] : $item['fecha_meta'];

        if (!$fecha)
            continue;

        $fechaObj = new DateTime($fecha);
        $mes = $fechaObj->format('Y-m');

        // Si es tarea y está vencida
        if ($item['tipo'] == 'tarea' && $mes < $mesActual && $item['estado'] != 'finalizado' && $item['estado'] != 'cancelado') {
            $vencidas[] = $item;
            continue;
        }

        $nombreMes = obtenerNombreMes($fechaObj);

        if (!isset($grupos[$mes])) {
            $grupos[$mes] = [
                'nombre' => $nombreMes,
                'orden' => $mes,
                'items' => []
            ];
        }

        $grupos[$mes]['items'][] = $item;
    }

    // Ordenar grupos por mes
    uasort($grupos, function ($a, $b) {
        return strcmp($a['orden'], $b['orden']);
    });

    // Agregar grupo de vencidas al inicio si hay
    if (!empty($vencidas)) {
        array_unshift($grupos, [
            'nombre' => 'Tareas Vencidas',
            'orden' => '0000-00',
            'items' => $vencidas
        ]);
    }

    return array_values($grupos);
}

/**
 * Agrupar por semana
 */
function agruparPorSemana($items, $conn)
{
    $grupos = [];
    $hoy = new DateTime();

    // Obtener semana actual del sistema
    $sqlSemanaActual = "SELECT numero_semana FROM SemanasSistema 
                        WHERE fecha_inicio <= CURDATE() AND fecha_fin >= CURDATE()";
    $stmt = $conn->query($sqlSemanaActual);
    $semanaActual = $stmt->fetchColumn();

    // Grupo de tareas vencidas
    $vencidas = [];

    foreach ($items as $item) {
        $fecha = $item['tipo'] == 'reunion' ? $item['fecha_reunion'] : $item['fecha_meta'];

        if (!$fecha)
            continue;

        // Obtener semana del sistema para esta fecha
        $sqlSemana = "SELECT numero_semana, fecha_inicio, fecha_fin 
                      FROM SemanasSistema 
                      WHERE fecha_inicio <= DATE(:fecha) AND fecha_fin >= DATE(:fecha)";
        $stmt = $conn->prepare($sqlSemana);
        $stmt->execute([':fecha' => $fecha]);
        $semanaData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$semanaData)
            continue;

        $numeroSemana = $semanaData['numero_semana'];

        // Si es tarea y está vencida
        if ($item['tipo'] == 'tarea' && $numeroSemana < $semanaActual && $item['estado'] != 'finalizado' && $item['estado'] != 'cancelado') {
            $vencidas[] = $item;
            continue;
        }

        $mesesCortos = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        $fechaInicio = new DateTime($semanaData['fecha_inicio']);
        $fechaFin = new DateTime($semanaData['fecha_fin']);
        $nombreSemana = "Semana " . $numeroSemana . " (" .
            $fechaInicio->format('d') . "-" . $mesesCortos[$fechaInicio->format('n') - 1] . " a " .
            $fechaFin->format('d') . "-" . $mesesCortos[$fechaFin->format('n') - 1] . ")";

        if (!isset($grupos[$numeroSemana])) {
            $grupos[$numeroSemana] = [
                'nombre' => $nombreSemana,
                'orden' => $numeroSemana,
                'items' => []
            ];
        }

        $grupos[$numeroSemana]['items'][] = $item;
    }

    // Ordenar grupos por semana
    uasort($grupos, function ($a, $b) {
        return $a['orden'] - $b['orden'];
    });

    // Agregar grupo de vencidas al inicio si hay
    if (!empty($vencidas)) {
        array_unshift($grupos, [
            'nombre' => 'Tareas Vencidas',
            'orden' => 0,
            'items' => $vencidas
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