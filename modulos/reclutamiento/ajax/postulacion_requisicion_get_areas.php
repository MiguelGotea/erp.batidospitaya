<?php
// postulacion_requisicion_get_areas.php

require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    // Obtener áreas únicas de la tabla NivelesCargos
    $sql = "SELECT DISTINCT Area as area 
            FROM NivelesCargos 
            WHERE Area IS NOT NULL 
            AND Area != ''
            ORDER BY Area ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $areas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'success' => true,
        'datos' => $areas
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>