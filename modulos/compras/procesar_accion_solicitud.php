<?php
//ini_set('display_errors', 1);
//error_reporting(E_ALL);
//require_once '../../includes/config.php';
require_once '../../core/auth/auth.php';
require_once 'includes/funciones_compras.php';
require_once '../../core/helpers/config.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: historial_solicitudes_cotizacion.php');
    exit();
}

$solicitudId = intval($_POST['solicitud_id'] ?? 0);
$accion = $_POST['accion'] ?? '';
$observaciones = trim($_POST['observaciones_accion'] ?? '');

if ($solicitudId <= 0 || empty($accion)) {
    $_SESSION['error'] = 'Datos inválidos';
    header('Location: historial_solicitudes_cotizacion.php');
    exit();
}

// Obtener información del usuario
$usuario = obtenerUsuarioActual();
$usuarioId = $_SESSION['usuario_id'];
$usuarioNombre = trim($usuario['Nombre'] . ' ' . $usuario['Apellido']);

try {
    // Obtener la solicitud
    $stmt = $conn->prepare("SELECT * FROM solicitudes_cotizacion WHERE id = ?");
    $stmt->execute([$solicitudId]);
    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$solicitud) {
        throw new Exception('Solicitud no encontrada');
    }
    
    $conn->beginTransaction();
    
    // Verificar permisos según acción
    switch ($accion) {
        case 'aprobar':
            if (!puedeAprobarSolicitudes()) {
                throw new Exception('No tiene permisos para aprobar solicitudes');
            }
            $nuevoEstado = 'aprobada';
            $accionHistorial = 'aprobada';
            
            // Actualizar aprobación en la solicitud
            $stmtUpdateAprobacion = $conn->prepare("
                UPDATE solicitudes_cotizacion 
                SET gerente_aprobador_id = ?, 
                    gerente_aprobador_nombre = ?, 
                    fecha_aprobacion = CURDATE()
                WHERE id = ?
            ");
            $stmtUpdateAprobacion->execute([$usuarioId, $usuarioNombre, $solicitudId]);
            break;
            
        case 'rechazar':
            if (!puedeAprobarSolicitudes()) {
                throw new Exception('No tiene permisos para rechazar solicitudes');
            }
            $nuevoEstado = 'rechazada';
            $accionHistorial = 'rechazada';
            break;
            
            
        case 'completar':
            // Verificar si es compras (9) o gerencia
            $puedeCompletar = puedeCompletarSolicitudes() || puedeAprobarSolicitudes();
            if (!$puedeCompletar) {
                throw new Exception('No tiene permisos para completar solicitudes');
            }
            $nuevoEstado = 'completada';
            $accionHistorial = 'completada';
            break;
            
        case 'cancelar':
            // Solo el solicitante (si está pendiente) o quien tenga permiso de completar (si está aprobada)
            $puedeCancelar = ($solicitud['estado'] === 'pendiente' && $solicitud['solicitante_id'] == $usuarioId) || puedeCompletarSolicitudes();
            
            if (!$puedeCancelar) {
                throw new Exception('No tiene permisos para cancelar esta solicitud');
            }
            $nuevoEstado = 'cancelada';
            $accionHistorial = 'cancelada';
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
    
    // Actualizar estado
    $stmtUpdate = $conn->prepare("
        UPDATE solicitudes_cotizacion 
        SET estado = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmtUpdate->execute([$nuevoEstado, $solicitudId]);
    
    // Registrar en el historial
    $stmtHistorial = $conn->prepare("
        INSERT INTO solicitudes_cotizacion_historial 
        (solicitud_id, usuario_id, usuario_nombre, accion, detalles) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $detallesHistorial = json_encode([
        'observaciones' => $observaciones,
        'estado_anterior' => $solicitud['estado'],
        'estado_nuevo' => $nuevoEstado
    ]);
    
    $stmtHistorial->execute([
        $solicitudId,
        $usuarioId,
        $usuarioNombre,
        $accionHistorial,
        $detallesHistorial
    ]);
    
    $conn->commit();
    
    $_SESSION['success'] = 'Acción realizada exitosamente';
    
} catch (Exception $e) {
    $conn->rollBack();
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

header('Location: historial_solicitudes_cotizacion.php');
exit();
?>