<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Ticket.php';

header('Content-Type: application/json');

try {
    global $db;
    $ticket = new Ticket();
    
    $ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
    $team_key = isset($_POST['team_key']) ? $_POST['team_key'] : '';
    $fecha_inicio = isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : '';
    $fecha_final = isset($_POST['fecha_final']) ? $_POST['fecha_final'] : '';
    
    if (!$ticket_id || !$team_key || !$fecha_inicio || !$fecha_final) {
        throw new Exception('Datos incompletos');
    }
    
    // Validar formato de fecha y convertir si es necesario
    $fecha_inicio_obj = DateTime::createFromFormat('Y-m-d', $fecha_inicio);
    $fecha_final_obj = DateTime::createFromFormat('Y-m-d', $fecha_final);
    
    if (!$fecha_inicio_obj || !$fecha_final_obj) {
        throw new Exception('Formato de fecha inválido');
    }
    
    $fecha_inicio_formatted = $fecha_inicio_obj->format('Y-m-d');
    $fecha_final_formatted = $fecha_final_obj->format('Y-m-d');
    
    // Iniciar transacción
    $db->getConnection()->beginTransaction();
    
    // Actualizar fechas del ticket
    $sql = "UPDATE mtto_tickets 
            SET fecha_inicio = ?, 
                fecha_final = ?, 
                status = 'agendado' 
            WHERE id = ?";
    $db->query($sql, [$fecha_inicio_formatted, $fecha_final_formatted, $ticket_id]);
    
    // Eliminar colaboradores anteriores
    $sql = "DELETE FROM mtto_tickets_colaboradores WHERE ticket_id = ?";
    $db->query($sql, [$ticket_id]);
    
    // Insertar nuevos colaboradores según el equipo
    if ($team_key !== 'CAMBIO_EQUIPOS') {
        $tipos_usuario = explode('|', $team_key);
        
        // Obtener el cod_operario del ticket original
        $sql = "SELECT cod_operario FROM mtto_tickets WHERE id = ?";
        $ticket_data = $db->fetchOne($sql, [$ticket_id]);
        
        if (!$ticket_data) {
            throw new Exception('Ticket no encontrado');
        }
        
        foreach ($tipos_usuario as $tipo) {
            $sql = "INSERT INTO mtto_tickets_colaboradores (ticket_id, cod_operario, tipo_usuario) 
                    VALUES (?, ?, ?)";
            $db->query($sql, [$ticket_id, $ticket_data['cod_operario'], trim($tipo)]);
        }
    } else {
        // Para cambio de equipos, solo necesitamos un registro básico
        $sql = "SELECT cod_operario FROM mtto_tickets WHERE id = ?";
        $ticket_data = $db->fetchOne($sql, [$ticket_id]);
        
        if ($ticket_data) {
            $sql = "INSERT INTO mtto_tickets_colaboradores (ticket_id, cod_operario, tipo_usuario) 
                    VALUES (?, ?, 'Cambio de Equipos')";
            $db->query($sql, [$ticket_id, $ticket_data['cod_operario']]);
        }
    }
    
    $db->getConnection()->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Solicitud asignada correctamente',
        'fecha_inicio' => $fecha_inicio_formatted,
        'fecha_final' => $fecha_final_formatted
    ]);
    
} catch (Exception $e) {
    if (isset($db) && $db->getConnection()->inTransaction()) {
        $db->getConnection()->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>