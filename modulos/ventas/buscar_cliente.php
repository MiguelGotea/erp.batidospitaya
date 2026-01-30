<?php
require_once '../../includes/auth.php';
require_once '../../includes/conexion.php';

header('Content-Type: application/json');

if (!isset($_POST['telefono'])) {
    echo json_encode(['success' => false, 'error' => 'Teléfono no proporcionado']);
    exit();
}

$telefono = trim($_POST['telefono']);

// Validar que el teléfono tenga al menos 4 dígitos
if (strlen($telefono) < 7 || !preg_match('/^[0-9]+$/', $telefono)) {
    echo json_encode(['success' => false, 'clientes' => []]);
    exit();
}

$stmt = $conn->prepare("SELECT id, codigo, nombre, telefono, direccion 
                       FROM clientes 
                       WHERE telefono LIKE ? AND activo = 1 
                       ORDER BY fecha_registro DESC");
$stmt->execute(["%$telefono%"]);
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($clientes) > 0) {
    // Asegurar que los códigos no sean null
    foreach ($clientes as &$cliente) {
        $cliente['codigo'] = !empty($cliente['codigo']) ? $cliente['codigo'] : '0';
    }
    
    // Si solo hay un cliente, lo devolvemos en el campo 'cliente' para compatibilidad
    if (count($clientes) === 1) {
        echo json_encode(['success' => true, 'cliente' => $clientes[0], 'clientes' => $clientes]);
    } else {
        echo json_encode(['success' => true, 'clientes' => $clientes]);
    }
} else {
    echo json_encode(['success' => false, 'clientes' => []]);
}
?>