<?php
/**
 * planilla_wsp_guardar.php
 * Crea o actualiza una programación de envío WSP para una fecha_planilla.
 * En lugar de usar una tabla auxiliar, marca directamente las boletas en BoletaPago
 * con wsp_programacion_id y resetea wsp_enviado=0.
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json; charset=utf-8');

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('envio_wsp_planilla', 'nueva_programacion', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permiso']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$fechaPlanilla = trim($body['fecha_planilla'] ?? '');
$fechaEnvio = trim($body['fecha_envio'] ?? '');
$mensaje = trim($body['mensaje'] ?? '');
$imagenB64 = $body['imagen_base64'] ?? null;
$progId = (int) ($body['prog_id'] ?? 0);

if (!$fechaPlanilla || !$fechaEnvio || !$mensaje) {
    echo json_encode(['error' => 'fecha_planilla, fecha_envio y mensaje son requeridos']);
    exit;
}

if (strtotime($fechaEnvio) <= time()) {
    echo json_encode(['error' => 'La fecha y hora de envío debe ser futura']);
    exit;
}

function formatearTelefonoNi($cel)
{
    $limpio = preg_replace('/\D/', '', $cel ?? '');
    if (strlen($limpio) === 8)
        return '+505' . $limpio;
    if (str_starts_with($limpio, '505') && strlen($limpio) === 11)
        return '+' . $limpio;
    if (strlen($limpio) > 8)
        return '+' . $limpio;
    return $limpio;
}

try {
    // ── Contar boletas con teléfono para esta fecha_planilla ──
    $stmtCount = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM BoletaPago b
        INNER JOIN Operarios o ON o.CodOperario = b.cod_operario
        WHERE b.fecha_planilla = :fp
          AND (
              NULLIF(TRIM(o.telefono_corporativo), '') IS NOT NULL
              OR NULLIF(TRIM(o.Celular), '') IS NOT NULL
          )
    ");
    $stmtCount->execute([':fp' => $fechaPlanilla]);
    $totalDest = (int) $stmtCount->fetchColumn();

    if ($totalDest === 0) {
        echo json_encode(['error' => 'No hay colaboradores con teléfono para esta fecha de planilla']);
        exit;
    }

    // ── Guardar imagen si la hay ──
    $imagenUrl = null;
    if ($imagenB64 && str_contains($imagenB64, 'base64,')) {
        $uploadDir = __DIR__ . '/../uploads/planilla_wsp/';
        if (!is_dir($uploadDir))
            mkdir($uploadDir, 0755, true);

        $extension = 'jpg';
        if (str_contains($imagenB64, 'image/png'))
            $extension = 'png';
        if (str_contains($imagenB64, 'image/webp'))
            $extension = 'webp';

        $nombreArchivo = 'planilla_' . time() . '_' . uniqid() . '.' . $extension;
        $datosImg = base64_decode(explode(',', $imagenB64)[1]);
        if (strlen($datosImg) > 5 * 1024 * 1024) {
            echo json_encode(['error' => 'La imagen supera el límite de 5MB']);
            exit;
        }
        file_put_contents($uploadDir . $nombreArchivo, $datosImg);
        $imagenUrl = '/modulos/rh/uploads/planilla_wsp/' . $nombreArchivo;
    }

    $conn->beginTransaction();
    $codOp = $usuario['CodOperario'];

    if ($progId > 0) {
        // ── Edición: limpiar la asociación anterior en BoletaPago ──
        $conn->prepare("
            UPDATE BoletaPago
            SET wsp_programacion_id = NULL,
                wsp_enviado         = 0,
                wsp_error           = NULL,
                wsp_fecha_envio     = NULL
            WHERE wsp_programacion_id = :pid
        ")->execute([':pid' => $progId]);

        // Actualizar la programación
        $conn->prepare("
            UPDATE wsp_planilla_programaciones_
            SET mensaje             = :mensaje,
                imagen_url          = COALESCE(:imagen_url, imagen_url),
                fecha_envio         = :fecha_envio,
                total_destinatarios = :total,
                total_enviados      = 0,
                total_errores       = 0,
                estado              = 'programada'
            WHERE id = :id AND estado = 'programada'
        ")->execute([
                    ':mensaje' => $mensaje,
                    ':imagen_url' => $imagenUrl,
                    ':fecha_envio' => $fechaEnvio,
                    ':total' => $totalDest,
                    ':id' => $progId
                ]);

        $campanaId = $progId;

    } else {
        // ── Nuevo ──
        $stmtIns = $conn->prepare("
            INSERT INTO wsp_planilla_programaciones_
                (fecha_planilla, mensaje, imagen_url, fecha_envio, estado,
                 total_destinatarios, usuario_creacion, fecha_creacion)
            VALUES
                (:fp, :mensaje, :imagen_url, :fecha_envio, 'programada',
                 :total, :usuario, CONVERT_TZ(NOW(),'+00:00','-06:00'))
        ");
        $stmtIns->execute([
            ':fp' => $fechaPlanilla,
            ':mensaje' => $mensaje,
            ':imagen_url' => $imagenUrl,
            ':fecha_envio' => $fechaEnvio,
            ':total' => $totalDest,
            ':usuario' => $codOp
        ]);
        $campanaId = (int) $conn->lastInsertId();
    }

    // ── Asociar boletas de esa fecha con esta programación ──
    // Solo las que tienen teléfono en Operarios
    $conn->prepare("
        UPDATE BoletaPago b
        INNER JOIN Operarios o ON o.CodOperario = b.cod_operario
        SET b.wsp_programacion_id = :pid,
            b.wsp_enviado         = 0,
            b.wsp_error           = NULL,
            b.wsp_fecha_envio     = NULL
        WHERE b.fecha_planilla = :fp
          AND (
              NULLIF(TRIM(o.telefono_corporativo), '') IS NOT NULL
              OR NULLIF(TRIM(o.Celular), '') IS NOT NULL
          )
    ")->execute([':pid' => $campanaId, ':fp' => $fechaPlanilla]);

    $conn->commit();
    echo json_encode(['success' => true, 'prog_id' => $campanaId, 'total' => $totalDest]);

} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['error' => 'Error al guardar: ' . $e->getMessage()]);
}
