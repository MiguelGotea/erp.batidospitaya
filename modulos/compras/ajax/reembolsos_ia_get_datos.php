<?php
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $pagina = isset($_POST['pagina']) ? (int)$_POST['pagina'] : 1;
    $registros_por_pagina = isset($_POST['registros_por_pagina']) ? (int)$_POST['registros_por_pagina'] : 25;
    $filtros = isset($_POST['filtros']) ? json_decode($_POST['filtros'], true) : [];
    $orden = isset($_POST['orden']) ? json_decode($_POST['orden'], true) : ['columna' => null, 'direccion' => 'asc'];
    
    $offset = ($pagina - 1) * $registros_por_pagina;
    
    // Construir WHERE
    $where = [];
    $params = [];
    
    // Filtro de texto (Proveedor)
    if (isset($filtros['proveedor_nombre']) && $filtros['proveedor_nombre'] !== '') {
        $where[] = "p.nombre LIKE :proveedor_nombre";
        $params[":proveedor_nombre"] = '%' . $filtros['proveedor_nombre'] . '%';
    }
    
    // Filtro de texto (Concepto)
    if (isset($filtros['concepto']) && $filtros['concepto'] !== '') {
        $where[] = "s.concepto LIKE :concepto";
        $params[":concepto"] = '%' . $filtros['concepto'] . '%';
    }
    
    // Filtro de texto (Registrado por)
    if (isset($filtros['usuario_nombre']) && $filtros['usuario_nombre'] !== '') {
        $where[] = "o.Nombre LIKE :usuario_nombre";
        $params[":usuario_nombre"] = '%' . $filtros['usuario_nombre'] . '%';
    }
    
    // Filtro de lista (CECO)
    if (isset($filtros['ceco']) && is_array($filtros['ceco']) && count($filtros['ceco']) > 0) {
        $placeholders = [];
        foreach ($filtros['ceco'] as $idx => $valor) {
            $key = ":ceco_$idx";
            $placeholders[] = $key;
            $params[$key] = $valor;
        }
        $where[] = "s.ceco IN (" . implode(',', $placeholders) . ")";
    }
    
    // Filtro numérico (Monto)
    if (isset($filtros['total_cordobas']) && is_array($filtros['total_cordobas'])) {
        if (!empty($filtros['total_cordobas']['min'])) {
            $where[] = "s.total_cordobas >= :monto_min";
            $params[':monto_min'] = $filtros['total_cordobas']['min'];
        }
        if (!empty($filtros['total_cordobas']['max'])) {
            $where[] = "s.total_cordobas <= :monto_max";
            $params[':monto_max'] = $filtros['total_cordobas']['max'];
        }
    }
    
    // Filtro de lista (Estado)
    if (isset($filtros['estado']) && is_array($filtros['estado']) && count($filtros['estado']) > 0) {
        $placeholders = [];
        foreach ($filtros['estado'] as $idx => $valor) {
            $key = ":estado_$idx";
            $placeholders[] = $key;
            $params[$key] = $valor;
        }
        $where[] = "s.estado IN (" . implode(',', $placeholders) . ")";
    }
    
    // Filtro de rango de fechas
    if (isset($filtros['fecha_solicitud']) && is_array($filtros['fecha_solicitud'])) {
        if (!empty($filtros['fecha_solicitud']['desde'])) {
            $where[] = "s.fecha_solicitud >= :fecha_desde";
            $params[':fecha_desde'] = $filtros['fecha_solicitud']['desde'];
        }
        if (!empty($filtros['fecha_solicitud']['hasta'])) {
            $where[] = "s.fecha_solicitud <= :fecha_hasta";
            $params[':fecha_hasta'] = $filtros['fecha_solicitud']['hasta'];
        }
    }
    
    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Construir ORDER BY
    $orderClause = '';
    if ($orden['columna']) {
        $columnas_validas = ['fecha_solicitud', 'proveedor_nombre', 'concepto', 'ceco', 'total_cordobas', 'estado', 'usuario_nombre'];
        if (in_array($orden['columna'], $columnas_validas)) {
            $direccion = strtoupper($orden['direccion']) === 'DESC' ? 'DESC' : 'ASC';
            
            // Mapear columnas virtuales
            $columna_real = $orden['columna'];
            if ($orden['columna'] === 'proveedor_nombre') {
                $columna_real = 'p.nombre';
            } elseif ($orden['columna'] === 'usuario_nombre') {
                $columna_real = 'o.Nombre';
            } elseif ($orden['columna'] === 'ceco') {
                $columna_real = 's.ceco';
            } else {
                $columna_real = 's.' . $orden['columna'];
            }
            
            $orderClause = "ORDER BY {$columna_real} $direccion";
        }
    } else {
        $orderClause = "ORDER BY s.created_at DESC";
    }
    
    // Consulta de conteo
    $sqlCount = "SELECT COUNT(*) as total 
                 FROM reembolsos_solicitudes s
                 LEFT JOIN proveedores p ON s.id_proveedor = p.id
                 LEFT JOIN Operarios o ON s.usuario_registro = o.CodOperario
                 $whereClause";
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->execute($params);
    $totalRegistros = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Consulta de datos con paginación
    $sql = "SELECT 
                s.id,
                s.fecha_solicitud,
                s.concepto,
                s.ceco,
                s.moneda,
                s.total_cordobas,
                s.estado,
                p.nombre as proveedor_nombre,
                o.Nombre as usuario_nombre,
                CONCAT(cc.Codigo, ' - ', cc.Nombre) as ceco_nombre
            FROM reembolsos_solicitudes s
            LEFT JOIN proveedores p ON s.id_proveedor = p.id
            LEFT JOIN Operarios o ON s.usuario_registro = o.CodOperario
            LEFT JOIN CentroCostos cc ON s.ceco = cc.Codigo
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
