<?php
require_once '../../../config/database.php';
require_once '../../core/auth/auth.php';

header('Content-Type: application/json');

if (!isset($_GET['ticket_id'])) {
    echo json_encode(['success' => false, 'message' => 'Falta el ID del ticket']);
    exit;
}

$ticket_id = intval($_GET['ticket_id']);
$conn = getDBConnection();

try {
    // Obtener la tarea de este ticket
    $stmt = $conn->prepare("
        SELECT id, trabajo_realizado, completado_100, created_at
        FROM mtto_informe_tareas 
        WHERE ticket_id = ?
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'No se encontró trabajo realizado para este ticket']);
        exit;
    }
    
    $tarea = $result->fetch_assoc();
    
    // Obtener las fotos asociadas a esta tarea
    $stmtFotos = $conn->prepare("
        SELECT id, foto 
        FROM mtto_informe_tareas_fotos 
        WHERE tarea_id = ?
        ORDER BY orden ASC
    ");
    $stmtFotos->bind_param("i", $tarea['id']);
    $stmtFotos->execute();
    $resultFotos = $stmtFotos->get_result();
    
    $fotos = [];
    while ($row = $resultFotos->fetch_assoc()) {
        $fotos[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'tarea' => $tarea,
        'fotos' => $fotos
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
