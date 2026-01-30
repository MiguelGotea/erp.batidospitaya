<?php
//clientes_get_datos.php
require_once '../../../includes/conexion.php';

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
    
    // Filtros de texto
    $filtrosTexto = ['membresia', 'nombre', 'apellido', 'celular', 'correo'];
    foreach ($filtrosTexto as $campo) {
        if (isset($filtros[$campo]) && $filtros[$campo] !== '') {
            $where[] = "$campo LIKE :$campo";
            $params[":$campo"] = '%' . $filtros[$campo] . '%';
        }
    }
    
    // Filtro de lista (sucursal)
    if (isset($filtros['nombre_sucursal']) && is_array($filtros['nombre_sucursal']) && count($filtros['nombre_sucursal']) > 0) {
        $placeholders = [];
        foreach ($filtros['nombre_sucursal'] as $idx => $valor) {
            $key = ":sucursal_$idx";
            $placeholders[] = $key;
            $params[$key] = $valor;
        }
        $where[] = "nombre_sucursal IN (" . implode(',', $placeholders) . ")";
    }
    
    // Filtro de rango de fechas de registro
    if (isset($filtros['fecha_registro']) && is_array($filtros['fecha_registro'])) {
        if (!empty($filtros['fecha_registro']['desde'])) {
            $where[] = "fecha_registro >= :fecha_registro_desde";
            $params[':fecha_registro_desde'] = $filtros['fecha_registro']['desde'];
        }
        if (!empty($filtros['fecha_registro']['hasta'])) {
            $where[] = "fecha_registro <= :fecha_registro_hasta";
            $params[':fecha_registro_hasta'] = $filtros['fecha_registro']['hasta'];
        }
    }
    
    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Construir ORDER BY
    $orderClause = '';
    if ($orden['columna']) {
        $columnas_validas = ['membresia', 'nombre', 'apellido', 'celular', 'fecha_nacimiento', 'correo', 'fecha_registro', 'nombre_sucursal'];
        if (in_array($orden['columna'], $columnas_validas)) {
            $direccion = strtoupper($orden['direccion']) === 'DESC' ? 'DESC' : 'ASC';
            $orderClause = "ORDER BY {$orden['columna']} $direccion";
        }
    } else {
        $orderClause = "ORDER BY fecha_registro DESC";
    }
    
    // Consulta de conteo
    $sqlCount = "SELECT COUNT(*) as total FROM clientesclub $whereClause";
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->execute($params);
    $totalRegistros = $stmtCount->fetch()['total'];
    
    // Consulta de datos con paginación
    $sql = "SELECT 
                membresia,
                nombre,
                apellido,
                celular,
                fecha_nacimiento,
                correo,
                fecha_registro,
                nombre_sucursal
            FROM clientesclub
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