<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

session_start();
$usuario_id = $_SESSION['usuario_id'];

$input = json_decode(file_get_contents('php://input'), true);
$movimiento_id = $input['movimiento_id'] ?? 0;

try {
    // Verificar que el movimiento existe y está agendado
    $movimiento = $db->fetchOne(
        "SELECT id, estado FROM mtto_equipos_movimientos WHERE id = ?",
        [$movimiento_id]
    );
    
    if (!$movimiento) {
        echo json_encode(['success' => false, 'message' => 'Movimiento no encontrado']);
        exit;
    }
    
    if ($movimiento['estado'] === 'finalizado') {
        echo json_encode(['success' => false, 'message' => 'El movimiento ya está finalizado']);
        exit;
    }
    
    // Actualizar movimiento
    $db->query(
        "UPDATE mtto_equipos_movimientos 
         SET estado = 'finalizado',
             fecha_realizada = NOW(),
             finalizado_por = ?
         WHERE id = ?",
        [$usuario_id, $movimiento_id]
    );
    
    echo json_encode(['success' => true, 'message' => 'Movimiento finalizado exitosamente']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>