<?php
/**
 * Crear nueva acción para una herramienta
 * Recibe: tool_id, nombre_accion, descripcion
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];
    
    // Verificar acceso para crear acciones
    if (!tienePermiso('gestion_permisos', 'crear_accion', $cargoOperario)) {
        echo json_encode([
            'success' => false,
            'message' => 'No tiene permisos para crear acciones'
        ]);
        exit;
    }
    
    $toolId = isset($_POST['tool_id']) ? (int)$_POST['tool_id'] : 0;
    $nombreAccion = isset($_POST['nombre_accion']) ? trim($_POST['nombre_accion']) : '';
    $descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
    
    // Validaciones
    if ($toolId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'ID de herramienta inválido'
        ]);
        exit;
    }
    
    if (empty($nombreAccion)) {
        echo json_encode([
            'success' => false,
            'message' => 'El nombre de la acción es requerido'
        ]);
        exit;
    }
    
    // Validar formato del nombre (solo minúsculas, números y guión bajo)
    if (!preg_match('/^[a-z0-9_]+$/', $nombreAccion)) {
        echo json_encode([
            'success' => false,
            'message' => 'El nombre debe contener solo minúsculas, números y guiones bajos'
        ]);
        exit;
    }
    
    // Verificar que la herramienta existe
    $sqlHerramienta = "SELECT id FROM tools_erp WHERE id = :tool_id";
    $stmtHerramienta = $conn->prepare($sqlHerramienta);
    $stmtHerramienta->execute([':tool_id' => $toolId]);
    
    if (!$stmtHerramienta->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'La herramienta no existe'
        ]);
        exit;
    }
    
    // Verificar que no exista ya una acción con ese nombre para esta herramienta
    $sqlExiste = "SELECT id FROM acciones_tools_erp 
                  WHERE tool_erp_id = :tool_id 
                  AND nombre_accion = :nombre_accion";
    
    $stmtExiste = $conn->prepare($sqlExiste);
    $stmtExiste->execute([
        ':tool_id' => $toolId,
        ':nombre_accion' => $nombreAccion
    ]);
    
    if ($stmtExiste->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'Ya existe una acción con ese nombre para esta herramienta'
        ]);
        exit;
    }
    
    // Insertar nueva acción
    $sqlInsert = "INSERT INTO acciones_tools_erp 
                  (tool_erp_id, nombre_accion, descripcion, created_at, updated_at)
                  VALUES (:tool_id, :nombre_accion, :descripcion, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
    
    $stmtInsert = $conn->prepare($sqlInsert);
    $stmtInsert->execute([
        ':tool_id' => $toolId,
        ':nombre_accion' => $nombreAccion,
        ':descripcion' => $descripcion
    ]);
    
    $nuevaAccionId = $conn->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Acción creada correctamente',
        'data' => [
            'id' => $nuevaAccionId,
            'nombre_accion' => $nombreAccion,
            'descripcion' => $descripcion
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error en crear_accion.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al crear la acción: ' . $e->getMessage()
    ]);
}
?>