<?php
/**
 * dvr_capturar_imagen_hora.php
 * Endpoint AJAX: obtiene una imagen del DVR de una sucursal en un momento específico.
 *
 * POST JSON → { canal: int, cod_sucursal: string|int, fecha_hora: "2026-05-18T14:30" }
 * Respuesta JSON → { success, path, filename, sucursal, canal, ip,
 *                    timestamp, fecha_hora_solicitada, size_kb, message? }
 *
 * ARQUITECTURA:
 *   ERP (Hostinger) → snapshot_server (VPS:8765/snapshot-hora) → DVR via túnel SSH
 *
 *   El snapshot_server usará el canal RTSP de playback con el timestamp enviado
 *   para extraer un frame del momento solicitado.
 */
require_once '../../../core/auth/auth.php';

header('Content-Type: application/json; charset=utf-8');

// URL del snapshot server corriendo en el VPS (endpoint de hora específica)
define('SNAPSHOT_HORA_URL',  'http://198.211.97.243:8765/snapshot-hora');
define('SNAPSHOT_API_TOKEN', 'c5b155ba8f6877a2eefca0183ab18e37fe9a6accde340cf5c88af724822cbf50');
define('DVR_VPS_IP',         '127.0.0.1');  // IP local en el VPS (túnel SSH)

$usuario = obtenerUsuarioActual();
if (!$usuario) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesion no valida.']);
    exit;
}

// Leer body JSON
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$codSucursalParam = isset($input['cod_sucursal']) ? intval($input['cod_sucursal']) : 0;
$codSucursal      = $codSucursalParam > 0 ? $codSucursalParam : null;

if (!$codSucursal) {
    echo json_encode(['success' => false, 'message' => 'Sin sucursal especificada.']);
    exit;
}

// Validar fecha_hora (formato: "2026-05-18T14:30" o "2026-05-18T14:30:00")
$fechaHoraRaw = trim($input['fecha_hora'] ?? '');
if (!$fechaHoraRaw) {
    echo json_encode(['success' => false, 'message' => 'Debes especificar fecha_hora.']);
    exit;
}

// Normalizar a "2026-05-18 14:30:00"
$fechaHoraRaw = str_replace('T', ' ', $fechaHoraRaw);
if (strlen($fechaHoraRaw) === 16) {
    $fechaHoraRaw .= ':00';  // agregar segundos si no vienen
}
$tsUnix = strtotime($fechaHoraRaw);
if (!$tsUnix) {
    echo json_encode(['success' => false, 'message' => 'Formato de fecha/hora inválido. Usa YYYY-MM-DD HH:MM.']);
    exit;
}

// No permitir fechas futuras
if ($tsUnix > time() + 60) {
    echo json_encode(['success' => false, 'message' => 'No se puede solicitar imágenes de momentos futuros.']);
    exit;
}

$fechaHoraFormatted = date('Y-m-d H:i:s', $tsUnix);

$canalParam = isset($input['canal']) ? intval($input['canal']) : null;

// ── Obtener configuracion DVR de la sucursal ─────────────────────────────
try {
    $stmt = $conn->prepare("SELECT * FROM DVR_Sucursales WHERE cod_sucursal = ? LIMIT 1");
    $stmt->execute([$codSucursal]);
    $dvr = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
    exit;
}

if (!$dvr) {
    echo json_encode([
        'success' => false,
        'message' => "No existe configuracion DVR para la sucursal «{$codSucursal}»."
    ]);
    exit;
}

$usuario_dvr = trim($dvr['portal_usuario'] ?? '');
$clave       = trim($dvr['portal_clave']   ?? '');
$puertoRtsp  = !empty($dvr['puerto_rtsp_vps']) ? intval($dvr['puerto_rtsp_vps']) : 0;
$puertoHttp  = !empty($dvr['puerto_http_vps']) ? intval($dvr['puerto_http_vps']) : 0;
$tunelActivo = !empty($dvr['tunel_activo']);
$canal       = $canalParam ?: (!empty($dvr['canal_caja']) ? intval($dvr['canal_caja']) : 101);

