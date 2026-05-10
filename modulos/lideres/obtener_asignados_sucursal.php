<?php
require_once '../../core/auth/auth.php';
header('Content-Type: application/json');


$sucursal = $_GET['sucursal'] ?? null;
$semanaNumero = $_GET['semana'] ?? null;

if (!$sucursal) {
    echo json_encode([]);
    exit;
}

try {
    $semana = obtenerSemanaPorNumero($semanaNumero);
    if (!$semana) {
        echo json_encode([]);
        exit;
    }

    // Usar la función estándar para mayor consistencia
    $operarios = obtenerOperariosSucursal($sucursal, $semana['fecha_inicio'], $semana['fecha_fin']);
    
    $colaboradores = [];
    foreach ($operarios as $op) {
        $colaboradores[] = [
            'id' => $op['CodOperario'],
            'nombre' => trim($op['Nombre'] . ' ' . ($op['Nombre2'] ?? '') . ' ' . $op['Apellido'] . ' ' . ($op['Apellido2'] ?? '')),
            'codigo' => $op['CodOperario']
        ];
    }
    
    echo json_encode($colaboradores);
    
} catch (Exception $e) {
    echo json_encode([]);
}