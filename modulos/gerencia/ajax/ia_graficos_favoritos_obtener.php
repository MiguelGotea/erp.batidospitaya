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
    
    // Obtener favorito
    $stmt = $conn->prepare("
        SELECT id, nombre_favorito, prompt_original, estructura_json, 
               tipo_grafico, descripcion, veces_usado
        FROM ia_graficos_favoritos
        WHERE id = ? AND usuario_id = ?
        LIMIT 1
    ");
    
    $stmt->execute([$favoritoId, $usuarioId]);
    $favorito = $stmt->fetch();
    
    if (!$favorito) {
        throw new Exception('Favorito no encontrado');
    }
    
    // Actualizar contador y última visualización
    $updateStmt = $conn->prepare("
        UPDATE ia_graficos_favoritos 
        SET veces_usado = veces_usado + 1,
            ultima_visualizacion = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([$favoritoId]);
    
    echo json_encode([
        'success' => true,
        'favorito' => $favorito
    ]);
    
} catch (Exception $e) {
    error_log('Error ia_graficos_favoritos_obtener: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>