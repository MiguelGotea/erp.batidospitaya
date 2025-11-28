<?php
// ajax/agenda_get_colaboradores.php
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
    $sql = "SELECT tc.id, tc.cod_operario, tc.tipo_usuario, 
                   CONCAT(o.Nombre, ' ', o.Apellido) as nombre_completo
            FROM mtto_tickets_colaboradores tc
            LEFT JOIN Operarios o ON tc.cod_operario = o.CodOperario
            WHERE tc.ticket_id = ?
            ORDER BY tc.fecha_asignacion ASC";
    
    $colaboradores = $db->fetchAll($sql, [$ticket_id]);
    
    echo json_encode([
        'success' => true, 
        'colaboradores' => $colaboradores
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error al obtener colaboradores: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>