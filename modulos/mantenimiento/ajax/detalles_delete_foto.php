<?php
// ajax/detalles_delete_foto.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$foto_id = isset($_POST['foto_id']) ? intval($_POST['foto_id']) : 0;
$ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;

if ($foto_id <= 0 || $ticket_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Obtener ruta de la foto
    $sql = "SELECT foto FROM mtto_tickets_fotos WHERE id = ? AND ticket_id = ?";
    $foto = $db->fetchOne($sql, [$foto_id, $ticket_id]);
    
    if (!$foto) {
        echo json_encode(['success' => false, 'message' => 'Foto no encontrada'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Eliminar archivo físico
    $rutaCompleta = __DIR__ . '/../uploads/tickets/' . $foto['foto'];
    if (file_exists($rutaCompleta)) {
        unlink($rutaCompleta);
    }
    
    // Eliminar registro de BD
    $sql = "DELETE FROM mtto_tickets_fotos WHERE id = ?";
    $db->query($sql, [$foto_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Foto eliminada correctamente'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al eliminar foto: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>