<?php
/**
 * AJAX: Probar conexión con servidor WhatsApp
 */

header('Content-Type: application/json');

require_once('../../../core/auth/auth.php');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $url = trim($input['url'] ?? '');
    $token = trim($input['token'] ?? '');

    if (empty($url) || empty($token)) {
        throw new Exception('URL y Token son obligatorios');
    }

    // Probar endpoint de health (sin auth)
    $healthUrl = rtrim($url, '/') . '/health';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $healthUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception('No se puede conectar: ' . $error);
    }

    if ($httpCode !== 200) {
        throw new Exception('El servidor no responde correctamente (HTTP ' . $httpCode . ')');
    }

    // Probar endpoint autenticado
    $statusUrl = rtrim($url, '/') . '/api/status';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $statusUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 401 || $httpCode === 403) {
        throw new Exception('Token de autenticación inválido');
    }

    if ($httpCode !== 200) {
        throw new Exception('Error en la autenticación (HTTP ' . $httpCode . ')');
    }

    $data = json_decode($response, true);

    echo json_encode([
        'success' => true,
        'mensaje' => 'Conexión exitosa',
        'estado' => $data['whatsapp']['connectionStatus'] ?? 'desconocido'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}