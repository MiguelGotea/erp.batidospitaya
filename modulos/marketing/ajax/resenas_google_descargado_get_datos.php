<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar permiso de vista
if (!tienePermiso('resenas_google_descargado', 'vista', $cargoOperario)) {
    echo json_encode(['success' => false, 'message' => 'No tiene permiso para ver estos datos.']);
    exit;
}

// Obtener parámetros
$pagina = isset($_POST['pagina']) ? (int)$_POST['pagina'] : 1;
$registrosPorPagina = isset($_POST['registros_por_pagina']) ? (int)$_POST['registros_por_pagina'] : 25;
$filtros = isset($_POST['filtros']) ? json_decode($_POST['filtros'], true) : [];
$orden = isset($_POST['orden']) ? json_decode($_POST['orden'], true) : ['columna' => 'createTime', 'direccion' => 'desc'];

$offset = ($pagina - 1) * $registrosPorPagina;

try {
    $whereClauses = ["1=1"];
    $params = [];

    // Procesar filtros
    if (!empty($filtros)) {
        foreach ($filtros as $columna => $valor) {
            if (empty($valor)) continue;

            if ($columna === 'locationId') {
                if (is_array($valor) && !empty($valor)) {
                    $placeholders = [];
                    foreach ($valor as $i => $v) {
                        $p = ":loc_$i";
                        $placeholders[] = $p;
                        $params[$p] = $v;
                    }
                    $whereClauses[] = "r.locationId IN (" . implode(',', $placeholders) . ")";
                }
            } elseif ($columna === 'reviewerName') {
                $whereClauses[] = "r.reviewerName LIKE :reviewer";
                $params[':reviewer'] = "%$valor%";
            } elseif ($columna === 'comment') {
                $whereClauses[] = "r.comment LIKE :comment";
                $params[':comment'] = "%$valor%";
            } elseif ($columna === 'starRating') {
                if (is_array($valor) && !empty($valor)) {
                    $placeholders = [];
                    foreach ($valor as $i => $v) {
                        $p = ":star_$i";
                        $placeholders[] = $p;
                        $params[$p] = $v;
                    }
                    $whereClauses[] = "r.starRating IN (" . implode(',', $placeholders) . ")";
                }
            } elseif ($columna === 'createTime') {
                if (!empty($valor['desde'])) {
                    $whereClauses[] = "DATE(r.createTime) >= :desde";
                    $params[':desde'] = $valor['desde'];
                }
                if (!empty($valor['hasta'])) {
                    $whereClauses[] = "DATE(r.createTime) <= :hasta";
                    $params[':hasta'] = $valor['hasta'];
                }
            }
        }
    }

    $whereSql = implode(" AND ", $whereClauses);

    // Obtener total de registros para paginación
    $countSql = "SELECT COUNT(*) as total 
                 FROM ResenasGoogle r 
                 LEFT JOIN sucursales s ON r.locationId = s.cod_googlebusiness
                 WHERE $whereSql";
    $stmtCount = $conn->prepare($countSql);
    $stmtCount->execute($params);
    $totalRegistros = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

    // Validar orden dinámico
    $columnasPermitidas = ['createTime', 'starRating', 'reviewerName', 'locationId', 'comment'];
    $orderBy = 'r.createTime';
    if (in_array($orden['columna'], $columnasPermitidas)) {
        if ($orden['columna'] === 'locationId') $orderBy = 's.nombre';
        else $orderBy = 'r.' . $orden['columna'];
    }
    $direction = (strtoupper($orden['direccion']) === 'ASC') ? 'ASC' : 'DESC';

    // Consulta final
    $sql = "SELECT 
                r.locationId,
                r.reviewerName,
                r.starRating,
                r.comment,
                r.createTime,
                s.nombre AS SucursalNombre
            FROM ResenasGoogle r
            LEFT JOIN sucursales s ON r.locationId = s.cod_googlebusiness
            WHERE $whereSql
            ORDER BY $orderBy $direction
            LIMIT :limit OFFSET :offset";

    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $registrosPorPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $resenas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Procesar datos para estrellas y fechas
    $ratingMap = ['ONE' => 1, 'TWO' => 2, 'THREE' => 3, 'FOUR' => 4, 'FIVE' => 5];
    foreach ($resenas as &$r) {
        $r['starRatingNum'] = isset($ratingMap[$r['starRating']]) ? $ratingMap[$r['starRating']] : 0;
        
        if (!empty($r['createTime'])) {
            $date = new DateTime($r['createTime']);
            $r['fechaFormateada'] = $date->format('d-M-y');
        } else {
            $r['fechaFormateada'] = 'N/A';
        }
        
        if (empty($r['SucursalNombre'])) {
            $r['SucursalNombre'] = 'Desconocida (' . $r['locationId'] . ')';
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $resenas,
        'total_registros' => (int)$totalRegistros
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener datos: ' . $e->getMessage()
    ]);
}
?>
