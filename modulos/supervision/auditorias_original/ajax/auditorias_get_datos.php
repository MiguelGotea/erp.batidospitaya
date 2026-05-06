<?php
// auditorias_get_datos.php
require_once '../../../../core/database/conexion.php'; // Cambiado: anteriormente llamaba al conexion de auditor�as, ahora llama al del core;
header('Content-Type: application/json');

try {
    $pagina = isset($_POST['pagina']) ? (int)$_POST['pagina'] : 1;
    $registros_por_pagina = isset($_POST['registros_por_pagina']) ? (int)$_POST['registros_por_pagina'] : 25;
    $filtros = isset($_POST['filtros']) ? json_decode($_POST['filtros'], true) : [];
    $orden = isset($_POST['orden']) ? json_decode($_POST['orden'], true) : ['columna' => null, 'direccion' => 'desc'];
    
    $offset = ($pagina - 1) * $registros_por_pagina;
    
    // Construir WHERE
    $where = [];
    $params = [];
    
    // Filtro de fecha (rango)
    if (isset($filtros['fecha_hora']) && is_array($filtros['fecha_hora'])) {
        if (!empty($filtros['fecha_hora']['desde'])) {
            $where[] = "DATE(fecha_hora) >= :fecha_desde";
            $params[':fecha_desde'] = $filtros['fecha_hora']['desde'];
        }
        if (!empty($filtros['fecha_hora']['hasta'])) {
            $where[] = "DATE(fecha_hora) <= :fecha_hasta";
            $params[':fecha_hasta'] = $filtros['fecha_hora']['hasta'];
        }
    }
    
    // Filtro de sucursal (lista)
    if (isset($filtros['sucursal']) && is_array($filtros['sucursal']) && count($filtros['sucursal']) > 0) {
        $placeholders = [];
        foreach ($filtros['sucursal'] as $idx => $valor) {
            $key = ":sucursal_$idx";
            $placeholders[] = $key;
            $params[$key] = $valor;
        }
        $where[] = "sucursal IN (" . implode(',', $placeholders) . ")";
    }
    
    // Filtro de tipo de auditoría (lista)
    if (isset($filtros['tipo_auditoria']) && is_array($filtros['tipo_auditoria']) && count($filtros['tipo_auditoria']) > 0) {
        $placeholders = [];
        foreach ($filtros['tipo_auditoria'] as $idx => $valor) {
            $key = ":tipo_$idx";
            $placeholders[] = $key;
            $params[$key] = $valor;
        }
        $where[] = "tipo_auditoria IN (" . implode(',', $placeholders) . ")";
    }
    
    // Filtro de persona (texto)
    if (isset($filtros['persona']) && $filtros['persona'] !== '') {
        $where[] = "(persona LIKE :persona OR operario_id LIKE :persona)";
        $params[":persona"] = '%' . $filtros['persona'] . '%';
    }
    
    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Construir ORDER BY
    $orderClause = 'ORDER BY fecha_hora DESC'; // Por defecto
    if ($orden['columna']) {
        $columnas_validas = ['fecha_hora', 'sucursal', 'persona', 'tipo_auditoria', 'promedio'];
        if (in_array($orden['columna'], $columnas_validas)) {
            $direccion = strtoupper($orden['direccion']) === 'DESC' ? 'DESC' : 'ASC';
            $orderClause = "ORDER BY {$orden['columna']} $direccion";
        }
    }
    
    // Subconsulta para combinar las 4 tablas
    $sqlBase = "
        SELECT * FROM (
            SELECT a.id, a.fecha_hora, s.nombre as sucursal, 
                   COALESCE(CONCAT(o.Nombre, ' ', o.Apellido), a.persona) as persona, 
                   a.promedio_general AS promedio, 'limpieza' AS tipo_auditoria,
                   a.operario_id
            FROM auditoria a 
            JOIN sucursales s ON a.cod_sucursal = s.codigo
            LEFT JOIN Operarios o ON a.operario_id = o.CodOperario
            UNION ALL
            SELECT ap.id, ap.fecha_hora, s.nombre as sucursal, 
                   COALESCE(CONCAT(o.Nombre, ' ', o.Apellido), ap.persona) as persona, 
                   ap.promedio_personal AS promedio, 'personal' AS tipo_auditoria,
                   ap.operario_id
            FROM auditoria_personal ap 
            JOIN sucursales s ON ap.cod_sucursal = s.codigo
            LEFT JOIN Operarios o ON ap.operario_id = o.CodOperario
            UNION ALL
            SELECT asv.id, asv.fecha_hora, s.nombre as sucursal, 
                   COALESCE(CONCAT(o.Nombre, ' ', o.Apellido), asv.persona) as persona, 
                   asv.promedio_calificacion AS promedio, 'servicio' AS tipo_auditoria,
                   asv.operario_id
            FROM auditoria_servicio asv 
            JOIN sucursales s ON asv.cod_sucursal = s.codigo
            LEFT JOIN Operarios o ON asv.operario_id = o.CodOperario
            UNION ALL
            SELECT ap.id, DATE_FORMAT(ap.fecha, '%Y-%m-%d %H:%i:%s') as fecha_hora, ap.sucursal_nombre as sucursal, 
                   ap.operario_nombre as persona, ap.porcentaje_cumplimiento AS promedio, 'procesos' AS tipo_auditoria,
                   ap.operario_id
            FROM auditoria_procesos ap
            UNION ALL
            SELECT apm.id, apm.fecha as fecha_hora, apm.sucursal_nombre as sucursal, 
                   apm.operario_nombre as persona, apm.porcentaje_cumplimiento AS promedio, 'promociones' AS tipo_auditoria,
                   apm.operario_id
            FROM auditoria_promociones apm
        ) AS combined_tables
    ";
    
    // Consulta de conteo
    $sqlCount = "SELECT COUNT(*) as total FROM ($sqlBase) AS subquery $whereClause";
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->execute($params);
    $totalRegistros = $stmtCount->fetch()['total'];
    
    // Consulta de datos con paginación
    $sql = "SELECT * FROM ($sqlBase) AS subquery $whereClause $orderClause LIMIT :offset, :limit";
    
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
?>