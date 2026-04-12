<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/database/conexion.php';
header('Content-Type: application/json');

try {
    // Verificar autenticación
    if (!isset($_SESSION['usuario_id'])) {
        throw new Exception('No autorizado');
    }
    
    $pagina = isset($_POST['pagina']) ? (int)$_POST['pagina'] : 1;
    $registros_por_pagina = isset($_POST['registros_por_pagina']) ? (int)$_POST['registros_por_pagina'] : 25;
    $filtros = isset($_POST['filtros']) ? json_decode($_POST['filtros'], true) : [];
    $orden = isset($_POST['orden']) ? json_decode($_POST['orden'], true) : ['columna' => null, 'direccion' => 'asc'];
    
    $offset = ($pagina - 1) * $registros_por_pagina;
    
    // Construir WHERE
    $where = [];
    $params = [];
    
    // Filtro de texto (Nombre)
    if (isset($filtros['Nombre']) && $filtros['Nombre'] !== '') {
        $where[] = "pm.Nombre LIKE :nombre";
        $params[":nombre"] = '%' . $filtros['Nombre'] . '%';
    }
    
    // Filtro de texto (SKU)
    if (isset($filtros['SKU']) && $filtros['SKU'] !== '') {
        $where[] = "pm.SKU LIKE :sku";
        $params[":sku"] = '%' . $filtros['SKU'] . '%';
    }
    
    // Filtro de texto (Descripcion)
    if (isset($filtros['Descripcion']) && $filtros['Descripcion'] !== '') {
        $where[] = "pm.Descripcion LIKE :descripcion";
        $params[":descripcion"] = '%' . $filtros['Descripcion'] . '%';
    }
    
    // Filtro de lista (categoria_nombre)
    if (isset($filtros['categoria_nombre']) && is_array($filtros['categoria_nombre']) && count($filtros['categoria_nombre']) > 0) {
        $placeholders = [];
        foreach ($filtros['categoria_nombre'] as $idx => $valor) {
            $key = ":categoria_$idx";
            $placeholders[] = $key;
            $params[$key] = $valor;
        }
        $where[] = "pm.Id_categoria IN (" . implode(',', $placeholders) . ")";
    }
    
    // Filtro de lista (Estado)
    if (isset($filtros['Estado']) && is_array($filtros['Estado']) && count($filtros['Estado']) > 0) {
        $placeholders = [];
        foreach ($filtros['Estado'] as $idx => $valor) {
            $key = ":estado_$idx";
            $placeholders[] = $key;
            $params[$key] = $valor;
        }
        $where[] = "pm.Estado IN (" . implode(',', $placeholders) . ")";
    }
    
    // Filtro de rango de fechas de creación
    if (isset($filtros['fecha_creacion']) && is_array($filtros['fecha_creacion'])) {
        if (!empty($filtros['fecha_creacion']['desde'])) {
            $where[] = "DATE(pm.fecha_creacion) >= :fecha_creacion_desde";
            $params[':fecha_creacion_desde'] = $filtros['fecha_creacion']['desde'];
        }
        if (!empty($filtros['fecha_creacion']['hasta'])) {
            $where[] = "DATE(pm.fecha_creacion) <= :fecha_creacion_hasta";
            $params[':fecha_creacion_hasta'] = $filtros['fecha_creacion']['hasta'];
        }
    }
    
    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Construir ORDER BY
    $orderClause = '';
    if ($orden['columna']) {
        $columnas_validas = ['Nombre', 'SKU', 'Descripcion', 'categoria_nombre', 'Estado', 'fecha_creacion'];
        if (in_array($orden['columna'], $columnas_validas)) {
            $direccion = strtoupper($orden['direccion']) === 'DESC' ? 'DESC' : 'ASC';
            
            $columna_real = $orden['columna'];
            if ($orden['columna'] === 'categoria_nombre') {
                $columna_real = 'c.Nombre';
            } else {
                $columna_real = 'pm.' . $orden['columna'];
            }
            
            $orderClause = "ORDER BY {$columna_real} $direccion";
        }
    } else {
        $orderClause = "ORDER BY pm.fecha_creacion DESC";
    }
    
    // Consulta de conteo
    $sqlCount = "SELECT COUNT(*) as total 
                 FROM producto_maestro pm
                 INNER JOIN categoria_producto_maestro c ON pm.Id_categoria = c.id
                 $whereClause";
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->execute($params);
    $totalRegistros = $stmtCount->fetch()['total'];
    
    // Consulta de datos con paginación
    $sql = "SELECT 
                pm.id,
                pm.Nombre,
                pm.SKU,
                pm.Descripcion,
                pm.Id_categoria,
                pm.Estado,
                pm.fecha_creacion,
                c.Nombre as categoria_nombre
            FROM producto_maestro pm
            INNER JOIN categoria_producto_maestro c ON pm.Id_categoria = c.id
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
    $datos = $stmt->fetchAll();
    
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