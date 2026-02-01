<?php
require_once '../../core/auth/auth.php';

header('Content-Type: application/json');

try {
    // Obtener contratos próximos a vencer (menos de 1 mes)
    $contratosProximos = obtenerContratosProximosVencer();
    $totalProximos = count($contratosProximos);

    echo json_encode([
        'success' => true,
        'total_proximos' => $totalProximos,
        'contratos_proximos' => $contratosProximos
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

/**
 * Obtiene los contratos que vencen en menos de 1 mes
 */
function obtenerContratosProximosVencer()
{
    global $conn;

    $fechaHoy = new DateTime();
    $fechaLimite = new DateTime();
    $fechaLimite->modify('+1 month'); // Contratos que vencen en los próximos 30 días

    $sql = "
        SELECT 
            c.*,
            o.CodOperario,
            CONCAT(
                TRIM(o.Nombre), 
                IF(o.Nombre2 IS NOT NULL AND o.Nombre2 != '', CONCAT(' ', TRIM(o.Nombre2)), ''), 
                ' ', 
                TRIM(o.Apellido),
                IF(o.Apellido2 IS NOT NULL AND o.Apellido2 != '', CONCAT(' ', TRIM(o.Apellido2)), '')
            ) as nombre_completo,
            s.nombre as sucursal_nombre,
            DATEDIFF(c.fin_contrato, CURDATE()) as dias_restantes
        FROM Contratos c
        JOIN Operarios o ON c.cod_operario = o.CodOperario
        LEFT JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
        LEFT JOIN sucursales s ON anc.Sucursal = s.codigo
        WHERE c.fin_contrato IS NOT NULL 
        AND c.fin_contrato != '0000-00-00'
        AND c.fin_contrato >= CURDATE() -- Que no hayan vencido
        AND c.fin_contrato <= ? -- Que venzan en menos de 1 mes
        AND o.Operativo = 1 -- Solo colaboradores activos
        AND (c.fecha_salida IS NULL OR c.fecha_salida = '0000-00-00') -- Que no tengan fecha de salida
        GROUP BY c.codigo_manual_contrato
        ORDER BY c.fin_contrato ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$fechaLimite->format('Y-m-d')]);

    return $stmt->fetchAll();
}
?>