<?php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

require_once '../../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'] ?? null;

// Verificar acceso
if (!tienePermiso('gestion_feriados', 'vista', $cargoOperario)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

header('Content-Type: application/json');

try {
    // Calcular fechas límite y periodo según quincenas
    $fechasCalculadas = calcularFechasFeriados();
    $hoy = new DateTime();

    // Obtener feriados pendientes
    $feriadosPendientes = obtenerFeriadosPendientes($fechasCalculadas['inicio_periodo'], $fechasCalculadas['fin_periodo']);

    // Calcular días restantes para la fecha límite
    $diasRestantes = $hoy->diff($fechasCalculadas['fecha_limite'])->days;
    if ($hoy > $fechasCalculadas['fecha_limite']) {
        $diasRestantes = -$diasRestantes; // Negativo si ya pasó la fecha límite
    }

    // Determinar color del indicador
    $colorIndicador = determinarColorIndicador($diasRestantes);

    // Contar total de pendientes
    $totalPendientes = count($feriadosPendientes);

    echo json_encode([
        'success' => true,
        'total_pendientes' => $totalPendientes,
        'dias_restantes' => $diasRestantes,
        'fecha_limite' => $fechasCalculadas['fecha_limite']->format('Y-m-d'),
        'fecha_limite_formateada' => $fechasCalculadas['fecha_limite']->format('d/m/Y'),
        'color_indicador' => $colorIndicador,
        'feriados_pendientes' => $feriadosPendientes,
        'periodo_actual' => [
            'inicio' => $fechasCalculadas['inicio_periodo']->format('Y-m-d'),
            'fin' => $fechasCalculadas['fin_periodo']->format('Y-m-d'),
            'quincena' => $fechasCalculadas['quincena']
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

/**
 * Calcula las fechas para feriados según lógica de quincenas
 */
function calcularFechasFeriados()
{
    $hoy = new DateTime();
    $dia = (int) $hoy->format('d');

    if ($dia <= 15) {
        // Primera quincena: del día 1 al 15
        $inicioPeriodo = $hoy->format('Y-m-01');
        $finPeriodo = $hoy->format('Y-m-15');
        $fechaLimite = new DateTime($hoy->format('Y-m-12')); // Día 12 como límite
        $quincena = 'primera';
    } else {
        // Segunda quincena: del día 16 al último día
        $inicioPeriodo = $hoy->format('Y-m-16');
        $finPeriodo = $hoy->format('Y-m-t');

        // Fecha límite: 3 días antes del fin de mes
        $fechaLimite = new DateTime($finPeriodo);
        $fechaLimite->modify('-3 days');
        $quincena = 'segunda';
    }

    return [
        'fecha_limite' => $fechaLimite,
        'inicio_periodo' => new DateTime($inicioPeriodo),
        'fin_periodo' => new DateTime($finPeriodo),
        'quincena' => $quincena
    ];
}

/**
 * Obtiene los feriados pendientes del periodo calculado
 */
function obtenerFeriadosPendientes($inicioPeriodo, $finPeriodo)
{
    global $conn;

    $sql = "
        SELECT 
            m.id as id_marcacion,
            m.CodOperario as cod_operario,
            CONCAT(o.Nombre, ' ', o.Apellido) as operario_nombre,
            o.Apellido as operario_apellido,
            m.fecha as fecha,
            m.sucursal_codigo,
            s.nombre as sucursal_nombre,
            s.departamento,
            m.hora_ingreso,
            m.hora_salida,
            f.nombre as feriado_nombre,
            f.tipo as feriado_tipo,
            NULL as registrador_nombre,
            NULL as registrador_apellido,
            'feriado_trabajado' as tipo_justificacion,
            'Feriado trabajado pendiente de procesar' as observaciones
        FROM marcaciones m
        INNER JOIN Operarios o ON m.CodOperario = o.CodOperario
        INNER JOIN sucursales s ON m.sucursal_codigo = s.codigo
        INNER JOIN feriadosnic f ON m.fecha = f.fecha
        LEFT JOIN FeriadosStatus fs ON m.id = fs.id_marcacion
        WHERE m.fecha BETWEEN ? AND ?
        AND m.hora_ingreso IS NOT NULL  -- Tiene marcación de entrada
        AND fs.id IS NULL  -- No tiene estado asignado en FeriadosStatus
        AND (
            f.tipo = 'Nacional' 
            OR (f.tipo = 'Departamental' AND f.departamento_codigo = s.cod_departamento)
            OR (f.tipo = 'Departamental' AND f.departamento_codigo IS NULL)
        )
        AND o.Operativo = 1
        AND s.activa = 1
        ORDER BY m.fecha DESC, o.Nombre, o.Apellido
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