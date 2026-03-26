<?php
/**
 * pitayabot_admin_trigger_cron.php
 * Dispara manualmente un endpoint del scheduler para pruebas.
 * POST: { "clave": "briefing_diario" }
 *
 * Incluye el scheduler directamente por filesystem (evita loopback cURL en Hostinger).
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('pitayabot', 'resetear_sesion', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permiso']);
    exit;
}

$body  = json_decode(file_get_contents('php://input'), true);
$clave = trim($body['clave'] ?? '');

// Mapa clave → ruta relativa al repo api.batidospitaya.com
// El DOCUMENT_ROOT apunta a la raíz de erp.batidospitaya.com/public_html
// En Hostinger ambos dominios comparten el mismo home de usuario
$schedulerFiles = [
    'briefing_diario'      => 'briefing_diario.php',
    'recordatorio_reunion' => 'recordatorio_reunion.php',
    'resumen_fin_dia'      => 'resumen_fin_dia.php',
    'revision_semanal'     => 'revision_semanal.php',
    'cumpleanios'          => 'cumpleanios.php',
];

if (!$clave || !isset($schedulerFiles[$clave])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Clave de cron inválida']);
    exit;
}

// Calcular ruta del scheduler en el filesystem de Hostinger
// Estructura típica Hostinger: /home/user/domains/erp.batidospitaya.com/public_html
// api.batidospitaya.com está en:  /home/user/domains/api.batidospitaya.com/public_html
$docRoot       = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$baseDir       = dirname(dirname($docRoot)); // sube 2 niveles: public_html → domain → home/user/domains
$schedulerPath = $baseDir . '/api.batidospitaya.com/public_html/api/bot/scheduler/' . $schedulerFiles[$clave];

// Fallback: intentar estructura alternativa de Hostinger
if (!file_exists($schedulerPath)) {
    $altBase       = dirname($docRoot);  // sube 1 nivel: public_html → domain-root
    $schedulerPath = $altBase . '/api/bot/scheduler/' . $schedulerFiles[$clave];
}

if (!file_exists($schedulerPath)) {
    echo json_encode([
        'success' => false,
        'message' => 'No se pudo localizar el scheduler en el servidor. Ruta calculada: ' . $schedulerPath,
    ]);
    exit;
}

// Simular entorno de la petición para que auth_bot.php pase
// (definir el token como superglobal de servidor)
define('BOT_TOKEN_SECRETO', 'c5b155ba8f6877a2eefca0183ab18e37fe9a6accde340cf5c88af724822cbf50');
$_SERVER['HTTP_X_WSP_TOKEN'] = BOT_TOKEN_SECRETO;
$_SERVER['REQUEST_METHOD']   = 'GET';

// Capturar la salida del scheduler
ob_start();
try {
    include $schedulerPath;
} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Error al ejecutar scheduler: ' . $e->getMessage()]);
    exit;
}
$output = ob_get_clean();

$data = json_decode($output, true);

if (!is_array($data)) {
    echo json_encode([
        'success' => false,
        'message' => 'El scheduler no devolvió JSON válido',
        'raw'     => substr($output, 0, 500),
    ]);
    exit;
}

echo json_encode([
    'success'  => $data['success'] ?? false,
    'mensajes' => count($data['data'] ?? []),
    'message'  => $data['message'] ?? ($data['success'] ? 'Ejecutado correctamente' : 'El cron devolvió error'),
    'motivo'   => $data['motivo'] ?? null,
]);
