<?php
/**
 * campanas_wsp_guardar.php
 * Guarda una nueva campaña + inserta destinatarios con teléfono formateado
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json; charset=utf-8');

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('campanas_wsp', 'nueva_campana', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permiso']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);

$nombre = trim($body['nombre'] ?? '');
$mensaje = trim($body['mensaje'] ?? '');
$fechaEnvio = $body['fecha_envio'] ?? '';
$imagenB64 = $body['imagen_base64'] ?? null;
$destinatarios = $body['destinatarios'] ?? [];

if (!$nombre || !$mensaje || !$fechaEnvio || empty($destinatarios)) {
    echo json_encode(['error' => 'Datos incompletos']);
    exit;
}

try {
    // ── Guardar imagen si la hay ──
    $imagenUrl = null;
    if ($imagenB64 && str_contains($imagenB64, 'base64,')) {
        $uploadDir = __DIR__ . '/../uploads/wsp_imagenes/';
        if (!is_dir($uploadDir))
            mkdir($uploadDir, 0755, true);

        $extension = 'jpg';
        if (str_contains($imagenB64, 'image/png'))
            $extension = 'png';
        if (str_contains($imagenB64, 'image/webp'))
            $extension = 'webp';

        $nombreArchivo = 'camp_' . time() . '_' . uniqid() . '.' . $extension;
        $datosImg = base64_decode(explode(',', $imagenB64)[1]);

        if (strlen($datosImg) > 5 * 1024 * 1024) {
            echo json_encode(['error' => 'La imagen supera el límite de 5MB']);
            exit;
        }

        file_put_contents($uploadDir . $nombreArchivo, $datosImg);
        $imagenUrl = '/modulos/marketing/uploads/wsp_imagenes/' . $nombreArchivo;
    }

    $conn->begin_transaction();

    // ── Insertar campaña ──
    $stmt = $conn->prepare("
        INSERT INTO wsp_campanas_ 
            (nombre, mensaje, imagen_url, fecha_envio, estado, total_destinatarios, usuario_creacion, fecha_creacion)
        VALUES (?, ?, ?, ?, 'programada', ?, ?, CONVERT_TZ(NOW(),'+00:00','-06:00'))
    ");
    $totalDest = count($destinatarios);
    $codOp = $usuario['CodOperario'];
    $stmt->bind_param('ssssis', $nombre, $mensaje, $imagenUrl, $fechaEnvio, $totalDest, $codOp);
    $stmt->execute();
    $campanaId = $conn->insert_id;
    $stmt->close();

    // ── Insertar destinatarios ──
    $stmtDest = $conn->prepare("
        INSERT INTO wsp_destinatarios_ (campana_id, id_cliente, nombre, telefono, sucursal)
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($destinatarios as $dest) {
        $idCliente = (int) ($dest['id'] ?? 0);
        $nombre_d = trim($dest['nombre'] ?? '');
        $telefono = formatearTelefonoNi($dest['telefono'] ?? '');
        $sucursal = trim($dest['sucursal'] ?? '');

        if (!$idCliente || !$telefono)
            continue;

        $stmtDest->bind_param('iisss', $campanaId, $idCliente, $nombre_d, $telefono, $sucursal);
        $stmtDest->execute();
    }
    $stmtDest->close();

    $conn->commit();

    echo json_encode(['success' => true, 'campana_id' => $campanaId]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['error' => 'Error al guardar: ' . $e->getMessage()]);
}

/**
 * Formatea el teléfono para Nicaragua:
 * 8 dígitos → +505XXXXXXXX
 * Ya tiene prefijo → lo deja como está con +
 */
function formatearTelefonoNi($cel)
{
    $limpio = preg_replace('/\D/', '', $cel);
    if (strlen($limpio) === 8)
        return '+505' . $limpio;
    if (str_starts_with($limpio, '505') && strlen($limpio) === 11)
        return '+' . $limpio;
    if (strlen($limpio) > 8)
        return '+' . $limpio;
    return $limpio;
}
