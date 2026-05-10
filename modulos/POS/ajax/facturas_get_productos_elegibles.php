<?php
// facturas_get_productos_elegibles.php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';

    $sql = "SELECT pp.id,
                   pp.SKU,
                   pp.Nombre,
                   pm.Nombre AS nombre_maestro,
                   up.nombre AS nombre_unidad
            FROM producto_presentacion pp
            INNER JOIN producto_maestro pm ON pp.id_producto_maestro = pm.id
            LEFT JOIN unidad_producto up ON pp.id_unidad_producto = up.id
            WHERE pp.compra_tienda = 1
              AND pp.Activo = 'SI'";

    $params = [];
    if ($busqueda !== '') {
        $sql .= " AND (pp.Nombre LIKE :q OR pm.Nombre LIKE :q2 OR pp.SKU LIKE :q3)";
        $params[':q']  = "%$busqueda%";
        $params[':q2'] = "%$busqueda%";
        $params[':q3'] = "%$busqueda%";
    }

    $sql .= " ORDER BY pm.Nombre ASC, pp.Nombre ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $productos
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
