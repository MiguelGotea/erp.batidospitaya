<?php
/**
 * erp/modulos/sistemas/ajax/anulaciones_get.php
 * Obtiene lista paginada de solicitudes de anulación del host.
 */

require_once '../../../core/database/conexion.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pagina = isset($_POST['pagina']) ? (int)$_POST['pagina'] : (isset($_GET['page']) ? (int)$_GET['page'] : 1);
    $registros_por_pagina = isset($_POST['registros_por_pagina']) ? (int)$_POST['registros_por_pagina'] : (isset($_GET['limit']) ? (int)$_GET['limit'] : 25);
    $filtros = isset($_POST['filtros']) ? json_decode($_POST['filtros'], true) : [];
    $orden = isset($_POST['orden']) ? json_decode($_POST['orden'], true) : ['columna' => null, 'direccion' => 'asc'];
    
    // Soporte para GET antiguo (compatibilidad)
    if (empty($filtros)) {
        if (isset($_GET['status']) && $_GET['status'] != -1) $filtros['Status'] = [$_GET['status']];
        if (isset($_GET['sucursal']) && $_GET['sucursal'] != 0) $filtros['Sucursal'] = [$_GET['sucursal']];
        if (isset($_GET['buscar']) && $_GET['buscar'] !== '') $filtros['CodPedido'] = $_GET['buscar'];
    }

    $offset = ($pagina - 1) * $registros_por_pagina;
    
    /** @var PDO $pdo */
    global $conn;
    $pdo = $conn;

    $where = [];
    $params = [];

    // Filtros dinámicos
    foreach ($filtros as $columna => $valor) {
        if ($valor === '' || $valor === null) continue;

        if ($columna === 'CodPedido' || $columna === 'Motivo' || $columna === 'AprobadoPor') {
            $where[] = "a.$columna LIKE :$columna";
            $params[":$columna"] = '%' . $valor . '%';
        } elseif ($columna === 'CodAnulacionHost') {
            if (is_array($valor)) {
                if (!empty($valor['min'])) {
                    $where[] = "a.CodAnulacionHost >= :id_min";
                    $params[':id_min'] = $valor['min'];
                }
                if (!empty($valor['max'])) {
                    $where[] = "a.CodAnulacionHost <= :id_max";
                    $params[':id_max'] = $valor['max'];
                }
            } else {
                $where[] = "a.CodAnulacionHost = :id";
                $params[':id'] = $valor;
            }
        } elseif ($columna === 'Sucursal' || $columna === 'Status' || $columna === 'EjecutadoEnTienda' || $columna === 'Modalidad') {
            if (is_array($valor) && count($valor) > 0) {
                $placeholders = [];
                foreach ($valor as $idx => $v) {
                    $key = ":{$columna}_$idx";
                    $placeholders[] = $key;
                    $params[$key] = $v;
                }
                $where[] = "a.$columna IN (" . implode(',', $placeholders) . ")";
            }
        } elseif ($columna === 'HoraSolicitada') {
            if (is_array($valor)) {
                if (!empty($valor['desde'])) {
                    $where[] = "a.HoraSolicitada >= :fecha_desde";
                    $params[':fecha_desde'] = $valor['desde'] . ' 00:00:00';
                }
                if (!empty($valor['hasta'])) {
                    $where[] = "a.HoraSolicitada <= :fecha_hasta";
                    $params[':fecha_hasta'] = $valor['hasta'] . ' 23:59:59';
                }
            }
        }
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Count total
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM AnulacionPedidosHost a $whereSQL");
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();

    // ORDER BY
    // Si no hay FechaPedido (porque no se ha subido), usamos la HoraSolicitada para el orden
    $orderClause = "ORDER BY COALESCE((SELECT MAX(Fecha) FROM VentasGlobalesAccessCSV WHERE CodPedido = a.CodPedido AND (local = CAST(a.Sucursal AS CHAR) OR local = CONCAT('S', CAST(a.Sucursal AS CHAR)))), DATE(a.HoraSolicitada)) DESC, a.HoraSolicitada DESC, a.CodPedido DESC";
    if ($orden['columna']) {
        $columnas_validas = ['CodAnulacionHost', 'CodPedido', 'Sucursal', 'HoraSolicitada', 'Status', 'Motivo', 'AprobadoPor', 'EjecutadoEnTienda'];
        if (in_array($orden['columna'], $columnas_validas)) {
            $direccion = strtoupper($orden['direccion']) === 'DESC' ? 'DESC' : 'ASC';
            $orderClause = "ORDER BY a.{$orden['columna']} $direccion";
        }
    }

    // Data
    $stmtData = $pdo->prepare(
        "SELECT a.CodAnulacionHost, a.CodPedido, a.Sucursal,
                a.HoraSolicitada, a.HoraAnulada, a.Status,
                a.Modalidad, a.CodPedidoCambio, a.Motivo,
                a.CodMotivoAnulacion, a.ComentarioAprobacion,
                a.AprobadoPor, a.FechaAprobacion,
                a.EjecutadoEnTienda, a.HoraEjecutadaTienda,
                a.FechaUltimoSync,
                a.ia_decision, a.ia_resultado,
                (SELECT nombre FROM sucursales WHERE codigo = CAST(a.Sucursal AS CHAR) LIMIT 1) AS Sucursal_Nombre,
                (SELECT MAX(Fecha) FROM VentasGlobalesAccessCSV WHERE CodPedido = a.CodPedido AND (local = CAST(a.Sucursal AS CHAR) OR local = CONCAT('S', CAST(a.Sucursal AS CHAR))) LIMIT 1) AS FechaPedido
         FROM AnulacionPedidosHost a
         $whereSQL
         $orderClause
         LIMIT :lim OFFSET :off"
    );
    
    foreach ($params as $key => $val) {
        $stmtData->bindValue($key, $val);
    }
    $stmtData->bindValue(':lim', (int)$registros_por_pagina, PDO::PARAM_INT);
    $stmtData->bindValue(':off', (int)$offset, PDO::PARAM_INT);
    $stmtData->execute();
    $registros = $stmtData->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'   => true,
        'total'     => $total,
        'registros' => $registros,
        'paginas'   => (int)ceil($total / $registros_por_pagina),
        // Compatibilidad con frontend antiguo si hace falta
        'total_registros' => $total,
        'datos' => $registros
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
