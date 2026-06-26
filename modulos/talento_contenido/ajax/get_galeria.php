<?php
// get_galeria.php - Obtener fotos de la galería de una noticia
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $noticia_id = isset($_GET['noticia_id']) ? intval($_GET['noticia_id']) : 0;

    if ($noticia_id <= 0) {
        throw new Exception("ID de noticia inválido.");
    }

    $stmt = $conn->prepare("SELECT id, ruta_foto, descripcion, orden FROM noticias_fotos_talento WHERE noticia_id = :noticia_id ORDER BY orden ASC, id ASC");
    $stmt->bindValue(':noticia_id', $noticia_id, PDO::PARAM_INT);
    $stmt->execute();
    $fotos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'datos' => $fotos
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
