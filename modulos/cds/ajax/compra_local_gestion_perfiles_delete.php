<?php
// ajax/compra_local_gestion_perfiles_delete.php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $id = $_POST['id'] ?? null;

    if (!$id) {
        throw new Exception("ID no proporcionado");
    }

    // Verificar si hay productos asociados antes de eliminar
    $check = $conn->prepare("SELECT COUNT(*) FROM compra_local_configuracion_despacho WHERE id_perfil = ?");
    $check->execute([$id]);
    if ($check->fetchColumn() > 0) {
        throw new Exception("No se puede eliminar el perfil porque tiene productos asociados.");
    }

    $stmt = $conn->prepare("DELETE FROM compra_local_perfiles_despacho WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
