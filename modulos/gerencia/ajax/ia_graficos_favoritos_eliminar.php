<?php
require_once '../../../core/database/conexion.php';
require_once '../../../core/auth/auth.php';

header('Content-Type: application/json');

try {
    $usuarioId = $_SESSION['usuario_id'] ?? null;
    
    if (!$usuarioId) {
        throw new Exception('Usuario no autenticado');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $favoritoId = isset($input['favorito_id']) ? intval($input['favorito_id']) : 0;
    
    if ($favoritoId <= 0) {
        throw new Exception('ID de favorito inválido');
    }
    
    $stmt = $conn->prepare("
        DELETE FROM ia_graficos_favoritos 
        WHERE id = ? AND usuario_id = ?
    ");
    
    $stmt->execute([$favoritoId, $usuarioId]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Favorito no encontrado o no tienes permiso para eliminarlo');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Favorito eliminado exitosamente'
    ]);
    
} catch (Exception $e) {
    error_log('Error ia_graficos_favoritos_eliminar: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>