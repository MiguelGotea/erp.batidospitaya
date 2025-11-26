<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Ticket.php';

header('Content-Type: application/json');

try {
    global $db;
    $ticket = new Ticket();
    
    $week_number = isset($_GET['week_number']) ? intval($_GET['week_number']) : 518;
    
    // Obtener fechas de la semana (Lunes a Sábado)
    $sql = "SELECT fecha, 
            CASE DAYOFWEEK(fecha)
                WHEN 2 THEN 'Lun'
                WHEN 3 THEN 'Mar'
                WHEN 4 THEN 'Mié'
                WHEN 5 THEN 'Jue'
                WHEN 6 THEN 'Vie'
                WHEN 7 THEN 'Sáb'
            END as day_name,
            DATE_FORMAT(fecha, '%d/%m/%Y') as date_formatted
            FROM FechasSistema 
            WHERE numero_semana = ? 
            AND DAYOFWEEK(fecha) BETWEEN 2 AND 7
            ORDER BY fecha";
    
    $dates = $db->fetchAll($sql, [$week_number]);
    
    if (empty($dates)) {
        echo json_encode([
            'dates' => [],
            'work_teams' => [],
            'scheduled_tickets' => []
        ]);
        exit;
    }
    
    $fecha_inicio = $dates[0]['fecha'];
    $fecha_final = $dates[count($dates) - 1]['fecha'];
    
    // Obtener equipos de trabajo únicos del historial
    $sql = "SELECT DISTINCT GROUP_CONCAT(DISTINCT tipo_usuario ORDER BY tipo_usuario SEPARATOR ' + ') as team_name,
            GROUP_CONCAT(DISTINCT tipo_usuario ORDER BY tipo_usuario SEPARATOR '|') as team_key
            FROM mtto_tickets_colaboradores
            WHERE ticket_id IN (
                SELECT id FROM mtto_tickets WHERE fecha_inicio IS NOT NULL
            )
            GROUP BY ticket_id
            ORDER BY team_name";
    
    $teams_raw = $db->fetchAll($sql);
    
    // Crear array único de equipos
    $unique_teams = [];
    $seen_keys = [];
    
    foreach ($teams_raw as $team) {
        $key = $team['team_key'];
        if (!in_array($key, $seen_keys)) {
            $seen_keys[] = $key;
            $unique_teams[] = [
                'team_key' => $key,
                'team_name' => $team['team_name'],
                'is_cambio_equipos' => false
            ];
        }
    }
    
    // Agregar grupo especial "Cambio de Equipos"
    $unique_teams[] = [
        'team_key' => 'CAMBIO_EQUIPOS',
        'team_name' => 'Cambio de Equipos',
        'is_cambio_equipos' => true
    ];
    
    // Obtener tickets programados en esta semana
    $sql = "SELECT t.*, 
            s.nombre as nombre_sucursal,
            GROUP_CONCAT(DISTINCT tc.tipo_usuario ORDER BY tc.tipo_usuario SEPARATOR '|') as team_key
            FROM mtto_tickets t
            LEFT JOIN sucursales s ON t.cod_sucursal = s.codigo
            LEFT JOIN mtto_tickets_colaboradores tc ON t.id = tc.ticket_id
            WHERE t.fecha_inicio IS NOT NULL 
            AND t.fecha_final IS NOT NULL
            AND (
                (t.fecha_inicio BETWEEN ? AND ?)
                OR (t.fecha_final BETWEEN ? AND ?)
                OR (t.fecha_inicio <= ? AND t.fecha_final >= ?)
            )
            GROUP BY t.id
            ORDER BY s.nombre";
    
    $tickets = $db->fetchAll($sql, [
        $fecha_inicio, $fecha_final,
        $fecha_inicio, $fecha_final,
        $fecha_inicio, $fecha_final
    ]);
    
    // Procesar tickets de cambio de equipos
    foreach ($tickets as &$ticket) {
        if ($ticket['tipo_formulario'] === 'cambio_equipos') {
            $ticket['team_key'] = 'CAMBIO_EQUIPOS';
        }
    }
    
    echo json_encode([
        'dates' => $dates,
        'work_teams' => $unique_teams,
        'scheduled_tickets' => $tickets
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>