<?php
// guardar_imagen_nosotros.php
// Sube la imagen grupal de la sección Sobre Nosotros y actualiza talento_configuracion.
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

// Ruta física donde se almacenarán las imágenes de nosotros
// El ERP y el portal de talento comparten el mismo servidor Hostinger,
// pero tienen DocumentRoots diferentes. Ajusta la ruta absoluta si es necesario.
define('TALENTO_UPLOADS_NOSOTROS', realpath(__DIR__ . '/../../../../talento.batidospitaya/uploads/nosotros'));

if (!TALENTO_UPLOADS_NOSOTROS || !is_dir(TALENTO_UPLOADS_NOSOTROS)) {
    // Intentar crear el directorio si no existe
    if (!mkdir(__DIR__ . '/../../../../talento.batidospitaya/uploads/nosotros', 0755, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'No se puede crear el directorio de subida. Verifica la ruta del servidor.']);
        exit();
    }
}

if (!isset($_FILES['imagen_nosotros']) || $_FILES['imagen_nosotros']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    $err = $_FILES['imagen_nosotros']['error'] ?? 'No se recibió archivo';
    echo json_encode(['error' => "Error en la subida: $err"]);
    exit();
}

$file = $_FILES['imagen_nosotros'];
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml', 'image/gif'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowed)) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo de archivo no permitido. Usa JPG, PNG, WebP, SVG o GIF.']);
    exit();
}

$maxSize = 50 * 1024 * 1024; // 50MB
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['error' => 'La imagen supera el tamaño máximo de 50MB.']);
    exit();
}

// Generar nombre único
$extension = match ($mime) {
    'image/jpeg'   => 'jpg',
    'image/png'    => 'png',
    'image/webp'   => 'webp',
    'image/svg+xml'=> 'svg',
    'image/gif'    => 'gif',
    default        => 'jpg'
};
$nombreArchivo = 'nosotros_grupal_' . time() . '.' . $extension;
$rutaFisica = TALENTO_UPLOADS_NOSOTROS . DIRECTORY_SEPARATOR . $nombreArchivo;

if (!move_uploaded_file($file['tmp_name'], $rutaFisica)) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo guardar la imagen en el servidor.']);
    exit();
}

// Ruta pública relativa para el portal de talento
$rutaPublica = 'uploads/nosotros/' . $nombreArchivo;

// Leer campos opcionales de texto
$altText   = isset($_POST['imagen_nosotros_alt'])   ? trim($_POST['imagen_nosotros_alt'])   : 'Líderes de Tienda Batidos Pitaya';
$badgeText = isset($_POST['imagen_nosotros_badge']) ? trim($_POST['imagen_nosotros_badge']) : 'Líderes que hacen posible la Experiencia WOW';

try {
    $claves = [
        'imagen_nosotros'       => $rutaPublica,
        'imagen_nosotros_alt'   => $altText,
        'imagen_nosotros_badge' => $badgeText,
    ];

    $stmt = $conn->prepare(
        "INSERT INTO talento_configuracion (clave, grupo, etiqueta_display, valor, usuario_modifica, fecha_modificacion)
         VALUES (?, 'imagen_nosotros', ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE valor = VALUES(valor), usuario_modifica = VALUES(usuario_modifica), fecha_modificacion = NOW()"
    );

    $etiquetas = [
        'imagen_nosotros'       => 'Imagen Grupal Sobre Nosotros',
        'imagen_nosotros_alt'   => 'Texto alternativo de la imagen',
        'imagen_nosotros_badge' => 'Texto del badge sobre la imagen',
    ];

    $conn->beginTransaction();
    foreach ($claves as $clave => $valor) {
        $stmt->execute([$clave, $etiquetas[$clave], $valor, $usuario['CodOperario']]);
    }
    $conn->commit();

    echo json_encode([
        'success'     => true,
        'mensaje'     => 'Imagen actualizada con éxito',
        'ruta_publica'=> $rutaPublica
    ]);
} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    // Borrar el archivo subido si la BD falla
    if (file_exists($rutaFisica)) unlink($rutaFisica);
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
