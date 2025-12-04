<?php
// ajax/detalles_get_colaboradores.php
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
    // Obtener colaboradores del ticket
    $sql = "SELECT tc.id, tc.cod_operario, tc.tipo_usuario, 
                   CONCAT(o.Nombre, ' ', o.Apellido) as nombre_completo
            FROM mtto_tickets_colaboradores tc
            LEFT JOIN Operarios o ON tc.cod_operario = o.CodOperario
            WHERE tc.ticket_id = ?
            ORDER BY tc.fecha_asignacion ASC";
    
    $colaboradores = $db->fetchAll($sql, [$ticket_id]);
    
    // Obtener operarios disponibles
    $sql_operarios = "SELECT CodOperario, CONCAT(Nombre, ' ', Apellido) as nombre_completo 
                      FROM Operarios 
                      WHERE Operativo = 1
                      ORDER BY Nombre, Apellido";
    
    $operarios = $db->fetchAll($sql_operarios);
    
    echo json_encode([
        'success' => true,
        'colaboradores' => $colaboradores,
        'operarios' => $operarios
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar colaboradores: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>