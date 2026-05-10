<?php
// ajax/promo_get_datos.php — Listado paginado de promociones
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    if (!isset($_SESSION['usuario_id'])) throw new Exception('No autorizado');

    $pagina    = isset($_POST['pagina']) ? max(1, (int)$_POST['pagina']) : 1;
    $rpp       = isset($_POST['registros_por_pagina']) ? max(1, (int)$_POST['registros_por_pagina']) : 25;
    $filtros   = isset($_POST['filtros']) ? json_decode($_POST['filtros'], true) : [];
    $orden     = isset($_POST['orden']) ? json_decode($_POST['orden'], true) : ['columna' => null, 'direccion' => 'asc'];
    $offset    = ($pagina - 1) * $rpp;

    $where  = [];
    $params = [];

    if (!empty($filtros['id'])) {
        $where[] = 'CAST(p.id AS CHAR) LIKE :id';
        $params[':id'] = '%' . trim($filtros['id']) . '%';
    }

    if (!empty($filtros['nombre'])) {
        $where[] = 'p.nombre LIKE :nombre';
        $params[':nombre'] = '%' . trim($filtros['nombre']) . '%';
    }

    if (!empty($filtros['estado']) && is_array($filtros['estado'])) {
        $ph = [];
        foreach ($filtros['estado'] as $i => $v) {
            $ph[] = ":est$i";
            $params[":est$i"] = $v;
        }
        $where[] = 'p.estado IN (' . implode(',', $ph) . ')';
    }

    if (!empty($filtros['resultado_tipo']) && is_array($filtros['resultado_tipo'])) {
        $ph = [];
        foreach ($filtros['resultado_tipo'] as $i => $v) {
            $ph[] = ":res$i";
            $params[":res$i"] = $v;
        }
        $where[] = 'p.resultado_tipo IN (' . implode(',', $ph) . ')';
    }

    if (!empty($filtros['num_condiciones'])) {
        $where[] = '(SELECT COUNT(*) FROM promo_condiciones c WHERE c.promo_id = p.id) = :num_condiciones';
        $params[':num_condiciones'] = (int)$filtros['num_condiciones'];
    }

    if (!empty($filtros['prioridad'])) {
        $where[] = 'CAST(p.prioridad AS CHAR) LIKE :prioridad';
        $params[':prioridad'] = '%' . trim($filtros['prioridad']) . '%';
    }

    if (!empty($filtros['fecha_inicio']) && is_array($filtros['fecha_inicio'])) {
        if (!empty($filtros['fecha_inicio']['desde'])) {
            $where[] = 'DATE(p.fecha_inicio) >= :fecha_inicio_desde';
            $params[':fecha_inicio_desde'] = $filtros['fecha_inicio']['desde'];
        }
        if (!empty($filtros['fecha_inicio']['hasta'])) {
            $where[] = 'DATE(p.fecha_inicio) <= :fecha_inicio_hasta';
            $params[':fecha_inicio_hasta'] = $filtros['fecha_inicio']['hasta'];
        }
    }

    $whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $columnasValidas = [
        'id' => 'p.id',
        'nombre' => 'p.nombre',
        'estado' => 'p.estado',
        'num_condiciones' => 'num_condiciones',
        'resultado_tipo' => 'p.resultado_tipo',
        'fecha_inicio' => 'p.fecha_inicio',
        'prioridad' => 'p.prioridad'
    ];

    $orderClause = 'ORDER BY p.prioridad ASC, p.fecha_creacion DESC';
    if (!empty($orden['columna']) && isset($columnasValidas[$orden['columna']])) {
        $direccion = strtoupper($orden['direccion'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        $orderClause = 'ORDER BY ' . $columnasValidas[$orden['columna']] . ' ' . $direccion . ', p.id DESC';
    }

    // Count
    $stmtC = $conn->prepare(
        "SELECT COUNT(*) as total FROM promo_promociones p $whereClause"
    );
    $stmtC->execute($params);
    $total = $stmtC->fetch()['total'];

    // Data
    $sql = "SELECT
                p.*,
                (SELECT COUNT(*) FROM promo_condiciones c WHERE c.promo_id = p.id) AS num_condiciones
            FROM promo_promociones p
            $whereClause
            $orderClause
            LIMIT :offset, :rpp";

    $stmt = $conn->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':rpp', $rpp, PDO::PARAM_INT);
    $stmt->execute();
    $datos = $stmt->fetchAll();

    echo json_encode(['success' => true, 'datos' => $datos, 'total_registros' => $total]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
