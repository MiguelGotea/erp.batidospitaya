<?php
// gestion_proyectos_get_historial.php
// Carga proyectos finalizados con filtros y paginación

header('Content-Type: application/json; charset=utf-8');
require_once '../../../core/auth/auth.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar permiso
if (!tienePermiso('gestion_proyectos', 'vista', $cargoOperario)) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit();
}

try {
    // Parámetros de paginación y filtros
    $pagina = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
    if ($pagina < 1)
        $pagina = 1;

    $limit = isset($_GET['registros_por_pagina']) ? (int) $_GET['registros_por_pagina'] : 25;
    if ($limit < 1)
        $limit = 25;

    $offset = ($pagina - 1) * $limit;

    $filtrosRaw = isset($_GET['filtros']) ? $_GET['filtros'] : '';
    $filtros = json_decode($filtrosRaw, true);
    if (!is_array($filtros))
        $filtros = [];

    $ordenCol = isset($_GET['orden_columna']) ? $_GET['orden_columna'] : 'fecha_fin';
    $ordenDir = isset($_GET['orden_direccion']) ? $_GET['orden_direccion'] : 'DESC';

    // Construcción de la consulta con filtros
    $where = ["p.fecha_fin < CURDATE()", "p.es_subproyecto = 0"]; // Solo proyectos padre finalizados
    $params = [];

    if (!empty($filtros['cargo'])) {
        $where[] = "nc.Nombre LIKE :cargo";
        $params[':cargo'] = "%" . $filtros['cargo'] . "%";
    }
    if (!empty($filtros['nombre'])) {
        $where[] = "p.nombre LIKE :nombre";
        $params[':nombre'] = "%" . $filtros['nombre'] . "%";
    }
    if (!empty($filtros['descripcion'])) {
        $where[] = "p.descripcion LIKE :desc";
        $params[':desc'] = "%" . $filtros['descripcion'] . "%";
    }
    if (!empty($filtros['inicio_desde'])) {
        $where[] = "p.fecha_inicio >= :ini_desde";
        $params[':ini_desde'] = $filtros['inicio_desde'];
    }
    if (!empty($filtros['inicio_hasta'])) {
        $where[] = "p.fecha_inicio <= :ini_hasta";
        $params[':ini_hasta'] = $filtros['inicio_hasta'];
    }

    $whereClause = "WHERE " . implode(" AND ", $where);

    // Contar total para paginación
    $sqlCount = "SELECT COUNT(*) FROM gestion_proyectos_proyectos p 
                 INNER JOIN NivelesCargos nc ON p.CodNivelesCargos = nc.CodNivelesCargos 
                 $whereClause";
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->execute($params);
    $totalRegistros = $stmtCount->fetchColumn();

    // Obtener datos
    $validCols = ['cargo_nombre' => 'nc.Nombre', 'nombre' => 'p.nombre', 'fecha_inicio' => 'p.fecha_inicio', 'fecha_fin' => 'p.fecha_fin'];
    $orderSql = isset($validCols[$ordenCol]) ? $validCols[$ordenCol] : 'p.fecha_fin';
    $dirSql = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

    $sqlData = "SELECT 
                    p.id,
                    p.nombre,
                    p.descripcion,
                    p.fecha_inicio,
                    p.fecha_fin,
                    nc.Nombre as cargo_nombre
                FROM gestion_proyectos_proyectos p
                INNER JOIN NivelesCargos nc ON p.CodNivelesCargos = nc.CodNivelesCargos
                $whereClause
                ORDER BY $orderSql $dirSql
                LIMIT :limit OFFSET :offset";

    $stmtData = $conn->prepare($sqlData);
    foreach ($params as $key => $val) {
        $stmtData->bindValue($key, $val);
    }
    $stmtData->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtData->execute();

    $datos = $stmtData->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'total_registros' => (int) $totalRegistros,
        'datos' => $datos
    ]);

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error en el servidor: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>