<?php
// postulacion_detalle_candidato_get_entrevistadores.php

require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    // Obtener operarios del área de Desarrollo Humano
    $sql = "SELECT DISTINCT
                o.CodOperario,
                CONCAT(o.Nombre, ' ', o.Apellido) as nombre_completo,
                nc.Nombre as cargo
            FROM Operarios o
            INNER JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
            INNER JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
            WHERE anc.Fin IS NULL
            AND nc.Area = 'Desarrollo Humano'
            AND o.Operativo = 1
            ORDER BY nc.Nombre, o.Nombre";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $entrevistadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'datos' => $entrevistadores
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>