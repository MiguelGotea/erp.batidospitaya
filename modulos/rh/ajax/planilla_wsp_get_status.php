<?php
/**
 * planilla_wsp_get_status.php
 * Retorna el estado del VPS y QR para la instancia wsp-planilla.
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
    $stmt = $conn->prepare("
        SELECT
            estado,
            qr_base64,
            numero_telefono,
            reset_solicitado,
            TIMESTAMPDIFF(SECOND, ultimo_ping, CONVERT_TZ(NOW(),'+00:00','-06:00')) AS segundos_sin_ping
        FROM wsp_sesion_vps_
        WHERE instancia = 'wsp-planilla'
        LIMIT 1
    ");
    $stmt->execute();
    $sesion = $stmt->fetch();

    if (!$sesion) {
        echo json_encode([
            'success' => true,
            'estado' => 'desconectado',
            'qr' => null,
            'numero' => null,
            'activo' => false
        ]);
        exit;
    }

    // Considerar desconectado si el último ping fue hace más de 2 minutos
    $activo = ($sesion['segundos_sin_ping'] !== null && $sesion['segundos_sin_ping'] <= 120);
    $estado = $activo ? $sesion['estado'] : 'desconectado';

    // Si hay reset solicitado, señalizar
    if ((int) $sesion['reset_solicitado'] === 1) {
        $estado = 'reset_pendiente';
    }

    echo json_encode([
        'success' => true,
        'estado' => $estado,
        'qr' => ($estado === 'qr_pendiente') ? $sesion['qr_base64'] : null,
        'numero' => $sesion['numero_telefono'],
        'activo' => $activo
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
