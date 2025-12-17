<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

session_start();
$usuario_id = $_SESSION['usuario_id'];

try {
    $solicitud_id = $_POST['solicitud_id'];
    $observaciones = $_POST['observaciones'] ?? '';
    
    // Verificar que la solicitud existe y está pendiente
    $solicitud = $db->fetchOne(
        "SELECT id, estado FROM mtto_equipos_solicitudes WHERE id = ?",
        [$solicitud_id]
    );
    
    if (!$solicitud) {
        echo json_encode(['success' => false, 'message' => 'Solicitud no encontrada']);
        exit;
    }
    
    if ($solicitud['estado'] === 'finalizado') {
        echo json_encode(['success' => false, 'message' => 'La solicitud ya está finalizada']);
        exit;
    }
    
    // Actualizar solicitud
    $db->query(
        "UPDATE mtto_equipos_solicitudes 
         SET estado = 'finalizado',
             finalizado_por = ?,
             fecha_finalizacion = NOW(),
             observaciones_finalizacion = ?
         WHERE id = ?",
        [$usuario_id, $observaciones, $solicitud_id]
    );
    
    echo json_encode(['success' => true, 'message' => 'Solicitud finalizada exitosamente']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>