<?php
header('Content-Type: application/json');
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/auth/auth.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar permiso de vista
if (!tienePermiso('gestion_sorteos', 'vista', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit;
}

$response = ['success' => false, 'data' => [], 'total' => 0];

try {
    // Parámetros de paginación
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $perPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 50;
    $offset = ($page - 1) * $perPage;

    // Filtros
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $fechaInicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
    $fechaFin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';
    $tipoQR = isset($_GET['tipo_qr']) ? $_GET['tipo_qr'] : '';
    $validadoIA = isset($_GET['validado_ia']) ? $_GET['validado_ia'] : '';

    // Construir WHERE clause
    $where = [];
    $params = [];

    if (!empty($search)) {
        $where[] = "(nombre_completo LIKE ? OR numero_factura LIKE ? OR numero_contacto LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    if (!empty($fechaInicio)) {
        $where[] = "DATE(fecha_registro) >= ?";
        $params[] = $fechaInicio;
    }

    if (!empty($fechaFin)) {
        $where[] = "DATE(fecha_registro) <= ?";
        $params[] = $fechaFin;
    }

    if ($tipoQR !== '') {
        $where[] = "tipo_qr = ?";
        $params[] = $tipoQR;
    }

    if ($validadoIA !== '') {
        $where[] = "validado_ia = ?";
        $params[] = (int) $validadoIA;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Contar total de registros
    $countSql = "SELECT COUNT(*) as total FROM pitaya_love_registros $whereClause";
    $countStmt = ejecutarConsulta($countSql, $params);
    $totalResult = $countStmt->fetch(PDO::FETCH_ASSOC);
    $total = $totalResult['total'];

    // Obtener registros paginados
    $sql = "SELECT 
                id,
                nombre_completo,
                numero_contacto,
                numero_cedula,
                numero_factura,
                correo_electronico,
                monto_factura,
                puntos_factura,
                tipo_qr,
                foto_factura,
                validado_ia,
                fecha_registro
            FROM pitaya_love_registros
            $whereClause
            ORDER BY fecha_registro DESC
            LIMIT ? OFFSET ?";

    $params[] = $perPage;
    $params[] = $offset;

    $stmt = ejecutarConsulta($sql, $params);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['success'] = true;
    $response['data'] = $registros;
    $response['total'] = $total;
    $response['page'] = $page;
    $response['per_page'] = $perPage;
    $response['total_pages'] = ceil($total / $perPage);

} catch (Exception $e) {
    $response['message'] = 'Error al obtener registros: ' . $e->getMessage();
}

echo json_encode($response);
