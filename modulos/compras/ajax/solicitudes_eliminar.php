<?php
// solicitudes_eliminar.php
require_once '../../../core/database/conexion.php';
require_once '../../../core/helpers/funciones.php';
require_once '../../../core/auth/auth.php';

verificarAutenticacion();

header('Content-Type: application/json');

try {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }
    
    // Verificar que la solicitud existe
    $stmt = $conn->prepare("SELECT * FROM solicitudes_cotizacion WHERE id = ?");
    $stmt->execute([$id]);
    $solicitud = $stmt->fetch();
    
    if (!$solicitud) {
        throw new Exception('Solicitud no encontrada');
    }
    
    // Verificar permisos
    
    if ($solicitud['solicitante_id'] != $_SESSION['usuario_id']) {
        throw new Exception('No tiene permisos para eliminar esta solicitud');
    }
    
    // Solo permitir eliminar solicitudes pendientes
    if ($solicitud['estado'] !== 'pendiente') {
        throw new Exception('Solo se pueden eliminar solicitudes pendientes');
    }
    
    $conn->beginTransaction();
    
    try {
        // Eliminar productos asociados
        $stmt = $conn->prepare("DELETE FROM solicitudes_cotizacion_productos WHERE solicitud_id = ?");
        $stmt->execute([$id]);
        
        // Eliminar historial
        $stmt = $conn->prepare("DELETE FROM solicitudes_cotizacion_historial WHERE solicitud_id = ?");
        $stmt->execute([$id]);
        
        // Eliminar fotos de productos (si existen)
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/modulos/compras/uploads/cotizaciones/';
        $stmtFotos = $conn->prepare("SELECT foto_referencia FROM solicitudes_cotizacion_productos WHERE solicitud_id = ?");
        $stmtFotos->execute([$id]);
        $fotos = $stmtFotos->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($fotos as $foto) {
            if (!empty($foto) && file_exists($uploadDir . $foto)) {
                unlink($uploadDir . $foto);
            }
        }
        
        // Eliminar solicitud
        $stmt = $conn->prepare("DELETE FROM solicitudes_cotizacion WHERE id = ?");
        $stmt->execute([$id]);
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Solicitud eliminada exitosamente'
        ]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>