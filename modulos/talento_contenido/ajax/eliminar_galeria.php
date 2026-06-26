<?php
// eliminar_galeria.php - Eliminar una foto de la galería de una noticia
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    // Verificar permisos
    if (!tienePermiso('talento_contenido', 'eliminar', $cargoOperario)) {
        throw new Exception("No tienes privilegios para eliminar fotos de la galería.");
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $foto_id = isset($input['id']) ? intval($input['id']) : 0;

    if ($foto_id <= 0) {
        throw new Exception("ID de foto inválido.");
    }

    // 1. Obtener la ruta de la foto para borrar el archivo físico
    $stmtFoto = $conn->prepare("SELECT ruta_foto FROM noticias_fotos_talento WHERE id = :id LIMIT 1");
    $stmtFoto->bindValue(':id', $foto_id, PDO::PARAM_INT);
    $stmtFoto->execute();
    $ruta_foto = $stmtFoto->fetchColumn();

    if (!$ruta_foto) {
        throw new Exception("Foto no encontrada en la base de datos.");
    }

    // 2. Eliminar el registro en la BD
    $stmt = $conn->prepare("DELETE FROM noticias_fotos_talento WHERE id = :id");
    $stmt->bindValue(':id', $foto_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        // Eliminar foto física del disco si existe
        $target_file = "../../../../talento.batidospitaya/uploads/noticias/galeria/" . $ruta_foto;
        if (file_exists($target_file)) {
            @unlink($target_file);
        }
        echo json_encode([
            'success' => true,
            'message' => "Foto eliminada de la galería correctamente."
        ]);
    } else {
        throw new Exception("Error al eliminar la foto de la base de datos.");
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
