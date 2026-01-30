<?php
require_once '../../includes/auth.php';
require_once '../../includes/conexion.php';

header('Content-Type: application/json');

if (!isset($_POST['producto_id'])) {
    echo json_encode(['success' => false, 'error' => 'Producto no proporcionado']);
    exit();
}

$producto_id = intval($_POST['producto_id']);

// Obtener información del producto
$stmt = $conn->prepare("SELECT p.id, p.subgrupo_id, s.grupo_id 
                       FROM productos_delivery p
                       JOIN subgrupos_productos s ON p.subgrupo_id = s.id
                       WHERE p.id = ?");
$stmt->execute([$producto_id]);
$producto_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$producto_info) {
    echo json_encode(['success' => false, 'error' => 'Producto no encontrado']);
    exit();
}

// Buscar promociones aplicables
$query = "
    SELECT pr.id, pr.nombre, pr.codigo, 
           CASE 
               WHEN pr.tipo_id = 1 THEN CONCAT(ROUND((1 - pr.valor) * 100), '% OFF')
               WHEN pr.tipo_id = 2 THEN CONCAT('C$', pr.valor, ' OFF')
               WHEN pr.tipo_id = 3 THEN 'GRATIS'
               WHEN pr.tipo_id = 4 THEN 'COMBO'
           END as descuento,
           pr.id as promocion_id
    FROM promociones pr
    LEFT JOIN promociones_productos pp ON pr.id = pp.promocion_id
    LEFT JOIN promociones_requisitos prq ON pr.id = prq.promocion_id
    WHERE pr.activo = 1 AND (
        pp.producto_id = :producto_id OR
        pp.subgrupo_id = :subgrupo_id OR
        pp.grupo_id = :grupo_id OR
        pr.grupo_id = :grupo_general
    )
    GROUP BY pr.id
    ORDER BY pr.nombre
";

$stmt = $conn->prepare($query);
$stmt->execute([
    ':producto_id' => $producto_id,
    ':subgrupo_id' => $producto_info['subgrupo_id'],
    ':grupo_id' => $producto_info['grupo_id'],
    ':grupo_general' => 4 // ID del grupo "Todos"
]);

$promociones = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'promociones' => $promociones]);