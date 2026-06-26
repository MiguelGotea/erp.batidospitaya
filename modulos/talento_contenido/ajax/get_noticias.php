<?php
// get_noticias.php - Obtener noticias para el listado o edición
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $noticia_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($noticia_id > 0) {
        // Obtener una sola noticia
        $stmt = $conn->prepare("SELECT id, titulo, resumen, contenido, imagen_principal, categoria, estado, fecha_publicacion, autor FROM noticias_talento WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', $noticia_id, PDO::PARAM_INT);
        $stmt->execute();
        $noticia = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($noticia) {
            echo json_encode([
                'success' => true,
                'datos' => $noticia
            ]);
        } else {
            throw new Exception("Noticia no encontrada.");
        }
    } else {
        // Obtener todas las noticias, ordenadas por fecha de publicación descendente
        $stmt = $conn->prepare("SELECT id, titulo, resumen, categoria, estado, fecha_publicacion, autor, imagen_principal FROM noticias_talento ORDER BY fecha_publicacion DESC, id DESC");
        $stmt->execute();
        $noticias = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'datos' => $noticias
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
