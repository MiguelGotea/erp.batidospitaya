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
    
    // Iniciar transacción
    $db->getConnection()->beginTransaction();
    
    // Actualizar fechas del ticket
    $sql = "UPDATE mtto_tickets 
            SET fecha_inicio = ?, 
                fecha_final = ?, 
                status = 'agendado' 
            WHERE id = ?";
    $db->query($sql, [$fecha_inicio, $fecha_final, $ticket_id]);
    
    // Eliminar colaboradores anteriores
    $sql = "DELETE FROM mtto_tickets_colaboradores WHERE ticket_id = ?";
    $db->query($sql, [$ticket_id]);
    
    // Insertar nuevos colaboradores según el equipo
    if ($team_key !== 'CAMBIO_EQUIPOS') {
        $tipos_usuario = explode('|', $team_key);
        
        foreach ($tipos_usuario as $tipo) {
            // Obtener el cod_operario del ticket original
            $sql = "SELECT cod_operario FROM mtto_tickets WHERE id = ?";
            $ticket_data = $db->fetchOne($sql, [$ticket_id]);
            
            $sql = "INSERT INTO mtto_tickets_colaboradores (ticket_id, cod_operario, tipo_usuario) 
                    VALUES (?, ?, ?)";
            $db->query($sql, [$ticket_id, $ticket_data['cod_operario'], trim($tipo)]);
        }
    }
    
    $db->getConnection()->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Solicitud asignada correctamente'
    ]);
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->getConnection()->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>