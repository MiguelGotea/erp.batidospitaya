<?php
// ajax/historial_get_solicitudes.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pagina = isset($_POST['pagina']) ? intval($_POST['pagina']) : 1;
$registros_por_pagina = isset($_POST['registros_por_pagina']) ? intval($_POST['registros_por_pagina']) : 25;
$filtros = isset($_POST['filtros']) ? json_decode($_POST['filtros'], true) : [];
$orden = isset($_POST['orden']) ? json_decode($_POST['orden'], true) : ['columna' => null, 'direccion' => 'asc'];

$offset = ($pagina - 1) * $registros_por_pagina;

try {
    // Construir WHERE con filtros
    $where_conditions = [];
    $params = [];
    
    foreach ($filtros as $columna => $valor) {
        if (is_array($valor)) {
            // Filtro de lista (múltiples valores)
            $placeholders = str_repeat('?,', count($valor) - 1) . '?';
            
            if ($columna === 'nombre_sucursal') {
                $where_conditions[] = "s.nombre IN ($placeholders)";
            } elseif ($columna === 'nivel_urgencia') {
                $where_conditions[] = "t.nivel_urgencia IN ($placeholders)";
            } elseif ($columna === 'tipo_formulario') {
                $where_conditions[] = "t.tipo_formulario IN ($placeholders)";
            } elseif ($columna === 'status') {
                $where_conditions[] = "t.status IN ($placeholders)";
            }
            
            $params = array_merge($params, $valor);
        } else {
            // Filtro de texto (LIKE)
            if ($columna === 'titulo') {
                $where_conditions[] = "t.titulo LIKE ?";
                $params[] = "%$valor%";
            } elseif ($columna === 'descripcion') {
                $where_conditions[] = "t.descripcion LIKE ?";
                $params[] = "%$valor%";
            } elseif ($columna === 'created_at' || $columna === 'fecha_inicio') {
                $where_conditions[] = "t.$columna LIKE ?";
                $params[] = "%$valor%";
            }
        }
    }
    
    $where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Construir ORDER BY
    $order_sql = '';
    if ($orden['columna']) {
        $columna_orden = $orden['columna'];
        $direccion = $orden['direccion'] === 'desc' ? 'DESC' : 'ASC';
        
        if ($columna_orden === 'nombre_sucursal') {
            $order_sql = "ORDER BY s.nombre $direccion";
        } elseif (in_array($columna_orden, ['created_at', 'titulo', 'descripcion', 'nivel_urgencia', 'status', 'fecha_inicio', 'tipo_formulario'])) {
            $order_sql = "ORDER BY t.$columna_orden $direccion";
        }
    } else {
        $order_sql = "ORDER BY t.created_at DESC";
    }
    
    // Consulta principal con paginación
    $sql = "SELECT t.*, 
                   s.nombre as nombre_sucursal,
                   (SELECT COUNT(*) FROM mtto_tickets_fotos WHERE ticket_id = t.id) as total_fotos
            FROM mtto_tickets t
            LEFT JOIN sucursales s ON t.cod_sucursal = s.codigo
            $where_sql
            $order_sql
            LIMIT ? OFFSET ?";
    
    $params[] = $registros_por_pagina;
    $params[] = $offset;
    
    $datos = $db->fetchAll($sql, $params);
    
    // Contar total de registros
    $sql_count = "SELECT COUNT(*) as total
                  FROM mtto_tickets t
                  LEFT JOIN sucursales s ON t.cod_sucursal = s.codigo
                  $where_sql";
    
    $count_params = array_slice($params, 0, count($params) - 2);
    $total_result = $db->fetchOne($sql_count, $count_params);
    $total_registros = $total_result['total'];
    
    echo json_encode([
        'success' => true,
        'datos' => $datos,
        'total_registros' => $total_registros
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar datos: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>