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
    
    // Filtro de texto (SKU)
    if (isset($filtros['SKU']) && $filtros['SKU'] !== '') {
        $where[] = "pp.SKU LIKE :SKU";
        $params[":SKU"] = '%' . $filtros['SKU'] . '%';
    }
    
    // Filtro de texto (Nombre)
    if (isset($filtros['Nombre']) && $filtros['Nombre'] !== '') {
        $where[] = "pp.Nombre LIKE :Nombre";
        $params[":Nombre"] = '%' . $filtros['Nombre'] . '%';
    }
    
    // Filtro de lista (unidad)
    if (isset($filtros['unidad_nombre']) && is_array($filtros['unidad_nombre']) && count($filtros['unidad_nombre']) > 0) {
        $placeholders = [];
        foreach ($filtros['unidad_nombre'] as $idx => $valor) {
            $key = ":unidad_nombre_$idx";
            $placeholders[] = $key;
            $params[$key] = $valor;
        }
        $where[] = "u.nombre IN (" . implode(',', $placeholders) . ")";
    }
    
    // Filtros Tri-State (es_vendible)
    if (isset($filtros['es_vendible']) && in_array($filtros['es_vendible'], ['SI', 'NO'])) {
        $where[] = "pp.es_vendible = :es_vendible";
        $params[':es_vendible'] = $filtros['es_vendible'];
    }
    
    // Filtros Tri-State (es_comprable)
    if (isset($filtros['es_comprable']) && in_array($filtros['es_comprable'], ['SI', 'NO'])) {
        $where[] = "pp.es_comprable = :es_comprable";
        $params[':es_comprable'] = $filtros['es_comprable'];
    }
    
    // Filtros Tri-State (es_fabricable)
    if (isset($filtros['es_fabricable']) && in_array($filtros['es_fabricable'], ['SI', 'NO'])) {
        $where[] = "pp.es_fabricable = :es_fabricable";
        $params[':es_fabricable'] = $filtros['es_fabricable'];
    }
    
    // Filtros Tri-State (tiene_receta) - este es especial porque depende de Id_receta_producto IS NOT NULL
    if (isset($filtros['tiene_receta']) && in_array($filtros['tiene_receta'], ['SI', 'NO'])) {
        if ($filtros['tiene_receta'] === 'SI') {
            $where[] = "pp.Id_receta_producto IS NOT NULL";
        } else {
            $where[] = "pp.Id_receta_producto IS NULL";
        }
    }
    
    // Filtros Tri-State (Activo)
    if (isset($filtros['Activo']) && in_array($filtros['Activo'], ['SI', 'NO'])) {
        $where[] = "pp.Activo = :Activo";
        $params[':Activo'] = $filtros['Activo'];
    }
    
    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Construir ORDER BY
    $orderClause = '';
    if ($orden['columna']) {
        $columnas_validas = ['SKU', 'Nombre', 'unidad_nombre', 'es_vendible', 'es_comprable', 'es_fabricable', 'tiene_receta', 'Activo'];
        if (in_array($orden['columna'], $columnas_validas)) {
            $direccion = strtoupper($orden['direccion']) === 'DESC' ? 'DESC' : 'ASC';
            
            // Mapear columnas virtuales a columnas reales
            $columna_real = $orden['columna'];
            if ($orden['columna'] === 'unidad_nombre') {
                $columna_real = 'u.nombre';
            } elseif ($orden['columna'] === 'tiene_receta') {
                // Ordenar por si tiene receta o no
                $columna_real = '(pp.Id_receta_producto IS NOT NULL)';
            } else {
                $columna_real = 'pp.' . $orden['columna'];
            }
            
            $orderClause = "ORDER BY {$columna_real} $direccion";
        }
    } else {
        $orderClause = "ORDER BY pp.fecha_creacion DESC";
    }
    
    // Consulta de conteo
    $sqlCount = "SELECT COUNT(*) as total 
                 FROM producto_presentacion pp
                 LEFT JOIN unidad_producto u ON pp.id_unidad_producto = u.id
                 $whereClause";
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->execute($params);
    $totalRegistros = $stmtCount->fetch()['total'];
    
    // Consulta de datos con paginación
    $sql = "SELECT 
                pp.id,
                pp.SKU,
                pp.Nombre,
                pp.id_producto_maestro,
                pp.id_unidad_producto,
                pp.es_vendible,
                pp.es_comprable,
                pp.es_fabricable,
                pp.Activo,
                pp.Id_receta_producto,
                u.nombre as unidad_nombre,
                CASE 
                    WHEN pp.Id_receta_producto IS NOT NULL THEN 'SI'
                    ELSE 'NO'
                END as tiene_receta
            FROM producto_presentacion pp
            LEFT JOIN unidad_producto u ON pp.id_unidad_producto = u.id
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