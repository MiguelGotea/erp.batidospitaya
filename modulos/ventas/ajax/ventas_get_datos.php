<?php
// ventas_get_datos.php
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $pagina = isset($_POST['pagina']) ? (int)$_POST['pagina'] : 1;
    $registros_por_pagina = isset($_POST['registros_por_pagina']) ? (int)$_POST['registros_por_pagina'] : 25;
    $filtros = isset($_POST['filtros']) ? json_decode($_POST['filtros'], true) : [];
    $orden = isset($_POST['orden']) ? json_decode($_POST['orden'], true) : ['columna' => null, 'direccion' => 'asc'];
    
    if ($pagina < 1) $pagina = 1;
    if ($registros_por_pagina < 1) $registros_por_pagina = 25;
    if ($registros_por_pagina > 100) $registros_por_pagina = 100;
    
    $offset = ($pagina - 1) * $registros_por_pagina;
    
    $where = [];
    $params = [];
    
    $where[] = "(b.CodGrupo IS NULL OR (b.CodGrupo != 25 AND b.CodGrupo != 11))";
    
    // Filtros de texto
    if (isset($filtros['CodPedido']) && $filtros['CodPedido'] !== '') {
        $where[] = "v.CodPedido LIKE :CodPedido";
        $params[":CodPedido"] = '%' . $filtros['CodPedido'] . '%';
    }
    if (isset($filtros['CodCliente']) && $filtros['CodCliente'] !== '') {
        $where[] = "v.CodCliente LIKE :CodCliente";
        $params[":CodCliente"] = '%' . $filtros['CodCliente'] . '%';
    }
    if (isset($filtros['NombreCliente']) && $filtros['NombreCliente'] !== '') {
        $where[] = "CONCAT(c.nombre, ' ', c.apellido) LIKE :NombreCliente";
        $params[":NombreCliente"] = '%' . $filtros['NombreCliente'] . '%';
    }
    if (isset($filtros['DBBatidos_Nombre']) && $filtros['DBBatidos_Nombre'] !== '') {
        $where[] = "v.DBBatidos_Nombre LIKE :DBBatidos_Nombre";
        $params[":DBBatidos_Nombre"] = '%' . $filtros['DBBatidos_Nombre'] . '%';
    }
    if (isset($filtros['Cantidad']) && $filtros['Cantidad'] !== '') {
        $where[] = "v.Cantidad LIKE :Cantidad";
        $params[":Cantidad"] = '%' . $filtros['Cantidad'] . '%';
    }
    if (isset($filtros['Puntos']) && $filtros['Puntos'] !== '') {
        $where[] = "v.Puntos LIKE :Puntos";
        $params[":Puntos"] = '%' . $filtros['Puntos'] . '%';
    }
    if (isset($filtros['Caja']) && $filtros['Caja'] !== '') {
        $where[] = "v.Caja LIKE :Caja";
        $params[":Caja"] = '%' . $filtros['Caja'] . '%';
    }
    if (isset($filtros['Precio']) && $filtros['Precio'] !== '') {
        $where[] = "v.Precio LIKE :Precio";
        $params[":Precio"] = '%' . $filtros['Precio'] . '%';
    }
    
    // Filtros de lista
    if (isset($filtros['Sucursal_Nombre']) && is_array($filtros['Sucursal_Nombre']) && count($filtros['Sucursal_Nombre']) > 0) {
        $placeholders = [];
        foreach ($filtros['Sucursal_Nombre'] as $idx => $valor) {
            $key = ":sucursal_$idx";
            $placeholders[] = $key;
            $params[$key] = $valor;
        }
        $where[] = "v.Sucursal_Nombre IN (" . implode(',', $placeholders) . ")";
    }
    
    if (isset($filtros['Medida']) && is_array($filtros['Medida']) && count($filtros['Medida']) > 0) {
        $placeholders = [];
        foreach ($filtros['Medida'] as $idx => $valor) {
            $key = ":medida_$idx";
            $placeholders[] = $key;
            $params[$key] = $valor;
        }
        $where[] = "v.Medida IN (" . implode(',', $placeholders) . ")";
    }
    
    if (isset($filtros['Modalidad']) && is_array($filtros['Modalidad']) && count($filtros['Modalidad']) > 0) {
        $placeholders = [];
        foreach ($filtros['Modalidad'] as $idx => $valor) {
            $key = ":modalidad_$idx";
            $placeholders[] = $key;
            $params[$key] = $valor;
        }
        $where[] = "v.Modalidad IN (" . implode(',', $placeholders) . ")";
    }
    
    if (isset($filtros['Anulado']) && is_array($filtros['Anulado']) && count($filtros['Anulado']) > 0) {
        $placeholders = [];
        foreach ($filtros['Anulado'] as $idx => $valor) {
            $key = ":anulado_$idx";
            $placeholders[] = $key;
            $params[$key] = $valor;
        }
        $where[] = "v.Anulado IN (" . implode(',', $placeholders) . ")";
    }
    
    // Filtro de rango de fechas
    if (isset($filtros['Fecha']) && is_array($filtros['Fecha'])) {
        if (!empty($filtros['Fecha']['desde'])) {
            $where[] = "v.Fecha >= :fecha_desde";
            $params[':fecha_desde'] = $filtros['Fecha']['desde'];
        }
        if (!empty($filtros['Fecha']['hasta'])) {
            $where[] = "v.Fecha <= :fecha_hasta";
            $params[':fecha_hasta'] = $filtros['Fecha']['hasta'];
        }
    }
    
    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $orderClause = '';
    if ($orden['columna']) {
        $columnas_validas = [
            'Sucursal_Nombre', 'CodPedido', 'Fecha', 'Hora', 
            'CodCliente', 'DBBatidos_Nombre', 'Medida', 'Cantidad', 'Puntos', 
            'Caja', 'Precio', 'Modalidad', 'Anulado'
        ];
        if (in_array($orden['columna'], $columnas_validas)) {
            $direccion = strtoupper($orden['direccion']) === 'DESC' ? 'DESC' : 'ASC';
            if ($orden['columna'] === 'NombreCliente') {
                $orderClause = "ORDER BY c.nombre $direccion, c.apellido $direccion";
            } else {
                $orderClause = "ORDER BY v.{$orden['columna']} $direccion";
            }
        }
    }
    
    if (empty($orderClause)) {
        $orderClause = "ORDER BY v.Fecha DESC, v.Hora DESC";
    }
    
    $sql = "SELECT SQL_CALC_FOUND_ROWS
                v.Sucursal_Nombre,
                v.CodPedido,
                v.Fecha,
                v.Hora,
                v.CodCliente,
                CASE 
                    WHEN v.CodCliente > 0 THEN CONCAT(c.nombre, ' ', c.apellido)
                    ELSE ''
                END as NombreCliente,
                v.DBBatidos_Nombre,
                v.Medida,
                v.Cantidad,
                v.Puntos,
                v.Caja,
                v.Precio,
                v.Modalidad,
                v.Anulado
            FROM VentasGlobalesAccessCSV v
            LEFT JOIN DBBatidos b ON v.CodProducto = b.CodBatido
            LEFT JOIN clientesclub c ON v.CodCliente = c.membresia
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
    
    $stmtCount = $conn->query("SELECT FOUND_ROWS() as total");
    $totalRegistros = (int)$stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Calcular totales (considerando TODOS los registros filtrados, no solo la página actual)
    $sqlTotales = "SELECT 
                    SUM(v.Precio) as total_monto,
                    SUM(v.Cantidad) as total_productos
                FROM VentasGlobalesAccessCSV v
                LEFT JOIN DBBatidos b ON v.CodProducto = b.CodBatido
                LEFT JOIN clientesclub c ON v.CodCliente = c.membresia
                $whereClause";
    
    $stmtTotales = $conn->prepare($sqlTotales);
    foreach ($params as $key => $value) {
        $stmtTotales->bindValue($key, $value);
    }
    $stmtTotales->execute();
    $totales = $stmtTotales->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'datos' => $datos,
        'total_registros' => $totalRegistros,
        'totales' => [
            'monto' => $totales['total_monto'] ?? 0,
            'productos' => $totales['total_productos'] ?? 0
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al procesar la solicitud'
    ], JSON_UNESCAPED_UNICODE);
}