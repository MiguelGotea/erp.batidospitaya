<?php
/**
 * ajax/whatsapp_ping_directo.php
 * Realiza una prueba de envío directo al VPS de WhatsApp (sin guardar en BD)
 * POST — body: { instancia, numero, mensaje }
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');
$usuario = obtenerUsuarioActual();
$cargo = $usuario['CodNivelesCargos'];

// El permiso depende de qué módulo esté llamando, o si tiene permiso general de sistemas
$puedeCrm = tienePermiso('crm_bot', 'responder', $cargo);
$puedePlanilla = tienePermiso('envio_wsp_planilla', 'nueva_programacion', $cargo);
$puedeCampanas = tienePermiso('campanas_wsp', 'nueva_campana', $cargo);

if (!$puedeCrm && !$puedePlanilla && !$puedeCampanas) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sin permisos para realizar pruebas de envío']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$instancia = trim($body['instancia'] ?? '');
$numero = trim($body['numero'] ?? '');
$mensaje = trim($body['mensaje'] ?? 'Prueba de conexión Pitaya WhatsApp ⚡');

if (!$instancia || !$numero) {
    echo json_encode(['success' => false, 'error' => 'Instancia y número son requeridos']);
    exit;
}

// Limpiar número
$numeroLimpio = preg_replace('/\D/', '', $numero);
if (strlen($numeroLimpio) < 8) {
    echo json_encode(['success' => false, 'error' => 'Número de teléfono inválido (mínimo 8 dígitos)']);
    exit;
}

// Configuración de puertos del VPS (IP fija de DigitalOcean)
$token = 'c5b155ba8f6877a2eefca0183ab18e37fe9a6accde340cf5c88af724822cbf50';
$vps_ip = '198.211.97.243';
$puertos = [
    'wsp-clientes' => 3001,
    'wsp-crmbot'   => 3003,
    'wsp-planilla' => 3005
];

$puerto = $puertos[$instancia] ?? null;
if (!$puerto) {
    echo json_encode(['success' => false, 'error' => "Instancia '{$instancia}' no reconocida"]);
    exit;
}

try {
    $destino = $numeroLimpio . '@c.us';
    $payload = json_encode(['to' => $destino, 'message' => $mensaje]);

    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\nX-WSP-Token: {$token}\r\n",
            'content' => $payload,
            'timeout' => 15 // Timeout generoso para pruebas
        ]
    ]);

    $url = "http://{$vps_ip}:{$puerto}/send";
    $resp = @file_get_contents($url, false, $ctx);

    if ($resp) {
        $respData = json_decode($resp, true);
        if ($respData['success'] ?? false) {
            echo json_encode(['success' => true, 'mensaje' => 'Ping enviado con éxito']);
        } else {
            echo json_encode(['success' => false, 'error' => $respData['error'] ?? 'Error desconocido en el VPS']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'El VPS no respondió. Verifica que el bot esté conectado y en línea.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión: ' . $e->getMessage()]);
}
