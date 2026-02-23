<?php
/**
 * campanas_wsp_reset_sesion.php
 * Solicita el reinicio de la sesión WhatsApp (cambio de número)
 * Solo accesible si el usuario tiene permiso 'resetear_sesion' en campanas_wsp
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json; charset=utf-8');

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('campanas_wsp', 'resetear_sesion', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sin permiso para resetear la sesión']);
    exit();
}

try {
    // Llamar al endpoint de la API bridge para solicitar el reset
    $apiUrl = 'https://api.batidospitaya.com/api/wsp/reset_sesion.php';
    $token = defined('WSP_TOKEN_ERP') ? WSP_TOKEN_ERP : '';

    // Leer token del archivo de config de la API (mismo que usa auth.php de la API)
    $tokenFile = __DIR__ . '/../../../core/config/wsp_token.php';
    if (file_exists($tokenFile)) {
        require_once $tokenFile;
    }

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['accion' => 'reset']),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-WSP-Token: ' . (defined('WSP_TOKEN_ERP') ? WSP_TOKEN_ERP : '')
        ],
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $respuesta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $data = json_decode($respuesta, true);
        echo json_encode([
            'success' => true,
            'mensaje' => 'Reset solicitado. El VPS cerrará la sesión en el próximo ciclo (máx. 60s) y mostrará un QR nuevo.',
            'api' => $data
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => "Error al contactar la API (HTTP $httpCode)",
            'detalle' => $respuesta
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
