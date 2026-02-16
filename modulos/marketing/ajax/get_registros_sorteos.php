<?php
header('Content-Type: application/json');
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/auth/auth.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar permiso de vista
if (!tienePermiso('gestion_sorteos', 'vista', $cargoOperario)) {
    error_log("Sin permiso de vista para cargo: $cargoOperario");
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit;
}



$response = ['success' => false, 'data' => [], 'total' => 0];

try {
    // Parámetros de paginación y ordenamiento
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $perPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 50;
    $offset = ($page - 1) * $perPage;

    $ordenColumna = isset($_GET['orden_columna']) ? $_GET['orden_columna'] : 'fecha_registro';
    $ordenDireccion = isset($_GET['orden_direccion']) ? strtoupper($_GET['orden_direccion']) : 'DESC';

    // Lista de columnas válidas para filtrar y ordenar
    $columnasValidas = [
        'nombre_completo',
        'numero_contacto',
        'numero_cedula',
        'numero_factura',
        'correo_electronico',
        'monto_factura',
        'puntos_factura',
        'tipo_qr',
        'validado_ia',
        'valido',
        'fecha_registro'
    ];

    // Validar columna de ordenamiento
    if (!in_array($ordenColumna, $columnasValidas)) {
        $ordenColumna = 'fecha_registro';
    }
    if (!in_array($ordenDireccion, ['ASC', 'DESC'])) {
        $ordenDireccion = 'DESC';
    }

    // Construir WHERE clause
    $where = [];
    $params = [];

    // Filtro especial para ID único (para modal)
    if (isset($_GET['id']) && $_GET['id'] !== '') {
        $where[] = "id = ?";
        $params[] = (int) $_GET['id'];
    }

    // Procesar filtros de cada columna
    foreach ($_GET as $key => $value) {
        // Saltar parámetros de sistema
        if (in_array($key, ['page', 'per_page', 'orden_columna', 'orden_direccion'])) {
            continue;
        }

        // Solo procesar columnas válidas
        if (!in_array($key, $columnasValidas)) {
            continue;
        }

        // Filtro de texto simple (but check for '0' explicitly)
        if (is_string($value) && $value !== '' && $value[0] !== '{' && $value[0] !== '[') {
            // For valido column, use exact match instead of LIKE
            if ($key === 'valido') {
                $where[] = "$key = ?";
                $params[] = (int) $value;
            } else {
                $where[] = "$key LIKE ?";
                $params[] = "%$value%";
            }
            continue;
        }

        // Intentar decodificar JSON para filtros complejos
        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            // Filtro de rango numérico o fecha
            if (isset($decoded['min']) && $decoded['min'] !== '') {
                $where[] = "$key >= ?";
                $params[] = $decoded['min'];
            }
            if (isset($decoded['max']) && $decoded['max'] !== '') {
                $where[] = "$key <= ?";
                $params[] = $decoded['max'];
            }
            if (isset($decoded['desde']) && $decoded['desde'] !== '') {
                $where[] = "DATE($key) >= ?";
                $params[] = $decoded['desde'];
            }
            if (isset($decoded['hasta']) && $decoded['hasta'] !== '') {
                $where[] = "DATE($key) <= ?";
                $params[] = $decoded['hasta'];
            }
        } elseif (is_array($value) || (is_string($value) && strpos($value, ',') !== false)) {
            // Filtro de lista (array o string separado por comas)
            $valores = is_array($value) ? $value : explode(',', $value);
            if (!empty($valores)) {
                $placeholders = implode(',', array_fill(0, count($valores), '?'));
                $where[] = "$key IN ($placeholders)";
                $params = array_merge($params, $valores);
            }
        }
    }

    // Filtros legacy (mantener compatibilidad)
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $fechaInicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
    $fechaFin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';
    $tipoQR = isset($_GET['tipo_qr']) ? $_GET['tipo_qr'] : '';
    $validadoIA = isset($_GET['validado_ia']) ? $_GET['validado_ia'] : '';

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

    // Filtro Verificación IA (3 opciones: verified, review, all)
    $iaFilter = isset($_GET['ia_filter']) ? $_GET['ia_filter'] : '';
    if ($iaFilter === 'verified') {
        $where[] = "(codigo_sorteo_ia IS NOT NULL AND codigo_sorteo_ia != '' AND numero_factura = codigo_sorteo_ia AND puntos_factura = puntos_ia)";
    } elseif ($iaFilter === 'review') {
        $where[] = "(codigo_sorteo_ia IS NULL OR codigo_sorteo_ia = '' OR numero_factura != codigo_sorteo_ia OR puntos_factura != puntos_ia)";
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
                codigo_sorteo_ia,
                puntos_ia,
                valido,
                fecha_registro
            FROM pitaya_love_registros
            $whereClause
            ORDER BY $ordenColumna $ordenDireccion
            LIMIT ? OFFSET ?";

    $params[] = $perPage;
    $params[] = $offset;

    $stmt = ejecutarConsulta($sql, $params);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Agregar URL completa de fotos para el subdominio
    foreach ($registros as &$registro) {
        if (!empty($registro['foto_factura'])) {
            $registro['foto_url'] = 'https://pitayalove.batidospitaya.com/uploads/' . $registro['foto_factura'];
        } else {
            $registro['foto_url'] = null;
        }
    }

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
