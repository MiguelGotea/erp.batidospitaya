<?php
//clientes_get_opciones_filtro.php
require_once '../../../includes/conexion.php';

header('Content-Type: application/json');

try {
    $columna = isset($_POST['columna']) ? $_POST['columna'] : '';
    
    if (empty($columna)) {
        throw new Exception('Columna no especificada');
    }
    
    // Solo permitir sucursal como filtro de lista
    if ($columna === 'nombre_sucursal') {
        $sql = "SELECT DISTINCT nombre_sucursal as valor, nombre_sucursal as texto 
                FROM clientesclub 
                WHERE nombre_sucursal IS NOT NULL AND nombre_sucursal != ''
                ORDER BY nombre_sucursal";
        
        $stmt = $conn->query($sql);
        $opciones = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'opciones' => $opciones
        ]);
    } else {
        throw new Exception('Columna no válida para filtro de lista');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>