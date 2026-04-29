<?php
// postulacion_requisicion_get_jefes.php

require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $sql = "SELECT 
                o.CodOperario,
                CONCAT(o.Nombre, ' ', o.Apellido) as nombre_completo,
                nc.Nombre as cargo
            FROM Operarios o
            INNER JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
            INNER JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
            WHERE anc.es_activo = 1
            AND anc.Fin IS NULL
            AND (nc.PermisosLider = 1 OR nc.EquipoLiderazgo = 1)
            ORDER BY nc.Nombre, o.Nombre";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $jefes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'datos' => $jefes
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>