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
$usuario_id = $_SESSION['usuario_id'];
$estado = $_POST['estado'] ?? null;

try {
    // Si se especificó un estado, actualizarlo
    if ($estado && in_array($estado, ['completado', 'enviado_cliente'])) {
        $stmt = $conn->prepare("UPDATE ventas SET estado = ? WHERE id = ?");
        $stmt->execute([$estado, $pedido_id]);
    }
    
    // Registrar la impresión en la base de datos
    $stmt = $conn->prepare("INSERT INTO pedidos_impresiones (venta_id, usuario_id, fecha_hora, tipo_impresion) 
                          VALUES (?, ?, NOW(), 'comanda')");
    $stmt->execute([$pedido_id, $usuario_id]);
    
    // Aquí iría el código para enviar realmente a la impresora
    // Por ahora simulamos que se envió correctamente
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error al registrar impresión: ' . $e->getMessage()]);
}
?>