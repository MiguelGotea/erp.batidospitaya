<?php
/**
 * planilla_wsp_get_planillas.php
 * Retorna las fechas de planilla distintas de BoletaPago
 * junto con info de programación WSP si existe para cada fecha.
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json; charset=utf-8');

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('envio_wsp_planilla', 'vista', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permiso']);
    exit;
}

try {
    // Fechas de planilla distintas con total de boletas
    $stmt = $conn->prepare("
        SELECT
            b.fecha_planilla,
            COUNT(b.id_boleta)                                         AS total_boletas,
            DATE_FORMAT(b.fecha_planilla, '%d-%b-%Y')                  AS fecha_planilla_fmt,
            -- Programación WSP si existe
            p.id                                                        AS prog_id,
            p.estado                                                    AS prog_estado,
            DATE_FORMAT(p.fecha_envio, '%d-%b-%Y %H:%i')               AS prog_fecha_envio,
            p.total_destinatarios                                       AS prog_total,
            p.total_enviados                                            AS prog_enviados,
            p.total_errores                                             AS prog_errores,
            p.mensaje                                                   AS prog_mensaje,
            p.imagen_url                                                AS prog_imagen_url
        FROM BoletaPago b
        LEFT JOIN wsp_planilla_programaciones_ p ON p.fecha_planilla = b.fecha_planilla
        GROUP BY b.fecha_planilla, p.id, p.estado, p.fecha_envio,
                 p.total_destinatarios, p.total_enviados, p.total_errores,
                 p.mensaje, p.imagen_url
        ORDER BY b.fecha_planilla DESC
        LIMIT 50
    ");
    $stmt->execute();
    $planillas = $stmt->fetchAll();

    echo json_encode(['success' => true, 'planillas' => $planillas]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
