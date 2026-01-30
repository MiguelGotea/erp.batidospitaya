<?php
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $columna = isset($_POST['columna']) ? $_POST['columna'] : '';
    
    $opciones = [];
    
    if ($columna === 'aplicado') {
        $opciones = [
            ['valor' => '0', 'texto' => 'Disponible'],
            ['valor' => '1', 'texto' => 'Aplicado']
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