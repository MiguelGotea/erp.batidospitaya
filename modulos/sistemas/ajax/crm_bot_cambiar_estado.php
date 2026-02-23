<?php
/**
 * ajax/crm_bot_cambiar_estado.php
 * Cambia status de una conversación directamente en BD
 * POST — body: { conversation_id, nuevo_status }
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json; charset=utf-8');
$usuario = obtenerUsuarioActual();
if (!tienePermiso('crm_bot', 'cambiar_estado', $usuario['CodNivelesCargos'])) {
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

try {
    $stmt = $conn->prepare("
        UPDATE conversations
        SET status = :s, updated_at = CONVERT_TZ(NOW(),'+00:00','-06:00')
        WHERE id = :id
    ");
    $stmt->execute([':s' => $nuevo, ':id' => $convId]);
    echo json_encode(['success' => true, 'nuevo_status' => $nuevo]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
