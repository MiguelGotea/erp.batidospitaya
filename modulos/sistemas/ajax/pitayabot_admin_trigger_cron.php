<?php
/**
 * pitayabot_admin_trigger_cron.php
 * Dispara manualmente un endpoint del scheduler para pruebas.
 * POST: { "clave": "briefing_diario" }
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('pitayabot', 'resetear_sesion', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permiso']);
    exit;
}

$body  = json_decode(file_get_contents('php://input'), true);
$clave = trim($body['clave'] ?? '');

$endpointsPermitidos = [
    'briefing_diario'      => 'https://api.batidospitaya.com/api/bot/scheduler/briefing_diario.php',
    'recordatorio_reunion' => 'https://api.batidospitaya.com/api/bot/scheduler/recordatorio_reunion.php',
    'resumen_fin_dia'      => 'https://api.batidospitaya.com/api/bot/scheduler/resumen_fin_dia.php',
    'revision_semanal'     => 'https://api.batidospitaya.com/api/bot/scheduler/revision_semanal.php',
    'cumpleanios'          => 'https://api.batidospitaya.com/api/bot/scheduler/cumpleanios.php',
];

if (!$clave || !isset($endpointsPermitidos[$clave])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Clave de cron inválida']);
    exit;
}

// Leer el token desde auth_bot.php de la API.
// Ambos sitios están en el mismo servidor Hostinger, por lo que el include funciona
// usando la ruta del servidor (DOCUMENT_ROOT sube un nivel de public_html).
$authBotPath = dirname($_SERVER['DOCUMENT_ROOT']) . '/api.batidospitaya.com/api/bot/auth/auth_bot.php';
if (file_exists($authBotPath)) {
    // Suprimir el header JSON que auth_bot.php emite al incluirse
    ob_start();
    require_once $authBotPath;
    ob_end_clean();
    $wspToken = BOT_TOKEN_SECRETO;
} else {
    // Fallback: token hardcodeado como respaldo (mantener sincronizado con auth_bot.php)
    $wspToken = 'c5b155ba8f6877a2eefca0183ab18e37fe9a6accde340cf5c88af724822cbf50';
}

$url = $endpointsPermitidos[$clave];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET        => true,
    CURLOPT_HTTPHEADER     => ['X-WSP-Token: ' . $wspToken],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    echo json_encode(['success' => false, 'message' => "cURL error: $curlErr"]);
    exit;
}

$data = json_decode($response, true);

echo json_encode([
    'success'    => $httpCode === 200 && ($data['success'] ?? false),
    'http_code'  => $httpCode,
    'mensajes'   => count($data['data'] ?? []),
    'message'    => $data['message'] ?? ($httpCode !== 200 ? "HTTP $httpCode" : 'Ejecutado'),
    'raw'        => $data
]);
