<?php
require_once '../../../core/auth/auth.php';
header('Content-Type: application/json');

try {
    $codOperario = $_GET['cod_operario'] ?? null;
    $codSucursal = $_GET['cod_sucursal'] ?? null;
    $fecha = $_GET['fecha'] ?? null;
    
    if (!$codOperario || !$codSucursal || !$fecha) {
        throw new Exception('Parámetros incompletos');
    }
    
    // Obtener horario programado
    $horarioProgramado = obtenerHorarioProgramado($codOperario, $codSucursal, $fecha);
    
    // Obtener marcaciones
    $marcaciones = obtenerMarcaciones($codOperario, $codSucursal, $fecha);
    
    echo json_encode([
        'horario_programado' => $horarioProgramado,
        'marcaciones' => $marcaciones
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
