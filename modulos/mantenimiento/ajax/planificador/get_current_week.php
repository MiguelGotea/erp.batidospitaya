<?php
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

try {
    global $db;
    
    $sql = "SELECT numero_semana 
            FROM FechasSistema 
            WHERE fecha = CURDATE() 
            LIMIT 1";
    
    $result = $db->fetchOne($sql);
    
    if ($result) {
        echo json_encode(['week_number' => $result['numero_semana']]);
    } else {
        // Si no encuentra la fecha actual, usar 518 como fallback
        echo json_encode(['week_number' => 518]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>