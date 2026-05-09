<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
// ajax/solicitudes_vacaciones_get_solicitud.php
require_once '../../../includes/conexion.php';
require_once '../../../includes/auth.php';

header('Content-Type: application/json');

try {
    verificarAutenticacion();
    
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if (!$id) {
        throw new Exception('ID de solicitud no válido');
    }
    
    $stmt = $conn->prepare("
        SELECT 
            sv.*,
            o.Nombre AS operario_nombre,
            o.Apellido AS operario_apellido,
            s.nombre AS sucursal_nombre,
            sol.Nombre AS solicitante_nombre,
            sol.Apellido AS solicitante_apellido
        FROM solicitudes_vacaciones sv
        JOIN Operarios o ON sv.cod_operario = o.CodOperario
        JOIN sucursales s ON sv.cod_sucursal = s.codigo
        LEFT JOIN Operarios sol ON sv.solicitado_por = sol.CodOperario
        WHERE sv.id = ?
    ");
    
    $stmt->execute([$id]);
    $solicitud = $stmt->fetch();
    
    if (!$solicitud) {
        throw new Exception('Solicitud no encontrada');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $solicitud
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}