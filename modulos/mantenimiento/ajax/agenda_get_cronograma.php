<?php
// ajax/agenda_get_cronograma.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$semana = isset($_GET['semana']) ? intval($_GET['semana']) : 518;

try {
    // Obtener fechas de la semana (Lunes a Sábado)
    $stmt = $db->query("
        SELECT DATE(fecha) as fecha, 
               DATE_FORMAT(fecha, '%d/%m') as fecha_formato
        FROM FechasSistema 
        WHERE numero_semana = ? 
        AND DAYOFWEEK(fecha) BETWEEN 2 AND 7
        ORDER BY fecha
        LIMIT 6
    ", [$semana]);
    
    $fechas = $stmt->fetchAll();
    
    if (empty($fechas)) {
        echo json_encode(['error' => 'Semana no encontrada'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $fechaInicio = $fechas[0]['fecha'];
    $fechaFinal = $fechas[count($fechas) - 1]['fecha'];
    
    // Obtener todos los equipos de trabajo históricos
    $equipos = obtenerEquiposTrabajo($db);
    
    // Agregar equipo especial para Cambio de Equipos
    $equipos[] = [
        'id' => 'cambio_equipos',
        'nombre' => 'Cambio de Equipos',
        'tipos_usuario' => [],
        'tipo_formulario' => 'cambio_equipos'
    ];
    
    // Para cada equipo, obtener sus tickets de la semana
    foreach ($equipos as &$equipo) {
        $equipo['tickets'] = obtenerTicketsEquipo($db, $equipo, $fechaInicio, $fechaFinal);
    }
    
    echo json_encode([
        'fechas' => $fechas,
        'equipos' => $equipos,
        'semana' => $semana
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

function obtenerEquiposTrabajo($db) {
    // Obtener todas las combinaciones únicas de tipo_usuario que han existido
    $sql = "
        SELECT GROUP_CONCAT(DISTINCT tipo_usuario ORDER BY tipo_usuario SEPARATOR '|') as tipos_usuario
        FROM mtto_tickets_colaboradores tc
        INNER JOIN mtto_tickets t ON tc.ticket_id = t.id
        WHERE t.tipo_formulario = 'mantenimiento_general'
        GROUP BY tc.ticket_id
    ";
    
    $stmt = $db->query($sql);
    $combinaciones = $stmt->fetchAll();
    
    // Obtener combinaciones únicas
    $equiposMap = [];
    foreach ($combinaciones as $comb) {
        $tipos = $comb['tipos_usuario'];
        if (!isset($equiposMap[$tipos])) {
            $tiposArray = explode('|', $tipos);
            $tiposArray = array_unique($tiposArray);
            sort($tiposArray);
            
            $nombre = implode(' + ', $tiposArray);
            
            $equiposMap[$tipos] = [
                'id' => md5($tipos),
                'nombre' => $nombre,
                'tipos_usuario' => $tiposArray,
                'tipo_formulario' => 'mantenimiento_general'
            ];
        }
    }
    
    return array_values($equiposMap);
}

function obtenerTicketsEquipo($db, $equipo, $fechaInicio, $fechaFinal) {
    if ($equipo['id'] === 'cambio_equipos') {
        // Tickets de cambio de equipos
        $sql = "
            SELECT t.*, s.nombre as nombre_sucursal
            FROM mtto_tickets t
            LEFT JOIN sucursales s ON t.cod_sucursal = s.codigo
            WHERE t.tipo_formulario = 'cambio_equipos'
            AND DATE(t.fecha_inicio) IS NOT NULL
            AND (
                (DATE(t.fecha_inicio) BETWEEN ? AND ?)
                OR (DATE(t.fecha_final) >= ? AND DATE(t.fecha_inicio) <= ?)
            )
            ORDER BY s.nombre, t.created_at
        ";
        
        $stmt = $db->query($sql, [$fechaInicio, $fechaFinal, $fechaInicio, $fechaFinal]);
        return $stmt->fetchAll();
    }
    
    // Tickets de mantenimiento general para este equipo
    $tiposUsuario = $equipo['tipos_usuario'];
    
    if (empty($tiposUsuario)) {
        return [];
    }
    
    $sql = "
        SELECT t.*, s.nombre as nombre_sucursal,
               GROUP_CONCAT(DISTINCT tc.tipo_usuario ORDER BY tc.tipo_usuario SEPARATOR '|') as tipos_asignados
        FROM mtto_tickets t
        LEFT JOIN sucursales s ON t.cod_sucursal = s.codigo
        INNER JOIN mtto_tickets_colaboradores tc ON t.id = tc.ticket_id
        WHERE t.tipo_formulario = 'mantenimiento_general'
        AND DATE(t.fecha_inicio) IS NOT NULL
        AND (
            (DATE(t.fecha_inicio) BETWEEN ? AND ?)
            OR (DATE(t.fecha_final) >= ? AND DATE(t.fecha_inicio) <= ?)
        )
        GROUP BY t.id
        HAVING tipos_asignados = ?
        ORDER BY s.nombre, t.created_at
    ";
    
    $tiposStr = implode('|', $tiposUsuario);
    $stmt = $db->query($sql, [$fechaInicio, $fechaFinal, $fechaInicio, $fechaFinal, $tiposStr]);
    
    return $stmt->fetchAll();
}
?>