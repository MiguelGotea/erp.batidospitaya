<?php
// postulacion_requisicion_get_datos.php

require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $filtros = $input['filtros'] ?? [];
    $orden = $input['orden'] ?? ['columna' => '', 'direccion' => 'ASC'];
    $pagina = (int)($input['pagina'] ?? 1);
    $registros_por_pagina = (int)($input['registros_por_pagina'] ?? 25);
    
    $offset = ($pagina - 1) * $registros_por_pagina;
    
    // Construir WHERE
    $where = [];
    $params = [];
    
    // Filtro por ID
    if (!empty($filtros['id'])) {
        $where[] = "rp.id = :id";
        $params[':id'] = (int)$filtros['id'];
    }
    
    // Filtro por nombre cargo
    if (!empty($filtros['nombre_cargo'])) {
        $where[] = "rp.nombre_cargo LIKE :nombre_cargo";
        $params[':nombre_cargo'] = '%' . $filtros['nombre_cargo'] . '%';
    }
    
    // Filtro por tipo plaza
    if (!empty($filtros['tipo_plaza']) && is_array($filtros['tipo_plaza'])) {
        $placeholders = [];
        foreach ($filtros['tipo_plaza'] as $index => $tipo) {
            $key = ":tipo_plaza_{$index}";
            $placeholders[] = $key;
            $params[$key] = $tipo;
        }
        $where[] = "rp.tipo_plaza IN (" . implode(',', $placeholders) . ")";
    }
    
    // Filtro por nivel de urgencia
    if (!empty($filtros['nivel_urgencia']) && is_array($filtros['nivel_urgencia'])) {
        $placeholders = [];
        foreach ($filtros['nivel_urgencia'] as $index => $nivel) {
            $key = ":nivel_urgencia_{$index}";
            $placeholders[] = $key;
            $params[$key] = (int)$nivel;
        }
        $where[] = "rp.nivel_urgencia IN (" . implode(',', $placeholders) . ")";
    }
    
    // Filtro por status
    if (!empty($filtros['status']) && is_array($filtros['status'])) {
        $placeholders = [];
        foreach ($filtros['status'] as $index => $stat) {
            $key = ":status_{$index}";
            $placeholders[] = $key;
            $params[$key] = $stat;
        }
        $where[] = "rp.status IN (" . implode(',', $placeholders) . ")";
    }
    
    // Filtro de rango de fechas
    if (isset($filtros['fecha_creacion']) && is_array($filtros['fecha_creacion'])) {
        if (!empty($filtros['fecha_creacion']['desde'])) {
            $where[] = "DATE(rp.fecha_creacion) >= :fecha_desde";
            $params[':fecha_desde'] = $filtros['fecha_creacion']['desde'];
        }
        if (!empty($filtros['fecha_creacion']['hasta'])) {
            $where[] = "DATE(rp.fecha_creacion) <= :fecha_hasta";
            $params[':fecha_hasta'] = $filtros['fecha_creacion']['hasta'];
        }
    }
    
    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Construir ORDER BY
    $orderClause = '';
    if ($orden['columna']) {
        $columnasValidas = ['id', 'nombre_cargo', 'tipo_plaza', 'cantidad', 'nivel_urgencia', 'fecha_creacion', 'status'];
        if (in_array($orden['columna'], $columnasValidas)) {
            $direccion = strtoupper($orden['direccion']) === 'DESC' ? 'DESC' : 'ASC';
            $orderClause = "ORDER BY rp.{$orden['columna']} $direccion";
        }
    } else {
        $orderClause = "ORDER BY rp.fecha_creacion DESC";
    }
    
    // Consulta de conteo
    $sqlCount = "SELECT COUNT(*) as total 
                 FROM requisicion_personal rp
                 LEFT JOIN sucursales s ON rp.sucursal = s.codigo
                 $whereClause";
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->execute($params);
    $totalRegistros = $stmtCount->fetch()['total'];
    
    // Consulta de datos
    $sql = "SELECT 
                rp.id,
                rp.nombre_cargo,
                rp.tipo_plaza,
                rp.cantidad,
                rp.nivel_urgencia,
                rp.fecha_creacion,
                rp.status,
                s.nombre as sucursal_nombre
            FROM requisicion_personal rp
            INNER JOIN sucursales s ON rp.sucursal = s.codigo
            $whereClause
            $orderClause
            LIMIT :offset, :limit";
    
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