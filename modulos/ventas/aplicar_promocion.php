<?php
require_once '../../includes/auth.php';
require_once '../../includes/conexion.php';

header('Content-Type: application/json');

$promocion_id = intval($_POST['promocion_id']);
$producto_id = intval($_POST['producto_id']);
$venta_id = intval($_POST['venta_id'] ?? 0);

// Obtener información de la promoción
$stmt = $conn->prepare("
    SELECT p.*, t.nombre as tipo_nombre
    FROM promociones p
    JOIN promociones_tipos t ON p.tipo_id = t.id
    WHERE p.id = ?
");
$stmt->execute([$promocion_id]);
$promocion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$promocion) {
    echo json_encode(['success' => false, 'error' => 'Promoción no encontrada']);
    exit();
}

// Obtener precio original del producto
$stmt = $conn->prepare("
    SELECT precio_16oz, precio_20oz, precio_fijo 
    FROM productos_delivery 
    WHERE id = ?
");
$stmt->execute([$producto_id]);
$producto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$producto) {
    echo json_encode(['success' => false, 'error' => 'Producto no encontrado']);
    exit();
}

// Calcular nuevo precio según tipo de promoción
$nuevo_precio = 0;
$tiene_tamanos = ($producto['precio_16oz'] !== null && $producto['precio_20oz'] !== null);

if ($tiene_tamanos) {
    $precio_base = $producto['precio_20oz']; // Por defecto usamos 20oz
} else {
    $precio_base = $producto['precio_fijo'];
}

switch ($promocion['tipo_id']) {
    case 1: // Porcentual
        $nuevo_precio = $precio_base * $promocion['valor'];
        break;
    case 2: // Fijo
        $nuevo_precio = $precio_base - $promocion['valor'];
        break;
    case 3: // Gratis
        $nuevo_precio = 0;
        break;
    case 4: // Combo
        // Para combos, usamos el valor fijo de la promoción
        $nuevo_precio = $promocion['valor'];
        break;
    default:
        $nuevo_precio = $precio_base;
}

// Asegurarnos que el precio no sea negativo
$nuevo_precio = max(0, $nuevo_precio);

echo json_encode([
    'success' => true,
    'nuevo_precio' => $nuevo_precio,
    'promocion' => $promocion['nombre'],
    'precio_base' => $precio_base  // Devuelve también el precio base para referencia
]);