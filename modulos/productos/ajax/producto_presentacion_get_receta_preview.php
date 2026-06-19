<?php
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }

    $sqlProducto = "SELECT Id_receta_producto FROM producto_presentacion WHERE id = :id";
    $stmt = $conn->prepare($sqlProducto);
    $stmt->execute([':id' => $id]);
    $producto = $stmt->fetch();

    if (!$producto) {
        throw new Exception('Producto no encontrado');
    }

    $idRecetaProducto = $producto['Id_receta_producto'];

    if (!$idRecetaProducto) {
        $sqlReceta = "SELECT id FROM receta_producto_global WHERE id_presentacion_producto = :id_producto LIMIT 1";
        $stmtReceta = $conn->prepare($sqlReceta);
        $stmtReceta->execute([':id_producto' => $id]);
        $receta = $stmtReceta->fetch(PDO::FETCH_ASSOC);
        if ($receta) {
            $idRecetaProducto = $receta['id'];
        }
    }

    $componentes = [];
    if ($idRecetaProducto) {
        $sqlComp = "SELECT c.cantidad, c.notas, 
                           pp.Nombre as nombre_producto,
                           up.nombre as unidad
                    FROM componentes_receta_producto c
                    INNER JOIN producto_presentacion pp ON c.id_presentacion_producto = pp.id
                    LEFT JOIN unidad_producto up ON pp.id_unidad_producto = up.id
                    WHERE c.id_receta_producto_global = :id_receta
                    ORDER BY c.orden ASC";

        $stmtComp = $conn->prepare($sqlComp);
        $stmtComp->execute([':id_receta' => $idRecetaProducto]);
        $componentes = $stmtComp->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success' => true,
        'componentes' => $componentes
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
