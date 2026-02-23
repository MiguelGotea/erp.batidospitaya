<?php
/**
 * ajax/crm_bot_get_intents.php — Listado de bot_intents
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

try {
    $stmt = $conn->query("SELECT * FROM bot_intents ORDER BY priority DESC, id ASC");
    echo json_encode(['success' => true, 'intents' => $stmt->fetchAll()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
