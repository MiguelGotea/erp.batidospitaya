<?php
// ajax/eliminar_herramienta_cascada.php
header('Content-Type: application/json');

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/db_connection.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar permiso para gestionar (usamos el mismo que para la vista por ahora, 
// o podríamos definir uno específico para borrar)
if (!tienePermiso('gestion_permisos', 'borrar', $cargoOperario) && !tienePermiso('gestion_permisos', 'vista', $cargoOperario)) {
    echo json_encode(['success' => false, 'message' => 'No tiene permisos para realizar esta acción.']);
    exit();
}

$tool_id = isset($_POST['tool_id']) ? (int)$_POST['tool_id'] : 0;

if ($tool_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de herramienta no válido.']);
    exit();
}

try {
    $conn = obtenerConexion();
    
    // Al tener ON DELETE CASCADE en las llaves foráneas:
    // 1. Borrar de tools_erp borrará automáticamente de acciones_tools_erp
    // 2. Borrar de acciones_tools_erp borrará automáticamente de permisos_tools_erp
    
    $stmt = $conn->prepare("DELETE FROM tools_erp WHERE id = ?");
    $result = $stmt->execute([$tool_id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Herramienta y todos sus permisos asociados eliminados correctamente.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se pudo eliminar la herramienta.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
