<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/database/conexion.php';
header('Content-Type: application/json');

try {
    // Verificar autenticación
    if (!isset($_SESSION['usuario_id'])) {
        throw new Exception('No autorizado');
    }
    
    $columna = isset($_POST['columna']) ? $_POST['columna'] : '';
    
    $opciones = [];
    
    if ($columna === 'categoria_nombre') {
        // Obtener categorías
        $sql = "SELECT id as valor, Nombre as texto 
                FROM categoria_producto_maestro 
                ORDER BY Nombre";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $opciones = $stmt->fetchAll();
        
    } elseif ($columna === 'Estado') {
        // Estados fijos
        $opciones = [
            ['valor' => '1', 'texto' => 'Activo'],
            ['valor' => '0', 'texto' => 'Inactivo']
        ];
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