<?php
/**
 * AJAX: Eliminar plantilla
 */

header('Content-Type: application/json');

require_once('../../../core/auth/auth.php');
require_once('../../../core/database/conexion.php');
require_once('../../../core/permissions/permissions.php');

try {
    $codNivelCargo = $_SESSION['cargo_cod'];

    if (!tienePermiso('whatsapp_campanas', 'eliminar', $codNivelCargo)) {
        throw new Exception('No tienes permiso para eliminar plantillas');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;

    if (!$id) {
        throw new Exception('ID requerido');
    }

    // Verificar que no esté en uso en campañas activas
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM whatsapp_campanas 
        WHERE plantilla_id = ? AND estado IN ('programada', 'en_proceso')
    ");
    $stmt->execute([$id]);

    if ($stmt->fetchColumn() > 0) {
        throw new Exception('No se puede eliminar: la plantilla está en uso en campañas activas');
    }

    $stmt = $conn->prepare("DELETE FROM whatsapp_plantillas WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode([
        'success' => true,
        'mensaje' => 'Plantilla eliminada'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}