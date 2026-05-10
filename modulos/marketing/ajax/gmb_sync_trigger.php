<?php
/**
 * gmb_sync_trigger.php — Dispara sync manual del GMB Worker
 * POST /modulos/marketing/ajax/gmb_sync_trigger.php
 * Requiere sesión ERP activa + permiso configuracion_bot_resenasgoogle > vista
 *
 * Llama a api.batidospitaya.com/api/google/reviews/sync_trigger.php con X-WSP-Token
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('configuracion_bot_resenasgoogle', 'vista', $cargoOperario)) {
    echo json_encode(['success' => false, 'message' => 'Sin permiso para esta acción.']);
    exit;
}

// Llamar al endpoint de la API con X-WSP-Token
$apiUrl = 'https://api.batidospitaya.com/api/google/reviews/sync_trigger.php';
$token  = 'c5b155ba8f6877a2eefca0183ab18e37fe9a6accde340cf5c88af724822cbf50';

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => '',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-WSP-Token: ' . $token
    ],
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión con el worker: ' . $curlErr]);
    exit;
}

$data = json_decode($response, true);

if ($httpCode === 200 || $httpCode === 202) {
    echo json_encode([
        'success'   => true,
        'message'   => $data['message']   ?? 'Sync iniciado correctamente',
        'startedAt' => $data['startedAt'] ?? null,
    ]);
} elseif ($httpCode === 409) {
    echo json_encode([
        'success' => false,
        'running' => true,
        'message' => $data['error'] ?? 'El sync ya está en ejecución',
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'El worker respondió con error ' . $httpCode . ': ' . ($data['error'] ?? $response),
    ]);
}
