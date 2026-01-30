<?php
require_once '../../includes/auth.php';
require_once '../../includes/conexion.php';

header('Content-Type: application/json');

if (!isset($_POST['producto_id'])) {
    echo json_encode(['success' => false, 'error' => 'Producto no proporcionado']);
    exit();
}

$producto_id = intval($_POST['producto_id']);

// Obtener el subgrupo y grupo del producto
$stmt = $conn->prepare("SELECT p.id, p.subgrupo_id, s.grupo_id, p.no_endulzantes, s.no_endulzantes as subgrupo_no_endulzantes
                       FROM productos_delivery p
                       JOIN subgrupos_productos s ON p.subgrupo_id = s.id
                       WHERE p.id = ?");
$stmt->execute([$producto_id]);
$producto_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$producto_info) {
    echo json_encode(['success' => false, 'error' => 'Producto no encontrado']);
    exit();
}

// Verificar si el producto o subgrupo no lleva endulzantes
if ($producto_info['no_endulzantes'] || $producto_info['subgrupo_no_endulzantes']) {
    echo json_encode(['success' => true, 'endulzantes' => []]);
    exit();
}

// Buscar endulzantes asignados a este producto, su subgrupo o su grupo
$stmt = $conn->prepare("
    SELECT e.id, e.nombre, e.unidad_medida
    FROM endulzantes_asignaciones ea
    JOIN endulzantes e ON ea.endulzante_id = e.id
    WHERE e.activo = 1 AND (
        (ea.producto_id = :producto_id) OR
        (ea.subgrupo_id = :subgrupo_id AND ea.producto_id IS NULL) OR
        (ea.grupo_id = :grupo_id AND ea.subgrupo_id IS NULL AND ea.producto_id IS NULL)
    )
    ORDER BY e.orden
");

$stmt->execute([
    ':producto_id' => $producto_id,
    ':subgrupo_id' => $producto_info['subgrupo_id'],
    ':grupo_id' => $producto_info['grupo_id']
]);

$endulzantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agregar información específica para cada endulzante
$endulzantes_formateados = array_map(function($endulzante) {
    $opciones = [];
    
    // Definir opciones según el tipo de endulzante
    switch($endulzante['id']) {
        case 5: // Azúcar Normal
        case 8: // Azúcar Alto
        case 9: // Azúcar Bajo
        case 2: // Stevia 1
        case 10: // Stevia 2
        case 11: // Stevia 3
        case 3: // Splenda 1
        case 12: // Splenda 2
        case 13: // Splenda 3
        case 4: // Miel de Abeja
        case 1: // Chocolate Hershey's
        case 6: // Leche condensada
        case 7: // Sin endulzante
        default:
            $opciones = [['valor' => '1', 'texto' => '']];
    }
    
    $endulzante['opciones'] = $opciones;
    return $endulzante;
}, $endulzantes);

echo json_encode(['success' => true, 'endulzantes' => $endulzantes_formateados]);