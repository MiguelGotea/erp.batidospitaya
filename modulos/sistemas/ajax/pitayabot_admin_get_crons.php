<?php
/**
 * pitayabot_admin_get_crons.php — Retorna la lista de crons con su estado.
 * GET — requiere permiso 'pitayabot.ver_estado'
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
header('Content-Type: application/json; charset=utf-8');

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('pitayabot', 'ver_estado', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permiso']);
    exit;
}

try {
    $stmt = $conn->query("
        SELECT id, clave, nombre, descripcion, horario, activo,
               ultima_ejecucion, updated_at
        FROM bot_crons_config
        ORDER BY id ASC
    ");
    $crons = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $crons]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
