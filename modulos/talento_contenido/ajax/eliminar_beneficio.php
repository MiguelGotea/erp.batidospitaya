<?php
// eliminar_beneficio.php
header('Content-Type: application/json; charset=utf-8');
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
if (!tienePermiso('talento_contenido', 'eliminar', $usuario['CodNivelesCargos'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit();
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if (empty($id)) {
    http_response_code(400);
    echo json_encode(['error' => 'ID no válido']);
    exit();
}

try {
    $stmt = $conn->prepare("DELETE FROM talento_beneficios WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true, 'mensaje' => 'Beneficio eliminado con éxito']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
