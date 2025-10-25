<?php
require_once '../models/Ticket.php';
require_once '../models/Chat.php';

header('Content-Type: application/json');

// Validar parámetros
if (!isset($_GET['ticket_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de ticket requerido']);
    exit;
}

$ticket_id = intval($_GET['ticket_id']);

$ticket_model = new Ticket();
$chat_model = new Chat();

$ticket = $ticket_model->getById($ticket_id);

if (!$ticket) {
    echo json_encode(['success' => false, 'message' => 'Ticket no encontrado']);
    exit;
}

// Obtener mensajes
$mensajes = $chat_model->getMessages($ticket_id);
$mensaje_pinned = $chat_model->getPinnedMessage($ticket_id);

// Formatear mensajes para JSON
$messages_formatted = [];
foreach ($mensajes as $msg) {
    $messages_formatted[] = [
        'id' => $msg['id'],
        'emisor_tipo' => $msg['emisor_tipo'],
        'emisor_nombre' => htmlspecialchars($msg['emisor_nombre']),
        'mensaje' => $msg['mensaje'] ? htmlspecialchars($msg['mensaje']) : null,
        'foto' => $msg['foto'],
        'created_at' => $msg['created_at']
    ];
}

$response = [
    'success' => true,
    'ticket' => [
        'id' => $ticket['id'],
        'codigo' => htmlspecialchars($ticket['codigo']),
        'titulo' => htmlspecialchars($ticket['titulo']),
        'area_equipo' => htmlspecialchars($ticket['area_equipo']),
        'status' => $ticket['status']
    ],
    'messages' => $messages_formatted,
    'pinned_message' => null
];

if ($mensaje_pinned) {
    $response['pinned_message'] = [
        'id' => $mensaje_pinned['id'],
        'emisor_nombre' => htmlspecialchars($mensaje_pinned['emisor_nombre']),
        'mensaje' => $mensaje_pinned['mensaje'] ? htmlspecialchars($mensaje_pinned['mensaje']) : '',
        'foto' => $mensaje_pinned['foto']
    ];
}

echo json_encode($response);
?>