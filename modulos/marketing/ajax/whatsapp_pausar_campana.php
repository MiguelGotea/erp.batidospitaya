<?php
/**
 * AJAX: Pausar campaña
 */

header('Content-Type: application/json');

require_once('../../../core/auth/auth.php');
require_once('../../../core/database/conexion.php');
require_once('../../../core/permissions/permissions.php');

try {
    $codNivelCargo = $_SESSION['cargo_cod'];

    if (!tienePermiso('whatsapp_campanas', 'enviar', $codNivelCargo)) {
        throw new Exception('No tienes permiso para esta acción');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $campanaId = $input['id'] ?? null;

    if (!$campanaId) {
        throw new Exception('ID de campaña requerido');
    }

    // Actualizar estado
    $stmt = $conn->prepare("
        UPDATE whatsapp_campanas 
        SET estado = 'pausada' 
        WHERE id = ? AND estado IN ('en_proceso', 'programada')
    ");
    $stmt->execute([$campanaId]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('No se pudo pausar la campaña');
    }

    // Cancelar mensajes pendientes
    $stmt = $conn->prepare("
        UPDATE whatsapp_mensajes 
        SET estado = 'cancelado' 
        WHERE campana_id = ? AND estado = 'pendiente'
    ");
    $stmt->execute([$campanaId]);

    // También pausar en el servidor VPS
    $stmt = $conn->prepare("SELECT * FROM whatsapp_config ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($config) {
        $url = rtrim($config['servidor_url'], '/') . '/api/queue/pause';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $config['servidor_token'],
                'Content-Type: application/json'
            ]
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    echo json_encode([
        'success' => true,
        'mensaje' => 'Campaña pausada'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}