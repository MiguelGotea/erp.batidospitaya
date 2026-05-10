<?php
/**
 * planilla_wsp_reset_sesion.php
 * Solicita al VPS cambiar el número de WhatsApp vinculado a wsp-planilla.
 * Escribe reset_solicitado = 1 en wsp_sesion_vps_.
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json; charset=utf-8');

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('envio_wsp_planilla', 'resetear_sesion', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permiso para resetear la sesión']);
    exit;
}

try {
    $stmt = $conn->prepare("
        UPDATE wsp_sesion_vps_
        SET reset_solicitado = 1
        WHERE instancia = 'wsp-planilla'
    ");
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        // Si no hay fila aún, insertarla
        $stmtIns = $conn->prepare("
            INSERT INTO wsp_sesion_vps_ (instancia, estado, reset_solicitado)
            VALUES ('wsp-planilla', 'desconectado', 1)
            ON DUPLICATE KEY UPDATE reset_solicitado = 1
        ");
        $stmtIns->execute();
    }

    echo json_encode(['success' => true, 'mensaje' => 'Reset de sesión solicitado']);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
