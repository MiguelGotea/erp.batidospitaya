<?php
// ajax/historial_get_opciones_filtro.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$campo = isset($_POST['campo']) ? $_POST['campo'] : '';
$filtrar_sucursal = isset($_POST['filtrar_sucursal']) && $_POST['filtrar_sucursal'] === 'true';
$codigo_sucursal = isset($_POST['codigo_sucursal']) ? $_POST['codigo_sucursal'] : '';

if (empty($campo)) {
    echo json_encode(['success' => false, 'message' => 'Campo no especificado'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $opciones = [];
    $whereClause = '1=1';
    $params = [];
    
    // Aplicar filtro de sucursal si corresponde
    if ($filtrar_sucursal && !empty($codigo_sucursal)) {
        $whereClause = "t.cod_sucursal = ?";
        $params[] = $codigo_sucursal;
    }
    
    switch ($campo) {
        case 'nivel_urgencia':
            $opciones = [
                ['valor' => 'No Clasificado', 'texto' => 'No Clasificado'],
                ['valor' => 'No Urgente', 'texto' => 'No Urgente'],
                ['valor' => 'Medio', 'texto' => 'Medio'],
                ['valor' => 'Urgente', 'texto' => 'Urgente'],
                ['valor' => 'Crítico', 'texto' => 'Crítico']
            ];
            break;
            
        case 'status':
            $sql = "SELECT DISTINCT status as valor, status as texto 
                    FROM mtto_tickets t
                    WHERE $whereClause AND status IS NOT NULL
                    ORDER BY status";
            $resultados = $db->fetchAll($sql, $params);
            foreach ($resultados as $row) {
                $opciones[] = ['valor' => $row['valor'], 'texto' => ucfirst($row['texto'])];
            }
            break;
            
        case 'tipo_formulario':
            $opciones = [
                ['valor' => 'mantenimiento_general', 'texto' => 'Mantenimiento'],
                ['valor' => 'cambio_equipos', 'texto' => 'Cambio Equipo']
            ];
            break;
            
        case 'nombre_sucursal':
            $sql = "SELECT DISTINCT s.nombre as valor, s.nombre as texto 
                    FROM mtto_tickets t
                    LEFT JOIN sucursales s ON t.cod_sucursal = s.codigo
                    WHERE $whereClause AND s.nombre IS NOT NULL
                    ORDER BY s.nombre";
            $resultados = $db->fetchAll($sql, $params);
            foreach ($resultados as $row) {
                $opciones[] = $row;
            }
            break;
            
        case 'titulo':
            $sql = "SELECT DISTINCT titulo as valor, titulo as texto 
                    FROM mtto_tickets t
                    WHERE $whereClause AND titulo IS NOT NULL AND titulo != ''
                    ORDER BY titulo
                    LIMIT 100";
            $resultados = $db->fetchAll($sql, $params);
            foreach ($resultados as $row) {
                $opciones[] = $row;
            }
            break;
            
        case 'descripcion':
            // Para descripción, solo devolver opciones de búsqueda por texto
            $opciones = [
                ['valor' => '', 'texto' => 'Use el campo de búsqueda']
            ];
            break;
            
        default:
            // Para fechas y otros campos
            $sql = "SELECT DISTINCT $campo as valor, $campo as texto 
                    FROM mtto_tickets t
                    WHERE $whereClause AND $campo IS NOT NULL
                    ORDER BY $campo DESC
                    LIMIT 50";
            $resultados = $db->fetchAll($sql, $params);
            foreach ($resultados as $row) {
                $opciones[] = $row;
            }
            break;
    }
    
    echo json_encode([
        'success' => true,
        'opciones' => $opciones
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener opciones: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>