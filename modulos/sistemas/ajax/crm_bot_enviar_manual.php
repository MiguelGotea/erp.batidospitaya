<?php
/**
 * ajax/crm_bot_enviar_manual.php
 * Guarda mensaje del agente en BD + notifica al VPS vía HTTP interno
 * POST — body: { conversation_id, texto }
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json; charset=utf-8');
$usuario = obtenerUsuarioActual();
if (!tienePermiso('crm_bot', 'responder', $usuario['CodNivelesCargos'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sin permiso']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$convId = (int) ($body['conversation_id'] ?? 0);
$texto = trim($body['texto'] ?? '');

if (!$convId || !$texto) {
    echo json_encode(['success' => false, 'error' => 'conversation_id y texto requeridos']);
    exit;
}

try {
    // Obtener datos de la conversación
    $stmtC = $conn->prepare("SELECT instancia, numero_cliente, status FROM conversations WHERE id = :id LIMIT 1");
    $stmtC->execute([':id' => $convId]);
    $conv = $stmtC->fetch();
    if (!$conv) {
        echo json_encode(['success' => false, 'error' => 'Conversación no encontrada']);
        exit;
    }

    // Guardar mensaje del agente
    $stmtMsg = $conn->prepare("
        INSERT INTO messages (conversation_id, direction, sender_type, message_text, message_type, created_at)
        VALUES (:cid, 'out', 'agent', :txt, 'text', CONVERT_TZ(NOW(),'+00:00','-06:00'))
    ");
    $stmtMsg->execute([':cid' => $convId, ':txt' => $texto]);
    $msgId = $conn->lastInsertId();

    // Actualizar timestamp de conversación
    $conn->prepare("UPDATE conversations SET last_interaction_at = CONVERT_TZ(NOW(),'+00:00','-06:00'), updated_at = CONVERT_TZ(NOW(),'+00:00','-06:00') WHERE id = :id")
        ->execute([':id' => $convId]);

    $enviadoVPS = false;
    $nota = null;

    // Intentar enviar via VPS (solo si instancia definida)
    if ($conv['instancia']) {
        $token = 'c5b155ba8f6877a2eefca0183ab18e37fe9a6accde340cf5c88af724822cbf50';
        $destino = $conv['numero_cliente'] . '@c.us';

        // Mapeo instancia → puerto VPS
        $puertos = [
            'wsp-clientes' => 3001,
            'wsp-crmbot' => 3003,
        ];
        $puerto = $puertos[$conv['instancia']] ?? null;

        if ($puerto) {
            $payload = json_encode(['to' => $destino, 'message' => $texto]);
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\nX-WSP-Token: {$token}\r\n",
                    'content' => $payload,
                    'timeout' => 5
                ]
            ]);
            $resp = @file_get_contents("http://198.211.97.243:{$puerto}/send", false, $ctx);
            if ($resp) {
                $respData = json_decode($resp, true);
                $enviadoVPS = ($respData['success'] ?? false) === true;
                if (!$enviadoVPS)
                    $nota = 'Mensaje guardado pero no pudo enviarse por WhatsApp en este momento.';
            } else {
                $nota = 'Mensaje guardado. El VPS no está disponible ahora, el mensaje se enviará al reconectar.';
            }
        } else {
            $nota = 'Instancia no reconocida.';
        }
    }

    // Actualizar enviado_ok según resultado
    if (!$enviadoVPS) {
        $conn->prepare("UPDATE messages SET enviado_ok = 0 WHERE id = :id")->execute([':id' => $msgId]);
    }

    echo json_encode([
        'success' => true,
        'mensaje_id' => $msgId,
        'enviado_via_vps' => $enviadoVPS,
        'nota' => $nota
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
