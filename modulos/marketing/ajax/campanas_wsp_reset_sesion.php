<?php
/**
 * campanas_wsp_reset_sesion.php
 * Solicita el reinicio de la sesión WhatsApp (cambio de número)
 * El ERP escribe directamente en la BD — el VPS lo detecta en el próximo ciclo (60s)
 * Solo accesible si el usuario tiene permiso 'resetear_sesion' en campanas_wsp
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';


header('Content-Type: application/json; charset=utf-8');

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('campanas_wsp', 'resetear_sesion', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sin permiso para resetear la sesión']);
    exit();
}

try {
    // Activar el flag de reset para la instancia de este módulo
    // El VPS lo detecta en el próximo ciclo de pendientes.php (máx. 60s)
    $stmt = $conn->prepare("
        INSERT INTO wsp_sesion_vps_ (instancia, estado, reset_solicitado)
        VALUES ('wsp-clientes', 'desconectado', 1)
        ON DUPLICATE KEY UPDATE reset_solicitado = 1
    ");
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'mensaje' => 'Reset solicitado. El VPS cerrará la sesión en el próximo ciclo (máx. 60s) y mostrará un QR nuevo.',
        'hora' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
