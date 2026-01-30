<?php
require_once '../../includes/auth.php';
require_once '../../includes/conexion.php';

header('Content-Type: application/json');

if (!isset($_POST['producto_id'])) {
    echo json_encode(['success' => false, 'error' => 'Producto no proporcionado']);
    exit();
}

$producto_id = intval($_POST['producto_id']);

// Obtener el subgrupo del producto
$stmt = $conn->prepare("SELECT subgrupo_id FROM productos_delivery WHERE id = ?");
$stmt->execute([$producto_id]);
$subgrupo_id = $stmt->fetchColumn();

// Buscar extras disponibles para este producto o su subgrupo
$stmt = $conn->prepare("
    SELECT e.id, e.nombre, e.precio 
    FROM extras e
    WHERE e.activo = 1
    ORDER BY e.nombre
");

$stmt->execute([
    ':producto_id' => $producto_id,
    ':subgrupo_id' => $subgrupo_id
]);

$extras = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'extras' => $extras]);