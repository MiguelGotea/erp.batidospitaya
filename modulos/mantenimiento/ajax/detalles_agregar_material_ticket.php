<?php
// ajax/detalles_agregar_material_ticket.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
$material_id = isset($_POST['material_id']) ? intval($_POST['material_id']) : null;
$detalle = isset($_POST['detalle']) ? trim($_POST['detalle']) : '';
$procedencia = isset($_POST['procedencia']) ? $_POST['procedencia'] : null;

if ($ticket_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de ticket inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Obtener nombre del material
    $material_nombre = '';
    if ($material_id) {
        $sql = "SELECT nombre FROM mtto_materiales_frecuentes WHERE id = ?";
        $material = $db->fetchOne($sql, [$material_id]);
        $material_nombre = $material['nombre'];
    }
    
    // Insertar material en el ticket
    $sql = "INSERT INTO mtto_tickets_materiales (ticket_id, material_id, material_nombre, detalle, procedencia) 
            VALUES (?, ?, ?, ?, ?)";
    
    $db->query($sql, [$ticket_id, $material_id, $material_nombre, $detalle, $procedencia]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Material agregado correctamente'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al agregar material: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>