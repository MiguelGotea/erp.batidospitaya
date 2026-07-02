<?php
// reordenar_galeria.php - Actualiza el campo `orden` de las fotos de galería de una noticia
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    if (!tienePermiso('talento_contenido', 'editar', $cargoOperario)) {
        throw new Exception("No tienes privilegios para reordenar la galería.");
    }

    $body = file_get_contents('php://input');
    $payload = json_decode($body, true);

    if (!isset($payload['orden']) || !is_array($payload['orden'])) {
        throw new Exception("Datos de orden inválidos.");
    }

    $stmt = $conn->prepare("UPDATE noticias_fotos_talento SET orden = :orden WHERE id = :id");

    foreach ($payload['orden'] as $index => $fotoId) {
        $fotoId = intval($fotoId);
        if ($fotoId <= 0) continue;
        $stmt->bindValue(':orden', $index, PDO::PARAM_INT);
        $stmt->bindValue(':id',    $fotoId, PDO::PARAM_INT);
        $stmt->execute();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Orden actualizado correctamente.'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