if (!$usuario_dvr || !$clave) {
    echo json_encode([
        'success' => false,
        'message' => 'Configuracion DVR incompleta (falta usuario o clave).'
    ]);
    exit;
}

if (!$tunelActivo || !$puertoRtsp) {
    echo json_encode([
        'success' => false,
        'message' => 'El tunel SSH de esta sucursal no esta activo. Verifica tunel_activo y puerto_rtsp_vps en DVR_Sucursales.'
    ]);
    exit;
}

// ── Llamar al snapshot server en el VPS con el timestamp ────────────────
// El snapshot server usará RTSP Playback para extraer el frame exacto.
// Si el DVR tiene puerto HTTP (firmware moderno), también puede usar
// la API de Playback ISAPI de Hikvision.
$payload = json_encode([
    'usuario'      => $usuario_dvr,
    'clave'        => $clave,
    'puerto_rtsp'  => $puertoRtsp,
    'puerto_http'  => $puertoHttp,
    'canal'        => $canal,
    'vps_ip'       => DVR_VPS_IP,
    'fecha_hora'   => $fechaHoraFormatted,   // "2026-05-18 14:30:00" (hora local del servidor)
]);

$ch = curl_init(SNAPSHOT_HORA_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-WSP-Token: ' . SNAPSHOT_API_TOKEN,
    ],
    CURLOPT_TIMEOUT        => 35,
    CURLOPT_CONNECTTIMEOUT => 5,
]);

$respuesta   = curl_exec($ch);
$httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError   = curl_error($ch);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

// ── Manejo de errores de conexion ───────────────────────────────────────
if ($curlError) {
    echo json_encode([
        'success' => false,
        'message' => "No se pudo conectar al snapshot server del VPS: {$curlError}"
    ]);
    exit;
}

// Si el servidor devolvió JSON → es un error
if ($httpCode !== 200 || strpos($contentType ?? '', 'image/jpeg') === false) {
    $errorData = json_decode($respuesta, true);
    $msg = $errorData['message'] ?? "Snapshot server respondio HTTP {$httpCode}";
    echo json_encode(['success' => false, 'message' => $msg, 'debug' => substr($respuesta, 0, 300)]);
    exit;
}

$imageData = $respuesta;

// Verificar que sea JPEG válido
if (substr($imageData, 0, 3) !== "\xFF\xD8\xFF") {
    echo json_encode([
        'success' => false,
        'message' => 'La respuesta no es una imagen JPEG valida.'
    ]);
    exit;
}

// ── Guardar imagen ───────────────────────────────────────────────────────
$rootDir   = realpath(__DIR__ . '/../../../');
$uploadDir = $rootDir . '/uploads/sucursales/dvr_capturas';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Nombre de archivo incluye la hora solicitada para fácil identificación
$tsTag    = date('Ymd_Hi', $tsUnix);
$filename = 'dvr_' . $codSucursal . '_canal' . $canal . '_hora_' . $tsTag . '_cap' . date('His') . '.jpg';
$filepath = $uploadDir . '/' . $filename;
$publicUrl = '/uploads/sucursales/dvr_capturas/' . $filename;

if (file_put_contents($filepath, $imageData) === false) {
    echo json_encode([
        'success' => false,
        'message' => 'No se pudo guardar la imagen en el servidor.'
    ]);
    exit;
}

// ── Respuesta exitosa ────────────────────────────────────────────────────
echo json_encode([
    'success'              => true,
    'path'                 => $publicUrl,
    'filename'             => $filename,
    'sucursal'             => $codSucursal,
    'canal'                => $canal,
    'ip'                   => "VPS:8765 → tunel:{$puertoRtsp} → DVR",
    'timestamp'            => date('Y-m-d H:i:s'),
    'fecha_hora_solicitada'=> $fechaHoraFormatted,
    'size_kb'              => round(strlen($imageData) / 1024, 1),
]);
