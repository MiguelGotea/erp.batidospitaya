<?php
// ajax/historial_get_solicitudes.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pagina = isset($_POST['pagina']) ? intval($_POST['pagina']) : 1;
$registros = isset($_POST['registros']) ? intval($_POST['registros']) : 25;
$filtros = isset($_POST['filtros']) ? json_decode($_POST['filtros'], true) : [];
$orden = isset($_POST['orden']) ? json_decode($_POST['orden'], true) : ['campo' => 'created_at', 'direccion' => 'DESC'];
$sucursal_global = isset($_POST['sucursal_global']) ? $_POST['sucursal_global'] : '';
$filtrar_sucursal = isset($_POST['filtrar_sucursal']) && $_POST['filtrar_sucursal'] === 'true';
$codigo_sucursal = isset($_POST['codigo_sucursal']) ? $_POST['codigo_sucursal'] : '';

$offset = ($pagina - 1) * $registros;

try {
    // Construir WHERE
    $where = ['1=1'];
    $params = [];
    
    // Filtro de sucursal por cargo
    if ($filtrar_sucursal && !empty($codigo_sucursal)) {
        $where[] = "t.cod_sucursal = ?";
        $params[] = $codigo_sucursal;
    } elseif (!empty($sucursal_global)) {
        $where[] = "t.cod_sucursal = ?";
        $params[] = $sucursal_global;
    }
    
    // Aplicar filtros
    foreach ($filtros as $campo => $valores) {
        if (!empty($valores) && is_array($valores)) {
            $placeholders = str_repeat('?,', count($valores) - 1) . '?';
            
            if ($campo === 'nivel_urgencia') {
                // Convertir texto a número
                $niveles = [];
                foreach ($valores as $texto) {
                    switch ($texto) {
                        case 'No Clasificado': $niveles[] = null; break;
                        case 'No Urgente': $niveles[] = 1; break;
                        case 'Medio': $niveles[] = 2; break;
                        case 'Urgente': $niveles[] = 3; break;
                        case 'Crítico': $niveles[] = 4; break;
                    }
                }
                if (!empty($niveles)) {
                    $where[] = "(t.nivel_urgencia IN ($placeholders) OR t.nivel_urgencia IS NULL)";
                    $params = array_merge($params, $niveles);
                }
            } elseif ($campo === 'tipo_formulario') {
                $where[] = "t.tipo_formulario IN ($placeholders)";
                $params = array_merge($params, $valores);
            } elseif ($campo === 'nombre_sucursal') {
                $where[] = "s.nombre IN ($placeholders)";
                $params = array_merge($params, $valores);
            } else {
                $where[] = "t.$campo IN ($placeholders)";
                $params = array_merge($params, $valores);
            }
        }
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Mapear campo de orden
    $campoOrden = $orden['campo'];
    if ($campoOrden === 'nombre_sucursal') {
        $campoOrden = 's.nombre';
    } else {
        $campoOrden = "t.$campoOrden";
    }
    
    $direccionOrden = $orden['direccion'] === 'ASC' ? 'ASC' : 'DESC';
    
    // Consulta principal
    $sql = "SELECT t.*, 
                   s.nombre as nombre_sucursal,
                   (SELECT COUNT(*) FROM mtto_tickets_fotos WHERE ticket_id = t.id) as total_fotos
            FROM mtto_tickets t
            LEFT JOIN sucursales s ON t.cod_sucursal = s.codigo
            WHERE $whereClause
            ORDER BY $campoOrden $direccionOrden
            LIMIT ? OFFSET ?";
    
    $params[] = $registros;
    $params[] = $offset;
    
    $datos = $db->fetchAll($sql, $params);
    
    // Contar total
    $sqlCount = "SELECT COUNT(*) as total
                 FROM mtto_tickets t
                 LEFT JOIN sucursales s ON t.cod_sucursal = s.codigo
                 WHERE $whereClause";
    
    $paramsCount = array_slice($params, 0, -2);
    $resultCount = $db->fetchOne($sqlCount, $paramsCount);
    $total = $resultCount['total'];
    
    echo json_encode([
        'success' => true,
        'datos' => $datos,
        'total' => $total
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener datos: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>