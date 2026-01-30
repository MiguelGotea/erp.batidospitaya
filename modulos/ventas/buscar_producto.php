<?php
require_once '../../includes/auth.php';
require_once '../../includes/conexion.php';

header('Content-Type: application/json');

try {
    if (!isset($_POST['id'])) {
        throw new Exception('ID de producto no proporcionado');
    }

    $producto_id = intval($_POST['id']);
    
    if($producto_id <= 0) {
        throw new Exception('ID de producto inválido');
    }

    $stmt = $conn->prepare("SELECT p.id, p.nombre, p.nombre_factura, p.tiene_tamanos, 
                           p.precio_16oz, p.precio_20oz, p.precio_fijo,
                           s.precio_16oz as precio_subgrupo_16oz,
                           s.precio_20oz as precio_subgrupo_20oz,
                           s.precio_normal as precio_subgrupo_normal
                           FROM productos_delivery p
                           JOIN subgrupos_productos s ON p.subgrupo_id = s.id
                           WHERE p.id = ? AND p.activo = 1");
    
    if(!$stmt->execute([$producto_id])) {
        throw new Exception('Error al ejecutar la consulta');
    }
    
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$producto) {
        throw new Exception('Producto no encontrado o inactivo');
    }

    // Usar precios del producto o del subgrupo si no están definidos
    $producto['precio_16oz'] = $producto['precio_16oz'] ?? $producto['precio_subgrupo_16oz'];
    $producto['precio_20oz'] = $producto['precio_20oz'] ?? $producto['precio_subgrupo_20oz'];
    $producto['precio_fijo'] = $producto['precio_fijo'] ?? $producto['precio_subgrupo_normal'];
    
    // Eliminar campos no necesarios
    unset($producto['precio_subgrupo_16oz'], $producto['precio_subgrupo_20oz'], $producto['precio_subgrupo_normal']);
    
    // Convertir valores
    $response = [
        'success' => true, 
        'producto' => [
            'id' => intval($producto['id']),
            'nombre_factura' => $producto['nombre_factura'],
            'tiene_tamanos' => (bool)$producto['tiene_tamanos'],
            'precio_16oz' => $producto['precio_16oz'] ? (float)$producto['precio_16oz'] : null,
            'precio_20oz' => $producto['precio_20oz'] ? (float)$producto['precio_20oz'] : null,
            'precio_fijo' => $producto['precio_fijo'] ? (float)$producto['precio_fijo'] : null
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}
?>