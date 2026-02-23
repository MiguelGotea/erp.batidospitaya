<?php
/**
 * ajax/crm_bot_get_status.php
 * Estado del VPS WhatsApp — consulta BD directamente
 * También retorna QR si el VPS lo tiene disponible
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
    // Estado desde la tabla de sesión (actualizada por el VPS)
    $stmt = $conn->prepare("
        SELECT estado, numero_telefono, qr_base64, updated_at
        FROM wsp_sesion_vps_
        WHERE instancia = :inst
        LIMIT 1
    ");
    $stmt->execute([':inst' => $instancia]);
    $sesion = $stmt->fetch();

    if (!$sesion) {
        // No hay registro aún => el VPS nunca registró esta instancia
        echo json_encode([
            'estado' => 'desconectado',
            'numero' => null,
            'qr' => null,
            'fuente' => 'bd_sin_registro'
        ]);
        exit;
    }

    // Si el estado es qr_pendiente, intentar también el endpoint directo del VPS
    // para obtener el QR más fresco (con fallback al QR en BD)
    $qr = $sesion['qr_base64'] ?? null;

    if ($sesion['estado'] === 'qr_pendiente' && !$qr) {
        // Mapeo instancia → puerto VPS
        $puertos = ['wsp-clientes' => 3001, 'wsp-crmbot' => 3003];
        $puerto = $puertos[$instancia] ?? null;
        if ($puerto) {
            $ctx = stream_context_create(['http' => ['timeout' => 3]]);
            $raw = @file_get_contents("http://198.211.97.243:{$puerto}/qr", false, $ctx);
            if ($raw) {
                $vpsData = json_decode($raw, true);
                $qr = $vpsData['qr'] ?? $qr;
            }
        }
    }

    echo json_encode([
        'estado' => $sesion['estado'],
        'numero' => $sesion['numero_telefono'],
        'qr' => $qr,
        'fuente' => 'bd'
    ]);

} catch (Exception $e) {
    echo json_encode(['estado' => 'desconectado', 'numero' => null, 'qr' => null]);
}
