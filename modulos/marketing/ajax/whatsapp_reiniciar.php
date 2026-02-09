<?php
/**
 * AJAX: Reiniciar conexión WhatsApp
 */

header('Content-Type: application/json');

require_once('../../../core/auth/auth.php');
require_once('../../../core/database/conexion.php');
require_once('../../../core/permissions/permissions.php');

try {
    $codNivelCargo = $_SESSION['cargo_cod'];

    if (!tienePermiso('whatsapp_campanas', 'configurar', $codNivelCargo)) {
        throw new Exception('No tienes permiso para esta acción');
    }

    // Obtener configuración
    $stmt = $conn->prepare("SELECT * FROM whatsapp_config ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$config) {
        throw new Exception('Servidor no configurado');
    }

    // Llamar endpoint de reinicio
    $url = rtrim($config['servidor_url'], '/') . '/api/status/restart';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $config['servidor_token'],
            'Content-Type: application/json'
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Error al reiniciar el servidor');
    }

    // Registrar en log
    $stmt = $conn->prepare("INSERT INTO whatsapp_logs (tipo, mensaje, usuario_id) VALUES ('conexion', 'Reinicio de conexión solicitado', ?)");
    $stmt->execute([$_SESSION['usuario_id']]);

    echo json_encode([
        'success' => true,
        'mensaje' => 'Reinicio iniciado'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}