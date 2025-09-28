<?php
header('Content-Type: application/json');
require_once '../models/Chat.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    if (!isset($_POST['message_id']) || !isset($_POST['ticket_id'])) {
        throw new Exception('Datos requeridos faltantes');
    }
    
    $message_id = intval($_POST['message_id']);
    $ticket_id = intval($_POST['ticket_id']);
    
    $chat = new Chat();
    $chat->pinMessage($message_id, $ticket_id);
    
    echo json_encode(['success' => true, 'message' => 'Mensaje fijado exitosamente']);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>