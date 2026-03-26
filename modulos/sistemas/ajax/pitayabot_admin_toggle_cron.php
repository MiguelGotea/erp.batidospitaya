<?php
/**
 * pitayabot_admin_toggle_cron.php — Activa/desactiva un cron.
 * POST: { id: int, activo: 0|1 }
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json; charset=utf-8');

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('pitayabot', 'resetear_sesion', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permiso']);
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true);
$id     = (int)($body['id'] ?? 0);
$activo = isset($body['activo']) ? (int)(bool)$body['activo'] : -1;

if (!$id || $activo === -1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Faltan parámetros: id, activo']);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE bot_crons_config SET activo = ? WHERE id = ?");
    $stmt->execute([$activo, $id]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Cron no encontrado']);
        exit;
    }

    echo json_encode(['success' => true, 'activo' => $activo]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
