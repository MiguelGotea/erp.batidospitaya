<?php
/**
 * campanas_wsp_eliminar.php
 * Elimina una campaña en estado borrador o cancelada
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json; charset=utf-8');

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('campanas_wsp', 'eliminar_campana', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permiso']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$id = (int) ($body['id'] ?? 0);

if (!$id) {
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

try {
    $conn->beginTransaction();

    // 1. Eliminar destinatarios asociados
    $stmtDest = $conn->prepare("DELETE FROM wsp_destinatarios_ WHERE campana_id = :id");
    $stmtDest->execute([':id' => $id]);

    // 2. Eliminar la campaña (solo si está en estado borrador, cancelada, programada o fallida)
    $stmt = $conn->prepare("
        DELETE FROM wsp_campanas_
        WHERE id = :id AND estado IN ('borrador', 'cancelada', 'programada', 'fallida')
    ");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) {
        $conn->rollBack();
        echo json_encode(['error' => 'No se puede eliminar la campaña en su estado actual (o ya fue eliminada)']);
        exit;
    }

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
