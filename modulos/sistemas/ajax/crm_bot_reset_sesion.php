<?php
/**
 * ajax/crm_bot_reset_sesion.php
 * Solicita el reinicio de sesión para wsp-crmbot (igual que campanas)
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json; charset=utf-8');
$usuario = obtenerUsuarioActual();
if (!tienePermiso('crm_bot', 'resetear_sesion', $usuario['CodNivelesCargos'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sin permiso']);
    exit;
}

try {
    $instancia = $_POST['instancia'] ?? $_GET['instancia'] ?? 'wsp-crmbot';

    $stmt = $conn->prepare("
        INSERT INTO wsp_sesion_vps_ (instancia, estado, reset_solicitado)
        VALUES (:inst, 'desconectado', 1)
        ON DUPLICATE KEY UPDATE reset_solicitado = 1
    ");
    $stmt->execute([':inst' => $instancia]);

    echo json_encode([
        'success' => true,
        'mensaje' => "Reset solicitado para $instancia. El bot cerrará la sesión en el próximo ciclo y mostrará un QR nuevo.",
        'hora' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
