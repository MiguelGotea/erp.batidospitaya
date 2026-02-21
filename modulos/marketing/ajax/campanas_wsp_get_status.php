<?php
/**
 * campanas_wsp_get_status.php
 * Proxy: consulta el estado del VPS a través de la API pública
 * Evita CORS — el browser llama a este PHP, que llama a la API
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('campanas_wsp', 'vista', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permiso']);
    exit;
}

// URL del endpoint público de la API
$apiUrl = 'https://api.batidospitaya.com/api/wsp/status.php';

try {
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 8,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ]
    ]);

    $respuesta = @file_get_contents($apiUrl, false, $ctx);

    if ($respuesta === false) {
        // API no accesible — reportar desconectado
        echo json_encode(['estado' => 'desconectado', 'qr' => null, '_error' => 'API no accesible']);
        exit;
    }

    $data = json_decode($respuesta, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['estado' => 'desconectado', 'qr' => null]);
        exit;
    }

    echo json_encode($data);

} catch (Exception $e) {
    echo json_encode(['estado' => 'desconectado', 'qr' => null]);
}
