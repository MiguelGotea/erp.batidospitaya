<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';
session_start();

header('Content-Type: application/json');

try {
    // Verificar sesión
    if (!isset($_SESSION['usuario_id'])) {
        throw new Exception('Sesión no válida');
    }
    
    $codOperario = $_SESSION['usuario_id'];
    $cargoOperario = $_SESSION['cargo_cod'];
    
    // Verificar permiso de vista
    if (!tienePermiso('unidades_conversion_productos', 'vista', $cargoOperario)) {
        throw new Exception('No tiene permiso para ver esta herramienta');
    }
    
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
        $where[] = "u.nombre LIKE :nombre";
        $params[":nombre"] = '%' . $filtros['nombre'] . '%';
    }
    
    // Filtro de texto (observaciones)
    if (isset($filtros['observaciones']) && $filtros['observaciones'] !== '') {
        $where[] = "u.observaciones LIKE :observaciones";
        $params[":observaciones"] = '%' . $filtros['observaciones'] . '%';
    }
    
    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Construir ORDER BY
    $orderClause = '';
    if ($orden['columna']) {
        $columnas_validas = ['nombre', 'observaciones'];
        if (in_array($orden['columna'], $columnas_validas)) {
            $direccion = strtoupper($orden['direccion']) === 'DESC' ? 'DESC' : 'ASC';
            $orderClause = "ORDER BY u.{$orden['columna']} $direccion";
        }
    } else {
        $orderClause = "ORDER BY u.nombre ASC";
    }
    
    // Consulta de conteo
    $sqlCount = "SELECT COUNT(*) as total FROM unidad_producto u $whereClause";
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->execute($params);
    $totalRegistros = $stmtCount->fetch()['total'];
    
    // Consulta de datos con paginación
    $sql = "SELECT 
                u.id,
                u.nombre,
                u.observaciones,
                u.fecha_creacion,
                CONCAT(o.Nombre, ' ', o.Apellido) as usuario_creacion
            FROM unidad_producto u
            LEFT JOIN Operarios o ON u.usuario_creacion = o.CodOperario
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
    
    // Agregar información de permisos
    $puedeCrear = tienePermiso('unidades_conversion_productos', 'nuevo_registro', $cargoOperario);
    foreach ($datos as &$row) {
        $row['puede_crear'] = $puedeCrear;
    }
    
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