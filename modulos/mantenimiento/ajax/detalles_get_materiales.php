<?php
// ajax/detalles_get_materiales.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ticket_id = isset($_GET['ticket_id']) ? intval($_GET['ticket_id']) : 0;

if ($ticket_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de ticket inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Materiales frecuentes
    $sql_frecuentes = "SELECT * FROM mtto_materiales_frecuentes WHERE activo = 1 ORDER BY nombre";
    $materiales_frecuentes = $db->fetchAll($sql_frecuentes);
    
    // Materiales del ticket
    $sql_ticket = "SELECT * FROM mtto_tickets_materiales WHERE ticket_id = ? ORDER BY id";
    $materiales_ticket = $db->fetchAll($sql_ticket, [$ticket_id]);
    
    // Crear array de IDs de materiales frecuentes usados en este ticket
    $materiales_usados = [];
    foreach ($materiales_ticket as $mat) {
        if ($mat['material_id']) {
            $materiales_usados[$mat['material_id']] = [
                'id' => $mat['id'],
                'detalle' => $mat['detalle'],
                'procedencia' => $mat['procedencia']
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'materiales_frecuentes' => $materiales_frecuentes,
        'materiales_usados' => $materiales_usados,
        'otros_materiales' => array_filter($materiales_ticket, function($m) {
            return $m['material_id'] === null;
        })
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar materiales: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>