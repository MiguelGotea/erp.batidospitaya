<?php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

// Verificar acceso
verificarAccesoCargo([11, 16, 21, 49]);

header('Content-Type: application/json');

try {
    // Obtener reclamos pendientes con más de 7 días
    $reclamosPendientes = obtenerReclamosPendientes();

    // Contar total de pendientes
    $totalPendientes = count($reclamosPendientes);

    // Determinar color del indicador según la cantidad y días
    $colorIndicador = determinarColorIndicadorReclamos($reclamosPendientes);

    echo json_encode([
        'success' => true,
        'total_pendientes' => $totalPendientes,
        'color_indicador' => $colorIndicador,
        'reclamos_pendientes' => $reclamosPendientes,
        'dias_tolerancia' => 7
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

/**
 * Obtiene los reclamos pendientes de investigación
 */
function obtenerReclamosPendientes()
{
    global $conn;

    $sql = "
        SELECT 
            r.id,
            r.fecha_evento,
            r.sucursal,
            r.descripcion,
            r.tipo_reclamo,
            r.medio_compra,
            DATEDIFF(CURDATE(), r.fecha_evento) as dias_pendiente,
            s.nombre as sucursal_nombre
        FROM reclamos r
        LEFT JOIN reportes_investigacion ri ON r.id = ri.reclamo_id 
        LEFT JOIN sucursales s ON r.sucursal = s.codigo
        WHERE ri.id IS NULL  -- Sin reporte de investigación
        AND r.fecha_evento IS NOT NULL
        ORDER BY r.fecha_evento ASC, dias_pendiente DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Determina el color del indicador según la cantidad de reclamos y días pendientes
 */
function determinarColorIndicadorReclamos($reclamosPendientes)
{
    if (empty($reclamosPendientes)) {
        return 'verde';
    }

    // Verificar si hay reclamos con más de 7 días
    $reclamosExcedidos = array_filter($reclamosPendientes, function ($reclamo) {
        return $reclamo['dias_pendiente'] > 7;
    });

    if (count($reclamosExcedidos) > 0) {
        return 'rojo';
    }

    // Si hay reclamos pero todos están dentro de los 7 días
    return 'amarillo';
}
?>