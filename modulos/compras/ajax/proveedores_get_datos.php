<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/database/conexion.php';
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
    
    // Filtro de texto (nombre)
    if (isset($filtros['nombre']) && $filtros['nombre'] !== '') {
        $where[] = "p.nombre LIKE :nombre";
        $params[":nombre"] = '%' . $filtros['nombre'] . '%';
    }
    
    // Filtro de texto (ruc_nit)
    if (isset($filtros['ruc_nit']) && $filtros['ruc_nit'] !== '') {
        $where[] = "p.ruc_nit LIKE :ruc_nit";
        $params[":ruc_nit"] = '%' . $filtros['ruc_nit'] . '%';
    }
    
    // Filtro de texto (direccion)
    if (isset($filtros['direccion']) && $filtros['direccion'] !== '') {
        $where[] = "p.direccion LIKE :direccion";
        $params[":direccion"] = '%' . $filtros['direccion'] . '%';
    }
    
    // Filtro de texto (nombre_sucursal)
    if (isset($filtros['nombre_sucursal']) && $filtros['nombre_sucursal'] !== '') {
        $where[] = "s.nombre LIKE :nombre_sucursal";
        $params[":nombre_sucursal"] = '%' . $filtros['nombre_sucursal'] . '%';
    }
    
    // Filtro de lista (vigente)
    if (isset($filtros['vigente']) && is_array($filtros['vigente']) && count($filtros['vigente']) > 0) {
        $placeholders = [];
        foreach ($filtros['vigente'] as $idx => $valor) {
            $key = ":vigente_$idx";
            $placeholders[] = $key;
            $params[$key] = $valor;
        }
        $where[] = "p.vigente IN (" . implode(',', $placeholders) . ")";
    }
    
    // Filtro de rango de fechas
    if (isset($filtros['fecha_registro']) && is_array($filtros['fecha_registro'])) {
        if (!empty($filtros['fecha_registro']['desde'])) {
            $where[] = "p.fecha_registro >= :fecha_registro_desde";
            $params[':fecha_registro_desde'] = $filtros['fecha_registro']['desde'] . ' 00:00:00';
        }
        if (!empty($filtros['fecha_registro']['hasta'])) {
            $where[] = "p.fecha_registro <= :fecha_registro_hasta";
            $params[':fecha_registro_hasta'] = $filtros['fecha_registro']['hasta'] . ' 23:59:59';
        }
    }
    
    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Construir ORDER BY
    $orderClause = '';
    if ($orden['columna']) {
        $columnas_validas = ['nombre', 'ruc_nit', 'direccion', 'vigente', 'fecha_registro', 'nombre_sucursal'];
        if (in_array($orden['columna'], $columnas_validas)) {
            $direccion = strtoupper($orden['direccion']) === 'DESC' ? 'DESC' : 'ASC';
            
            // Mapear columnas virtuales a columnas reales
            $columna_real = $orden['columna'];
            if ($orden['columna'] === 'nombre_sucursal') {
                $columna_real = 's.nombre';
            } else {
                $columna_real = 'p.' . $orden['columna'];
            }
            
            $orderClause = "ORDER BY {$columna_real} $direccion";
        }
    } else {
        $orderClause = "ORDER BY p.fecha_registro DESC";
    }
    
    // Consulta de conteo
    $sqlCount = "SELECT COUNT(*) as total 
                 FROM proveedores p
                 LEFT JOIN sucursales s ON p.comprasucursal = s.codigo
                 $whereClause";
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->execute($params);
    $totalRegistros = $stmtCount->fetch()['total'];
    
    // Consulta de datos con paginación
    $sql = "SELECT 
                p.id,
                p.nombre,
                p.ruc_nit,
                p.direccion,
                p.vigente,
                p.fecha_registro,
                s.nombre as nombre_sucursal
            FROM proveedores p
            LEFT JOIN sucursales s ON p.comprasucursal = s.codigo
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