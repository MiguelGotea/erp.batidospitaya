<?php
require_once '../../../core/auth/auth.php';
header('Content-Type: application/json');

try {
    $codSucursal = $_GET['sucursal'] ?? null;
    $fechaFalta = $_GET['fecha_falta'] ?? null;
    
    if (!$codSucursal) {
        throw new Exception('Sucursal no especificada');
    }
    
    // Si NO se proporciona fecha, usar fecha de ayer por defecto
    if (!$fechaFalta) {
        $fechaFalta = date('Y-m-d', strtotime('-1 day'));
    }
    
    // NUEVA LÓGICA: Obtener operarios considerando fecha de liquidación
    // Usar la función que filtra por AsignacionNivelesCargos Y fecha_liquidacion
    $operarios = obtenerOperariosSucursalPorFecha($codSucursal, $fechaFalta);
    
    // Agregar información de contrato para validaciones en frontend
    foreach ($operarios as &$operario) {
        $estadoContrato = obtenerMensajeEstadoContrato($operario['CodOperario']);
        $operario['tiene_contrato'] = operarioTieneContrato($operario['CodOperario']);
        $operario['estado_contrato'] = $estadoContrato['tipo'];
        $operario['mensaje_contrato'] = $estadoContrato['mensaje'];
    }
    
    echo json_encode($operarios);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
