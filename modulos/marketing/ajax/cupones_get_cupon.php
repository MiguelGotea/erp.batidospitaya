<?php
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }
    
    $sql = "SELECT id, numero_cupon, monto, fecha_caducidad, observaciones, aplicado 
            FROM cupones_sucursales 
            WHERE id = :id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $cupon = $stmt->fetch();
    
    if (!$cupon) {
        throw new Exception('Cupón no encontrado');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $cupon
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>