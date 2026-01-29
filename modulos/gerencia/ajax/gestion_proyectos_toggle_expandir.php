<?php
// gestion_proyectos_toggle_expandir.php
// Cambia el estado de expansión de un proyecto padre

header('Content-Type: application/json; charset=utf-8');
require_once '../../../core/auth/auth.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar permiso (vista es suficiente para esto ya que es visual)
if (!tienePermiso('gestion_proyectos', 'vista', $cargoOperario)) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;
$expandido = $data['expandido'] ?? 1;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID requerido']);
    exit();
}

try {
    $sql = "UPDATE gestion_proyectos_proyectos SET esta_expandido = :expandido WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':expandido' => $expandido,
        ':id' => $id
    ]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>