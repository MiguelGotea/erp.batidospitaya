<?php
/**
 * Guardar Firma Electrónica de Reembolso
 * Ubicación: /modulos/compras/reembolsos_firma_guardar.php
 *
 * Recibe: JSON { id_orden: int, firma_base64: "data:image/png;base64,..." }
 * Retorna: JSON { success: bool, firma_url: string } o { success: false, error: string }
 */

@session_start();
require_once '../../core/database/conexion.php';
require_once '../../core/permissions/permissions.php';

header('Content-Type: application/json');

// ── 1. Verificar sesión activa ────────────────────────────────────────────────
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'Sesión no iniciada.']);
    exit();
}

$usuarioId   = (int) $_SESSION['usuario_id'];
$cargoOperario = $_SESSION['datos_usuario_actual']['CodNivelesCargos'] ?? null;

// Si el cargo no está cacheado, consultarlo
if (!$cargoOperario) {
    try {
        $stmtCargo = $conn->prepare("
            SELECT nc.CodNivelesCargos
            FROM Operarios o
            JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
            JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
            WHERE o.CodOperario = ?
              AND (anc.Fin IS NULL OR anc.Fin > NOW())
            ORDER BY anc.Fecha DESC
            LIMIT 1
        ");
        $stmtCargo->execute([$usuarioId]);
        $cargoOperario = $stmtCargo->fetchColumn();
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Error al verificar cargo.']);
        exit();
    }
}

// ── 2. Verificar permiso firma_electronica ────────────────────────────────────
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';
if (!$esAdmin && !tienePermiso('reembolsos_ia_plantilla', 'firma_electronica', $cargoOperario)) {
    echo json_encode(['success' => false, 'error' => 'No tiene permiso para firmar órdenes.']);
    exit();
}

// ── 3. Leer y validar input ───────────────────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['id_orden']) || empty($input['firma_base64'])) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos.']);
    exit();
}

$idOrden    = (int) $input['id_orden'];
$firmaB64   = $input['firma_base64'];

// ── 4. Verificar que la orden exista y NO esté ya firmada ────────────────────
try {
    $stmtCheck = $conn->prepare("SELECT id, firma_imagen FROM reembolsos_solicitudes WHERE id = ?");
    $stmtCheck->execute([$idOrden]);
    $solicitud = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$solicitud) {
        echo json_encode(['success' => false, 'error' => 'Orden no encontrada.']);
        exit();
    }

    if (!empty($solicitud['firma_imagen'])) {
        echo json_encode(['success' => false, 'error' => 'Esta orden ya fue firmada anteriormente.']);
        exit();
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error al verificar la orden.']);
    exit();
}

// ── 5. Decodificar y validar el base64 ───────────────────────────────────────
// Formato esperado: "data:image/png;base64,iVBORw0KGgo..."
if (!preg_match('/^data:image\/png;base64,(.+)$/s', $firmaB64, $matches)) {
    echo json_encode(['success' => false, 'error' => 'Formato de imagen inválido.']);
    exit();
}

$imagenDecodificada = base64_decode($matches[1]);
if ($imagenDecodificada === false || strlen($imagenDecodificada) < 100) {
    echo json_encode(['success' => false, 'error' => 'Imagen de firma vacía o inválida.']);
    exit();
}

// ── 6. Crear directorio de destino si no existe ───────────────────────────────
$dirRelativo   = 'uploads/firmaordenreembolso';
$dirAbsoluto   = $_SERVER['DOCUMENT_ROOT'] . '/modulos/compras/' . $dirRelativo;

if (!is_dir($dirAbsoluto)) {
    if (!mkdir($dirAbsoluto, 0755, true)) {
        echo json_encode(['success' => false, 'error' => 'No se pudo crear el directorio de firmas.']);
        exit();
    }
    // Crear .htaccess para permitir servir imágenes PNG
    $htaccessContent = "Options -Indexes\n<FilesMatch \"\\.png$\">\n    Require all granted\n</FilesMatch>\n";
    file_put_contents($dirAbsoluto . '/.htaccess', $htaccessContent);
}

// ── 7. Guardar el archivo PNG ─────────────────────────────────────────────────
$timestamp  = date('Ymd_His');
$nombreArchivo = $idOrden . '_' . $timestamp . '.png';
$rutaAbsoluta  = $dirAbsoluto . '/' . $nombreArchivo;
$rutaRelativa  = 'modulos/compras/' . $dirRelativo . '/' . $nombreArchivo;

if (file_put_contents($rutaAbsoluta, $imagenDecodificada) === false) {
    echo json_encode(['success' => false, 'error' => 'No se pudo guardar el archivo de firma.']);
    exit();
}

// ── 8. Obtener IP del cliente ─────────────────────────────────────────────────
function obtenerIpCliente_firma() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}
$ipCliente = obtenerIpCliente_firma();

// ── 9. UPDATE en la base de datos ────────────────────────────────────────────
try {
    $stmtUpdate = $conn->prepare("
        UPDATE reembolsos_solicitudes
        SET firma_imagen      = ?,
            firma_firmado_por = ?,
            firma_firmado_at  = NOW(),
            firma_ip          = ?
        WHERE id = ?
          AND firma_imagen IS NULL
    ");
    $stmtUpdate->execute([$rutaRelativa, $usuarioId, $ipCliente, $idOrden]);

    if ($stmtUpdate->rowCount() === 0) {
        // Si rowCount es 0, puede que ya fue firmada por concurrencia
        @unlink($rutaAbsoluta); // Limpiar archivo huérfano
        echo json_encode(['success' => false, 'error' => 'La orden ya fue firmada o no se pudo actualizar.']);
        exit();
    }
} catch (PDOException $e) {
    @unlink($rutaAbsoluta);
    error_log("Error en reembolsos_firma_guardar.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error al registrar la firma en la base de datos.']);
    exit();
}

// ── 10. Respuesta exitosa ─────────────────────────────────────────────────────
echo json_encode([
    'success'   => true,
    'firma_url' => '/' . $rutaRelativa,
    'message'   => 'Firma registrada correctamente.'
]);
