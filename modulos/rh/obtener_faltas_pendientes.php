<?php
require_once '../../core/auth/auth.php';

// Verificar acceso
verificarAccesoCargo([13, 16]);

header('Content-Type: application/json');

try {
    // Obtener faltas pendientes
    $faltasPendientes = obtenerFaltasPendientesRevisión();
    $totalPendientes = count($faltasPendientes);

    // Calcular días restantes y color del indicador
    $diasRestantes = calcularDiasRestantesRevisionFaltas();
    $colorIndicador = determinarColorIndicadorFaltas($diasRestantes);

    // Obtener periodo actual para mostrar en el modal
    $periodo = calcularPeriodoRevisionFaltas();

    // Función para calcular el rango de fechas según la lógica específica
    function calcularRangoFechasFaltas()
    {
        $hoy = new DateTime();
        $dia = (int) $hoy->format('d');

        if ($dia <= 2) {
            // Si es día 1 o 2, rango del mes anterior
            $mesAnterior = new DateTime('first day of last month');
            $desde = $mesAnterior->format('Y-m-01');
            $hasta = $mesAnterior->format('Y-m-t');
        } else {
            // Si es día 3 en adelante, rango del mes actual
            $desde = $hoy->format('Y-m-01');
            $hasta = $hoy->format('Y-m-t');
        }

        return ['desde' => $desde, 'hasta' => $hasta];
    }

    // Calcular rango de fechas para la URL
    $rangoFechas = calcularRangoFechasFaltas();
    $urlFaltas = "../lideres/faltas_manual.php?desde={$rangoFechas['desde']}&hasta={$rangoFechas['hasta']}";

    echo json_encode([
        'success' => true,
        'total_pendientes' => $totalPendientes,
        'dias_restantes' => $diasRestantes,
        'color_indicador' => $colorIndicador,
        'faltas_pendientes' => $faltasPendientes,
        'periodo_actual' => $periodo,
        'fecha_limite_info' => obtenerFechaRevisiónInfo(),
        'url_faltas' => $urlFaltas
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

/**
 * Obtiene información de la fecha límite para mostrar
 */
function obtenerFechaRevisiónInfo()
{
    $hoy = new DateTime();
    $dia = (int) $hoy->format('d');

    if ($dia <= 2) {
        return "Fecha límite: Día 2 del mes (hoy es día $dia)";
    } else {
        $proximoMes = new DateTime('first day of next month');
        $proximoMes->modify('+1 day'); // Día 2 del próximo mes
        return "Próxima fecha límite: " . $proximoMes->format('d/m/Y');
    }
}
?>