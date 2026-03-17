<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/database/conexion.php';
header('Content-Type: application/json');

try {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }
    
    $sql = "SELECT * FROM contacto_proveedores WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
    $contacto = $stmt->fetch();
    
    if (!$contacto) {
        throw new Exception('Contacto no encontrado');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $contacto
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>