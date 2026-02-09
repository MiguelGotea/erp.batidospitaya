<?php
/**
 * AJAX: Guardar configuración del servidor WhatsApp
 */

header('Content-Type: application/json');

require_once('../../../core/auth/auth.php');
require_once('../../../core/database/conexion.php');
require_once('../../../core/permissions/permissions.php');

try {
    $codNivelCargo = $_SESSION['cargo_cod'];

    if (!tienePermiso('whatsapp_campanas', 'configurar', $codNivelCargo)) {
        throw new Exception('No tienes permiso para configurar');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $url = trim($input['url'] ?? '');
    $token = trim($input['token'] ?? '');

    if (empty($url) || empty($token)) {
        throw new Exception('URL y Token son obligatorios');
    }

    // Validar formato URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new Exception('URL inválida');
    }

    // Verificar si existe configuración
    $stmt = $conn->prepare("SELECT id FROM whatsapp_config LIMIT 1");
    $stmt->execute();
    $config = $stmt->fetch();

    if ($config) {
        $stmt = $conn->prepare("
            UPDATE whatsapp_config SET 
                servidor_url = ?, 
                servidor_token = ?,
                fecha_actualizacion = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$url, $token, $config['id']]);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO whatsapp_config (servidor_url, servidor_token) 
            VALUES (?, ?)
        ");
        $stmt->execute([$url, $token]);
    }

    // Registrar en log
    $stmt = $conn->prepare("INSERT INTO whatsapp_logs (tipo, mensaje, usuario_id) VALUES ('config', 'Configuración actualizada', ?)");
    $stmt->execute([$_SESSION['usuario_id']]);

    echo json_encode([
        'success' => true,
        'mensaje' => 'Configuración guardada'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}