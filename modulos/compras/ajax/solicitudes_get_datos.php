<?php
// solicitudes_get_datos.php
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../includes/funciones_compras.php';
require_once '../../../core/helpers/config.php';

verificarAutenticacion();

header('Content-Type: application/json');

try {
    $pagina = isset($_POST['pagina']) ? (int)$_POST['pagina'] : 1;
    $registros_por_pagina = isset($_POST['registros_por_pagina']) ? (int)$_POST['registros_por_pagina'] : 25;
    $filtros = isset($_POST['filtros']) ? json_decode($_POST['filtros'], true) : [];
    $orden = isset($_POST['orden']) ? json_decode($_POST['orden'], true) : ['columna' => null, 'direccion' => 'asc'];
    
    $offset = ($pagina - 1) * $registros_por_pagina;
    
    // Obtener información del usuario
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'] ?? null;
    
    // Obtener filtro por cargo
    $filtroCargo = obtenerFiltroSolicitudesPorCargo($_SESSION['usuario_id'], $cargoOperario);
    
    // Construir WHERE
    $where = [];
    $params = [];
    
    // Aplicar filtro por cargo
    if ($filtroCargo && !empty($filtroCargo['filtros'])) {
        $condicion = $filtroCargo['condicion'] ?? 'AND';
        
        if ($condicion === 'OR') {
            $subConditions = [];
            foreach ($filtroCargo['filtros'] as $filtro) {
                $subConditions[] = "{$filtro['campo']} {$filtro['operador']} ?";
                $params[] = $filtro['valor'];
            }
            $where[] = "(" . implode(" OR ", $subConditions) . ")";
        } else {
            foreach ($filtroCargo['filtros'] as $filtro) {
                $where[] = "{$filtro['campo']} {$filtro['operador']} ?";
                $params[] = $filtro['valor'];
            }
        }
    }
    
    // Filtro de texto - código
    if (isset($filtros['codigo']) && $filtros['codigo'] !== '') {
        $where[] = "sc.codigo LIKE ?";
        $params[] = '%' . $filtros['codigo'] . '%';
    }
    
    // Filtro de texto - solicitante
    if (isset($filtros['solicitante_nombre']) && $filtros['solicitante_nombre'] !== '') {
        $where[] = "sc.solicitante_nombre LIKE ?";
        $params[] = '%' . $filtros['solicitante_nombre'] . '%';
    }
    
    // Filtro de texto - productos
    if (isset($filtros['productos_resumen']) && $filtros['productos_resumen'] !== '') {
        $where[] = "EXISTS (
            SELECT 1 FROM solicitudes_cotizacion_productos scp 
            WHERE scp.solicitud_id = sc.id 
            AND scp.producto_descripcion LIKE ?
        )";
        $params[] = '%' . $filtros['productos_resumen'] . '%';
    }
    
    // Filtro de lista - estado
    if (isset($filtros['estado']) && is_array($filtros['estado']) && count($filtros['estado']) > 0) {
        $placeholders = [];
        foreach ($filtros['estado'] as $idx => $valor) {
            $key = ":estado_$idx";
            $placeholders[] = "?";
            $params[] = $valor;
        }
        $where[] = "sc.estado IN (" . implode(',', $placeholders) . ")";
    }
    
    // Filtro de lista - gerencia (Opción C: ambas opciones)
    if (isset($filtros['gerente_aprobador_nombre']) && is_array($filtros['gerente_aprobador_nombre']) && count($filtros['gerente_aprobador_nombre']) > 0) {
        $subConditions = [];
        
        foreach ($filtros['gerente_aprobador_nombre'] as $valor) {
            if ($valor === 'sin_aprobar') {
                $subConditions[] = "sc.gerente_aprobador_nombre IS NULL";
            } elseif ($valor === 'aprobadas') {
                $subConditions[] = "sc.gerente_aprobador_nombre IS NOT NULL";
            } else {
                // Nombre específico de gerente
                $subConditions[] = "sc.gerente_aprobador_nombre = ?";
                $params[] = $valor;
            }
        }
        
        if (!empty($subConditions)) {
            $where[] = "(" . implode(" OR ", $subConditions) . ")";
        }
    }
    
    // Filtro de rango de fechas - fecha_solicitud
    if (isset($filtros['fecha_solicitud']) && is_array($filtros['fecha_solicitud'])) {
        if (!empty($filtros['fecha_solicitud']['desde'])) {
            $where[] = "sc.fecha_solicitud >= ?";
            $params[] = $filtros['fecha_solicitud']['desde'];
        }
        if (!empty($filtros['fecha_solicitud']['hasta'])) {
            $where[] = "sc.fecha_solicitud <= ?";
            $params[] = $filtros['fecha_solicitud']['hasta'];
        }
    }
    
    // Filtro de rango de fechas - updated_at
    if (isset($filtros['updated_at']) && is_array($filtros['updated_at'])) {
        if (!empty($filtros['updated_at']['desde'])) {
            $where[] = "DATE(sc.updated_at) >= ?";
            $params[] = $filtros['updated_at']['desde'];
        }
        if (!empty($filtros['updated_at']['hasta'])) {
            $where[] = "DATE(sc.updated_at) <= ?";
            $params[] = $filtros['updated_at']['hasta'];
        }
    }
    
    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Construir ORDER BY
    $orderClause = '';
    if ($orden['columna']) {
        $columnas_validas = ['codigo', 'fecha_solicitud', 'solicitante_nombre', 'estado', 'gerente_aprobador_nombre', 'updated_at'];
        if (in_array($orden['columna'], $columnas_validas)) {
            $direccion = strtoupper($orden['direccion']) === 'DESC' ? 'DESC' : 'ASC';
            $columna_real = 'sc.' . $orden['columna'];
            $orderClause = "ORDER BY {$columna_real} $direccion";
        }
    } else {
        $orderClause = "ORDER BY sc.created_at DESC";
    }
    
    // Consulta de conteo
    $sqlCount = "SELECT COUNT(DISTINCT sc.id) as total 
                 FROM solicitudes_cotizacion sc
                 LEFT JOIN solicitudes_cotizacion_productos scp ON sc.id = scp.solicitud_id
                 $whereClause";
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->execute($params);
    $totalRegistros = $stmtCount->fetch()['total'];
    
    // Consulta de datos con paginación
    $sql = "SELECT 
                sc.id,
                sc.codigo,
                sc.version,
                sc.solicitante_id,
                sc.solicitante_nombre,
                sc.fecha_solicitud,
                sc.estado,
                sc.gerente_aprobador_id,
                sc.gerente_aprobador_nombre,
                sc.fecha_aprobacion,
                sc.updated_at,
                COUNT(DISTINCT scp.id) as total_productos,
                GROUP_CONCAT(DISTINCT scp.producto_descripcion ORDER BY scp.orden SEPARATOR '; ') as productos_resumen
            FROM solicitudes_cotizacion sc
            LEFT JOIN solicitudes_cotizacion_productos scp ON sc.id = scp.solicitud_id
            $whereClause
            GROUP BY sc.id
            $orderClause
            LIMIT ?, ?";
    
    $stmt = $conn->prepare($sql);
    
    // Ejecutar con parámetros
    $paramsCount = count($params);
    $params[] = $offset;
    $params[] = $registros_por_pagina;
    
    $stmt->execute($params);
    $datos = $stmt->fetchAll();
    
    // Agregar acciones permitidas para cada solicitud
    foreach ($datos as &$solicitud) {
        $acciones = obtenerAccionesPermitidas($solicitud, $_SESSION['usuario_id']);
        $solicitud['acciones_permitidas'] = implode(',', $acciones);
    }
    
    echo json_encode([
        'success' => true,
        'datos' => $datos,
        'total_registros' => $totalRegistros
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>