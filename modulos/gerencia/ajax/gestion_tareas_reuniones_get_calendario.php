<?php
ob_start();
/**
 * Obtener eventos para el calendario (tareas y reuniones)
 * Versión corregida - Compatible con FullCalendar v6
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    if (!$usuario) {
        throw new Exception('Sesión expirada');
    }

    $codCargo = $usuario['CodNivelesCargos'];
    $start = $_GET['start'] ?? null;
    $end = $_GET['end'] ?? null;

    // Log para debugging
    error_log("Calendario request - start: $start, end: $end, cargo: $codCargo");

    $events = [];

    // 1. OBTENER TAREAS (se muestran en su fecha límite)
    $sqlTareas = "SELECT 
                    i.titulo, 
                    i.descripcion,
                    i.fecha_meta, 
                    i.estado, 
                    'tarea' as tipo,
                    (SELECT COUNT(*) FROM gestion_tareas_reuniones_items sub 
                     WHERE sub.id_padre = i.id AND sub.tipo = 'subtarea') as total_subtareas,
                    (SELECT COUNT(*) FROM gestion_tareas_reuniones_items sub 
                     WHERE sub.id_padre = i.id AND sub.tipo = 'subtarea' 
                     AND sub.estado = 'finalizado') as subtareas_completadas,
                    i.cod_cargo_asignado,
                    i.cod_cargo_creador
                  FROM 
                    gestion_tareas_reuniones_items i
                  WHERE 
                    i.tipo = 'tarea' 
                    AND i.estado != 'cancelado'
                    AND i.fecha_meta IS NOT NULL
                    AND DATE(i.fecha_meta) BETWEEN DATE(:start) AND DATE(:end)
                    AND (
                        i.cod_cargo_asignado = :cod_cargo 
                        OR i.cod_cargo_creador = :cod_cargo_creador
                        OR EXISTS (
                            SELECT 1 FROM gestion_tareas_reuniones_participantes p 
                            WHERE p.id_item = i.id AND p.cod_cargo = :cod_cargo_part
                        )
                    )
                  ORDER BY i.fecha_meta ASC";

    $stmtT = $conn->prepare($sqlTareas);
    $stmtT->execute([
        ':start' => $start,
        ':end' => $end,
        ':cod_cargo' => $codCargo,
        ':cod_cargo_creador' => $codCargo,
        ':cod_cargo_part' => $codCargo
    ]);
    $tareas = $stmtT->fetchAll(PDO::FETCH_ASSOC);

    foreach ($tareas as $t) {
        // Determinar color según estado
        $color = '#0d6efd'; // Azul por defecto
        $textColor = '#ffffff';

        switch ($t['estado']) {
            case 'finalizado':
                $color = '#28a745'; // Verde
                break;
            case 'en_progreso':
                $color = '#ffc107'; // Amarillo
                $textColor = '#000000';
                break;
            case 'solicitado':
                $color = '#0dcaf0'; // Cyan
                break;
            case 'cancelado':
                $color = '#6c757d'; // Gris
                break;
        }

        // Información de subtareas
        $infoSub = "";
        if ($t['total_subtareas'] > 0) {
            $infoSub = " ({$t['subtareas_completadas']}/{$t['total_subtareas']} subtareas)";
        }

        // Calcular fecha de fin (un día después de la fecha meta)
        $startDate = new DateTime($t['fecha_meta']);
        $endDate = clone $startDate;
        $endDate->modify('+1 day');

        $events[] = [
            'id' => 'tarea_' . $t['id'],
            'title' => '📌 ' . $t['titulo'] . $infoSub,
            'start' => $t['fecha_meta'], // Solo fecha, no hora
            'end' => $endDate->format('Y-m-d'),
            'allDay' => true, // Evento de día completo
            'color' => $color,
            'textColor' => $textColor,
            'extendedProps' => [
                'tipo' => 'tarea',
                'estado' => $t['estado'],
                'itemId' => $t['id'],
                'descripcion' => $t['descripcion']
            ]
        ];
    }

    // 2. OBTENER REUNIONES (eventos con fecha y hora específica)
    $sqlReuniones = "SELECT 
                        i.id, 
                        i.titulo, 
                        i.descripcion,
                        i.fecha_reunion, 
                        i.estado, 
                        'reunion' as tipo,
                        (SELECT COUNT(*) FROM gestion_tareas_reuniones_participantes p 
                         WHERE p.id_item = i.id) as total_invitados,
                        -- Usar != 'pendiente' porque el enum es asistire/no_asistire
                        (SELECT COUNT(*) FROM gestion_tareas_reuniones_participantes p 
                         WHERE p.id_item = i.id AND p.confirmacion != 'pendiente') as confirmados,
                        i.cod_cargo_asignado,
                        i.cod_cargo_creador
                      FROM 
                        gestion_tareas_reuniones_items i
                      WHERE 
                        i.tipo = 'reunion' 
                        AND i.estado != 'cancelado'
                        AND i.fecha_reunion IS NOT NULL
                        AND DATE(i.fecha_reunion) BETWEEN DATE(:start) AND DATE(:end)
                        AND (
                            i.cod_cargo_asignado = :cod_cargo 
                            OR i.cod_cargo_creador = :cod_cargo_creador
                            OR EXISTS (
                                SELECT 1 FROM gestion_tareas_reuniones_participantes p 
                                WHERE p.id_item = i.id AND p.cod_cargo = :cod_cargo_part
                            )
                        )
                      ORDER BY i.fecha_reunion ASC";

    $stmtR = $conn->prepare($sqlReuniones);
    $stmtR->execute([
        ':start' => $start,
        ':end' => $end,
        ':cod_cargo' => $codCargo,
        ':cod_cargo_creador' => $codCargo,
        ':cod_cargo_part' => $codCargo
    ]);
    $reuniones = $stmtR->fetchAll(PDO::FETCH_ASSOC);

    foreach ($reuniones as $r) {
        // Verificar si fecha_reunion ya tiene hora
        $startDateTime = new DateTime($r['fecha_reunion']);

        // Si no tiene hora, agregar hora por defecto (9:00 AM)
        if ($startDateTime->format('H:i:s') == '00:00:00') {
            $startDateTime->setTime(9, 0, 0);
        }

        // Duración por defecto: 1 hora
        $endDateTime = clone $startDateTime;
        $endDateTime->modify('+1 hour');

        $infoPart = "";
        if ($r['total_invitados'] > 0) {
            $infoPart = " ({$r['confirmados']}/{$r['total_invitados']} confirmados)";
        }

        $events[] = [
            'id' => 'reunion_' . $r['id'],
            'title' => '👥 ' . $r['titulo'] . $infoPart,
            'start' => $startDateTime->format('Y-m-d\TH:i:s'),
            'end' => $endDateTime->format('Y-m-d\TH:i:s'),
            'allDay' => false, // Evento con hora específica
            'color' => '#6610f2', // Morado para reuniones
            'textColor' => '#ffffff',
            'extendedProps' => [
                'tipo' => 'reunion',
                'estado' => $r['estado'],
                'itemId' => $r['id'],
                'descripcion' => $r['descripcion']
            ]
        ];
    }

    // Log para debugging
    error_log("Eventos encontrados: " . count($events));

    $json = json_encode($events);
    ob_end_clean();
    echo $json;

} catch (Exception $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    error_log("Error en get_calendario: " . $e->getMessage());
    // IMPORTANTE: Devolver array vacío en caso de error
    echo json_encode([]);
}