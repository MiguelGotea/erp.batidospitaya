<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    if (!isset($_SESSION['usuario_id'])) {
        throw new Exception('No autorizado');
    }

    $pagina = isset($_POST['pagina']) ? max(1, (int)$_POST['pagina']) : 1;
    $registrosPorPagina = isset($_POST['registros_por_pagina']) ? max(1, (int)$_POST['registros_por_pagina']) : 25;
    $filtros = isset($_POST['filtros']) ? json_decode($_POST['filtros'], true) : [];
    $orden = isset($_POST['orden']) ? json_decode($_POST['orden'], true) : ['columna' => null, 'direccion' => 'asc'];
    $offset = ($pagina - 1) * $registrosPorPagina;

    $where = [];
    $params = [];

    if (!empty($filtros['numero_factura'])) {
        $where[] = 'f.numero_factura LIKE :numero_factura';
        $params[':numero_factura'] = '%' . trim($filtros['numero_factura']) . '%';
    }

    if (!empty($filtros['total_factura'])) {
        $where[] = 'CAST(f.total_factura AS CHAR) LIKE :total_factura';
        $params[':total_factura'] = '%' . trim($filtros['total_factura']) . '%';
    }

    if (!empty($filtros['nombre_proveedor']) && !is_array($filtros['nombre_proveedor'])) {
        $where[] = 'p.nombre LIKE :nombre_proveedor';
        $params[':nombre_proveedor'] = '%' . trim($filtros['nombre_proveedor']) . '%';
    }

    if (isset($filtros['estado']) && is_array($filtros['estado']) && count($filtros['estado']) > 0) {
        $placeholders = [];
        foreach ($filtros['estado'] as $idx => $valor) {
            $key = ':estado_' . $idx;
            $placeholders[] = $key;
            $params[$key] = $valor;
        }
        $where[] = 'f.estado IN (' . implode(',', $placeholders) . ')';
    }

    if (!empty($filtros['registrado_por_nombre']) && !is_array($filtros['registrado_por_nombre'])) {
        $where[] = "CONCAT(o.Nombre, ' ', o.Apellido) LIKE :registrado_por_nombre";
        $params[':registrado_por_nombre'] = '%' . trim($filtros['registrado_por_nombre']) . '%';
    }

    if (isset($filtros['fecha']) && is_array($filtros['fecha'])) {
        if (!empty($filtros['fecha']['desde'])) {
            $where[] = 'DATE(f.fecha) >= :fecha_desde';
            $params[':fecha_desde'] = $filtros['fecha']['desde'];
        }
        if (!empty($filtros['fecha']['hasta'])) {
            $where[] = 'DATE(f.fecha) <= :fecha_hasta';
            $params[':fecha_hasta'] = $filtros['fecha']['hasta'];
        }
    }

    if (isset($filtros['fecha_hora_regsys']) && is_array($filtros['fecha_hora_regsys'])) {
        if (!empty($filtros['fecha_hora_regsys']['desde'])) {
            $where[] = 'DATE(f.fecha_hora_regsys) >= :fecha_reg_desde';
            $params[':fecha_reg_desde'] = $filtros['fecha_hora_regsys']['desde'];
        }
        if (!empty($filtros['fecha_hora_regsys']['hasta'])) {
            $where[] = 'DATE(f.fecha_hora_regsys) <= :fecha_reg_hasta';
            $params[':fecha_reg_hasta'] = $filtros['fecha_hora_regsys']['hasta'];
        }
    }

    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

    $columnasValidas = [
        'numero_factura' => 'f.numero_factura',
        'fecha' => 'f.fecha',
        'nombre_proveedor' => 'p.nombre',
        'total_factura' => 'f.total_factura',
        'estado' => 'f.estado',
        'registrado_por_nombre' => 'registrado_por_nombre',
        'fecha_hora_regsys' => 'f.fecha_hora_regsys'
    ];

    $orderClause = 'ORDER BY f.fecha DESC, f.id DESC';
    if (!empty($orden['columna']) && isset($columnasValidas[$orden['columna']])) {
        $direccion = strtoupper($orden['direccion'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        $orderClause = 'ORDER BY ' . $columnasValidas[$orden['columna']] . ' ' . $direccion . ', f.id DESC';
    }

    $sqlCount = "SELECT COUNT(*) AS total
                 FROM pos_facturas f
                 LEFT JOIN proveedores p ON f.id_proveedor = p.id
                 LEFT JOIN Operarios o ON f.registrado_por = o.CodOperario
                 $whereClause";
    $stmtCount = $conn->prepare($sqlCount);
    foreach ($params as $k => $v) {
        $stmtCount->bindValue($k, $v);
    }
    $stmtCount->execute();
    $totalRegistros = (int)$stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

    $sql = "SELECT f.id,
                   f.numero_factura,
                   f.fecha,
                   f.total_factura,
                   f.estado,
                   f.notas,
                   f.fecha_hora_regsys,
                   f.registrado_por,
                   p.nombre AS nombre_proveedor,
                   CONCAT(o.Nombre, ' ', o.Apellido) AS registrado_por_nombre
            FROM pos_facturas f
            LEFT JOIN proveedores p ON f.id_proveedor = p.id
            LEFT JOIN Operarios o ON f.registrado_por = o.CodOperario
            $whereClause
            $orderClause
            LIMIT :offset, :limit";

    $stmt = $conn->prepare($sql);
    foreach ($params as $k => $v) {
        if (is_int($v)) {
            $stmt->bindValue($k, $v, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $registrosPorPagina, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'datos' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'total_registros' => $totalRegistros
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
