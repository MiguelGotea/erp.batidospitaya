<?php
/**
 * Verifica qué solicitudes de cotización puede ver el usuario según su cargo
 */
function obtenerFiltroSolicitudesPorCargo($usuarioId, $esAdmin, $cargosUsuario)
{
    global $conn;

    if ($esAdmin) {
        return []; // Admin ve todas las solicitudes
    }

    // Si es gerente (ReportaA es NULL/vacío), puede ver todas
    if (esGerente($usuarioId)) {
        return []; // Ver todas
    }

    // Si tiene cargo 9 (Compras), puede ver:
    // 1. Las que ha subido él mismo
    // 2. Las que están aprobadas por gerencia
    // 3. Las que están en proceso
    // 4. Las que están completadas
    if (in_array(9, $cargosUsuario)) {
        return [
            'condicion' => 'OR',
            'filtros' => [
                ['campo' => 'sc.solicitante_id', 'valor' => $usuarioId, 'operador' => '='],
                ['campo' => 'sc.estado', 'valor' => 'aprobada', 'operador' => '='],
                ['campo' => 'sc.estado', 'valor' => 'en_proceso', 'operador' => '='],
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

    // Si es admin, puede completar
    if (isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin') {
        return true;
    }

    // Usar el sistema de permisos dinámico
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'] ?? null;

    return tienePermiso('historial_solicitudes_cotizacion', 'completar', $cargoOperario);
}

/**
 * Obtiene las acciones permitidas (actualizada para usar función esGerente)
 */
function obtenerAccionesPermitidas($solicitud, $usuarioId, $esAdmin)
{
    $acciones = [];

    // Si es admin, puede hacer todo
    if ($esAdmin) {
        $acciones = ['aprobar', 'rechazar', 'completar', 'en_proceso', 'cancelar'];
        return $acciones;
    }

    // Gerencia puede aprobar y rechazar
    if (esGerente($usuarioId)) {
        if ($solicitud['estado'] === 'pendiente') {
            $acciones[] = 'aprobar';
            $acciones[] = 'rechazar';
        }
        if ($solicitud['estado'] === 'aprobada') {
            $acciones[] = 'en_proceso';
        }
        if ($solicitud['estado'] === 'en_proceso') {
            $acciones[] = 'completar';
        }
    }

    // Compras puede marcar como completado y/o en proceso
    if (puedeCompletarSolicitudes() && in_array($solicitud['estado'], ['aprobada', 'en_proceso'])) {
        $acciones[] = 'completar';
        $acciones[] = 'en_proceso';
    }

    // El solicitante puede cancelar si está pendiente
    if ($solicitud['solicitante_id'] == $usuarioId && $solicitud['estado'] === 'pendiente') {
        $acciones[] = 'cancelar';
    }

    return array_unique($acciones);
}
?>