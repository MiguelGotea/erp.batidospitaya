<?php
/**
 * ajax/crm_bot_get_mensajes.php
 * Proxy ERP → API para obtener mensajes de una conversación
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('crm_bot', 'vista', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sin permiso']);
    exit;
}

$convId = (int) ($_GET['conversation_id'] ?? 0);
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = (int) ($_GET['per_page'] ?? 50);

if (!$convId) {
    echo json_encode(['success' => false, 'error' => 'conversation_id requerido']);
    exit;
}

$token = 'c5b155ba8f6877a2eefca0183ab18e37fe9a6accde340cf5c88af724822cbf50';
$params = http_build_query(['conversation_id' => $convId, 'page' => $page, 'per_page' => $per_page]);
$ctx = stream_context_create(['http' => ['header' => "X-WSP-Token: {$token}\r\n", 'timeout' => 10]]);

$raw = @file_get_contents("https://api.batidospitaya.com/api/crm/get_mensajes.php?{$params}", false, $ctx);
echo $raw ?: json_encode(['success' => false, 'error' => 'No se pudo conectar a la API']);
