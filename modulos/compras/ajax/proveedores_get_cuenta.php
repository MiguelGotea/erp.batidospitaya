<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/database/conexion.php';
header('Content-Type: application/json');

try {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }
    
    $sql = "SELECT * FROM cuenta_proveedor WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
    $cuenta = $stmt->fetch();
    
    if (!$cuenta) {
        throw new Exception('Cuenta no encontrada');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $cuenta
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>