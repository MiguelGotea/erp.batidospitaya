<?php
require_once '../../../core/database/conexion.php';
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');

try {
    // Verificar sesión
    verificarAutenticacion();
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];
    
    // Verificar permiso de desactivar
    if (!tienePermiso('producto_presentacion', 'desactivar', $cargoOperario)) {
        throw new Exception('No tiene permisos para cambiar el estado del producto');
    }
    
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $estado = isset($_POST['estado']) ? $_POST['estado'] : '';
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }
    
    if (!in_array($estado, ['SI', 'NO'])) {
        throw new Exception('Estado inválido');
    }
    
    // Actualizar estado
    $sql = "UPDATE producto_presentacion 
            SET Activo = :estado,
                fecha_modificacion = NOW(),
                usuario_modificacion = :usuario_modificacion
            WHERE id = :id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':estado', $estado);
    $stmt->bindValue(':usuario_modificacion', $usuario['CodOperario'], PDO::PARAM_INT);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'Estado actualizado correctamente'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>