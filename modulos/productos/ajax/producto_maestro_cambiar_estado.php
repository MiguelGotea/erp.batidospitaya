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
        throw new Exception('No tiene permisos para realizar esta acción');
    }
    
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $estado = isset($_POST['estado']) ? (int)$_POST['estado'] : 0;
    $usuario_id = $_SESSION['usuario_id'];
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }
    
    // Verificar que el producto existe
    $sqlCheck = "SELECT COUNT(*) as total FROM producto_maestro WHERE id = :id";
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->bindValue(':id', $id, PDO::PARAM_INT);
    $stmtCheck->execute();
    
    if ($stmtCheck->fetch()['total'] == 0) {
        throw new Exception('Producto no encontrado');
    }
    
    // Actualizar estado
    $sql = "UPDATE producto_maestro 
            SET Estado = :estado,
                usuario_modificacion = :usuario_modificacion
            WHERE id = :id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':estado', $estado, PDO::PARAM_INT);
    $stmt->bindValue(':usuario_modificacion', $usuario_id, PDO::PARAM_INT);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $mensaje = $estado == 1 ? 'Producto activado exitosamente' : 'Producto desactivado exitosamente';
    
    echo json_encode([
        'success' => true,
        'message' => $mensaje
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>