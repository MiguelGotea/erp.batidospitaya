<?php
// ajax/agenda_actualizar_fechas.php
// Solo actualiza fecha_inicio y fecha_final (usado por resize)
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$ticketId = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
$fechaInicio = isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : '';
$fechaFinal = isset($_POST['fecha_final']) ? $_POST['fecha_final'] : '';

if (!$ticketId || !$fechaInicio || !$fechaFinal) {
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
    // Solo actualizar fechas, mantener todo lo demás igual
    $stmt = $db->query("
        UPDATE mtto_tickets 
        SET fecha_inicio = ?, 
            fecha_final = ?
        WHERE id = ?
    ", [$fechaInicio, $fechaFinal, $ticketId]);
    
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>