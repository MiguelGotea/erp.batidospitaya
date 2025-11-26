<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    global $db;
    
    $ticketId = $_POST['ticket_id'] ?? null;
    $fechaInicio = $_POST['fecha_inicio'] ?? null;
    $fechaFinal = $_POST['fecha_final'] ?? null;
    
    if (!$ticketId || !$fechaInicio || !$fechaFinal) {
        throw new Exception('Faltan datos requeridos');
    }
    
    // Validar formato de fechas
    $fechaInicioObj = DateTime::createFromFormat('Y-m-d', $fechaInicio);
    $fechaFinalObj = DateTime::createFromFormat('Y-m-d', $fechaFinal);
    
    if (!$fechaInicioObj || !$fechaFinalObj) {
        throw new Exception('Formato de fecha inválido');
    }
    
    // Actualizar solo las fechas
    $db->query(
        "UPDATE mtto_tickets 
         SET fecha_inicio = DATE(?), 
             fecha_final = DATE(?)
         WHERE id = ?",
        [$fechaInicio, $fechaFinal, $ticketId]
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Fechas actualizadas correctamente'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}