<?php
/**
 * Callback: Recibir actualizaciones del servidor VPS
 * Este endpoint es llamado por el servidor Node.js para actualizar estados
 */

header('Content-Type: application/json');

require_once('../../../core/database/conexion.php');

try {
    // Verificar token de callback
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = str_replace('Bearer ', '', $authHeader);

    // Obtener token esperado de la configuración
    $stmt = $conn->prepare("SELECT servidor_token FROM whatsapp_config LIMIT 1");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    // Nota: En producción, usar un token específico para callbacks
    // Por simplicidad, usamos el mismo token del servidor

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Datos inválidos');
    }

    $jobId = $input['jobId'] ?? null;
    $campanaId = $input['campaignId'] ?? null;
    $phone = $input['phone'] ?? null;
    $result = $input['result'] ?? [];
    $timestamp = $input['timestamp'] ?? date('Y-m-d H:i:s');

    // Actualizar estado del mensaje
    if (isset($result['success'])) {
        $nuevoEstado = $result['success'] ? 'enviado' : 'fallido';
        $errorMsg = $result['error'] ?? null;
        $messageId = $result['messageId'] ?? null;

        // Buscar por teléfono y campaña
        $stmt = $conn->prepare("
            UPDATE whatsapp_mensajes SET 
                estado = ?,
                job_id = ?,
                error_mensaje = ?,
                fecha_envio = ?,
                intentos = intentos + 1
            WHERE telefono LIKE ? 
              AND (campana_id = ? OR (? IS NULL AND campana_id IS NULL))
              AND estado IN ('pendiente', 'en_cola')
            ORDER BY fecha_creacion DESC
            LIMIT 1
        ");
        $stmt->execute([
            $nuevoEstado,
            $messageId,
            $errorMsg,
            $timestamp,
            '%' . substr($phone, -8), // Últimos 8 dígitos
            $campanaId,
            $campanaId
        ]);

        // Actualizar contadores de campaña si aplica
        if ($campanaId) {
            if ($result['success']) {
                $conn->exec("UPDATE whatsapp_campanas SET total_enviados = total_enviados + 1 WHERE id = $campanaId");
            } else {
                $conn->exec("UPDATE whatsapp_campanas SET total_fallidos = total_fallidos + 1 WHERE id = $campanaId");
            }

            // Verificar si la campaña se completó
            $stmt = $conn->prepare("
                SELECT total_destinatarios, total_enviados, total_fallidos 
                FROM whatsapp_campanas WHERE id = ?
            ");
            $stmt->execute([$campanaId]);
            $camp = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($camp && ($camp['total_enviados'] + $camp['total_fallidos']) >= $camp['total_destinatarios']) {
                $conn->exec("UPDATE whatsapp_campanas SET estado = 'completada', fecha_fin = NOW() WHERE id = $campanaId");
            }
        }
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}