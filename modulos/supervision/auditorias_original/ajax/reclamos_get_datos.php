<?php
// reclamos_get_datos.php
require_once '../../../../core/database/conexion.php';
require_once '../../../../core/auth/auth.php';
require_once '../../../../core/permissions/permissions.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    $verHistorialCompleto = tienePermiso('investigacion_reclamos', 'vista_total', $cargoOperario);
    $tieneVista = tienePermiso('investigacion_reclamos', 'vista', $cargoOperario);

    if (!$verHistorialCompleto && !$tieneVista) {
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit();
    }

    $pagina = isset($_POST['pagina']) ? (int)$_POST['pagina'] : 1;
    $registros_por_pagina = isset($_POST['registros_por_pagina']) ? (int)$_POST['registros_por_pagina'] : 25;
    $filtros = isset($_POST['filtros']) ? json_decode($_POST['filtros'], true) : [];
    $orden = isset($_POST['orden']) ? json_decode($_POST['orden'], true) : ['columna' => null, 'direccion' => 'desc'];
    
    $offset = ($pagina - 1) * $registros_por_pagina;
    
    $where = [];
    $params = [];
    
    // Si no tiene permiso de ver historial completo, solo ver los pendientes (donde ri.id IS NULL)
    if (!$verHistorialCompleto) {
        $where[] = "ri.id IS NULL";
    }

    // Filtros de encabezado
    // 1. ID (Código)
    if (isset($filtros['id']) && $filtros['id'] !== '') {
        $where[] = "r.id LIKE :id";
        $params[':id'] = '%' . $filtros['id'] . '%';
    }

    // 2. Fecha Reclamo
    if (isset($filtros['fecha_reclamo']) && is_array($filtros['fecha_reclamo'])) {
        if (!empty($filtros['fecha_reclamo']['desde'])) {
            $where[] = "DATE(r.fecha_reclamo) >= :fecha_reclamo_desde";
            $params[':fecha_reclamo_desde'] = $filtros['fecha_reclamo']['desde'];
        }
        if (!empty($filtros['fecha_reclamo']['hasta'])) {
            $where[] = "DATE(r.fecha_reclamo) <= :fecha_reclamo_hasta";
            $params[':fecha_reclamo_hasta'] = $filtros['fecha_reclamo']['hasta'];
        }
    }

    // 3. Fecha Evento
    if (isset($filtros['fecha_evento']) && is_array($filtros['fecha_evento'])) {
        if (!empty($filtros['fecha_evento']['desde'])) {
            $where[] = "DATE(r.fecha_evento) >= :fecha_evento_desde";
            $params[':fecha_evento_desde'] = $filtros['fecha_evento']['desde'];
        }
        if (!empty($filtros['fecha_evento']['hasta'])) {
            $where[] = "DATE(r.fecha_evento) <= :fecha_evento_hasta";
            $params[':fecha_evento_hasta'] = $filtros['fecha_evento']['hasta'];
        }
    }

    // 4. Sucursal (lista)
    if (isset($filtros['sucursal']) && is_array($filtros['sucursal']) && count($filtros['sucursal']) > 0) {
        $placeholders = [];
        foreach ($filtros['sucursal'] as $idx => $valor) {
            $key = ":sucursal_$idx";
            $placeholders[] = $key;
            $params[$key] = $valor;
        }
        $where[] = "s.nombre IN (" . implode(',', $placeholders) . ")";
    }

    // 5. Medio (lista)
    if (isset($filtros['medio_compra']) && is_array($filtros['medio_compra']) && count($filtros['medio_compra']) > 0) {
        $placeholders = [];
        foreach ($filtros['medio_compra'] as $idx => $valor) {
            $key = ":medio_$idx";
            $placeholders[] = $key;
            $params[$key] = $valor;
        }
        $where[] = "COALESCE(r.medio_compra, '--') IN (" . implode(',', $placeholders) . ")";
    }

    // 6. Estado (lista) - Solo relevante si puede ver historial completo
    if ($verHistorialCompleto && isset($filtros['estado']) && is_array($filtros['estado']) && count($filtros['estado']) > 0) {
        // "Abierto" = ri.id IS NULL, "Cerrado" = ri.id IS NOT NULL
        if (count($filtros['estado']) === 1) {
            if ($filtros['estado'][0] === 'Abierto') {
                $where[] = "ri.id IS NULL";
            } elseif ($filtros['estado'][0] === 'Cerrado') {
                $where[] = "ri.id IS NOT NULL";
            }
        }
    }

    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

    // Ordenamiento
    $orderClause = 'ORDER BY r.fecha_evento DESC'; // Por defecto
    if ($orden['columna']) {
        $columnas_validas = [
            'id' => 'r.id',
            'fecha_reclamo' => 'r.fecha_reclamo',
            'fecha_evento' => 'r.fecha_evento',
            'sucursal' => 's.nombre',
            'medio_compra' => 'r.medio_compra',
            'estado' => 'ri.id'
        ];
        if (array_key_exists($orden['columna'], $columnas_validas)) {
            $colDb = $columnas_validas[$orden['columna']];
            $direccion = strtoupper($orden['direccion']) === 'DESC' ? 'DESC' : 'ASC';
            $orderClause = "ORDER BY $colDb $direccion";
        }
    }

    // Query de base (FROM y JOINs)
    $sqlFrom = "
        FROM reclamos r 
        LEFT JOIN reportes_investigacion ri ON r.id = ri.reclamo_id 
        JOIN sucursales s ON r.sucursal_codigo = s.codigo
        LEFT JOIN reclamos_grupos rg ON r.grupo_id = rg.id
        LEFT JOIN reclamos_tipos rt ON r.tipo_reclamo_id = rt.id
    ";

    // Conteo total
    $sqlCount = "SELECT COUNT(*) as total $sqlFrom $whereClause";
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->execute($params);
    $totalRegistros = $stmtCount->fetch()['total'];

    // Selección de campos
    $sqlSelect = "
        SELECT r.id, 
               DATE_FORMAT(r.fecha_evento, '%d-%b-%y') as fecha_evento_formatted,
               DATE_FORMAT(r.fecha_reclamo, '%d-%b-%y') as fecha_reclamo_formatted,
               r.hora_evento,
               s.nombre as sucursal, 
               r.sucursal_codigo,
               r.descripcion,
               r.tipo_reclamo,
               rg.nombre as grupo_nombre,
               rt.nombre as tipo_nombre,
               r.medio_compra,
               r.fecha_evento,
               r.fecha_reclamo,
               ri.id as reporte_id
    ";

    // Consulta paginada
    $sql = "$sqlSelect $sqlFrom $whereClause $orderClause LIMIT :offset, :limit";
    $stmt = $conn->prepare($sql);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
    
    $stmt->execute();
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
