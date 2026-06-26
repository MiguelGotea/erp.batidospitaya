<?php
// eliminar_noticia.php - Eliminar una noticia y sus fotos (portada + galería)
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    if (!tienePermiso('talento_contenido', 'eliminar', $cargoOperario)) {
        throw new Exception("No tienes privilegios para eliminar noticias.");
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $noticia_id = isset($input['id']) ? intval($input['id']) : 0;

    if ($noticia_id <= 0) {
        throw new Exception("ID de noticia inválido.");
    }

    // 1. Obtener la foto de portada
    $stmtPortada = $conn->prepare("SELECT imagen_principal FROM noticias_talento WHERE id = :id LIMIT 1");
    $stmtPortada->bindValue(':id', $noticia_id, PDO::PARAM_INT);
    $stmtPortada->execute();
    $portada = $stmtPortada->fetchColumn();

    // 2. Obtener todas las fotos de la galería relacionada para borrarlas físicamente
    $stmtGaleria = $conn->prepare("SELECT ruta_foto FROM noticias_fotos_talento WHERE noticia_id = :noticia_id");
    $stmtGaleria->bindValue(':noticia_id', $noticia_id, PDO::PARAM_INT);
    $stmtGaleria->execute();
    $fotos_galeria = $stmtGaleria->fetchAll(PDO::FETCH_COLUMN);

    // 3. Eliminar el registro en la BD (ON DELETE CASCADE eliminará automáticamente las filas de la galería en la base de datos)
    $stmt = $conn->prepare("DELETE FROM noticias_talento WHERE id = :id");
    $stmt->bindValue(':id', $noticia_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        // Eliminar portada física del disco si existe
        if ($portada && file_exists("../../../../talento.batidospitaya/uploads/noticias/" . $portada)) {
            @unlink("../../../../talento.batidospitaya/uploads/noticias/" . $portada);
        }

        // Eliminar todas las fotos de galería físicas del disco
        foreach ($fotos_galeria as $foto) {
            if ($foto && file_exists("../../../../talento.batidospitaya/uploads/noticias/galeria/" . $foto)) {
                @unlink("../../../../talento.batidospitaya/uploads/noticias/galeria/" . $foto);
            }
        }

        echo json_encode([
            'success' => true,
            'message' => "Noticia y todos sus archivos asociados eliminados correctamente."
        ]);
    } else {
        throw new Exception("Error al eliminar el registro de la base de datos.");
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
