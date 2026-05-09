<?php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

verificarAutenticacion();
header('Content-Type: application/json');

try {
    $usuarioId = $_SESSION['usuario_id'];
    $sucursales = obtenerSucursalesLider($usuarioId);
    
    // Determinar periodo según día del mes
    $hoy = new DateTime();
    $diaActual = (int)$hoy->format('d');
    
    if ($diaActual <= 2) {
        $periodo = 'mes_anterior';
        $fechaDesde = (clone $hoy)->modify('first day of last month')->format('Y-m-01');
        $fechaHasta = (clone $hoy)->modify('last day of last month')->format('Y-m-d');
        $fechaLimite = (clone $hoy)->modify('first day of this month')->modify('+2 days')->format('Y-m-d');
    } else {
        $periodo = 'mes_actual';
        $fechaDesde = $hoy->format('Y-m-01');
        $fechaHasta = $hoy->format('Y-m-t');
        $fechaLimite = (clone $hoy)->modify('first day of next month')->modify('+2 days')->format('Y-m-d');
    }
    
    // Calcular días restantes
    $fechaLimiteObj = new DateTime($fechaLimite);
    $diasRestantes = $hoy->diff($fechaLimiteObj)->days;
    $diasRestantes = $fechaLimiteObj >= $hoy ? $diasRestantes : -$diasRestantes;
    
    // Determinar color
    $colorIndicador = determinarColorIndicadorLider($diasRestantes);
    
    // Calcular pendientes
    $faltasPendientes = 0;
    $tardanzasPendientes = 0;
    
    foreach ($sucursales as $sucursal) {
        $faltasPendientes += obtenerTotalFaltasPendientes($sucursal['codigo'], $fechaDesde, $fechaHasta);
        $tardanzasPendientes += obtenerTotalTardanzasPendientes($sucursal['codigo'], $fechaDesde, $fechaHasta);
    }
    
    echo json_encode([
        'success' => true,
        'faltas_pendientes' => $faltasPendientes,
        'tardanzas_pendientes' => $tardanzasPendientes,
        'periodo' => $periodo,
        'fecha_desde' => $fechaDesde,
        'fecha_hasta' => $fechaHasta,
        'fecha_limite' => $fechaLimite,
        'dias_restantes' => $diasRestantes,
        'color_indicador' => $colorIndicador
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function determinarColorIndicadorLider($diasRestantes) {
    if ($diasRestantes < 0) return 'rojo';
    if ($diasRestantes <= 2) return 'rojo';
    if ($diasRestantes <= 5) return 'amarillo';
    return 'verde';
}
?>