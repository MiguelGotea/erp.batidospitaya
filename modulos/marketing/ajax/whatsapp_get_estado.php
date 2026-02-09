<?php
/**
 * AJAX: Obtener estado del servidor WhatsApp
 */

header('Content-Type: application/json');

require_once('../../../core/auth/auth.php');
require_once('../../../core/database/conexion.php');

try {
    // Obtener configuración
    $stmt = $conn->prepare("SELECT * FROM whatsapp_config ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$config || empty($config['servidor_url']) || empty($config['servidor_token'])) {
        echo json_encode([
            'success' => false,
            'mensaje' => 'Servidor no configurado',
            'conexion' => 'not_configured'
        ]);
        exit;
    }

    // Llamar al servidor VPS
    $url = rtrim($config['servidor_url'], '/') . '/api/status';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $config['servidor_token'],
            'Content-Type: application/json'
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception('Error de conexión: ' . $error);
    }

    if ($httpCode !== 200) {
        throw new Exception('Error del servidor: HTTP ' . $httpCode);
    }

    $data = json_decode($response, true);

    if (!$data || !isset($data['success'])) {
        throw new Exception('Respuesta inválida del servidor');
    }

    // Actualizar estado en BD
    $stmt = $conn->prepare("UPDATE whatsapp_config SET estado_conexion = ?, ultimo_check = NOW() WHERE id = ?");
    $stmt->execute([$data['whatsapp']['connectionStatus'] ?? 'unknown', $config['id']]);

    // Preparar respuesta
    echo json_encode([
        'success' => true,
        'conexion' => $data['whatsapp']['connectionStatus'] ?? 'unknown',
        'qr' => $data['whatsapp']['qrCodeDataUrl'] ?? null,
        'stats' => [
            'messagesSentToday' => $data['whatsapp']['stats']['messagesSentToday'] ?? 0,
            'messagesSentThisHour' => $data['whatsapp']['stats']['messagesSentThisHour'] ?? 0,
            'queueWaiting' => $data['queue']['waiting'] ?? 0,
            'isPaused' => $data['whatsapp']['stats']['isPaused'] ?? false
        ]
    ]);

} catch (Exception $e) {
    // Registrar error en log
    $stmt = $conn->prepare("INSERT INTO whatsapp_logs (tipo, mensaje, datos) VALUES ('error', ?, ?)");
    $stmt->execute([$e->getMessage(), json_encode(['url' => $url ?? 'N/A'])]);

    echo json_encode([
        'success' => false,
        'mensaje' => $e->getMessage(),
        'conexion' => 'error'
    ]);
}