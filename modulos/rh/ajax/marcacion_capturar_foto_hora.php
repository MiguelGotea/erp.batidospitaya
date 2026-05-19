<?php
/**
 * marcacion_capturar_foto_hora.php
 * Endpoint AJAX: captura/retoma la foto del DVR para una marcación específica.
 *
 * POST JSON → { id_marcacion: int, tipo: "entrada"|"salida", cod_sucursal: string|int, fecha_hora: "2026-05-19T08:05:23" }
 * Respuesta JSON → { success, path, filename, tipo, id_marcacion, message? }
 *
 * ARQUITECTURA:
 *   ERP (Hostinger) → snapshot_server (VPS:8765/snapshot-hora) → DVR via túnel SSH
 *
 * Guarda en: modulos/rh/uploads/marcaciones/marcacion_{id_marcacion}_{tipo}.jpg
 * SOBREESCRIBE si ya existe (comportamiento de "Retomar foto").
 *
 * Solo accesible con permiso: foto_marcacion
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');

// URL del snapshot server en el VPS
define('SNAP_HORA_URL',   'http://198.211.97.243:8765/snapshot-hora');
define('SNAP_API_TOKEN',  'c5b155ba8f6877a2eefca0183ab18e37fe9a6accde340cf5c88af724822cbf50');
define('SNAP_DVR_VPS_IP', '127.0.0.1');

$usuario = obtenerUsuarioActual();
if (!$usuario) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesión no válida.']);
    exit;
}

// Verificar permiso foto_marcacion
if (!tienePermiso('historial_marcaciones_globales', 'foto_marcacion', $usuario['CodNivelesCargos'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permiso para capturar fotos de marcación.']);
    exit;
}

// Leer body JSON
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Validar id_marcacion
$idMarcacion = isset($input['id_marcacion']) ? intval($input['id_marcacion']) : 0;
if ($idMarcacion <= 0) {
    echo json_encode(['success' => false, 'message' => 'id_marcacion inválido.']);
    exit;
}

// Validar tipo
$tipo = trim($input['tipo'] ?? '');
if (!in_array($tipo, ['entrada', 'salida'], true)) {
    echo json_encode(['success' => false, 'message' => 'Tipo debe ser "entrada" o "salida".']);
    exit;
}

// Validar cod_sucursal
$codSucursalParam = isset($input['cod_sucursal']) ? intval($input['cod_sucursal']) : 0;
if ($codSucursalParam <= 0) {
    echo json_encode(['success' => false, 'message' => 'Sin sucursal especificada.']);
    exit;
}

// Validar fecha_hora (formato: "2026-05-19T08:05:23" o "2026-05-19T08:05")
$fechaHoraRaw = trim($input['fecha_hora'] ?? '');
if (!$fechaHoraRaw) {
    echo json_encode(['success' => false, 'message' => 'Debes especificar fecha_hora.']);
    exit;
}

// Normalizar a "2026-05-19 08:05:23"
$fechaHoraNorm = str_replace('T', ' ', $fechaHoraRaw);
if (strlen($fechaHoraNorm) === 16) {
    $fechaHoraNorm .= ':00';
}
$tsUnix = strtotime($fechaHoraNorm);
if (!$tsUnix) {
    echo json_encode(['success' => false, 'message' => 'Formato de fecha_hora inválido. Usa YYYY-MM-DDTHH:MM:SS.']);
    exit;
}

// No permitir fechas futuras (tolerancia 5 min para marcaciones recientes)
if ($tsUnix > time() + 300) {
    echo json_encode(['success' => false, 'message' => 'No se puede capturar imágenes de momentos futuros.']);
    exit;
}

$fechaHoraFormatted = date('Y-m-d H:i:s', $tsUnix);

// ── Obtener configuración DVR de la sucursal ─────────────────────────────────
try {
    $stmt = $conn->prepare("SELECT * FROM DVR_Sucursales WHERE cod_sucursal = ? LIMIT 1");
    $stmt->execute([$codSucursalParam]);
    $dvr = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
    exit;
}

if (!$dvr) {
    echo json_encode([
        'success' => false,
        'message' => "No existe configuración DVR para la sucursal «{$codSucursalParam}»."
    ]);
    exit;
}

$usuario_dvr = trim($dvr['portal_usuario'] ?? '');
$clave       = trim($dvr['portal_clave']   ?? '');
$puertoRtsp  = !empty($dvr['puerto_rtsp_vps']) ? intval($dvr['puerto_rtsp_vps']) : 0;
$puertoHttp  = !empty($dvr['puerto_http_vps']) ? intval($dvr['puerto_http_vps']) : 0;
$tunelActivo = !empty($dvr['tunel_activo']);
$canal       = !empty($dvr['canal_caja']) ? intval($dvr['canal_caja']) : 101;

if (!$usuario_dvr || !$clave) {
    echo json_encode([
        'success' => false,
        'message' => 'Configuración DVR incompleta (falta usuario o clave).'
    ]);
    exit;
}

if (!$tunelActivo || !$puertoRtsp) {
    echo json_encode([
        'success' => false,
        'message' => 'El túnel SSH de esta sucursal no está activo. Verifica tunel_activo y puerto_rtsp_vps en DVR_Sucursales.'
    ]);
    exit;
}

// ── Llamar al snapshot server en el VPS ──────────────────────────────────────
$payload = json_encode([
    'usuario'     => $usuario_dvr,
    'clave'       => $clave,
    'puerto_rtsp' => $puertoRtsp,
    'puerto_http' => $puertoHttp,
    'canal'       => $canal,
    'vps_ip'      => SNAP_DVR_VPS_IP,
    'fecha_hora'  => $fechaHoraFormatted,
]);

$ch = curl_init(SNAP_HORA_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-WSP-Token: ' . SNAP_API_TOKEN,
    ],
    CURLOPT_TIMEOUT        => 35,
    CURLOPT_CONNECTTIMEOUT => 5,
]);

$respuesta   = curl_exec($ch);
$httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError   = curl_error($ch);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

// ── Manejo de errores de conexión ────────────────────────────────────────────
if ($curlError) {
    echo json_encode([
        'success' => false,
        'message' => "No se pudo conectar al snapshot server: {$curlError}"
    ]);
    exit;
}

if ($httpCode !== 200 || strpos($contentType ?? '', 'image/jpeg') === false) {
    $errorData = json_decode($respuesta, true);
    $msg = $errorData['message'] ?? "Snapshot server respondió HTTP {$httpCode}";
    echo json_encode(['success' => false, 'message' => $msg, 'debug' => substr($respuesta, 0, 300)]);
    exit;
}

$imageData = $respuesta;

// Verificar que sea JPEG válido
if (substr($imageData, 0, 3) !== "\xFF\xD8\xFF") {
    echo json_encode([
        'success' => false,
        'message' => 'La respuesta no es una imagen JPEG válida.'
    ]);
    exit;
}

// ── Guardar imagen ────────────────────────────────────────────────────────────
$rootDir   = realpath(__DIR__ . '/../../../');
$uploadDir = $rootDir . '/modulos/rh/uploads/marcaciones';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Nombre fijo por id_marcacion + tipo → sobreescribe si ya existe
$filename  = 'marcacion_' . $idMarcacion . '_' . $tipo . '.jpg';
$filepath  = $uploadDir . '/' . $filename;
$publicUrl = '/modulos/rh/uploads/marcaciones/' . $filename;

if (file_put_contents($filepath, $imageData) === false) {
    echo json_encode([
        'success' => false,
        'message' => 'No se pudo guardar la imagen en el servidor.'
    ]);
    exit;
}

// ── Respuesta exitosa ─────────────────────────────────────────────────────────
echo json_encode([
    'success'      => true,
    'path'         => $publicUrl,
    'filename'     => $filename,
    'tipo'         => $tipo,
    'id_marcacion' => $idMarcacion,
    'canal'        => $canal,
    'sucursal'     => $codSucursalParam,
    'fecha_hora'   => $fechaHoraFormatted,
    'size_kb'      => round(strlen($imageData) / 1024, 1),
    'timestamp'    => date('Y-m-d H:i:s'),
]);
