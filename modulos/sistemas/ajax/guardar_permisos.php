<?php
/**
 * Guardar cambios masivos de permisos
 * Recibe: tool_id, permisos {accionId: {cargoId: 'allow'|'deny'}}
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];
    
    // Verificar acceso para editar permisos
    if (!tienePermiso('gestion_permisos', 'editar', $cargoOperario)) {
        echo json_encode([
            'success' => false,
            'message' => 'No tiene permisos para editar'
        ]);
        exit;
    }
    
    $toolId = isset($_POST['tool_id']) ? (int)$_POST['tool_id'] : 0;
    $permisosJson = isset($_POST['permisos']) ? $_POST['permisos'] : '';
    
    if ($toolId <= 0 || empty($permisosJson)) {
        echo json_encode([
            'success' => false,
            'message' => 'Datos incompletos'
        ]);
        exit;
    }
    
    $permisos = json_decode($permisosJson, true);
    
    if (!is_array($permisos)) {
        echo json_encode([
            'success' => false,
            'message' => 'Formato de permisos inválido'
        ]);
        exit;
    }
    
    // Iniciar transacción
    $conn->beginTransaction();
    
    $actualizados = 0;
    $insertados = 0;
    
    // Procesar cada acción
    foreach ($permisos as $accionId => $cargoPermisos) {
        foreach ($cargoPermisos as $cargoId => $permiso) {
            // Validar permiso
            if (!in_array($permiso, ['allow', 'deny'])) {
                continue;
            }
            
            // Verificar si existe el registro
            $sqlExiste = "SELECT id FROM permisos_tools_erp 
                         WHERE accion_tool_erp_id = :accion_id 
                         AND CodNivelesCargos = :cargo_id";
            
            $stmtExiste = $conn->prepare($sqlExiste);
            $stmtExiste->execute([
                ':accion_id' => $accionId,
                ':cargo_id' => $cargoId
            ]);
            
            $existe = $stmtExiste->fetch(PDO::FETCH_ASSOC);
            
            if ($existe) {
                // Actualizar permiso existente
                $sqlUpdate = "UPDATE permisos_tools_erp 
                             SET permiso = :permiso,
                                 updated_at = CURRENT_TIMESTAMP
                             WHERE id = :id";
                
                $stmtUpdate = $conn->prepare($sqlUpdate);
                $stmtUpdate->execute([
                    ':permiso' => $permiso,
                    ':id' => $existe['id']
                ]);
                $actualizados++;
            } else {
                // Insertar nuevo permiso
                $sqlInsert = "INSERT INTO permisos_tools_erp 
                             (accion_tool_erp_id, CodNivelesCargos, permiso, created_at, updated_at)
                             VALUES (:accion_id, :cargo_id, :permiso, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
                
                $stmtInsert = $conn->prepare($sqlInsert);
                $stmtInsert->execute([
                    ':accion_id' => $accionId,
                    ':cargo_id' => $cargoId,
                    ':permiso' => $permiso
                ]);
                $insertados++;
            }
        }
    }
    
    // Confirmar transacción
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Permisos guardados correctamente",
        'stats' => [
            'actualizados' => $actualizados,
            'insertados' => $insertados
        ]
    ]);
    
} catch (Exception $e) {
    // Revertir en caso de error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Error en guardar_permisos.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar los permisos: ' . $e->getMessage()
    ]);
}
?>