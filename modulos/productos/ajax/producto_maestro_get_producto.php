<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/database/conexion.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/permissions/permissions.php';
header('Content-Type: application/json');

try {
    // Verificar autenticación
    if (!isset($_SESSION['usuario_id'])) {
        throw new Exception('No autorizado');
    }
    
    // Verificar permisos de edición
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];
    
    if (!tienePermiso('producto_maestro', 'editar', $cargoOperario)) {
        throw new Exception('No tiene permisos para editar productos');
    }
    
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }
    
    $sql = "SELECT id, Nombre, SKU, Descripcion, Id_categoria, Estado 
            FROM producto_maestro 
            WHERE id = :id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $producto = $stmt->fetch();
    
    if (!$producto) {
        throw new Exception('Producto no encontrado');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $producto
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>