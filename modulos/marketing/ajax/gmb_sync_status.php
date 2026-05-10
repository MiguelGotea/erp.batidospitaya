<?php
/**
 * gmb_sync_status.php — Consulta el estado del sync del GMB Worker
 * GET /modulos/marketing/ajax/gmb_sync_status.php
 * Requiere sesión ERP activa + permiso configuracion_bot_resenasgoogle > vista
 *
 * Llama a api.batidospitaya.com/api/google/reviews/sync_status.php con X-WSP-Token
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('configuracion_bot_resenasgoogle', 'vista', $cargoOperario)) {
    echo json_encode(['success' => false, 'message' => 'Sin permiso.']);
    exit;
}

$apiUrl = 'https://api.batidospitaya.com/api/google/reviews/sync_status.php';
$token  = 'c5b155ba8f6877a2eefca0183ab18e37fe9a6accde340cf5c88af724822cbf50';

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_HTTPHEADER     => ['X-WSP-Token: ' . $token],
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión: ' . $curlErr]);
    exit;
}

if ($httpCode !== 200) {
    echo json_encode(['success' => false, 'message' => 'El worker respondió ' . $httpCode]);
    exit;
}

// Pasar respuesta del worker al frontend
echo $response;
