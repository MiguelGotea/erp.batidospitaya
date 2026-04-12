<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $usuarioId = $usuario['CodOperario'];
    
    $idProducto = (int)$_POST['id_producto'];
    $idPresentacionProducto = (int)$_POST['id_presentacion_producto'];
    $cantidad = (float)$_POST['cantidad'];
    $notas = trim($_POST['notas']);
    
    // Validaciones
    if ($idProducto <= 0) {
        throw new Exception('ID de producto inválido');
    }
    
    if ($idPresentacionProducto <= 0) {
        throw new Exception('Debe seleccionar un producto componente');
    }
    
    if ($cantidad <= 0) {
        throw new Exception('La cantidad debe ser mayor a 0');
    }
    
    // Validar que el producto no sea componente de sí mismo
    if ($idPresentacionProducto === $idProducto) {
        throw new Exception('Un producto no puede ser componente de sí mismo');
    }
    
    // Obtener el ID de la receta del producto
    $sqlReceta = "SELECT id_receta_producto FROM producto_presentacion WHERE id = :id";
    $stmtReceta = $conn->prepare($sqlReceta);
    $stmtReceta->execute([':id' => $idProducto]);
    $idReceta = $stmtReceta->fetchColumn();
    
    if (!$idReceta) {
        throw new Exception('El producto no tiene una receta asociada');
    }
    
    // Obtener el siguiente orden
    $sqlOrden = "SELECT COALESCE(MAX(orden), 0) + 1 as siguiente_orden 
                 FROM componentes_receta_producto 
                 WHERE id_receta_producto_global = :id_receta";
    $stmtOrden = $conn->prepare($sqlOrden);
    $stmtOrden->execute([':id_receta' => $idReceta]);
    $orden = $stmtOrden->fetchColumn();
    
    // Insertar componente
    $sql = "INSERT INTO componentes_receta_producto 
            (id_receta_producto_global, id_presentacion_producto, cantidad, notas, orden, usuario_creacion, fecha_creacion)
            VALUES (:id_receta, :id_producto, :cantidad, :notas, :orden, :usuario, NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':id_receta' => $idReceta,
        ':id_producto' => $idPresentacionProducto,
        ':cantidad' => $cantidad,
        ':notas' => $notas,
        ':orden' => $orden,
        ':usuario' => $usuarioId
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Componente agregado exitosamente'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>