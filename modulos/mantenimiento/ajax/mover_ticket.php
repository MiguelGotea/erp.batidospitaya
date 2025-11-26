<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Ticket.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $ticket = new Ticket();
    global $db;
    
    $ticketId = $_POST['ticket_id'] ?? null;
    $equipo = $_POST['equipo'] ?? null;
    $fechaInicio = $_POST['fecha_inicio'] ?? null;
    $fechaFinal = $_POST['fecha_final'] ?? null;
    
    if (!$ticketId || !$equipo || !$fechaInicio || !$fechaFinal) {
        throw new Exception('Faltan datos requeridos');
    }
    
    // Validar formato de fechas
    $fechaInicioObj = DateTime::createFromFormat('Y-m-d', $fechaInicio);
    $fechaFinalObj = DateTime::createFromFormat('Y-m-d', $fechaFinal);
    
    if (!$fechaInicioObj || !$fechaFinalObj) {
        throw new Exception('Formato de fecha inválido');
    }
    
    // Actualizar fechas del ticket
    $db->query(
        "UPDATE mtto_tickets 
         SET fecha_inicio = DATE(?), 
             fecha_final = DATE(?),
             status = 'agendado'
         WHERE id = ?",
        [$fechaInicio, $fechaFinal, $ticketId]
    );
    
    // Actualizar tipo_usuario de los colaboradores existentes
    $tiposUsuario = explode(' + ', $equipo);
    
    // Eliminar colaboradores actuales
    $db->query(
        "DELETE FROM mtto_tickets_colaboradores WHERE ticket_id = ?",
        [$ticketId]
    );
    
    // Insertar nuevos según el equipo
    if ($equipo !== 'Cambio de Equipos') {
        foreach ($tiposUsuario as $tipoUsuario) {
            $tipoUsuario = trim($tipoUsuario);
            
            // Buscar un operario de ese tipo
            $operario = $db->fetchOne(
                "SELECT CodOperario 
                 FROM Operarios 
                 WHERE Operativo = 1 
                 LIMIT 1"
            );
            
            if ($operario) {
                $db->query(
                    "INSERT INTO mtto_tickets_colaboradores 
                     (ticket_id, cod_operario, tipo_usuario) 
                     VALUES (?, ?, ?)",
                    [$ticketId, $operario['CodOperario'], $tipoUsuario]
                );
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Ticket movido correctamente'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}