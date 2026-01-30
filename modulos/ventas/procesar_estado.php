<?php
require_once '../../includes/auth.php';
require_once '../../includes/conexion.php';
require_once '../../includes/funciones.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

$pedido_id = intval($_POST['id'] ?? 0);
$estado = $_POST['estado'] ?? '';
$usuario_id = $_SESSION['usuario_id'];

if (!in_array($estado, ['pendiente', 'completado', 'cancelado'])) {
    echo json_encode(['success' => false, 'error' => 'Estado no válido']);
    exit();
}

try {
    $stmt = $conn->prepare("UPDATE ventas SET estado = ?, usuario_id = ? WHERE id = ?");
    $stmt->execute([$estado, $usuario_id, $pedido_id]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error en la base de datos: ' . $e->getMessage()]);
}
?>