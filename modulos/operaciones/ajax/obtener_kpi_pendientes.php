<?php
require_once '../../includes/auth.php';
// Verificar acceso
verificarAccesoCargo([11, 16, 21, 49]);

header('Content-Type: application/json');

try {
    // Obtener información de KPI pendientes
    $kpiPendientes = obtenerKpiPendientes();

    // Calcular porcentaje de completitud
    $totalSucursales = count($kpiPendientes['sucursales']);
    $sucursalesConKPI = $kpiPendientes['sucursales_con_kpi'];
    $sucursalesSinKPI = $kpiPendientes['sucursales_sin_kpi'];

    $porcentajeCompletitud = $totalSucursales > 0 ? ($sucursalesConKPI / $totalSucursales) * 100 : 100;

    // Determinar color del indicador según el porcentaje
    $colorIndicador = determinarColorIndicadorKPI($porcentajeCompletitud);

    echo json_encode([
        'success' => true,
        'total_sucursales' => $totalSucursales,
        'sucursales_con_kpi' => $sucursalesConKPI,
        'sucursales_sin_kpi' => $sucursalesSinKPI,
        'porcentaje_completitud' => round($porcentajeCompletitud, 1),
        'color_indicador' => $colorIndicador,
        'sucursales_pendientes' => $kpiPendientes['sucursales_pendientes'],
        'periodo_actual' => $kpiPendientes['periodo_actual']
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

/**
 * Obtiene información de KPI pendientes
 */
function obtenerKpiPendientes()
{
    global $conn;

    // Obtener el periodo actual (mes y año)
    $periodoActual = obtenerPeriodoActualKPI();
    $mesActual = $periodoActual['mes'];
    $anioActual = $periodoActual['anio'];

    // Lista de sucursales que deben tener KPI (las mismas que en kpi.php)
    $sucursales = [
        'León',
        'Matagalpa',
        'Estelí',
        'Altamira',
        'Villa Fontana',
        'Granada',
        'Las Colinas',
        'Masaya',
        'Natura',
        'Las Brisas',
        'Rivas'
    ];

    // Verificar qué sucursales tienen KPI registrado para el periodo actual
    $sucursalesConKPI = [];
    $sucursalesSinKPI = [];

    $placeholders = str_repeat('?,', count($sucursales) - 1) . '?';

    $sql = "
        SELECT DISTINCT sucursal 
        FROM kpi_reclamos 
        WHERE sucursal IN ($placeholders) 
        AND mes = ? 
        AND anio = ?
        AND (kpi_ventas IS NOT NULL OR reclamos_cantidad IS NOT NULL OR reclamos_porcentaje IS NOT NULL)
    ";

    $params = array_merge($sucursales, [$mesActual, $anioActual]);
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    $sucursalesConRegistro = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($sucursales as $sucursal) {
        if (in_array($sucursal, $sucursalesConRegistro)) {
            $sucursalesConKPI[] = $sucursal;
        } else {
            $sucursalesSinKPI[] = $sucursal;
        }
    }

    return [
        'sucursales' => $sucursales,
        'sucursales_con_kpi' => count($sucursalesConKPI),
        'sucursales_sin_kpi' => count($sucursalesSinKPI),
        'sucursales_pendientes' => $sucursalesSinKPI,
        'periodo_actual' => $periodoActual
    ];
}

/**
 * Obtiene el periodo actual para KPI (mes y año actual)
 */
function obtenerPeriodoActualKPI()
{
    $hoy = new DateTime();

    return [
        'mes' => (int) $hoy->format('n'),
        'anio' => (int) $hoy->format('Y'),
        'mes_nombre' => $hoy->format('F'),
        'mes_nombre_es' => traducirMesInglesAEspanol($hoy->format('F'))
    ];
}

/**
 * Traduce el nombre del mes de inglés a español
 */
function traducirMesInglesAEspanol($mesIngles)
{
    $meses = [
        'January' => 'Enero',
        'February' => 'Febrero',
        'March' => 'Marzo',
        'April' => 'Abril',
        'May' => 'Mayo',
        'June' => 'Junio',
        'July' => 'Julio',
        'August' => 'Agosto',
        'September' => 'Septiembre',
        'October' => 'Octubre',
        'November' => 'Noviembre',
        'December' => 'Diciembre'
    ];

    return $meses[$mesIngles] ?? $mesIngles;
}

/**
 * Determina el color del indicador según el porcentaje de completitud
 */
function determinarColorIndicadorKPI($porcentaje)
{
    if ($porcentaje >= 100) {
        return 'verde';
    } elseif ($porcentaje >= 70) {
        return 'amarillo';
    } else {
        return 'rojo';
    }
}
?>