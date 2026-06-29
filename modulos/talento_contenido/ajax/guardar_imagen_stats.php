<?php
// guardar_imagen_stats.php
// Sube la imagen del lado izquierdo de los indicadores y actualiza talento_configuracion.
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

// Ruta física donde se almacenará la imagen de los indicadores.
// En Hostinger los dominios tienen rutas absolutas separadas.
$rutasIntento = [
    '/files/domains/talento.batidospitaya.com/public_html/uploads/nosotros',
    realpath(__DIR__ . '/../../../../talento.batidospitaya/uploads/nosotros'),
];
$dirNosotros = null;
foreach ($rutasIntento as $ruta) {
    if ($ruta && is_dir($ruta)) {
        $dirNosotros = $ruta;
        break;
    }
}
if (!$dirNosotros) {
    $nuevaRuta = '/files/domains/talento.batidospitaya.com/public_html/uploads/nosotros';
    if (!mkdir($nuevaRuta, 0755, true)) {
        $nuevaRuta = __DIR__ . '/../../../../talento.batidospitaya/uploads/nosotros';
        if (!mkdir($nuevaRuta, 0755, true)) {
            http_response_code(500);
            echo json_encode(['error' => 'No se puede crear el directorio de subida.']);
            exit();
        }
    }
    $dirNosotros = realpath($nuevaRuta);
}

if (!isset($_FILES['imagen_stats_nosotros']) || $_FILES['imagen_stats_nosotros']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    $err = $_FILES['imagen_stats_nosotros']['error'] ?? 'No se recibió archivo';
    echo json_encode(['error' => "Error en la subida: $err"]);
    exit();
}

$file = $_FILES['imagen_stats_nosotros'];
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowed)) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo de archivo no permitido. Usa JPG, PNG, WebP, SVG o GIF.']);
    exit();
}

$maxSize = 50 * 1024 * 1024; // 50 MB
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['error' => 'La imagen supera el tamaño máximo de 50MB.']);
    exit();
}

$extension = match ($mime) {
    'image/jpeg'    => 'jpg',
    'image/png'     => 'png',
    'image/webp'    => 'webp',
    'image/gif'     => 'gif',
    'image/svg+xml' => 'svg',
    default         => 'png'
};

$nombreArchivo = 'imagen_stats.' . $extension;
$rutaFisica    = $dirNosotros . DIRECTORY_SEPARATOR . $nombreArchivo;

if (!move_uploaded_file($file['tmp_name'], $rutaFisica)) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo guardar la imagen en el servidor.']);
    exit();
}

$rutaPublica = 'uploads/nosotros/' . $nombreArchivo;

try {
    $stmt = $conn->prepare(
        "INSERT INTO talento_configuracion (clave, grupo, etiqueta_display, valor, usuario_modifica, fecha_modificacion)
         VALUES ('imagen_stats_nosotros', 'nosotros', 'Imagen de Indicadores (Sobre Nosotros)', ?, ?, NOW())
         ON DUPLICATE KEY UPDATE valor = VALUES(valor), usuario_modifica = VALUES(usuario_modifica), fecha_modificacion = NOW()"
    );
    $stmt->execute([$rutaPublica, $usuario['CodOperario']]);

    echo json_encode([
        'success'      => true,
        'mensaje'      => 'Imagen de indicadores actualizada con éxito',
        'ruta_publica' => $rutaPublica
    ]);
} catch (Exception $e) {
    if (file_exists($rutaFisica)) unlink($rutaFisica);
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
