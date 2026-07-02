<?php
/**
 * _imagen_helpers.php
 * Funciones compartidas para el procesamiento de imágenes en el módulo talento_contenido.
 */

/**
 * Comprime y convierte una imagen subida al formato WebP.
 *
 * Si la extensión GD no está disponible, deja el archivo original intacto y
 * retorna el mismo nombre (fallback silencioso).
 *
 * @param string $rutaArchivo  Ruta absoluta al archivo ya guardado en disco.
 * @param int    $anchoMax     Ancho máximo en píxeles (la altura se ajusta proporcionalmente).
 * @param int    $calidad      Calidad WebP de 0 a 100 (defecto: 82).
 * @return string              Nombre de archivo final (puede ser .webp o el original si GD no está).
 */
function comprimirYConvertirWebP(string $rutaArchivo, int $anchoMax = 1920, int $calidad = 82): string
{
    // Fallback: si GD no está disponible, devolver el archivo tal cual
    if (!function_exists('imagecreatefromjpeg') || !function_exists('imagewebp')) {
        return basename($rutaArchivo);
    }

    $mime = mime_content_type($rutaArchivo);

    // Cargar imagen según tipo
    $imagen = null;
    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            $imagen = @imagecreatefromjpeg($rutaArchivo);
            break;
        case 'image/png':
            $imagen = @imagecreatefrompng($rutaArchivo);
            // Preservar transparencia PNG
            if ($imagen) {
                imagealphablending($imagen, true);
                imagesavealpha($imagen, true);
            }
            break;
        case 'image/webp':
            $imagen = @imagecreatefromwebp($rutaArchivo);
            break;
        case 'image/gif':
            $imagen = @imagecreatefromgif($rutaArchivo);
            break;
    }

    // Si no se pudo cargar, fallback
    if (!$imagen) {
        return basename($rutaArchivo);
    }

    // Redimensionar si supera el ancho máximo
    $anchoOriginal = imagesx($imagen);
    $altoOriginal  = imagesy($imagen);

    if ($anchoOriginal > $anchoMax) {
        $ratio        = $anchoMax / $anchoOriginal;
        $nuevoAncho   = $anchoMax;
        $nuevoAlto    = (int)round($altoOriginal * $ratio);
        $imagenRedim  = imagescale($imagen, $nuevoAncho, $nuevoAlto, IMG_BICUBIC);
        imagedestroy($imagen);
        $imagen = $imagenRedim;
    }

    // Determinar nueva ruta .webp
    $dirArchivo    = dirname($rutaArchivo);
    $nombreSinExt  = pathinfo($rutaArchivo, PATHINFO_FILENAME);
    $nuevaRuta     = $dirArchivo . DIRECTORY_SEPARATOR . $nombreSinExt . '.webp';
    $nuevoNombre   = $nombreSinExt . '.webp';

    // Guardar como WebP
    $exito = imagewebp($imagen, $nuevaRuta, $calidad);
    imagedestroy($imagen);

    if ($exito) {
        // Eliminar archivo original si es distinto al .webp
        if ($rutaArchivo !== $nuevaRuta && file_exists($rutaArchivo)) {
            @unlink($rutaArchivo);
        }
        return $nuevoNombre;
    }

    // Si imagewebp falló, fallback: devolver nombre original
    return basename($rutaArchivo);
}
