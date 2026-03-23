<?php
/**
 * gestion_tareas_reuniones_pitayabot_ping.php
 * Envía un mensaje de prueba desde wsp-pitayabot al número destino
 * Llama directamente al endpoint /ping del VPS (mismo patrón que campanas_wsp)
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json; charset=utf-8');

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('pitayabot', 'prueba_envio', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sin permiso para prueba de envío']);
    exit();
}

$input  = json_decode(file_get_contents('php://input'), true);
$numero = trim($input['numero'] ?? '');
$numero = preg_replace('/\D/', '', $numero);

if (empty($numero) || strlen($numero) < 8) {
    echo json_encode(['success' => false, 'error' => 'Número de teléfono inválido']);
    exit();
}

try {
    // Obtener IP del VPS desde el registro de sesión
    $stmt = $conn->prepare("SELECT ip_vps FROM wsp_sesion_vps_ WHERE instancia = 'wsp-pitayabot' LIMIT 1");
    $stmt->execute();
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fila || empty($fila['ip_vps'])) {
        echo json_encode(['success' => false, 'error' => 'No se puede determinar la dirección del VPS. Verifica que PitayaBot está activo.']);
        exit();
    }

    $ipVps = $fila['ip_vps'];
    $puerto = 3007;

    // Obtener WSP_TOKEN desde auth de la instancia
    // Reutilizamos el token almacenado en la tabla si existe, o usamos la constante
    $token = defined('WSP_TOKEN_SECRETO') ? WSP_TOKEN_SECRETO : '';

    $agente  = ($usuario['Nombre'] ?? '') . ' ' . ($usuario['Apellido'] ?? '');
    $payload = json_encode([
        'to'      => $numero,
        'message' => '🤖 *PitayaBot* — Mensaje de prueba desde el ERP. Si recibes esto, la conexión funciona correctamente.',
        'agente'  => trim($agente)
    ]);

    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\nX-WSP-Token: {$token}\r\n",
            'content' => $payload,
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ]);

    $url      = "http://{$ipVps}:{$puerto}/ping";
    $respuesta = @file_get_contents($url, false, $ctx);

    if ($respuesta === false) {
        echo json_encode(['success' => false, 'error' => 'No se pudo conectar al VPS. Verifica que PitayaBot está corriendo.']);
        exit();
    }

    $data = json_decode($respuesta, true);
    echo json_encode($data ?: ['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
