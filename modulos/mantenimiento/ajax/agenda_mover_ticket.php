<?php
// ajax/agenda_mover_ticket.php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/database.php';

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

$ticketId = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
$equipoId = isset($_POST['equipo_id']) ? $_POST['equipo_id'] : '';
$fechaInicio = isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : '';
$fechaFinal = isset($_POST['fecha_final']) ? $_POST['fecha_final'] : '';

if (!$ticketId || !$equipoId || !$fechaInicio || !$fechaFinal) {
    echo json_encode([
        'error' => 'Datos incompletos',
        'received' => [
            'ticket_id' => $ticketId,
            'equipo_id' => $equipoId,
            'fecha_inicio' => $fechaInicio,
            'fecha_final' => $fechaFinal
        ]
    ], JSON_UNESCAPED_UNICODE);
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
    
    // Obtener ticket actual
    $ticket = $db->fetchOne("SELECT cod_operario, tipo_formulario FROM mtto_tickets WHERE id = ?", [$ticketId]);
    
    if (!$ticket) {
        throw new Exception('Ticket no encontrado');
    }
    
    // Actualizar fechas del ticket
    $stmt = $db->query("
        UPDATE mtto_tickets 
        SET fecha_inicio = ?, 
            fecha_final = ?
        WHERE id = ?
    ", [$fechaInicio, $fechaFinal, $ticketId]);
    
    // Eliminar colaboradores existentes
    $db->query("DELETE FROM mtto_tickets_colaboradores WHERE ticket_id = ?", [$ticketId]);
    
    // Reasignar según nuevo equipo
    if ($equipoId === 'cambio_equipos') {
        // Para cambio de equipos, no asignar colaboradores específicos
        // El ticket solo tiene fecha programada
    } else {
        // Para mantenimiento general, obtener tipos de usuario del equipo
        $tiposUsuario = obtenerTiposUsuarioEquipo($db, $equipoId);
        
        if (!empty($tiposUsuario)) {
            foreach ($tiposUsuario as $tipoUsuario) {
                // Insertar con cod_operario original y nuevo tipo_usuario
                $db->query("
                    INSERT INTO mtto_tickets_colaboradores (ticket_id, cod_operario, tipo_usuario, fecha_asignacion)
                    VALUES (?, ?, ?, NOW())
                ", [$ticketId, $ticket['cod_operario'], $tipoUsuario]);
            }
        } else {
            // Si no se pueden obtener los tipos de usuario, crear registro genérico
            $db->query("
                INSERT INTO mtto_tickets_colaboradores (ticket_id, cod_operario, tipo_usuario, fecha_asignacion)
                VALUES (?, ?, 'Jefe de Manteniento', NOW())
            ", [$ticketId, $ticket['cod_operario']]);
        }
    }
    
    $db->getConnection()->commit();
    
    echo json_encode([
        'success' => true,
        'ticket_id' => $ticketId,
        'equipo_id' => $equipoId
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    $db->getConnection()->rollBack();
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}

function obtenerTiposUsuarioEquipo($db, $equipoId) {
    try {
        $sql = "
            SELECT GROUP_CONCAT(DISTINCT tipo_usuario ORDER BY tipo_usuario SEPARATOR '|') as tipos
            FROM mtto_tickets_colaboradores tc
            INNER JOIN mtto_tickets t ON tc.ticket_id = t.id
            WHERE t.tipo_formulario = 'mantenimiento_general'
            AND tc.tipo_usuario IS NOT NULL
            AND tc.tipo_usuario != ''
            GROUP BY tc.ticket_id
            HAVING MD5(tipos) = ?
            LIMIT 1
        ";
        
        $stmt = $db->query($sql, [$equipoId]);
        $result = $stmt->fetch();
        
        if ($result && $result['tipos']) {
            $tipos = explode('|', $result['tipos']);
            return array_values(array_filter($tipos));
        }
        
        return [];
    } catch (Exception $e) {
        return [];
    }
}
?>