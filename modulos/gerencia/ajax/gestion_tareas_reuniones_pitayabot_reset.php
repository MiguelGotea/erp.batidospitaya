<?php
/**
 * gestion_tareas_reuniones_pitayabot_reset.php
 * Solicita el reinicio de sesión de wsp-pitayabot (cambiar número WhatsApp)
 * El VPS detecta el flag reset_solicitado=1 en el próximo heartbeat (~60s)
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
header('Content-Type: application/json; charset=utf-8');

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('pitayabot', 'resetear_sesion', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sin permiso para resetear la sesión de PitayaBot']);
    exit();
}

try {
    $stmt = $conn->prepare("
        INSERT INTO wsp_sesion_vps_ (instancia, estado, reset_solicitado)
        VALUES ('wsp-pitayabot', 'desconectado', 1)
        ON DUPLICATE KEY UPDATE reset_solicitado = 1
    ");
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'mensaje' => 'Reset solicitado. PitayaBot cerrará la sesión en el próximo ciclo (máx. 60s) y mostrará un QR nuevo.',
        'hora'    => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
