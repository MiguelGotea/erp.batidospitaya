<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if ($id <= 0) {
        throw new Exception('ID inválido');
    }

    // Datos del producto
    $sqlProducto = "SELECT pp.*, 
                           gp.id as id_grupo
                    FROM producto_presentacion pp
                    LEFT JOIN subgrupo_presentacion_producto sp ON pp.id_subgrupo_presentacion_producto = sp.id
                    LEFT JOIN grupo_presentacion_producto gp ON sp.id_grupo_presentacion_producto = gp.id
                    WHERE pp.id = :id";

    $stmt = $conn->prepare($sqlProducto);
    $stmt->execute([':id' => $id]);
    $producto = $stmt->fetch();

    if (!$producto) {
        throw new Exception('Producto no encontrado');
    }

    // Receta (si existe) - Buscar por id_presentacion_producto para evitar problemas de case-sensitivity o vínculos rotos
    $sqlReceta = "SELECT * FROM receta_producto_global WHERE id_presentacion_producto = :id_producto";
    $stmtReceta = $conn->prepare($sqlReceta);
    $stmtReceta->execute([':id_producto' => $id]);
    $receta = $stmtReceta->fetch();

    // Componentes de la receta
    $componentes = [];
    if ($receta) {
        $sqlComp = "SELECT c.*, 
                           pp.Nombre as nombre_producto,
                           up.nombre as unidad
                    FROM componentes_receta_producto c
                    INNER JOIN producto_presentacion pp ON c.id_presentacion_producto = pp.id
                    LEFT JOIN unidad_producto up ON pp.id_unidad_producto = up.id
                    WHERE c.id_receta_producto_global = :id_receta
                    ORDER BY c.orden, c.id";

        $stmtComp = $conn->prepare($sqlComp);
        $stmtComp->execute([':id_receta' => $receta['id']]);
        $componentes = $stmtComp->fetchAll();
    }

    // Variaciones
    $sqlVar = "SELECT * FROM variedad_producto_presentacion 
               WHERE id_presentacion_producto = :id 
               ORDER BY fecha_creacion DESC";
    $stmtVar = $conn->prepare($sqlVar);
    $stmtVar->execute([':id' => $id]);
    $variaciones = $stmtVar->fetchAll();

    // Fotos
    $sqlFotos = "SELECT * FROM fotos_presentacion_producto 
                 WHERE id_presentacion_producto = :id 
                 ORDER BY fecha_creacion DESC";
    $stmtFotos = $conn->prepare($sqlFotos);
    $stmtFotos->execute([':id' => $id]);
    $fotos = $stmtFotos->fetchAll();

    // Archivos
    $sqlArchivos = "SELECT * FROM archivos_presentacion_producto 
                    WHERE id_presentacion_producto = :id 
                    ORDER BY fecha_creacion DESC";
    $stmtArchivos = $conn->prepare($sqlArchivos);
    $stmtArchivos->execute([':id' => $id]);
    $archivos = $stmtArchivos->fetchAll();

    // Ficha técnica
    $sqlFicha = "SELECT * FROM fichatecnica_presentacion_producto 
                 WHERE id_presentacion_producto = :id 
                 ORDER BY campo";
    $stmtFicha = $conn->prepare($sqlFicha);
    $stmtFicha->execute([':id' => $id]);
    $fichaTecnica = $stmtFicha->fetchAll();

    echo json_encode([
        'success' => true,
        'producto' => $producto,
        'receta' => $receta,
        'componentes' => $componentes,
        'variaciones' => $variaciones,
        'fotos' => $fotos,
        'archivos' => $archivos,
        'ficha_tecnica' => $fichaTecnica
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>