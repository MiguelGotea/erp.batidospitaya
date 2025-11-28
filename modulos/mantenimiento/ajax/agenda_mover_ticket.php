<?php
// ajax/agenda_mover_ticket.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
$fecha_inicio = isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : '';
$fecha_final = isset($_POST['fecha_final']) ? $_POST['fecha_final'] : '';
$tipos_usuario = isset($_POST['tipos_usuario']) ? json_decode($_POST['tipos_usuario'], true) : [];

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
    $conn = $db->getConnection();
    $conn->beginTransaction();
    
    // Actualizar fechas y status
    $stmt = $conn->prepare(
        "UPDATE mtto_tickets 
         SET fecha_inicio = CAST(? AS DATE), 
             fecha_final = CAST(? AS DATE),
             status = 'agendado'
         WHERE id = ?"
    );
    $stmt->execute([$fecha_inicio, $fecha_final, $ticket_id]);
    
    // Eliminar colaboradores anteriores
    $stmt = $conn->prepare("DELETE FROM mtto_tickets_colaboradores WHERE ticket_id = ?");
    $stmt->execute([$ticket_id]);
    
    // Insertar nuevos colaboradores según tipos de usuario
    if (!empty($tipos_usuario)) {
        $stmt = $conn->prepare(
            "INSERT INTO mtto_tickets_colaboradores (ticket_id, cod_operario, tipo_usuario) 
             VALUES (?, NULL, ?)"
        );
        
        foreach ($tipos_usuario as $tipo) {
            $stmt->execute([$ticket_id, $tipo]);
        }
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Solicitud movida correctamente'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false, 
        'message' => 'Error al mover solicitud: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>