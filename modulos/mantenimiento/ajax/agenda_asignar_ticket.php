<?php
// ajax/agenda_asignar_ticket.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$ticketId = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
$equipoId = isset($_POST['equipo_id']) ? $_POST['equipo_id'] : '';
$fechaInicio = isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : '';
$fechaFinal = isset($_POST['fecha_final']) ? $_POST['fecha_final'] : '';

if (!$ticketId || !$equipoId || !$fechaInicio || !$fechaFinal) {
    echo json_encode(['error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validar formato de fechas
$dateInicio = DateTime::createFromFormat('Y-m-d', $fechaInicio);
$dateFinal = DateTime::createFromFormat('Y-m-d', $fechaFinal);

if (!$dateInicio || !$dateFinal) {
    echo json_encode(['error' => 'Formato de fecha inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db->getConnection()->beginTransaction();
    
    // Actualizar fechas y status del ticket
    $stmt = $db->query("
        UPDATE mtto_tickets 
        SET fecha_inicio = ?, 
            fecha_final = ?,
            status = 'agendado'
        WHERE id = ?
    ", [$fechaInicio, $fechaFinal, $ticketId]);
    
    // Eliminar colaboradores existentes
    $db->query("DELETE FROM mtto_tickets_colaboradores WHERE ticket_id = ?", [$ticketId]);
    
    // Asignar colaboradores según el equipo
    if ($equipoId === 'cambio_equipos') {
        // No se asignan colaboradores específicos para cambio de equipos
        // Solo se agenda la fecha
    } else {
        // Obtener tipos de usuario del equipo
        $tiposUsuario = obtenerTiposUsuarioEquipo($db, $equipoId);
        
        if (!empty($tiposUsuario)) {
            foreach ($tiposUsuario as $tipoUsuario) {
                // Insertar registro de colaborador con tipo de usuario
                $db->query("
                    INSERT INTO mtto_tickets_colaboradores (ticket_id, tipo_usuario, fecha_asignacion)
                    VALUES (?, ?, NOW())
                ", [$ticketId, $tipoUsuario]);
            }
        }
    }
    
    $db->getConnection()->commit();
    
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    $db->getConnection()->rollBack();
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

function obtenerTiposUsuarioEquipo($db, $equipoId) {
    // Buscar un ticket con este equipo para obtener sus tipos
    $sql = "
        SELECT GROUP_CONCAT(DISTINCT tipo_usuario ORDER BY tipo_usuario SEPARATOR '|') as tipos
        FROM mtto_tickets_colaboradores tc
        INNER JOIN mtto_tickets t ON tc.ticket_id = t.id
        WHERE t.tipo_formulario = 'mantenimiento_general'
        GROUP BY tc.ticket_id
        HAVING MD5(tipos) = ?
        LIMIT 1
    ";
    
    $stmt = $db->query($sql, [$equipoId]);
    $result = $stmt->fetch();
    
    if ($result && $result['tipos']) {
        return explode('|', $result['tipos']);
    }
    
    return [];
}
?>