<?php
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $numero_cupon = isset($_POST['numero_cupon']) ? trim($_POST['numero_cupon']) : '';
    
    if (empty($numero_cupon)) {
        echo json_encode(['existe' => false]);
        exit();
    }
    
    $sql = "SELECT COUNT(*) as total FROM cupones_sucursales WHERE numero_cupon = :numero_cupon";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':numero_cupon', $numero_cupon);
    $stmt->execute();
    
    $resultado = $stmt->fetch();
    $existe = $resultado['total'] > 0;
    
    echo json_encode(['existe' => $existe]);
    
} catch (Exception $e) {
    echo json_encode(['existe' => false]);
}
?>