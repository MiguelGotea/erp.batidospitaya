<?php
// postulacion_panel_control_get_produccion.php

require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    // Obtener cargos de producción por CodNivelesCargos específicos: 20, 23, 34
    $sql = "SELECT 
                nc.CodNivelesCargos as cod_cargo,
                nc.Nombre as nombre_cargo,
                nc.Area as area_cargo,
                COALESCE(pc.id, 0) as config_id,
                COALESCE(pc.cantidad_real, 0) as cantidad_real,
                COALESCE(pc.cantidad_adicional, 0) as cantidad_adicional,
                COALESCE(pc.obligatorio, 0) as obligatorio,
                COALESCE(pc.visible_web, 0) as visible_web,
                COALESCE(pc.salario_propuesto, 0) as salario_propuesto,
                COALESCE(pc.nivel_urgencia, 1) as nivel_urgencia,
                COALESCE(pc.ruta_pdf_cargo, '') as ruta_pdf_cargo,
                COALESCE(pc.ruta_banner, '') as ruta_banner,
                (SELECT COUNT(DISTINCT anc.CodOperario) 
                 FROM AsignacionNivelesCargos anc
                 INNER JOIN Contratos c ON anc.CodOperario = c.cod_operario
                 WHERE anc.CodNivelesCargos = nc.CodNivelesCargos
                 AND anc.Fecha <= CURDATE()
                 AND (anc.Fin IS NULL OR anc.Fin = '' OR anc.Fin >= CURDATE())
                 AND c.Finalizado = 0
                ) as cantidad_cubierta
            FROM NivelesCargos nc
            LEFT JOIN plazas_cargos pc ON nc.CodNivelesCargos = pc.cargo AND pc.area = 'Produccion'
            WHERE nc.CodNivelesCargos IN (20, 23, 34, 17, 19, 12, 9, 10)
            ORDER BY nc.Area ASC, nc.Nombre ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'datos' => $datos
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
