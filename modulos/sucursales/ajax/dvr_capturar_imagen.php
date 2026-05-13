<?php
/**
 * dvr_capturar_imagen.php
 * Endpoint AJAX: captura una imagen estatica del DVR Hikvision.
 * POST JSON → { canal: int (opcional), cod_sucursal: string (opcional) }
 * Respuesta JSON → { success, path, filename, sucursal, canal, ip, timestamp, size_kb, message? }
 *
 * ARQUITECTURA:
 *   ERP → snapshot_server (VPS:8765) → ffmpeg → tunel RTSP → DVR
 *
 *   El DVR DS-7104HGHI-M1 no soporta ISAPI HTTP snapshot, por eso
 *   usamos el snapshot_server en el VPS que captura via RTSP con ffmpeg.
 */
require_once '../../../core/auth/auth.php';

header('Content-Type: application/json; charset=utf-8');

// URL del snapshot server corriendo en el VPS
define('SNAPSHOT_SERVER_URL', 'http://198.211.97.243:8765/snapshot');
define('SNAPSHOT_API_TOKEN',  'c5b155ba8f6877a2eefca0183ab18e37fe9a6accde340cf5c88af724822cbf50');
define('DVR_VPS_IP',          '127.0.0.1');  // IP local en el VPS (tunel SSH)

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

$usuario_dvr  = trim($dvr['portal_usuario']   ?? '');
$clave        = trim($dvr['portal_clave']     ?? '');
$puertoRtsp   = !empty($dvr['puerto_rtsp_vps'])  ? intval($dvr['puerto_rtsp_vps'])  : 0;
$puertoHttp   = !empty($dvr['puerto_http_vps'])  ? intval($dvr['puerto_http_vps'])  : 0; // 0 = no hay tunel HTTP
$tunelActivo  = !empty($dvr['tunel_activo']);
$canal        = $canalParam ?: (!empty($dvr['canal_caja']) ? intval($dvr['canal_caja']) : 101);

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

// ── Llamar al snapshot server en el VPS ─────────────────────────────────
// Si el DVR tiene puerto HTTP (firmware moderno), el snapshot server
// usara ISAPI para imagen en vivo. Si no, usara RTSP + grabacion (~5 min lag).
$payload = json_encode([
    'usuario'      => $usuario_dvr,
    'clave'        => $clave,
    'puerto_rtsp'  => $puertoRtsp,
    'puerto_http'  => $puertoHttp,   // 0 = firmware antiguo, >0 = ISAPI disponible
    'canal'        => $canal,
    'vps_ip'       => DVR_VPS_IP,
]);

$ch = curl_init(SNAPSHOT_SERVER_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-WSP-Token: ' . SNAPSHOT_API_TOKEN,
    ],
    CURLOPT_TIMEOUT        => 25,
    CURLOPT_CONNECTTIMEOUT => 5,
]);

$respuesta  = curl_exec($ch);
$httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError  = curl_error($ch);
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

// Si el servidor devolvio JSON es un error
if ($httpCode !== 200 || strpos($contentType, 'image/jpeg') === false) {
    $errorData = json_decode($respuesta, true);
    $msg = $errorData['message'] ?? "Snapshot server respondio HTTP {$httpCode}";
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

$imageData = $respuesta;

// Verificar que sea JPEG valido
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

$filename  = 'dvr_' . $codSucursal . '_canal' . $canal . '_' . date('Ymd_His') . '.jpg';
$filepath  = $uploadDir . '/' . $filename;
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
    'success'   => true,
    'path'      => $publicUrl,
    'filename'  => $filename,
    'sucursal'  => $codSucursal,
    'canal'     => $canal,
    'ip'        => "VPS:8765 → tunel:{$puertoRtsp} → DVR",
    'timestamp' => date('Y-m-d H:i:s'),
    'size_kb'   => round(strlen($imageData) / 1024, 1),
]);
