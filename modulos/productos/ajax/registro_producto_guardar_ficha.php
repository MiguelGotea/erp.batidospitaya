<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $usuarioId = $usuario['CodOperario'];
    
    $idProducto = (int)$_POST['id_producto'];
    $campo = trim($_POST['campo']);
    $descripcion = trim($_POST['descripcion']);
    
    // Validaciones
    if ($idProducto <= 0) {
        throw new Exception('ID de producto inválido');
    }
    
    if (empty($campo)) {
        throw new Exception('El campo es obligatorio');
    }
    
    if (empty($descripcion)) {
        throw new Exception('El valor es obligatorio');
    }
    
    $sql = "INSERT INTO fichatecnica_presentacion_producto 
            (id_presentacion_producto, campo, descripcion, usuario_creacion, fecha_creacion)
            VALUES (:id_producto, :campo, :descripcion, :usuario, NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':id_producto' => $idProducto,
        ':campo' => $campo,
        ':descripcion' => $descripcion,
        ':usuario' => $usuarioId
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Campo agregado exitosamente'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>