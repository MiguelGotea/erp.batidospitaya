<?php
header('Content-Type: application/json');
require_once '../models/Chat.php';

try {
    if (!isset($_GET['ticket_id']) || !isset($_GET['last_count'])) {
        throw new Exception('Parámetros requeridos faltantes');
    }
    
    $ticket_id = intval($_GET['ticket_id']);
    $last_count = intval($_GET['last_count']);
    
    $chat = new Chat();
    $current_messages = $chat->getMessages($ticket_id);
    $current_count = count($current_messages);
    
    echo json_encode([
        'has_new' => $current_count > $last_count,
        'current_count' => $current_count
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['has_new' => false, 'error' => $e->getMessage()]);
}
?>