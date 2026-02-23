<?php
/**
 * ajax/crm_bot_cambiar_estado.php
 * Cambia estado de conversación bot ↔ humano
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');
$usuario = obtenerUsuarioActual();
$cargo = $usuario['CodNivelesCargos'];

if (!tienePermiso('crm_bot', 'cambiar_estado', $cargo)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sin permiso']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$convId = (int) ($body['conversation_id'] ?? 0);
$nuevo = $body['nuevo_status'] ?? '';

if (!$convId || !in_array($nuevo, ['bot', 'humano'])) {
    echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

$token = 'c5b155ba8f6877a2eefca0183ab18e37fe9a6accde340cf5c88af724822cbf50';
$ctx = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "X-WSP-Token: {$token}\r\nContent-Type: application/json\r\n",
        'content' => json_encode(['conversation_id' => $convId, 'nuevo_status' => $nuevo]),
        'timeout' => 10
    ]
]);

$raw = @file_get_contents('https://api.batidospitaya.com/api/crm/cambiar_estado.php', false, $ctx);
echo $raw ?: json_encode(['success' => false, 'error' => 'No se pudo conectar a la API']);
