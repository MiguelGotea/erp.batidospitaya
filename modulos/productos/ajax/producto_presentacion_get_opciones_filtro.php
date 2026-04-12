<?php
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $columna = isset($_POST['columna']) ? $_POST['columna'] : '';
    
    $opciones = [];
    
    if ($columna === 'unidad_nombre') {
        // Obtener todas las unidades de producto
        $sql = "SELECT DISTINCT nombre as valor, nombre as texto 
                FROM unidad_producto 
                ORDER BY nombre";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $opciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'opciones' => $opciones
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>