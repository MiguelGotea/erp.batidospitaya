<?php
require_once '../../../core/auth/auth.php';

$id = $_POST['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
    exit;
}

$sql = "DELETE FROM mtto_tickets_colaboradores WHERE id = ?";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Error eliminando colaborador: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al eliminar']);
}
?>