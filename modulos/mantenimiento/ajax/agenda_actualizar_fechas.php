<?php
// ajax/agenda_actualizar_fechas.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
$fecha_inicio = isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : '';
$fecha_final = isset($_POST['fecha_final']) ? $_POST['fecha_final'] : '';

if ($ticket_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de ticket inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validar formato de fechas
if (!DateTime::createFromFormat('Y-m-d', $fecha_inicio) || !DateTime::createFromFormat('Y-m-d', $fecha_final)) {
    echo json_encode(['success' => false, 'message' => 'Formato de fecha inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $db->getConnection()->prepare(
        "UPDATE mtto_tickets 
         SET fecha_inicio = CAST(? AS DATE), 
             fecha_final = CAST(? AS DATE),
             status = 'agendado'
         WHERE id = ?"
    );
    
    $stmt->execute([$fecha_inicio, $fecha_final, $ticket_id]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Fechas actualizadas correctamente'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error al actualizar fechas: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>