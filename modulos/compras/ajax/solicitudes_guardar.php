<?php
// solicitudes_guardar.php
require_once '../../../core/auth/auth.php';

verificarAutenticacion();

header('Content-Type: application/json');

try {
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';
    
    if ($accion === 'crear') {
        // Lógica para crear nueva solicitud
        // (Este endpoint puede ser usado si decides agregar creación vía AJAX en el futuro)
        
        throw new Exception('Funcionalidad no implementada. Use solicitud_cotizacion.php');
        
    } elseif ($accion === 'editar') {
        // Lógica para editar solicitud existente
        
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if ($id <= 0) {
            throw new Exception('ID inválido');
        }
        
        // Verificar que la solicitud existe y pertenece al usuario o es admin
        $stmt = $conn->prepare("SELECT * FROM solicitudes_cotizacion WHERE id = ?");
        $stmt->execute([$id]);
        $solicitud = $stmt->fetch();
        
        if (!$solicitud) {
            throw new Exception('Solicitud no encontrada');
        }
        
        // No admin check needed here if we only allow solicitante or specific permissions, but user said admin is gone.
        
        if ($solicitud['solicitante_id'] != $_SESSION['usuario_id']) {
            throw new Exception('No tiene permisos para editar esta solicitud');
        }
        
        if ($solicitud['estado'] !== 'pendiente') {
            throw new Exception('Solo se pueden editar solicitudes pendientes');
        }
        
        // Actualizar observaciones
        $observaciones = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : '';
        
        $stmt = $conn->prepare("UPDATE solicitudes_cotizacion SET observaciones = ? WHERE id = ?");
        $stmt->execute([$observaciones, $id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Solicitud actualizada exitosamente'
        ]);
        
    } else {
        throw new Exception('Acción no válida');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>