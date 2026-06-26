<?php
// guardar_galeria.php - Subir una foto a la galería de una noticia
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    // Verificar permisos
    if (!tienePermiso('talento_contenido', 'editar', $cargoOperario)) {
        throw new Exception("No tienes privilegios para subir fotos a la galería.");
    }

    $noticia_id = isset($_POST['noticia_id']) ? intval($_POST['noticia_id']) : 0;
    if ($noticia_id <= 0) {
        throw new Exception("ID de noticia inválido.");
    }

    if (!isset($_FILES['foto_galeria']) || $_FILES['foto_galeria']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("No se ha recibido una imagen válida.");
    }

    $file = $_FILES['foto_galeria'];
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception("El tipo de archivo de imagen no está permitido (solo JPG, PNG o WebP).");
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        throw new Exception("La imagen excede el tamaño máximo permitido de 2MB.");
    }

    // Crear carpeta destino si no existe
    $target_dir = "../../../../talento.batidospitaya/uploads/noticias/galeria/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    // Generar nombre de archivo único
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (empty($ext)) {
        $ext = ($file['type'] === 'image/png') ? 'png' : (($file['type'] === 'image/webp') ? 'webp' : 'jpg');
    }
    $filename = "gal_" . $noticia_id . "_" . time() . "_" . uniqid() . "." . strtolower($ext);
    $target_file = $target_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        // Insertar en base de datos
        $stmt = $conn->prepare("INSERT INTO noticias_fotos_talento (noticia_id, ruta_foto, descripcion, orden) VALUES (:noticia_id, :ruta_foto, :descripcion, :orden)");
        $stmt->bindValue(':noticia_id', $noticia_id, PDO::PARAM_INT);
        $stmt->bindValue(':ruta_foto', $filename, PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', '', PDO::PARAM_STR);
        $stmt->bindValue(':orden', 0, PDO::PARAM_INT);

        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => "Foto subida e integrada a la galería correctamente.",
                'foto' => [
                    'id' => $conn->lastInsertId(),
                    'ruta_foto' => $filename
                ]
            ]);
        } else {
            // Borrar archivo si falla la base de datos
            @unlink($target_file);
            throw new Exception("Error al guardar la foto de galería en la base de datos.");
        }
    } else {
        throw new Exception("Error al guardar la foto en el servidor.");
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
