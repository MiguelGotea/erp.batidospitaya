<?php
header('Content-Type: application/json');
require_once '../models/Chat.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    if (!isset($_POST['message_id'])) {
        throw new Exception('ID de mensaje requerido');
    }
    
    $message_id = intval($_POST['message_id']);
    
    $chat = new Chat();
    $chat->unpinMessage($message_id);
    
    echo json_encode(['success' => true, 'message' => 'Mensaje desfijado exitosamente']);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>