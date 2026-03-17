<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/database/conexion.php';
header('Content-Type: application/json');

try {
    $columna = isset($_POST['columna']) ? $_POST['columna'] : '';
    
    $opciones = [];
    
    if ($columna === 'vigente') {
        $opciones = [
            ['valor' => '1', 'texto' => 'Vigente'],
            ['valor' => '0', 'texto' => 'No Vigente']
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