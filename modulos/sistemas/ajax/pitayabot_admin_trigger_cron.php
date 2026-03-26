<?php
/**
 * pitayabot_admin_trigger_cron.php
 * Dispara manualmente un endpoint del scheduler para pruebas.
 * POST: { "clave": "briefing_diario" }
 */

// Capturar cualquier output antes del JSON (warnings, notices, etc.)
ob_start();

// Extender tiempo de ejecución para la llamada cURL al scheduler
set_time_limit(60);

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

// Limpiar cualquier output que hayan generado los includes anteriores
ob_clean();

header('Content-Type: application/json; charset=utf-8');

// Handler de errores PHP → JSON para que el front siempre reciba JSON válido
set_error_handler(function ($errno, $errstr) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => "PHP Error ($errno): $errstr"]);
    exit;
});

try {
    $usuario       = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    if (!tienePermiso('pitayabot', 'resetear_sesion', $cargoOperario)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Sin permiso']);
        exit;
    }

    $body  = json_decode(file_get_contents('php://input'), true);
    $clave = trim($body['clave'] ?? '');

    $endpointsPermitidos = [
        'briefing_diario'      => 'https://api.batidospitaya.com/api/bot/scheduler/briefing_diario.php',
        'recordatorio_reunion' => 'https://api.batidospitaya.com/api/bot/scheduler/recordatorio_reunion.php',
        'resumen_fin_dia'      => 'https://api.batidospitaya.com/api/bot/scheduler/resumen_fin_dia.php',
        'revision_semanal'     => 'https://api.batidospitaya.com/api/bot/scheduler/revision_semanal.php',
        'cumpleanios'          => 'https://api.batidospitaya.com/api/bot/scheduler/cumpleanios.php',
    ];

    if (!$clave || !isset($endpointsPermitidos[$clave])) {
        echo json_encode(['success' => false, 'message' => 'Clave de cron inválida']);
        exit;
    }

    // Token del bot
    $wspToken = 'c5b155ba8f6877a2eefca0183ab18e37fe9a6accde340cf5c88af724822cbf50';
    $url      = $endpointsPermitidos[$clave];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET        => true,
        CURLOPT_HTTPHEADER     => ['X-WSP-Token: ' . $wspToken],
        CURLOPT_TIMEOUT        => 50,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    $curlNo   = curl_errno($ch);
    curl_close($ch);

    if ($curlNo) {
        echo json_encode([
            'success' => false,
            'message' => "Error de red (#$curlNo): $curlErr",
        ]);
        exit;
    }

    $data = json_decode($response, true);

    if (!is_array($data)) {
        echo json_encode([
            'success' => false,
            'message' => "HTTP $httpCode — respuesta no-JSON del scheduler",
            'raw'     => substr($response, 0, 500),
        ]);
        exit;
    }

    echo json_encode([
        'success'   => $data['success'] ?? false,
        'mensajes'  => count($data['data'] ?? []),
        'message'   => $data['message'] ?? ($data['success'] ? 'Ejecutado correctamente' : 'El cron devolvió error'),
        'motivo'    => $data['motivo']   ?? null,
        'http_code' => $httpCode,
    ]);

} catch (Throwable $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Excepción: ' . $e->getMessage() . ' en ' . basename($e->getFile()) . ':' . $e->getLine(),
    ]);
}
