<?php
// postulacion_requisicion_get_cargos.php

require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $sql = "SELECT 
                CodNivelesCargos as codigo,
                Nombre as nombre
            FROM NivelesCargos
            WHERE DisponibleRegistros = 1
            ORDER BY Nombre ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $cargos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'datos' => $cargos
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>