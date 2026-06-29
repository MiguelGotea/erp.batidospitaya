<?php
// eliminar_area.php — Eliminar un área de la empresa
header('Content-Type: application/json; charset=utf-8');
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('talento_contenido', 'eliminar', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit();
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID inválido']);
    exit();
}

try {
    $stmt = $conn->prepare("DELETE FROM talento_areas_equipo WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true, 'mensaje' => 'Área eliminada con éxito']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
