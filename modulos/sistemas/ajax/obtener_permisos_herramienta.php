<?php
/**
 * Obtener permisos de una herramienta específica
 * Retorna: acciones, áreas, cargos y permisos actuales
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];
    
    // Verificar acceso
    if (!tienePermiso('gestion_permisos', 'vista', $cargoOperario)) {
        echo json_encode([
            'success' => false,
            'message' => 'No tiene permisos para acceder'
        ]);
        exit;
    }
    
    $toolId = isset($_GET['tool_id']) ? (int)$_GET['tool_id'] : 0;
    
    if ($toolId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'ID de herramienta inválido'
        ]);
        exit;
    }
    
    // 1. Obtener acciones de la herramienta
    $sqlAcciones = "SELECT id, nombre_accion, descripcion
                    FROM acciones_tools_erp
                    WHERE tool_erp_id = :tool_id
                    ORDER BY nombre_accion ASC";
    
    $stmtAcciones = $conn->prepare($sqlAcciones);
    $stmtAcciones->execute([':tool_id' => $toolId]);
    $acciones = $stmtAcciones->fetchAll(PDO::FETCH_ASSOC);
    
    // 2. Obtener todos los cargos con sus áreas
    $sqlCargos = "SELECT CodNivelesCargos, Nombre, Area
                  FROM NivelesCargos
                  WHERE Nombre IS NOT NULL
                  ORDER BY Area ASC, Nombre ASC";
    
    $stmtCargos = $conn->query($sqlCargos);
    $cargos = $stmtCargos->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Obtener permisos existentes
    $permisos = [];
    
    foreach ($acciones as $accion) {
        $accionId = (int)$accion['id'];
        $permisos[$accionId] = [];
        
        $sqlPermisos = "SELECT CodNivelesCargos, permiso
                        FROM permisos_tools_erp
                        WHERE accion_tool_erp_id = :accion_id";
        
        $stmtPermisos = $conn->prepare($sqlPermisos);
        $stmtPermisos->execute([':accion_id' => $accionId]);
        $permisosAccion = $stmtPermisos->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($permisosAccion as $permiso) {
            $cargoId = (int)$permiso['CodNivelesCargos'];
            $permisos[$accionId][$cargoId] = $permiso['permiso'];
        }
        
        // Asegurar que todos los cargos estén en el array (aunque sea sin permiso)
        foreach ($cargos as $cargo) {
            $cargoId = (int)$cargo['CodNivelesCargos'];
            if (!isset($permisos[$accionId][$cargoId])) {
                $permisos[$accionId][$cargoId] = 'deny';
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'acciones' => $acciones,
            'areas' => $cargos, // Incluye CodNivelesCargos, Nombre, Area
            'permisos' => $permisos // [accionId][cargoId] = 'allow'|'deny'
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error en obtener_permisos_herramienta.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar los permisos'
    ]);
}
?>