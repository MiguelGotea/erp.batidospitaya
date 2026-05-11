<?php
/**
 * dvr_capturar_imagen.php
 * Endpoint AJAX: captura una imagen estática del DVR Hikvision de la sucursal del usuario.
 * POST → { canal: int (opcional, default canal_caja) }
 * Respuesta JSON → { success, path, filename, sucursal, canal, message? }
 */
require_once '../../../core/auth/auth.php';

header('Content-Type: application/json; charset=utf-8');

$usuario         = obtenerUsuarioActual();
$codSucursal     = $usuario['sucursal_codigo'] ?? null;

if (!$codSucursal) {
    echo json_encode(['success' => false, 'message' => 'Sin sucursal asignada o sesión no válida.']);
    exit;
}

// Parámetro opcional de canal (por POST JSON o form-data)
$input      = json_decode(file_get_contents('php://input'), true);
$canalParam = isset($input['canal']) ? intval($input['canal']) : null;
if (!$canalParam && isset($_POST['canal'])) {
    $canalParam = intval($_POST['canal']);
}

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

$ip      = trim($dvr['portal_ip_local'] ?? '');
$usuario_dvr = trim($dvr['portal_usuario'] ?? '');
$clave   = trim($dvr['portal_clave']    ?? '');
$canal   = $canalParam ?: (!empty($dvr['canal_caja']) ? intval($dvr['canal_caja']) : 101);

if (!$ip || !$usuario_dvr || !$clave) {
    echo json_encode([
        'success' => false,
        'message' => 'Configuración DVR incompleta (falta IP, usuario o clave).'
    ]);
    exit;
}

// ── Llamada ISAPI Hikvision ───────────────────────────────────────────────
$url = "http://{$ip}/ISAPI/Streaming/channels/{$canal}/picture";

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
        'message' => "Error de conexión con DVR ({$ip}): {$curlError}"
    ]);
    exit;
}

if ($httpCode !== 200 || !$imageData) {
    echo json_encode([
        'success' => false,
        'message' => "DVR respondió HTTP {$httpCode} sin imagen. Verifica IP/credenciales."
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
    'ip'        => $ip,
    'timestamp' => date('Y-m-d H:i:s'),
    'size_kb'   => round(strlen($imageData) / 1024, 1),
]);
