<?php
// postulacion_get_cargos_reporta.php

require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    // Seleccionar cargos que tienen subordinados (aparecen en ReportaA)
    // O cargos que son reportados por otros. 
    // El requerimiento dice: "asegurándote que en la lista solo aparezcan Cargos Superiores según la columna ReportaA"
    // Esto implica que si un cargo X tiene ReportaA = Y, entonces Y es un superior.
    // Queremos listar todos los Y posibles.

    $sql = "SELECT DISTINCT 
                nc_superior.CodNivelesCargos,
                nc_superior.Nombre as cargo_nombre
            FROM NivelesCargos nc_sub
            INNER JOIN NivelesCargos nc_superior ON nc_sub.ReportaA = nc_superior.CodNivelesCargos
            WHERE nc_superior.Nombre IS NOT NULL
            ORDER BY nc_superior.Nombre ASC";

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