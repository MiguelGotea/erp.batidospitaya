<?php
/**
 * dvr_capturar_marcacion.php
 * Endpoint AJAX: captura silenciosa de la imagen DVR al momento de una marcación.
 *
 * POST JSON → { id_marcacion: int, tipo: "entrada"|"salida", cod_sucursal: int|string }
 * Respuesta JSON → { success, path?, filename?, message? }
 *
 * ARQUITECTURA:
 *   marcacion.php (JS) → este endpoint → snapshot_server (VPS:8765/snapshot) → DVR via túnel SSH
 *   Intento 1: HTTP (ISAPI / en vivo) vía puerto_http_vps
 *   Intento 2 (fallback): RTSP playback vía snapshot-hora
 *
 * Guarda en: modulos/rh/uploads/marcaciones/marcacion_{id_marcacion}_{tipo}.jpg
 * Mismo naming que marcacion_capturar_foto_hora.php para compatibilidad con ver_marcaciones_todas_nuevo.
 *
 * SILENCIOSO: siempre devuelve JSON (nunca lanza excepciones al cliente).
 */
require_once '../../../core/auth/auth.php';

header('Content-Type: application/json; charset=utf-8');

// ── Constantes del snapshot server ───────────────────────────────────────────
define('DVR_SNAP_URL',      'http://198.211.97.243:8765/snapshot');
define('DVR_SNAP_HORA_URL', 'http://198.211.97.243:8765/snapshot-hora');
define('DVR_API_TOKEN',     'c5b155ba8f6877a2eefca0183ab18e37fe9a6accde340cf5c88af724822cbf50');
define('DVR_VPS_LOCAL_IP',  '127.0.0.1');

// ── Sesión válida ─────────────────────────────────────────────────────────────
$usuario = obtenerUsuarioActual();
if (!$usuario) {
    echo json_encode(['success' => false, 'message' => 'Sesion no valida.']);
    exit;
}

// ── Leer body JSON ────────────────────────────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$idMarcacion     = isset($input['id_marcacion'])  ? intval($input['id_marcacion'])  : 0;
$tipo            = trim($input['tipo']            ?? '');   // 'entrada' | 'salida'
$codSucursalRaw  = isset($input['cod_sucursal'])  ? intval($input['cod_sucursal'])  : 0;

if ($idMarcacion <= 0 || !in_array($tipo, ['entrada', 'salida'], true) || $codSucursalRaw <= 0) {
    echo json_encode(['success' => false, 'message' => 'Parametros invalidos.']);
    exit;
}

// ── Obtener configuración DVR ─────────────────────────────────────────────────
try {
    $stmt = $conn->prepare("SELECT * FROM DVR_Sucursales WHERE cod_sucursal = ? LIMIT 1");
    $stmt->execute([$codSucursalRaw]);
    $dvr = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
    exit;
}

if (!$dvr) {
    echo json_encode(['success' => false, 'message' => "Sin configuracion DVR para sucursal {$codSucursalRaw}."]);
    exit;
}

$usuario_dvr = trim($dvr['portal_usuario'] ?? '');
$clave       = trim($dvr['portal_clave']   ?? '');
$puertoRtsp  = !empty($dvr['puerto_rtsp_vps']) ? intval($dvr['puerto_rtsp_vps']) : 0;
$puertoHttp  = !empty($dvr['puerto_http_vps']) ? intval($dvr['puerto_http_vps']) : 0;
$tunelActivo = !empty($dvr['tunel_activo']);
$canal       = !empty($dvr['canal_caja']) ? intval($dvr['canal_caja']) : 101;

if (!$usuario_dvr || !$clave) {
    echo json_encode(['success' => false, 'message' => 'Credenciales DVR incompletas.']);
    exit;
}

if (!$tunelActivo || !$puertoRtsp) {
    echo json_encode(['success' => false, 'message' => 'Tunel SSH inactivo o sin puerto RTSP configurado.']);
    exit;
}

