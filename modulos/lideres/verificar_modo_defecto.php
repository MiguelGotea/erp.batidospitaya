<?php
require_once '../../includes/auth.php';
header('Content-Type: application/json');

verificarAutenticacion();

$sucursal = $_GET['sucursal'] ?? null;
$semanaNumero = $_GET['semana'] ?? null;

if (!$sucursal || !$semanaNumero) {
    echo json_encode(['mostrando_por_defecto' => false]);
    exit;
}

try {
    $semana = obtenerSemanaPorNumero($semanaNumero);
    if (!$semana) {
        echo json_encode(['mostrando_por_defecto' => false]);
        exit;
    }
    
    // Verificar si hay operarios con horario guardado
    $operariosConHorario = obtenerOperariosSucursalConHorario($sucursal, $semana['id']);
    
    // Si NO hay operarios con horario, estamos en modo por defecto
    $mostrandoPorDefecto = empty($operariosConHorario);
    
    echo json_encode([
        'mostrando_por_defecto' => $mostrandoPorDefecto,
        'total_con_horario' => count($operariosConHorario)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['mostrando_por_defecto' => false]);
}