<?php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

// Verificar acceso
verificarAccesoCargo([5, 13, 11, 16, 8, 28, 39, 30, 37, 43, 49]);

header('Content-Type: application/json');

try {
    // Calcular fechas límite y periodo según lógica del día 3
    $fechasCalculadas = calcularFechasTardanzas();
    $hoy = new DateTime();

    // Obtener tardanzas pendientes
    $tardanzasPendientes = obtenerTardanzasPendientes($fechasCalculadas['inicio_periodo'], $fechasCalculadas['fin_periodo']);

    // Calcular días restantes para la fecha límite
    $diasRestantes = $hoy->diff($fechasCalculadas['fecha_limite'])->days;
    if ($hoy > $fechasCalculadas['fecha_limite']) {
        $diasRestantes = -$diasRestantes; // Negativo si ya pasó la fecha límite
    }

    // Determinar color del indicador
    $colorIndicador = determinarColorIndicador($diasRestantes);

    // Contar total de pendientes
    $totalPendientes = count($tardanzasPendientes);

    echo json_encode([
        'success' => true,
        'total_pendientes' => $totalPendientes,
        'dias_restantes' => $diasRestantes,
        'fecha_limite' => $fechasCalculadas['fecha_limite']->format('Y-m-d'),
        'fecha_limite_formateada' => $fechasCalculadas['fecha_limite']->format('d/m/Y'),
        'color_indicador' => $colorIndicador,
        'tardanzas_pendientes' => $tardanzasPendientes,
        'periodo_tardanzas' => [
            'inicio' => $fechasCalculadas['inicio_periodo']->format('Y-m-d'), // FORMATAR COMO STRING
            'fin' => $fechasCalculadas['fin_periodo']->format('Y-m-d'),       // FORMATAR COMO STRING
            'inicio_formateado' => $fechasCalculadas['inicio_periodo']->format('d/m/Y'),
            'fin_formateado' => $fechasCalculadas['fin_periodo']->format('d/m/Y')
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

/**
 * Calcula las fechas para tardanzas según lógica del día 3
 */
function calcularFechasTardanzas()
{
    $hoy = new DateTime();
    $dia = (int) $hoy->format('d');

    if ($dia <= 3) {
        // Si estamos en días 1-3, periodo es el mes anterior
        $mesAnterior = new DateTime('first day of last month');
        $inicioPeriodo = $mesAnterior->format('Y-m-01');
        $finPeriodo = $mesAnterior->format('Y-m-t');

        // Fecha límite es día 3 del mes actual
        $fechaLimite = new DateTime($hoy->format('Y-m-03'));
    } else {
        // Si estamos después del día 3, periodo es el mes actual
        $mesActual = new DateTime('first day of this month');
        $inicioPeriodo = $mesActual->format('Y-m-01');
        $finPeriodo = $mesActual->format('Y-m-t');

        // Fecha límite es día 3 del próximo mes
        $proximoMes = new DateTime('first day of next month');
        $fechaLimite = new DateTime($proximoMes->format('Y-m-03'));
    }

    return [
        'fecha_limite' => $fechaLimite,
        'inicio_periodo' => new DateTime($inicioPeriodo),
        'fin_periodo' => new DateTime($finPeriodo)
    ];
}

/**
 * Obtiene las tardanzas pendientes de justificación del periodo calculado
 */
function obtenerTardanzasPendientes($inicioPeriodo, $finPeriodo)
{
    global $conn;

    $sql = "
        SELECT tm.*, 
               o.Nombre AS operario_nombre, 
               o.Apellido AS operario_apellido,
               s.nombre AS sucursal_nombre,
               r.Nombre AS registrador_nombre,
               r.Apellido AS registrador_apellido
        FROM TardanzasManuales tm
        JOIN Operarios o ON tm.cod_operario = o.CodOperario
        JOIN sucursales s ON tm.cod_sucursal = s.codigo
        JOIN Operarios r ON tm.registrado_por = r.CodOperario
        WHERE tm.fecha_tardanza BETWEEN ? AND ?
        AND tm.estado = 'Pendiente'
        ORDER BY tm.fecha_tardanza ASC, o.Nombre, o.Apellido
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$inicioPeriodo->format('Y-m-d'), $finPeriodo->format('Y-m-d')]);

    return $stmt->fetchAll();
}

/**
 * Determina el color del indicador según los días restantes
 */
function determinarColorIndicador($diasRestantes)
{
    if ($diasRestantes < 0) {
        return 'rojo'; // Pasó la fecha límite
    } elseif ($diasRestantes <= 1) {
        return 'rojo'; // 1 día antes o menos
    } elseif ($diasRestantes <= 2) {
        return 'amarillo'; // 2 días antes
    } else {
        return 'verde'; // 3 días antes o más
    }
}
?>