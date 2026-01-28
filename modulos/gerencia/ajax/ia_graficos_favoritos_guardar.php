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
    
    $nombreFavorito = isset($input['nombre_favorito']) ? trim($input['nombre_favorito']) : '';
    $descripcion = isset($input['descripcion']) ? trim($input['descripcion']) : null;
    $promptOriginal = isset($input['prompt_original']) ? trim($input['prompt_original']) : '';
    $estructuraJson = isset($input['estructura_json']) ? $input['estructura_json'] : '';
    $tipoGrafico = isset($input['tipo_grafico']) ? $input['tipo_grafico'] : null;
    
    if (empty($nombreFavorito)) {
        throw new Exception('El nombre del favorito es requerido');
    }
    
    if (empty($promptOriginal)) {
        throw new Exception('El prompt es requerido');
    }
    
    if (empty($estructuraJson)) {
        throw new Exception('La estructura es requerida');
    }
    
    $stmt = $conn->prepare("
        INSERT INTO ia_graficos_favoritos 
        (usuario_id, nombre_favorito, prompt_original, estructura_json, tipo_grafico, descripcion)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $usuarioId,
        $nombreFavorito,
        $promptOriginal,
        $estructuraJson,
        $tipoGrafico,
        $descripcion
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Favorito guardado exitosamente'
    ]);
    
} catch (Exception $e) {
    error_log('Error ia_graficos_favoritos_guardar: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>