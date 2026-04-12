<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $usuarioId = $usuario['CodOperario'];
    
    $idProducto = (int)$_POST['id_producto'];
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    
    // Validaciones
    if ($idProducto <= 0) {
        throw new Exception('ID de producto inválido');
    }
    
    if (empty($nombre)) {
        throw new Exception('El nombre es obligatorio');
    }
    
    $sql = "INSERT INTO variedad_producto_presentacion 
            (id_presentacion_producto, nombre, descripcion, usuario_creacion, fecha_creacion)
            VALUES (:id_producto, :nombre, :descripcion, :usuario, NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':id_producto' => $idProducto,
        ':nombre' => $nombre,
        ':descripcion' => $descripcion,
        ':usuario' => $usuarioId
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Variación agregada exitosamente'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>