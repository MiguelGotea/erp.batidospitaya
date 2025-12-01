<?php
// ajax/historial_get_opciones_filtro.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$columna = isset($_POST['columna']) ? $_POST['columna'] : '';
$tipo = isset($_POST['tipo']) ? $_POST['tipo'] : '';

try {
    $opciones = [];
    
    if ($columna === 'nombre_sucursal') {
        $sql = "SELECT DISTINCT s.codigo as valor, s.nombre as texto
                FROM sucursales s
                INNER JOIN mtto_tickets t ON t.cod_sucursal = s.codigo
                ORDER BY s.nombre";
        $result = $db->fetchAll($sql);
        
        foreach ($result as $row) {
            $opciones[] = [
                'valor' => $row['valor'],
                'texto' => $row['texto']
            ];
        }
        
    } elseif ($columna === 'tipo_formulario') {
        $opciones = [
            ['valor' => 'mantenimiento_general', 'texto' => 'Mantenimiento'],
            ['valor' => 'cambio_equipos', 'texto' => 'Cambio Equipo']
        ];
        
    } elseif ($columna === 'status') {
        $sql = "SELECT DISTINCT status as valor
                FROM mtto_tickets
                WHERE status IS NOT NULL
                ORDER BY status";
        $result = $db->fetchAll($sql);
        
        foreach ($result as $row) {
            $opciones[] = [
                'valor' => $row['valor'],
                'texto' => ucfirst($row['valor'])
            ];
        }
        
    } elseif ($columna === 'nivel_urgencia') {
        $opciones = [
            ['valor' => '0', 'texto' => 'No Clasificado'],
            ['valor' => '1', 'texto' => 'No Urgente'],
            ['valor' => '2', 'texto' => 'Medio'],
            ['valor' => '3', 'texto' => 'Urgente'],
            ['valor' => '4', 'texto' => 'Crítico']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'opciones' => $opciones
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar opciones: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>