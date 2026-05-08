<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');

verificarAutenticacion();

$usuario = obtenerUsuarioActual();
$cargoUsuario = $usuario['CodNivelesCargos'];

// Solo quienes tienen permiso de gestionar o admin pueden eliminar (si se habilitara el botón)
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';
if (!tienePermiso('horas_extras_manual', 'gestionar', $cargoUsuario) && !$esAdmin) {
    echo json_encode(['success' => false, 'message' => 'No tiene permisos para eliminar registros.']);
    exit();
}

$id = $_POST['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID no proporcionado.']);
    exit();
}

try {
    $stmt = $conn->prepare("DELETE FROM horas_extras_manual WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
