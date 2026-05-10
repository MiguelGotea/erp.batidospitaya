<?php
require_once '../../core/auth/auth.php';
header('Content-Type: application/json');


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$semanaNumero = $_POST['semana'] ?? null;
$sucursal = $_POST['sucursal'] ?? null;

if (!$semanaNumero || !$sucursal) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

try {
    $semana = obtenerSemanaPorNumero($semanaNumero);
    if (!$semana) {
        echo json_encode(['success' => false, 'message' => 'Semana no válida']);
        exit;
    }
    
    // Limpiar la selección de colaboradores para esta semana/sucursal
    if (isset($_SESSION['operarios_seleccionados'][$semana['id']][$sucursal])) {
        unset($_SESSION['operarios_seleccionados'][$semana['id']][$sucursal]);
    }
    
    // También limpiar la lista de operarios a eliminar
    if (isset($_SESSION['operarios_a_eliminar'][$semana['id']][$sucursal])) {
        unset($_SESSION['operarios_a_eliminar'][$semana['id']][$sucursal]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Selección de colaboradores limpiada']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}