// ── INTENTO 1: Snapshot en vivo via HTTP (ISAPI) ──────────────────────────────
$imageData = null;

$payloadVivo = json_encode([
    'usuario'     => $usuario_dvr,
    'clave'       => $clave,
    'puerto_rtsp' => $puertoRtsp,
    'puerto_http' => $puertoHttp,
    'canal'       => $canal,
    'vps_ip'      => DVR_VPS_LOCAL_IP,
]);

$ch = curl_init(DVR_SNAP_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payloadVivo,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-WSP-Token: ' . DVR_API_TOKEN,
    ],
    CURLOPT_TIMEOUT        => 18,
    CURLOPT_CONNECTTIMEOUT => 5,
]);

$resp1       = curl_exec($ch);
$httpCode1   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError1  = curl_error($ch);
$contentType1 = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if (!$curlError1 && $httpCode1 === 200 && strpos($contentType1 ?? '', 'image/jpeg') !== false) {
    if (substr($resp1, 0, 3) === "\xFF\xD8\xFF") {
        $imageData = $resp1;
    }
}

// ── INTENTO 2: Snapshot por hora via RTSP (fallback) ─────────────────────────
if ($imageData === null) {
    // Usar hora actual menos 30 segundos como timestamp para el DVR
    $tsCaptura        = time() - 30;
    $fechaHoraFallback = date('Y-m-d H:i:s', $tsCaptura);

    $payloadHora = json_encode([
        'usuario'     => $usuario_dvr,
        'clave'       => $clave,
        'puerto_rtsp' => $puertoRtsp,
        'puerto_http' => $puertoHttp,
        'canal'       => $canal,
        'vps_ip'      => DVR_VPS_LOCAL_IP,
        'fecha_hora'  => $fechaHoraFallback,
    ]);

    $ch2 = curl_init(DVR_SNAP_HORA_URL);
    curl_setopt_array($ch2, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payloadHora,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-WSP-Token: ' . DVR_API_TOKEN,
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);

    $resp2       = curl_exec($ch2);
    $httpCode2   = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    $curlError2  = curl_error($ch2);
    $contentType2 = curl_getinfo($ch2, CURLINFO_CONTENT_TYPE);
    curl_close($ch2);

    if (!$curlError2 && $httpCode2 === 200 && strpos($contentType2 ?? '', 'image/jpeg') !== false) {
        if (substr($resp2, 0, 3) === "\xFF\xD8\xFF") {
            $imageData = $resp2;
        }
    }
}

// ── Sin imagen → fallo silencioso ────────────────────────────────────────────
if ($imageData === null) {
    echo json_encode(['success' => false, 'message' => 'No fue posible obtener imagen del DVR.']);
    exit;
}

// ── Guardar imagen ────────────────────────────────────────────────────────────
$rootDir   = realpath(__DIR__ . '/../../../');
$uploadDir = $rootDir . '/modulos/rh/uploads/marcaciones';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Mismo naming que marcacion_capturar_foto_hora.php
// → marcacion_{id_marcacion}_{tipo}.jpg
$filename  = 'marcacion_' . $idMarcacion . '_' . $tipo . '.jpg';
$filepath  = $uploadDir . '/' . $filename;
$publicUrl = '/modulos/rh/uploads/marcaciones/' . $filename;

if (file_put_contents($filepath, $imageData) === false) {
    echo json_encode(['success' => false, 'message' => 'No se pudo guardar imagen en servidor.']);
    exit;
}

// ── Respuesta exitosa ─────────────────────────────────────────────────────────
echo json_encode([
    'success'      => true,
    'path'         => $publicUrl,
    'filename'     => $filename,
    'tipo'         => $tipo,
    'id_marcacion' => $idMarcacion,
    'size_kb'      => round(strlen($imageData) / 1024, 1),
    'timestamp'    => date('Y-m-d H:i:s'),
]);
