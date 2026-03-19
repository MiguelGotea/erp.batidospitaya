<?php
/**
 * Verifica qué solicitudes de cotización puede ver el usuario según su cargo
 */
function obtenerFiltroSolicitudesPorCargo($usuarioId, $cargoOperario)
{
    global $conn;

    // Si es gerente (ReportaA es NULL/vacío), puede ver todas
    if (esGerente($usuarioId)) {
        return []; // Ver todas
    }

    // Si tiene el permiso dinámico de ver todas las aprobadas/en proceso/completadas
    if (tienePermiso('historial_solicitudes_cotizacion', 'ver_todo_aprobadas', $cargoOperario)) {
        return [
            'condicion' => 'OR',
            'filtros' => [
                ['campo' => 'sc.solicitante_id', 'valor' => $usuarioId, 'operador' => '='],
                ['campo' => 'sc.estado', 'valor' => 'aprobada', 'operador' => '='],
                ['campo' => 'sc.estado', 'valor' => 'completada', 'operador' => '=']
            ]
        ];
    }

    // Para otros cargos, solo ven las que han subido ellos mismos
    return [
        'condicion' => 'AND',
        'filtros' => [
            ['campo' => 'sc.solicitante_id', 'valor' => $usuarioId, 'operador' => '=']
        ]
    ];
}

/**
 * Obtiene las solicitudes con los filtros por cargo aplicados
 */
function obtenerSolicitudesConFiltroCargo($filtroCargo = null, $filtrosAdicionales = [])
{
    global $conn;

    $query = "
        SELECT sc.*, 
               COUNT(scp.id) as total_productos,
               GROUP_CONCAT(scp.producto_descripcion SEPARATOR '; ') as productos_resumen,
               -- Obtener información del gerente si existe
               gerente.Nombre as gerente_nombre,
               gerente.Apellido as gerente_apellido
        FROM solicitudes_cotizacion sc
        LEFT JOIN solicitudes_cotizacion_productos scp ON sc.id = scp.solicitud_id
        LEFT JOIN Operarios gerente ON sc.gerente_aprobador_id = gerente.CodOperario
        WHERE 1=1
    ";

    $params = [];

    // Aplicar filtros por cargo
    if ($filtroCargo && !empty($filtroCargo['filtros'])) {
        $condicion = $filtroCargo['condicion'] ?? 'AND';

        if ($condicion === 'OR') {
            $subConditions = [];
            foreach ($filtroCargo['filtros'] as $filtro) {
                $subConditions[] = "{$filtro['campo']} {$filtro['operador']} ?";
                $params[] = $filtro['valor'];
            }
            $query .= " AND (" . implode(" OR ", $subConditions) . ")";
        }
        else {
            foreach ($filtroCargo['filtros'] as $filtro) {
                $query .= " AND {$filtro['campo']} {$filtro['operador']} ?";
                $params[] = $filtro['valor'];
            }
        }
    }

    // Aplicar filtros adicionales
    foreach ($filtrosAdicionales as $filtro) {
        if (!empty($filtro['valor']) || $filtro['valor'] === '0') {
            $query .= " AND {$filtro['campo']} {$filtro['operador']} ?";
            $params[] = $filtro['valor'];
        }
    }

    $query .= " GROUP BY sc.id ORDER BY sc.created_at DESC";

    try {
        $stmt = $conn->prepare($query);

        if (!empty($params)) {
            $stmt->execute($params);
        }
        else {
            $stmt->execute();
        }

        $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Combinar nombre completo del gerente
        foreach ($solicitudes as &$solicitud) {
            if (!empty($solicitud['gerente_nombre']) && !empty($solicitud['gerente_apellido'])) {
                $solicitud['gerente_nombre_completo'] = trim($solicitud['gerente_nombre'] . ' ' . $solicitud['gerente_apellido']);
            }
            else {
                $solicitud['gerente_nombre_completo'] = $solicitud['gerente_aprobador_nombre'] ?? null;
            }
        }

        return $solicitudes;

    }
    catch (Exception $e) {
        error_log("Error al obtener solicitudes: " . $e->getMessage());
        return [];
    }
}

/**
 * Verifica si el usuario puede aprobar solicitudes (es gerente)
 */
function puedeAprobarSolicitudes()
{
    return esGerente();
}

/**
 * Verifica si el usuario puede completar solicitudes (cargo 9 - Compras)
 */
function puedeCompletarSolicitudes()
{
    if (!isset($_SESSION['usuario_id'])) {
        return false;
    }

    // Usar el sistema de permisos dinámico
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'] ?? null;

    return tienePermiso('historial_solicitudes_cotizacion', 'completar', $cargoOperario);
}

/**
 * Obtiene las acciones permitidas (actualizada para usar función esGerente)
 */
function obtenerAccionesPermitidas($solicitud, $usuarioId)
{
    $acciones = [];

    // Gerencia puede aprobar y rechazar
    if (esGerente($usuarioId)) {
        if ($solicitud['estado'] === 'pendiente') {
            $acciones[] = 'aprobar';
            $acciones[] = 'rechazar';
        }
        if ($solicitud['estado'] === 'aprobada') {
            $acciones[] = 'completar';
        }
    }

    // Compras puede marcar como completado
    if (puedeCompletarSolicitudes() && $solicitud['estado'] === 'aprobada') {
        $acciones[] = 'completar';
    }

    // El solicitante puede cancelar si está pendiente
    $usuario = obtenerUsuarioActual();
    $cargoUsuario = $usuario['CodNivelesCargos'] ?? null;

    if ($solicitud['solicitante_id'] == $usuarioId && $solicitud['estado'] === 'pendiente') {
        $acciones[] = 'cancelar';
    }

    return array_unique($acciones);
}
?>