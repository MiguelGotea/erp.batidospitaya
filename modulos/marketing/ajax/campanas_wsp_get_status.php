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

// Intentar con proxy primero, luego con api directa como fallback
$urls = [
    'proxy' => 'https://proxy.batidospitaya.com/api/wsp/status.php?instancia=wsp-clientes',
    'api'   => 'https://api.batidospitaya.com/api/wsp/status.php?instancia=wsp-clientes',
];

$ctx = stream_context_create([
    'http' => [
        'timeout'       => 8,
        'ignore_errors' => true,
    ],
    'ssl' => [
        'verify_peer'      => false,   // Desactivado para diagnosticar SSL
        'verify_peer_name' => false,
    ]
]);

$respuesta   = false;
$urlUsada    = null;
$erroresPHP  = [];

foreach ($urls as $origen => $url) {
    error_clear_last();
    $respuesta = @file_get_contents($url, false, $ctx);
    if ($respuesta !== false) {
        $urlUsada = $origen;
        break;
    }
    $erroresPHP[$origen] = error_get_last()['message'] ?? 'desconocido';
}

if ($respuesta === false) {
    echo json_encode([
        'estado'   => 'desconectado',
        'qr'       => null,
        '_error'   => 'Ninguna URL respondió',
        '_detalles'=> $erroresPHP,
    ]);
    exit;
}

$data = json_decode($respuesta, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'estado'    => 'desconectado',
        'qr'        => null,
        '_error'    => 'JSON inválido',
        '_url_usada'=> $urlUsada,
        '_raw'      => substr($respuesta, 0, 200),
    ]);
    exit;
}

// Añadir qué URL funcionó (útil para diagnóstico desde DevTools)
$data['_url_usada'] = $urlUsada;
echo json_encode($data);
