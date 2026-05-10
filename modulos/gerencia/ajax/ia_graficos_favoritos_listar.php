<?php
require_once '../../../core/database/conexion.php';
require_once '../../../core/auth/auth.php';

header('Content-Type: application/json');

try {
    $usuarioId = $_SESSION['usuario_id'] ?? null;
    
    if (!$usuarioId) {
        throw new Exception('Usuario no autenticado');
    }
    
    $stmt = $conn->prepare("
        SELECT id, nombre_favorito, prompt_original, estructura_json, 
               tipo_grafico, descripcion, veces_usado, 
               fecha_creacion, ultima_visualizacion
        FROM ia_graficos_favoritos
        WHERE usuario_id = ?
        ORDER BY fecha_creacion DESC
    ");
    
    $stmt->execute([$usuarioId]);
    $favoritos = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'favoritos' => $favoritos
    ]);
    
} catch (Exception $e) {
    error_log('Error ia_graficos_favoritos_listar: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>