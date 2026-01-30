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
    
    // Filtro de texto (numero_cupon)
    if (isset($filtros['numero_cupon']) && $filtros['numero_cupon'] !== '') {
        $where[] = "c.numero_cupon LIKE :numero_cupon";
        $params[":numero_cupon"] = '%' . $filtros['numero_cupon'] . '%';
    }
    
    // Filtro de texto (observaciones)
    if (isset($filtros['observaciones']) && $filtros['observaciones'] !== '') {
        $where[] = "c.observaciones LIKE :observaciones";
        $params[":observaciones"] = '%' . $filtros['observaciones'] . '%';
    }
    
    // Filtro de texto (nombre_sucursal)
    if (isset($filtros['nombre_sucursal']) && $filtros['nombre_sucursal'] !== '') {
        $where[] = "s.nombre LIKE :nombre_sucursal";
        $params[":nombre_sucursal"] = '%' . $filtros['nombre_sucursal'] . '%';
    }
    
    // Filtro de texto (cod_pedido)
    if (isset($filtros['cod_pedido']) && $filtros['cod_pedido'] !== '') {
        $where[] = "c.cod_pedido LIKE :cod_pedido";
        $params[":cod_pedido"] = '%' . $filtros['cod_pedido'] . '%';
    }
    
    // Filtro numérico (monto)
    if (isset($filtros['monto']) && is_array($filtros['monto'])) {
        if (!empty($filtros['monto']['min'])) {
            $where[] = "c.monto >= :monto_min";
            $params[':monto_min'] = $filtros['monto']['min'];
        }
        if (!empty($filtros['monto']['max'])) {
            $where[] = "c.monto <= :monto_max";
            $params[':monto_max'] = $filtros['monto']['max'];
        }
    }
    
    // Filtro de lista (aplicado/estado)
    if (isset($filtros['aplicado']) && is_array($filtros['aplicado']) && count($filtros['aplicado']) > 0) {
        $placeholders = [];
        foreach ($filtros['aplicado'] as $idx => $valor) {
            $key = ":aplicado_$idx";
            $placeholders[] = $key;
            $params[$key] = $valor;
        }
        $where[] = "c.aplicado IN (" . implode(',', $placeholders) . ")";
    }
    
    // Filtro de rango de fechas de caducidad
    if (isset($filtros['fecha_caducidad']) && is_array($filtros['fecha_caducidad'])) {
        if (!empty($filtros['fecha_caducidad']['desde'])) {
            $where[] = "c.fecha_caducidad >= :fecha_caducidad_desde";
            $params[':fecha_caducidad_desde'] = $filtros['fecha_caducidad']['desde'];
        }
        if (!empty($filtros['fecha_caducidad']['hasta'])) {
            $where[] = "c.fecha_caducidad <= :fecha_caducidad_hasta";
            $params[':fecha_caducidad_hasta'] = $filtros['fecha_caducidad']['hasta'];
        }
    }
    
    // Filtro de rango de fechas de registro
    if (isset($filtros['fecha_registro']) && is_array($filtros['fecha_registro'])) {
        if (!empty($filtros['fecha_registro']['desde'])) {
            $where[] = "c.fecha_registro >= :fecha_registro_desde";
            $params[':fecha_registro_desde'] = $filtros['fecha_registro']['desde'];
        }
        if (!empty($filtros['fecha_registro']['hasta'])) {
            $where[] = "c.fecha_registro <= :fecha_registro_hasta";
            $params[':fecha_registro_hasta'] = $filtros['fecha_registro']['hasta'];
        }
    }
    
    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Construir ORDER BY
    $orderClause = '';
    if ($orden['columna']) {
        $columnas_validas = ['numero_cupon', 'monto', 'fecha_caducidad', 'fecha_registro', 'observaciones', 'aplicado', 'nombre_sucursal', 'cod_pedido'];
        if (in_array($orden['columna'], $columnas_validas)) {
            $direccion = strtoupper($orden['direccion']) === 'DESC' ? 'DESC' : 'ASC';
            
            // Mapear columnas virtuales a columnas reales
            $columna_real = $orden['columna'];
            if ($orden['columna'] === 'nombre_sucursal') {
                $columna_real = 's.nombre';
            } else {
                $columna_real = 'c.' . $orden['columna'];
            }
            
            $orderClause = "ORDER BY {$columna_real} $direccion";
        }
    } else {
        $orderClause = "ORDER BY c.fecha_registro DESC";
    }
    
    // Consulta de conteo
    $sqlCount = "SELECT COUNT(*) as total 
                 FROM cupones_sucursales c
                 LEFT JOIN sucursales s ON c.cod_sucursal = s.codigo
                 $whereClause";
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->execute($params);
    $totalRegistros = $stmtCount->fetch()['total'];
    
    // Consulta de datos con paginación
    $sql = "SELECT 
                c.id,
                c.numero_cupon,
                c.monto,
                c.fecha_caducidad,
                c.fecha_registro,
                c.observaciones,
                c.aplicado,
                c.cod_pedido,
                s.nombre as nombre_sucursal
            FROM cupones_sucursales c
            LEFT JOIN sucursales s ON c.cod_sucursal = s.codigo
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