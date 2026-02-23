<?php
/**
 * ajax/crm_bot_enviar_manual.php
 * Envío manual de mensaje del agente al cliente via WhatsApp
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');
$usuario = obtenerUsuarioActual();
$cargo = $usuario['CodNivelesCargos'];

if (!tienePermiso('crm_bot', 'responder', $cargo)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sin permiso']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$convId = (int) ($body['conversation_id'] ?? 0);
$texto = trim($body['texto'] ?? '');
$agenteId = (int) ($usuario['CodOperario'] ?? 0);

if (!$convId || !$texto) {
    echo json_encode(['success' => false, 'error' => 'conversation_id y texto son requeridos']);
    exit;
}

$token = 'c5b155ba8f6877a2eefca0183ab18e37fe9a6accde340cf5c88af724822cbf50';
$ctx = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "X-WSP-Token: {$token}\r\nContent-Type: application/json\r\n",
        'content' => json_encode(['conversation_id' => $convId, 'texto' => $texto, 'agente_id' => $agenteId]),
        'timeout' => 15
    ]
]);

$raw = @file_get_contents('https://api.batidospitaya.com/api/crm/enviar_manual.php', false, $ctx);
echo $raw ?: json_encode(['success' => false, 'error' => 'No se pudo conectar a la API']);
