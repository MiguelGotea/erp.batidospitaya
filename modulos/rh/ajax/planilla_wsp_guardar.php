<?php
/**
 * planilla_wsp_guardar.php
 * Crea o actualiza una programación de envío WSP para una fecha_planilla.
 * Inserta los destinatarios (operarios con boleta para esa fecha).
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
$progId = (int) ($body['prog_id'] ?? 0); // Si > 0 es edición

if (!$fechaPlanilla || !$fechaEnvio || !$mensaje) {
    echo json_encode(['error' => 'fecha_planilla, fecha_envio y mensaje son requeridos']);
    exit;
}

// Validar que la fecha_envio es futura
if (strtotime($fechaEnvio) <= time()) {
    echo json_encode(['error' => 'La fecha y hora de envío debe ser futura']);
    exit;
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

try {
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

    // ── Obtener todos los destinatarios de esta fecha_planilla ──
    $stmtDest = $conn->prepare("
        SELECT
            b.id_boleta,
            b.cod_operario,
            CONCAT(
                COALESCE(o.Nombre, ''), ' ',
                COALESCE(o.Nombre2, ''), ' ',
                COALESCE(o.Apellido, ''), ' ',
                COALESCE(o.Apellido2, '')
            ) AS nombre_completo,
            COALESCE(
                NULLIF(TRIM(o.telefono_corporativo), ''),
                NULLIF(TRIM(o.Celular), '')
            ) AS telefono
        FROM BoletaPago b
        INNER JOIN Operarios o ON o.CodOperario = b.cod_operario
        WHERE b.fecha_planilla = :fp
          AND (
              NULLIF(TRIM(o.telefono_corporativo), '') IS NOT NULL
              OR NULLIF(TRIM(o.Celular), '') IS NOT NULL
          )
    ");
    $stmtDest->execute([':fp' => $fechaPlanilla]);
    $destinatarios = $stmtDest->fetchAll();

    if (empty($destinatarios)) {
        echo json_encode(['error' => 'No hay colaboradores con teléfono para esta fecha de planilla']);
        exit;
    }

    $totalDest = count($destinatarios);
    $codOp = $usuario['CodOperario'];

    $conn->beginTransaction();

    if ($progId > 0) {
        // ── Edición: actualizar solo campos editables (si aún está programada) ──
        $stmtUpd = $conn->prepare("
            UPDATE wsp_planilla_programaciones_
            SET mensaje             = :mensaje,
                imagen_url          = COALESCE(:imagen_url, imagen_url),
                fecha_envio         = :fecha_envio,
                total_destinatarios = :total
            WHERE id = :id AND estado = 'programada'
        ");
        $stmtUpd->execute([
            ':mensaje' => $mensaje,
            ':imagen_url' => $imagenUrl,
            ':fecha_envio' => $fechaEnvio,
            ':total' => $totalDest,
            ':id' => $progId
        ]);

        // Borrar destinatarios anteriores y reinsertarlos frescos
        $conn->prepare("DELETE FROM wsp_planilla_destinatarios_ WHERE programacion_id = :pid")
            ->execute([':pid' => $progId]);

        $campanaId = $progId;

    } else {
        // ── Nuevo ──
        $stmtIns = $conn->prepare("
            INSERT INTO wsp_planilla_programaciones_
                (fecha_planilla, mensaje, imagen_url, fecha_envio, estado, total_destinatarios, usuario_creacion, fecha_creacion)
            VALUES
                (:fp, :mensaje, :imagen_url, :fecha_envio, 'programada', :total, :usuario,
                 CONVERT_TZ(NOW(),'+00:00','-06:00'))
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

    // ── Insertar destinatarios ──
    $stmtDestIns = $conn->prepare("
        INSERT INTO wsp_planilla_destinatarios_
            (programacion_id, id_boleta, cod_operario, nombre, telefono)
        VALUES (:pid, :id_boleta, :cod_op, :nombre, :telefono)
    ");
    foreach ($destinatarios as $d) {
        $tel = formatearTelefonoNi($d['telefono'] ?? '');
        if (!$tel)
            continue;
        $stmtDestIns->execute([
            ':pid' => $campanaId,
            ':id_boleta' => (int) $d['id_boleta'],
            ':cod_op' => (int) $d['cod_operario'],
            ':nombre' => trim($d['nombre_completo']),
            ':telefono' => $tel
        ]);
    }

    $conn->commit();
    echo json_encode(['success' => true, 'prog_id' => $campanaId, 'total' => $totalDest]);

} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['error' => 'Error al guardar: ' . $e->getMessage()]);
}
