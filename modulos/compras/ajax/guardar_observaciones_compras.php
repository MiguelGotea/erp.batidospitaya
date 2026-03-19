<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/database/conexion.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php';
require_once '../../../core/helpers/funciones.php';
require_once '../includes/funciones_compras.php';

verificarAutenticacion();

header('Content-Type: application/json');

try {
    // Verificar que sea cargo 9 (Compras) o admin
    if (!puedeCompletarSolicitudes()) {
        throw new Exception('No tiene permisos para agregar observaciones');
    }
    
    $solicitudId = isset($_POST['solicitud_id']) ? (int)$_POST['solicitud_id'] : 0;
    $observaciones = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : '';
    
    if ($solicitudId <= 0) {
        throw new Exception('ID de solicitud inválido');
    }
    
    if (empty($observaciones)) {
        throw new Exception('Las observaciones no pueden estar vacías');
    }
    
    // Verificar que la solicitud existe
    $stmt = $conn->prepare("SELECT * FROM solicitudes_cotizacion WHERE id = ?");
    $stmt->execute([$solicitudId]);
    $solicitud = $stmt->fetch();
    
    if (!$solicitud) {
        throw new Exception('Solicitud no encontrada');
    }
    
    // Obtener información del usuario
    $usuario = obtenerUsuarioActual();
    $esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';
    $usuarioNombre = $esAdmin ? $usuario['nombre'] : trim($usuario['Nombre'] . ' ' . $usuario['Apellido']);
    
    // Actualizar observaciones
    $stmt = $conn->prepare("
        UPDATE solicitudes_cotizacion 
        SET observaciones_compras = ?, 
            compras_usuario_id = ?,
            compras_usuario_nombre = ?,
            fecha_observaciones_compras = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$observaciones, $_SESSION['usuario_id'], $usuarioNombre, $solicitudId]);
    
    // Registrar en el historial
    $stmtHistorial = $conn->prepare("
        INSERT INTO solicitudes_cotizacion_historial 
        (solicitud_id, usuario_id, usuario_nombre, accion, detalles) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $detalles = json_encode([
        'observaciones_compras' => $observaciones
    ]);
    
    $stmtHistorial->execute([
        $solicitudId,
        $_SESSION['usuario_id'],
        $usuarioNombre,
        'observaciones_compras_agregadas',
        $detalles
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Observaciones guardadas exitosamente'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>