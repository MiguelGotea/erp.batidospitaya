<?php
/**
 * ajax/crm_bot_get_status.php
 * Estado del VPS WhatsApp — consulta BD directamente
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json; charset=utf-8');
$usuario = obtenerUsuarioActual();
if (!tienePermiso('crm_bot', 'vista', $usuario['CodNivelesCargos'])) {
    http_response_code(403);
    exit;
}

$instancia = $_GET['instancia'] ?? 'wsp-crmbot';

try {
    $stmt = $conn->prepare("
        SELECT estado, numero_telefono
        FROM wsp_sesion_vps_
        WHERE instancia = :inst
        LIMIT 1
    ");
    $stmt->execute([':inst' => $instancia]);
    $sesion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sesion) {
        echo json_encode(['estado' => 'desconectado', 'numero' => null, 'qr' => null]);
        exit;
    }

    // Intentar obtener QR desde el VPS directamente si está pendiente
    $qr = null;
    if ($sesion['estado'] === 'qr_pendiente') {
        $puertos = ['wsp-clientes' => 3001, 'wsp-crmbot' => 3003];
        $puerto = $puertos[$instancia] ?? null;
        if ($puerto) {
            $ctx = stream_context_create(['http' => ['timeout' => 3]]);
            $raw = @file_get_contents("http://198.211.97.243:{$puerto}/qr", false, $ctx);
            if ($raw) {
                $vpsData = json_decode($raw, true);
                $qr = $vpsData['qr'] ?? null;
            }
        }
    }

    echo json_encode([
        'estado' => $sesion['estado'],
        'numero' => $sesion['numero_telefono'],
        'qr' => $qr
    ]);

} catch (Exception $e) {
    // Fallback: mostrar el error como header debug si es admin, sino desconectado
    echo json_encode(['estado' => 'desconectado', 'numero' => null, 'qr' => null, 'debug' => $e->getMessage()]);
}
