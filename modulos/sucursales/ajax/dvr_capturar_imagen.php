<?php
/**
 * dvr_capturar_imagen.php
 * Endpoint AJAX: captura una imagen estática del DVR Hikvision.
 * POST JSON → { canal: int (opcional), cod_sucursal: string (opcional) }
 * Si cod_sucursal se omite, usa la sucursal asignada al usuario.
 * Respuesta JSON → { success, path, filename, sucursal, canal, ip, timestamp, size_kb, message? }
 *
 * ARQUITECTURA DE CONEXION:
 *   - Si tunel_activo=1 y puerto_http_vps tiene valor:
 *       → conecta a http://VPS_IP:puerto_http_vps/ISAPI/...  (a través del túnel SSH)
 *   - Si tunel_activo=0 o sin tunnel:
 *       → conecta a http://portal_ip_local/ISAPI/...  (red local, solo para pruebas)
 */
require_once '../../../core/auth/auth.php';

header('Content-Type: application/json; charset=utf-8');

// IP pública del VPS donde viven los túneles SSH de DVR
define('DVR_VPS_IP', '198.211.97.243');

$usuario = obtenerUsuarioActual();
if (!$usuario) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesión no válida.']);
    exit;
}

// Leer body JSON
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// cod_sucursal recibido del frontend = sucursales.id (int) = DVR_Sucursales.cod_sucursal (int)
// Si no viene del frontend, NO hay fallback por varchar (sucursal_codigo del usuario es varchar)
$codSucursalParam = isset($input['cod_sucursal']) ? intval($input['cod_sucursal']) : 0;
$codSucursal      = $codSucursalParam > 0 ? $codSucursalParam : null;

if (!$codSucursal) {
    echo json_encode(['success' => false, 'message' => 'Sin sucursal especificada.']);
    exit;
}

// Canal: viene del frontend o se usa el de la BD
$canalParam = isset($input['canal']) ? intval($input['canal']) : null;

// ── Obtener configuración DVR de la sucursal ──────────────────────────────
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
        'message' => "No existe configuración DVR para la sucursal «{$codSucursal}»."
    ]);
    exit;
}

$ipLocal       = trim($dvr['portal_ip_local'] ?? '');
$usuario_dvr   = trim($dvr['portal_usuario']  ?? '');
$clave         = trim($dvr['portal_clave']     ?? '');
$canal         = $canalParam ?: (!empty($dvr['canal_caja']) ? intval($dvr['canal_caja']) : 101);
$tunelActivo   = !empty($dvr['tunel_activo']);
$puertoHttpVps = !empty($dvr['puerto_http_vps']) ? intval($dvr['puerto_http_vps']) : null;

if (!$ipLocal || !$usuario_dvr || !$clave) {
    echo json_encode([
        'success' => false,
        'message' => 'Configuración DVR incompleta (falta IP, usuario o clave).'
    ]);
    exit;
}

// ── Decidir cómo conectar al DVR ─────────────────────────────────────────
// Si el túnel SSH está activo, usamos el puerto HTTP expuesto en el VPS.
// Esto evita intentar conectar a la IP local del DVR desde el servidor ERP.
if ($tunelActivo && $puertoHttpVps) {
    // Conexión a través del túnel SSH inverso en el VPS
    $url       = "http://" . DVR_VPS_IP . ":{$puertoHttpVps}/ISAPI/Streaming/channels/{$canal}/picture";
    $ipDisplay = DVR_VPS_IP . ":{$puertoHttpVps} (túnel→{$ipLocal})";
} else {
    // Conexión directa a IP local (solo funciona si el ERP está en la misma red)
    $url       = "http://{$ipLocal}/ISAPI/Streaming/channels/{$canal}/picture";
    $ipDisplay = $ipLocal;
}

// ── Llamada ISAPI Hikvision ───────────────────────────────────────────────
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPAUTH       => CURLAUTH_DIGEST | CURLAUTH_BASIC,
    CURLOPT_USERPWD        => "{$usuario_dvr}:{$clave}",
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_FOLLOWLOCATION => true,
]);

$imageData = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// ── Manejo de errores ─────────────────────────────────────────────────────
if ($curlError) {
    echo json_encode([
        'success' => false,
        'message' => "Error de conexión con DVR ({$ipDisplay}): {$curlError}"
    ]);
    exit;
}

if ($httpCode !== 200 || !$imageData) {
    echo json_encode([
        'success' => false,
        'message' => "DVR respondió HTTP {$httpCode} sin imagen. Verifica IP/credenciales.",
        'url_usada' => $url
    ]);
    exit;
}

// Verificar que sea realmente una imagen JPEG
if (substr($imageData, 0, 3) !== "\xFF\xD8\xFF") {
    echo json_encode([
        'success' => false,
        'message' => 'La respuesta del DVR no es una imagen JPEG válida (posible error de auth).',
        'debug'   => substr($imageData, 0, 200)
    ]);
    exit;
}

// ── Guardar imagen ────────────────────────────────────────────────────────
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

// ── Respuesta exitosa ─────────────────────────────────────────────────────
echo json_encode([
    'success'   => true,
    'path'      => $publicUrl,
    'filename'  => $filename,
    'sucursal'  => $codSucursal,
    'canal'     => $canal,
    'ip'        => $ipDisplay,
    'timestamp' => date('Y-m-d H:i:s'),
    'size_kb'   => round(strlen($imageData) / 1024, 1),
]);
