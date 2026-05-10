<?php
/**
 * planilla_wsp_get_destinatarios.php
 * Dado una fecha_planilla, devuelve la lista de operarios con boleta emitida,
 * con nombre completo y teléfono (telefono_corporativo → Celular).
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

$fechaPlanilla = trim($_GET['fecha_planilla'] ?? '');
if (!$fechaPlanilla) {
    echo json_encode(['error' => 'fecha_planilla requerida']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT
            b.id_boleta,
            b.cod_operario,
            b.empleado_nombre,
            CONCAT(
                COALESCE(o.Nombre, ''), ' ',
                COALESCE(o.Nombre2, ''), ' ',
                COALESCE(o.Apellido, ''), ' ',
                COALESCE(o.Apellido2, '')
            ) AS nombre_completo,
            COALESCE(
                NULLIF(TRIM(o.telefono_corporativo), ''),
                NULLIF(TRIM(o.Celular), '')
            ) AS telefono
        FROM BoletaPago b
        INNER JOIN Operarios o ON o.CodOperario = b.cod_operario
        WHERE b.fecha_planilla = :fp
          AND (
              NULLIF(TRIM(o.telefono_corporativo), '') IS NOT NULL
              OR NULLIF(TRIM(o.Celular), '') IS NOT NULL
          )
        ORDER BY b.empleado_nombre ASC
    ");
    $stmt->execute([':fp' => $fechaPlanilla]);
    $destinatarios = $stmt->fetchAll();

    // Contar boletas sin teléfono (para notificar al usuario)
    $stmtSinTel = $conn->prepare("
        SELECT COUNT(*) AS sin_telefono
        FROM BoletaPago b
        INNER JOIN Operarios o ON o.CodOperario = b.cod_operario
        WHERE b.fecha_planilla = :fp
          AND NULLIF(TRIM(o.telefono_corporativo), '') IS NULL
          AND NULLIF(TRIM(o.Celular), '') IS NULL
    ");
    $stmtSinTel->execute([':fp' => $fechaPlanilla]);
    $sinTelefono = (int) $stmtSinTel->fetchColumn();

    echo json_encode([
        'success' => true,
        'destinatarios' => $destinatarios,
        'total' => count($destinatarios),
        'sin_telefono' => $sinTelefono
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
