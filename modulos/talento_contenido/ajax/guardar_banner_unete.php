<?php
// guardar_banner_unete.php
// Sube el banner de la sección "Únete al Equipo" y guarda la ruta en talento_configuracion.
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

// Ruta física del directorio de banners en el portal de talento
$dirBanners = realpath(__DIR__ . '/../../../../talento.batidospitaya/uploads/banners');
if (!$dirBanners || !is_dir($dirBanners)) {
    // Intentar crear el directorio si no existe
    $nuevaRuta = __DIR__ . '/../../../../talento.batidospitaya/uploads/banners';
    if (!mkdir($nuevaRuta, 0755, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'No se puede crear el directorio de subida. Verifica la ruta del servidor.']);
        exit();
    }
    $dirBanners = realpath($nuevaRuta);
}

if (!isset($_FILES['banner_unete']) || $_FILES['banner_unete']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    $err = $_FILES['banner_unete']['error'] ?? 'No se recibió archivo';
    echo json_encode(['error' => "Error en la subida: $err"]);
    exit();
}

$file = $_FILES['banner_unete'];
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowed)) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo de archivo no permitido. Usa JPG, PNG, WebP o GIF.']);
    exit();
}

$maxSize = 20 * 1024 * 1024; // 20 MB
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['error' => 'La imagen supera el tamaño máximo de 20MB.']);
    exit();
}

// Nombre fijo: siempre sobreescribimos con el mismo nombre para que la URL no cambie
$extension = match ($mime) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
    default      => 'jpg'
};
$nombreArchivo = 'banner_unete.' . $extension;
$rutaFisica    = $dirBanners . DIRECTORY_SEPARATOR . $nombreArchivo;

if (!move_uploaded_file($file['tmp_name'], $rutaFisica)) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo guardar la imagen en el servidor.']);
    exit();
}

// Ruta pública relativa usada desde el portal de talento
$rutaPublica = 'uploads/banners/' . $nombreArchivo;

try {
    $stmt = $conn->prepare(
        "INSERT INTO talento_configuracion (clave, grupo, etiqueta_display, valor, usuario_modifica, fecha_modificacion)
         VALUES ('banner_unete', 'banners', 'Banner Únete al Equipo', ?, ?, NOW())
         ON DUPLICATE KEY UPDATE valor = VALUES(valor), usuario_modifica = VALUES(usuario_modifica), fecha_modificacion = NOW()"
    );
    $stmt->execute([$rutaPublica, $usuario['CodOperario']]);

    echo json_encode([
        'success'      => true,
        'mensaje'      => 'Banner actualizado con éxito',
        'ruta_publica' => $rutaPublica
    ]);
} catch (Exception $e) {
    // Si falla la BD, borrar el archivo recién subido
    if (file_exists($rutaFisica)) unlink($rutaFisica);
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
