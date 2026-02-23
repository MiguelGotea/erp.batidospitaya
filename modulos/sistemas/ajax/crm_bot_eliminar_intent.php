<?php
/**
 * ajax/crm_bot_eliminar_intent.php
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json; charset=utf-8');
$usuario = obtenerUsuarioActual();
if (!tienePermiso('crm_bot', 'gestionar_intents', $usuario['CodNivelesCargos'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sin permiso']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$id = (int) ($body['id'] ?? 0);
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'id requerido']);
    exit;
}

try {
    $conn->prepare("DELETE FROM intent_embeddings WHERE intent_id = :id")->execute([':id' => $id]);
    $conn->prepare("DELETE FROM bot_intents WHERE id = :id")->execute([':id' => $id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
