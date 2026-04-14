<?php
// ajax/historial_actualizar_resolucion.php
require_once __DIR__ . '/../config/database.php';
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Usar el nuevo permiso editar_resolucion
if (!tienePermiso('historial_solicitudes_mantenimiento', 'editar_resolucion', $cargoOperario)) {
    echo json_encode(['success' => false, 'message' => 'No tiene permiso para realizar esta acción'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
$resolucion = isset($_POST['resolucion']) ? trim($_POST['resolucion']) : '';

if ($ticket_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de ticket inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $sql = "UPDATE mtto_tickets SET resolucion = ? WHERE id = ?";
    $db->query($sql, [$resolucion, $ticket_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Resolución actualizada correctamente'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar resolución: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
