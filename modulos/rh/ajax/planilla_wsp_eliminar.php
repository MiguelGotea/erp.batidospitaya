<?php
/**
 * planilla_wsp_eliminar.php
 * Elimina una programación de planilla (solo si está en estado 'programada').
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json; charset=utf-8');

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('envio_wsp_planilla', 'eliminar_programacion', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permiso']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$progId = (int) ($body['prog_id'] ?? 0);

if (!$progId) {
    echo json_encode(['error' => 'prog_id requerido']);
    exit;
}

try {
    // Solo se puede eliminar si está 'programada' (no en proceso de envío)
    $stmtCheck = $conn->prepare("SELECT estado FROM wsp_planilla_programaciones_ WHERE id = :id LIMIT 1");
    $stmtCheck->execute([':id' => $progId]);
    $prog = $stmtCheck->fetch();

    if (!$prog) {
        echo json_encode(['error' => 'Programación no encontrada']);
        exit;
    }
    if ($prog['estado'] !== 'programada') {
        echo json_encode(['error' => 'Solo se pueden eliminar programaciones en estado "programada". Estado actual: ' . $prog['estado']]);
        exit;
    }

    // Eliminar (CASCADE elimina los destinatarios)
    $stmt = $conn->prepare("DELETE FROM wsp_planilla_programaciones_ WHERE id = :id");
    $stmt->execute([':id' => $progId]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
