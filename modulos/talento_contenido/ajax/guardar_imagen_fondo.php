<?php
// guardar_imagen_fondo.php
// Sube la imagen de fondo del portal de talento y actualiza talento_configuracion.
header('Content-Type: application/json; charset=utf-8');
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
if (!tienePermiso('talento_contenido', 'editar', $usuario['CodNivelesCargos'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit();
}

// Directorio de fondos en el portal de talento
$dirFondos = realpath(__DIR__ . '/../../../../talento.batidospitaya/uploads/fondos');
if (!$dirFondos || !is_dir($dirFondos)) {
    $nuevaRuta = __DIR__ . '/../../../../talento.batidospitaya/uploads/fondos';
    if (!mkdir($nuevaRuta, 0755, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'No se puede crear el directorio de subida. Verifica la ruta del servidor.']);
        exit();
    }
    $dirFondos = realpath($nuevaRuta);
}

if (!isset($_FILES['imagen_fondo']) || $_FILES['imagen_fondo']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    $err = $_FILES['imagen_fondo']['error'] ?? 'No se recibió archivo';
    echo json_encode(['error' => "Error en la subida: $err"]);
    exit();
}

$file    = $_FILES['imagen_fondo'];
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'];
$finfo   = finfo_open(FILEINFO_MIME_TYPE);
$mime    = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowed)) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo de archivo no permitido. Usa JPG, PNG, WebP, SVG o GIF.']);
    exit();
}

$maxSize = 20 * 1024 * 1024; // 20 MB
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['error' => 'La imagen supera el tamaño máximo de 20 MB.']);
    exit();
}

$extension = match ($mime) {
    'image/jpeg'    => 'jpg',
    'image/png'     => 'png',
    'image/webp'    => 'webp',
    'image/gif'     => 'gif',
    'image/svg+xml' => 'svg',
    default         => 'jpg'
};

// Nombre fijo → siempre sobreescribimos para que la URL en BD no cambie
$nombreArchivo = 'fondo_portal.' . $extension;
$rutaFisica    = $dirFondos . DIRECTORY_SEPARATOR . $nombreArchivo;

if (!move_uploaded_file($file['tmp_name'], $rutaFisica)) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo guardar la imagen en el servidor.']);
    exit();
}

// Ruta pública relativa usada desde el portal de talento
$rutaPublica = 'uploads/fondos/' . $nombreArchivo;

try {
    $stmt = $conn->prepare(
        "INSERT INTO talento_configuracion (clave, grupo, etiqueta_display, valor, usuario_modifica, fecha_modificacion)
         VALUES ('imagen_fondo', 'apariencia', 'Imagen de Fondo del Portal', ?, ?, NOW())
         ON DUPLICATE KEY UPDATE valor = VALUES(valor), usuario_modifica = VALUES(usuario_modifica), fecha_modificacion = NOW()"
    );
    $stmt->execute([$rutaPublica, $usuario['CodOperario']]);

    echo json_encode([
        'success'      => true,
        'mensaje'      => 'Imagen de fondo actualizada con éxito',
        'ruta_publica' => $rutaPublica
    ]);
} catch (Exception $e) {
    if (file_exists($rutaFisica)) unlink($rutaFisica);
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
