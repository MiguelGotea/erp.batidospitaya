<?php
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }
    
    // Verificar que el cupón existe y no está aplicado
    $sqlCheck = "SELECT aplicado FROM cupones_sucursales WHERE id = :id";
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->bindValue(':id', $id, PDO::PARAM_INT);
    $stmtCheck->execute();
    $cupon = $stmtCheck->fetch();
    
    if (!$cupon) {
        throw new Exception('Cupón no encontrado');
    }
    
    if ($cupon['aplicado'] == 1) {
        throw new Exception('No se puede eliminar un cupón que ya ha sido aplicado');
    }
    
    // Eliminar cupón
    $sql = "DELETE FROM cupones_sucursales WHERE id = :id AND aplicado = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'Cupón eliminado exitosamente'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>