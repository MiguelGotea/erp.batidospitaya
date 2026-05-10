<?php
/**
 * campanas_wsp_guardar.php
 * Guarda una nueva campaña + inserta destinatarios con teléfono formateado
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

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

    $conn->beginTransaction();

    // ── Insertar campaña ──
    $totalDest = count($destinatarios);
    $codOp = $usuario['CodOperario'];

    $stmt = $conn->prepare("
        INSERT INTO wsp_campanas_
            (nombre, mensaje, imagen_url, fecha_envio, estado, total_destinatarios, usuario_creacion, fecha_creacion)
        VALUES (:nombre, :mensaje, :imagen_url, :fecha_envio, 'programada', :total, :usuario, CONVERT_TZ(NOW(),'+00:00','-06:00'))
    ");
    $stmt->execute([
        ':nombre' => $nombre,
        ':mensaje' => $mensaje,
        ':imagen_url' => $imagenUrl,
        ':fecha_envio' => $fechaEnvio,
        ':total' => $totalDest,
        ':usuario' => $codOp
    ]);
    $campanaId = (int) $conn->lastInsertId();

    // ── Insertar destinatarios con hora aleatoria dentro del día ──
    // Ventana horaria: 08:00 a 21:00 (hora Managua) = 46800 segundos de margen
    $stmtDest = $conn->prepare("
        INSERT INTO wsp_destinatarios_ (campana_id, id_cliente, nombre, telefono, sucursal, hora_envio_programada)
        VALUES (:campana_id, :id_cliente, :nombre, :telefono, :sucursal, :hora_prog)
    ");

    // Calcular base del día de envío en timestamp
    $fechaBase = new DateTime($fechaEnvio, new DateTimeZone('America/Managua'));
    // Inicio de ventana: el mismo día a las 08:00
    $inicioDia = clone $fechaBase;
    $inicioDia->setTime(8, 0, 0);
    // Si la fecha programada es posterior a las 08:00, usar la hora programada como inicio
    if ($fechaBase > $inicioDia) {
        $inicioDia = clone $fechaBase;
    }
    // Fin de ventana: ese mismo día a las 21:00
    $finDia = clone $fechaBase;
    $finDia->setTime(21, 0, 0);
    // Si el inicio ya supera las 21:00, extender el fin al día siguiente a las 21:00
    if ($inicioDia >= $finDia) {
        $finDia->modify('+1 day');
    }
    $ventanaSeg = $finDia->getTimestamp() - $inicioDia->getTimestamp();

    foreach ($destinatarios as $dest) {
        $idCliente = (int) ($dest['id'] ?? 0);
        $nom = trim($dest['nombre'] ?? '');
        $telefono = formatearTelefonoNi($dest['telefono'] ?? '');
        $sucursal = trim($dest['sucursal'] ?? '');

        if (!$idCliente || !$telefono)
            continue;

        // Hora aleatoria dentro de la ventana del día
        $offsetSeg = random_int(0, max(0, $ventanaSeg - 1));
        $horaIndividual = clone $inicioDia;
        $horaIndividual->modify("+{$offsetSeg} seconds");
        $horaProgStr = $horaIndividual->format('Y-m-d H:i:s');

        $stmtDest->execute([
            ':campana_id' => $campanaId,
            ':id_cliente' => $idCliente,
            ':nombre'     => $nom,
            ':telefono'   => $telefono,
            ':sucursal'   => $sucursal,
            ':hora_prog'  => $horaProgStr
        ]);
    }

    $conn->commit();
    echo json_encode(['success' => true, 'campana_id' => $campanaId]);

} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['error' => 'Error al guardar: ' . $e->getMessage()]);
}

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
