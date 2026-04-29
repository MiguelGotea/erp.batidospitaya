<?php
// postulacion_requisicion_get_operarios.php

require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $sql = "SELECT DISTINCT
                o.CodOperario,
                CONCAT(o.Nombre, ' ', o.Apellido) as nombre_completo,
                nc.Nombre as cargo
            FROM Operarios o
            LEFT JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario AND (anc.Fin IS NULL OR anc.Fin >= CURDATE()) AND anc.Fecha <= CURDATE()
            LEFT JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
            WHERE anc.CodNivelesCargos NOT IN (27)
            ORDER BY o.Nombre, o.Apellido";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $operarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'datos' => $operarios
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>