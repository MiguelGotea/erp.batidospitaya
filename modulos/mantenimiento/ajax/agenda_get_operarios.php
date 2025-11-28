<?php
// ajax/agenda_get_operarios.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $sql = "SELECT CodOperario, CONCAT(Nombre, ' ', Apellido) as nombre_completo 
            FROM Operarios 
            WHERE Operativo = 1
            ORDER BY Nombre, Apellido";
    
    $operarios = $db->fetchAll($sql);
    
    echo json_encode([
        'success' => true, 
        'operarios' => $operarios
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error al obtener operarios: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>