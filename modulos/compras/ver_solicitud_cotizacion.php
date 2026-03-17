<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
//require_once '../../includes/config.php';
require_once '../../includes/funciones.php';
require_once '../../includes/auth.php';
require_once 'includes/funciones_compras.php';
require_once '../../core/permissions/permissions.php';
require_once '../../includes/menu_lateral.php';
require_once '../../includes/config.php';

verificarAutenticacion();

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('historial_solicitudes_cotizacion', 'vista', $cargoOperario)) {
    header('Location: /index.php');
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: historial_solicitudes_cotizacion.php');
    exit();
}

$solicitudId = intval($_GET['id']);

// Obtener información del usuario actual
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Obtener la solicitud
try {
    // Obtener información principal
    $stmt = $conn->prepare("
        SELECT sc.* 
        FROM solicitudes_cotizacion sc
        WHERE sc.id = ?
    ");
    $stmt->execute([$solicitudId]);
    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$solicitud) {
        $_SESSION['error'] = 'La solicitud no existe';
        header('Location: historial_solicitudes_cotizacion.php');
        exit();
    }
    
    // Obtener productos de la solicitud
    $stmtProductos = $conn->prepare("
        SELECT * 
        FROM solicitudes_cotizacion_productos 
        WHERE solicitud_id = ? 
        ORDER BY orden
    ");
    $stmtProductos->execute([$solicitudId]);
    $productos = $stmtProductos->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener historial
    $stmtHistorial = $conn->prepare("
        SELECT * 
        FROM solicitudes_cotizacion_historial 
        WHERE solicitud_id = ? 
        ORDER BY fecha_accion DESC
    ");
    $stmtHistorial->execute([$solicitudId]);
    $historial = $stmtHistorial->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Error al cargar la solicitud: ' . $e->getMessage();
    header('Location: historial_solicitudes_cotizacion.php');
    exit();
}

// Procesar acciones si se envía el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    $observaciones = trim($_POST['observaciones_accion'] ?? '');
    $usuarioId = $_SESSION['usuario_id'];
    $usuarioNombre = $esAdmin ? 
        $usuario['nombre'] : 
        trim($usuario['Nombre'] . ' ' . $usuario['Apellido']);
    
    try {
        $conn->beginTransaction();
        
        // Verificar permisos según acción
        switch ($accion) {
            case 'aprobar':
                if (!puedeAprobarSolicitudes()) {
                    throw new Exception('No tiene permisos para aprobar solicitudes');
                }
                $nuevoEstado = 'aprobada';
                $accionHistorial = 'aprobada';
                
                // Determinar qué gerencia aprueba (16 o 49)
                $cargosUsuario = obtenerCargosUsuario($usuarioId);
                if (in_array(16, $cargosUsuario)) {
                    $campoGerencia = 'aprobado_1';
                } elseif (in_array(49, $cargosUsuario)) {
                    $campoGerencia = 'aprobado_2';
                } else {
                    $campoGerencia = 'aprobado_1'; // Por defecto
                }
                
                // Actualizar aprobación en la solicitud
                $stmtUpdateAprobacion = $conn->prepare("
                    UPDATE solicitudes_cotizacion 
                    SET {$campoGerencia}_id = ?, 
                        {$campoGerencia}_nombre = ?, 
                        fecha_{$campoGerencia} = CURDATE()
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
                
            case 'en_proceso':
                if (!puedeAprobarSolicitudes()) {
                    throw new Exception('No tiene permisos para marcar como en proceso');
                }
                $nuevoEstado = 'en_proceso';
                $accionHistorial = 'en_proceso';
                break;
                
            case 'completar':
                // Verificar si es compras (9) o gerencia (16, 49)
                $puedeCompletar = puedeCompletarSolicitudes() || puedeAprobarSolicitudes();
                if (!$puedeCompletar) {
                    throw new Exception('No tiene permisos para completar solicitudes');
                }
                $nuevoEstado = 'completada';
                $accionHistorial = 'completada';
                break;
                
            case 'cancelar':
                // Solo el solicitante puede cancelar
                if ($solicitud['solicitante_id'] != $usuarioId && !$esAdmin) {
                    throw new Exception('Solo el solicitante puede cancelar esta solicitud');
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
        
        $_SESSION['success'] = 'Solicitud actualizada exitosamente';
        header('Location: ver_solicitud_cotizacion.php?id=' . $solicitudId);
        exit();
        
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = 'Error al procesar la acción: ' . $e->getMessage();
        header('Location: ver_solicitud_cotizacion.php?id=' . $solicitudId);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Solicitud de Cotización</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <style>
        * {
            font-family: 'Calibri', sans-serif;
            box-sizing: border-box;
        }
        
        body {
            background-color: #F6F6F6;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #51B8AC;
        }
        
        .titulo {
            color: #0E544C;
            margin: 0;
            font-size: 24px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #51B8AC;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .alert {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .solicitud-header {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .header-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .info-item {
            margin-bottom: 10px;
        }
        
        .info-label {
            font-weight: bold;
            color: #666;
            font-size: 14px;
            margin-bottom: 3px;
        }
        
        .info-value {
            font-size: 16px;
            color: #333;
        }
        
        .estado-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            display: inline-block;
        }
        
        .estado-pendiente {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .estado-en_proceso {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .estado-aprobada {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .estado-rechazada {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .estado-completada {
            background-color: #0E544C;
            color: white;
            border: 1px solid #0E544C;
        }
        
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-primary {
            background-color: #0E544C;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #51B8AC;
        }
        
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background-color: #218838;
        }
        
        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background-color: #e0a800;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        /* Productos */
        .productos-section {
            margin-bottom: 30px;
        }
        
        .section-title {
            color: #0E544C;
            border-bottom: 2px solid #51B8AC;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .productos-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .productos-table th {
            background-color: #0E544C;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: normal;
        }
        
        .productos-table td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            vertical-align: top;
        }
        
        .productos-table tr:hover {
            background-color: #f9f9f9;
        }
        
        .foto-container {
            max-width: 150px;
        }
        
        .foto-referencia {
            max-width: 100%;
            max-height: 100px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .no-foto {
            color: #999;
            font-style: italic;
            font-size: 14px;
        }
        
        /* Firmas */
        .firmas-section {
            margin-bottom: 30px;
        }
        
        .firmas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .firma-box {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background-color: #f8f9fa;
        }
        
        .firma-box.aprobada {
            border-color: #28a745;
            background-color: #d4edda;
        }
        
        .firma-box.pendiente {
            border-color: #ffc107;
            background-color: #fff3cd;
        }
        
        .firma-nombre {
            font-weight: bold;
            color: #0E544C;
            margin-bottom: 10px;
        }
        
        .firma-fecha {
            color: #666;
            font-size: 14px;
            margin-top: 10px;
        }
        
        .firma-placeholder {
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-style: italic;
            border: 1px dashed #ccc;
            border-radius: 4px;
            margin-top: 10px;
        }
        
        /* Historial */
        .historial-section {
            margin-bottom: 30px;
        }
        
        .historial-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .historial-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .historial-item:last-child {
            border-bottom: none;
        }
        
        .historial-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }
        
        .historial-usuario {
            font-weight: bold;
            color: #0E544C;
        }
        
        .historial-fecha {
            color: #666;
            font-size: 12px;
        }
        
        .historial-accion {
            margin-bottom: 5px;
        }
        
        .historial-detalles {
            color: #666;
            font-size: 14px;
            font-style: italic;
        }
        
        /* Modal de acción */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .modal-title {
            margin-top: 0;
            color: #0E544C;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            resize: vertical;
            min-height: 100px;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .user-info {
                align-self: flex-end;
            }
            
            .header-grid {
                grid-template-columns: 1fr;
            }
            
            .productos-table {
                display: block;
                overflow-x: auto;
            }
            
            .firmas-grid {
                grid-template-columns: 1fr;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        /* Modal para foto ampliada - MEJORADO */
        #fotoModal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            overflow: auto;
            animation: fadeIn 0.3s;
        }
        
        #fotoModal .modal-content {
            position: relative;
            margin: auto;
            padding: 0;
            width: auto;
            max-width: 95%;
            max-height: 95vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            box-shadow: none;
            background: transparent;
        }
        
        #fotoModal .modal-content > div {
            width: 100%;
            max-width: 1400px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        
        #fotoAmpliada {
            width: 100%;
            height: auto;
            max-height: 85vh;
            object-fit: contain;
            border-radius: 8px;
            display: block;
            transition: transform 0.3s ease;
        }
        
        /* Botón de cerrar mejorado */
        #fotoModal .btn-close-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            background-color: rgba(220, 53, 69, 0.9);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s;
            z-index: 10000;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        
        #fotoModal .btn-close-modal:hover {
            background-color: rgba(200, 35, 51, 1);
            transform: scale(1.05);
        }
        
        /* Animación de entrada */
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        
        /* Responsive para móviles */
        @media (max-width: 768px) {
            #fotoModal .modal-content > div {
                max-width: 100%;
                padding: 15px;
                border-radius: 0;
            }
            
            #fotoAmpliada {
                max-height: 80vh;
            }
            
            #fotoModal .btn-close-modal {
                top: 10px;
                right: 10px;
                padding: 10px 16px;
                font-size: 14px;
            }
        }
        
        /* Para pantallas muy pequeñas */
        @media (max-width: 480px) {
            #fotoModal .modal-content > div {
                padding: 10px;
            }
            
            #fotoAmpliada {
                max-height: 75vh;
            }
            
            #fotoModal .btn-close-modal {
                padding: 8px 12px;
                font-size: 13px;
            }
        }
        
        /* Estilos para notas de compras por producto */
        .notas-compras-container {
            min-height: 40px;
        }
        
        .nota-existente {
            background-color: #fff8e1;
            border-left: 3px solid #ffc107;
            padding: 10px;
            border-radius: 4px;
        }
        
        .btn-agregar-nota,
        .btn-editar-nota {
            background-color: #ffc107;
            color: #212529;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: background-color 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-agregar-nota:hover,
        .btn-editar-nota:hover {
            background-color: #e0a800;
        }
        
        .btn-editar-nota {
            margin-top: 8px;
            background-color: #17a2b8;
            color: white;
        }
        
        .btn-editar-nota:hover {
            background-color: #138496;
        }
        
        /* Estilos para observaciones generales de compras */
        .observaciones-compras-section {
            margin-bottom: 30px;
        }
        
        .observaciones-compras-container {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
        }
        
        .observacion-existente {
            background-color: white;
            border-left: 4px solid #0E544C;
            padding: 15px;
            border-radius: 4px;
        }
        
        .observacion-contenido {
            font-size: 14px;
            color: #333;
            line-height: 1.6;
            margin-bottom: 12px;
        }
        
        .observacion-info {
            display: flex;
            gap: 20px;
            font-size: 12px;
            color: #666;
            margin-bottom: 12px;
        }
        
        .observacion-info span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        /* Formulario de edición inline */
        .form-nota-inline {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 12px;
        }
        
        .form-nota-inline textarea {
            width: 100%;
            min-height: 80px;
            padding: 8px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 13px;
            resize: vertical;
            margin-bottom: 10px;
        }
        
        .form-nota-inline .btn-group {
            display: flex;
            gap: 8px;
        }
        
        .form-nota-inline .btn {
            flex: 1;
            padding: 6px 12px;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    
    <div class="container">
        <div class="header">
            <div>
                <h1 class="titulo">SOLICITUD DE COTIZACIÓN</h1>
                <div style="display: flex; gap: 20px; margin-top: 10px;">
                    <div class="info-item">
                        <div class="info-label">Código:</div>
                        <div class="info-value"><?php echo htmlspecialchars($solicitud['codigo']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Versión:</div>
                        <div class="info-value"><?php echo htmlspecialchars($solicitud['version']); ?></div>
                    </div>
                </div>
            </div>
            <div class="user-info">
                <div style="text-align: right;">
                    <div style="font-weight: bold;">
                        <?php echo htmlspecialchars($esAdmin ? $usuario['nombre'] : $usuario['Nombre'].' '.$usuario['Apellido']); ?>
                    </div>
                    <div style="color: #666; font-size: 14px;">
                        <?php echo obtenerCargoPrincipalUsuario($_SESSION['usuario_id']); ?>
                    </div>
                </div>
                <div class="user-avatar">
                    <?php echo strtoupper(substr($esAdmin ? $usuario['nombre'] : $usuario['Nombre'], 0, 1)); ?>
                </div>
            </div>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <div class="solicitud-header">
            <div class="header-grid">
                <div class="info-item">
                    <div class="info-label">Estado:</div>
                    <div class="info-value">
                        <?php 
                        $estadoClase = 'estado-' . $solicitud['estado'];
                        $estadoTexto = ucfirst(str_replace('_', ' ', $solicitud['estado']));
                        ?>
                        <span class="estado-badge <?php echo $estadoClase; ?>">
                            <?php echo htmlspecialchars($estadoTexto); ?>
                        </span>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Solicitante:</div>
                    <div class="info-value"><?php echo htmlspecialchars($solicitud['solicitante_nombre']); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Fecha de Solicitud:</div>
                    <div class="info-value">
                        <?php echo date('d/m/Y', strtotime($solicitud['fecha_solicitud'])); ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Última Actualización:</div>
                    <div class="info-value">
                        <?php echo date('d/m/Y H:i', strtotime($solicitud['updated_at'])); ?>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($solicitud['observaciones'])): ?>
                <div class="info-item">
                    <div class="info-label">Observaciones Generales:</div>
                    <div class="info-value" style="background-color: #f8f9fa; padding: 10px; border-radius: 4px;">
                        <?php echo nl2br(htmlspecialchars($solicitud['observaciones'])); ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="actions">
                <?php if ($solicitud['estado'] === 'pendiente' && 
                         ($solicitud['solicitante_id'] == $_SESSION['usuario_id'] || $esAdmin)): ?>
                    <a style="display:none;" href="solicitud_cotizacion.php?editar=<?php echo $solicitudId; ?>" 
                       class="btn btn-warning">
                        <i class="fas fa-edit"></i> Editar Solicitud
                    </a>
                <?php endif; ?>
                
                <button style="display:none;" type="button" class="btn btn-secondary" onclick="imprimirSolicitud()">
                    <i class="fas fa-print"></i> Imprimir
                </button>
                
                <?php if ($esAdmin || esGerente()): ?>
                    <!-- Solo gerentes y admin pueden aprobar -->
                    <?php if ($solicitud['estado'] === 'pendiente'): ?>
                        <button type="button" class="btn btn-success" onclick="mostrarModal('aprobar')">
                            <i class="fas fa-check"></i> Aprobar
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($solicitud['estado'] === 'pendiente' || $solicitud['estado'] === 'en_proceso'): ?>
                        <button type="button" class="btn btn-danger" onclick="mostrarModal('rechazar')">
                            <i class="fas fa-times"></i> Rechazar
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($solicitud['estado'] === 'aprobada'): ?>
                        <button style="display:none;" type="button" class="btn btn-warning" onclick="mostrarModal('en_proceso')">
                            <i class="fas fa-cogs"></i> En Proceso
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($solicitud['estado'] === 'en_proceso'): ?>
                        <button type="button" class="btn btn-primary" onclick="mostrarModal('completar')">
                            <i class="fas fa-flag-checkered"></i> Completar
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Productos -->
        <div class="productos-section">
            <h2 class="section-title">
                <i class="fas fa-boxes"></i> Productos Solicitados
                <span style="font-size: 14px; color: #666; margin-left: 10px;">
                    (<?php echo count($productos); ?> productos)
                </span>
            </h2>
            
            <?php if (empty($productos)): ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <i class="fas fa-box-open" style="font-size: 48px; margin-bottom: 10px;"></i>
                    <p>No hay productos en esta solicitud</p>
                </div>
            <?php else: ?>
                <table class="productos-table">
                    <thead>
                        <tr>
                            <th style="width: 40%;">Producto</th>
                            <th style="width: 15%;">Referencia</th>
                            <th style="width: 10%;">Cantidad</th>
                            <th style="width: 15%;">Precio Unitario (USD)</th>
                            <th style="width: 20%;">Notas de Compras</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $totalEstimado = 0;
                        foreach ($productos as $producto): 
                            $subtotal = $producto['cantidad'] * $producto['precio_unitario'];
                            $totalEstimado += $subtotal;
                        ?>
                            <tr>
                                <td>
                                    <div style="font-weight: bold;">
                                        <?php echo htmlspecialchars($producto['producto_descripcion']); ?>
                                    </div>
                                </td>
                                <td class="foto-container">
                                    <?php if (!empty($producto['foto_referencia'])): 
                                        $rutaFotoWeb = '/modulos/compras/uploads/cotizaciones/' . $producto['foto_referencia'];
                                        $rutaFotoServidor = $_SERVER['DOCUMENT_ROOT'] . $rutaFotoWeb;
                                        
                                        if (file_exists($rutaFotoServidor)):
                                    ?>
                                        <img src="<?php echo htmlspecialchars($rutaFotoWeb); ?>" 
                                             alt="Referencia" 
                                             class="foto-referencia"
                                             onclick="ampliarFoto('<?php echo htmlspecialchars($rutaFotoWeb); ?>')"
                                             style="cursor: pointer;"
                                             onerror="this.parentElement.innerHTML='<div class=\'no-foto\'><i class=\'fas fa-exclamation-triangle\'></i> Error al cargar imagen</div>';">
                                    <?php else: ?>
                                        <div class="no-foto">
                                            <i class="fas fa-image"></i> Imagen no disponible
                                        </div>
                                    <?php endif; else: ?>
                                        <div class="no-foto">
                                            <i class="fas fa-ban"></i> Sin imagen
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($producto['cantidad']); ?></td>
                                <td>
                                    $<?php echo number_format($producto['precio_unitario'], 2); ?>
                                    <?php if ($producto['precio_unitario'] > 0): ?>
                                        <br><small style="color: #666;">
                                            Subtotal: $<?php echo number_format($subtotal, 2); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="notas-compras-container" id="notasContainer<?php echo $producto['id']; ?>">
                                        <?php if (!empty($producto['notas_compras'])): ?>
                                            <div class="nota-existente">
                                                <div style="font-size: 13px; color: #333; margin-bottom: 5px;">
                                                    <?php echo nl2br(htmlspecialchars($producto['notas_compras'])); ?>
                                                </div>
                                                <div style="font-size: 11px; color: #999;">
                                                    <?php echo date('d/m/Y H:i', strtotime($producto['fecha_notas_compras'])); ?>
                                                </div>
                                                <?php if (puedeCompletarSolicitudes()): ?>
                                                    <button type="button" class="btn-editar-nota" 
                                                            onclick="editarNotaProducto(<?php echo $producto['id']; ?>)">
                                                        <i class="fas fa-edit"></i> Editar
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <?php if (puedeCompletarSolicitudes()): ?>
                                                <button type="button" class="btn-agregar-nota" 
                                                        onclick="agregarNotaProducto(<?php echo $producto['id']; ?>)">
                                                    <i class="fas fa-plus"></i> Agregar Nota
                                                </button>
                                            <?php else: ?>
                                                <span style="color: #999; font-style: italic; font-size: 13px;">Sin notas</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <?php if ($totalEstimado > 0): ?>
                            <tr style="background-color: #f8f9fa;">
                                <td colspan="3" style="text-align: right; font-weight: bold;">Total Estimado:</td>
                                <td style="font-weight: bold; color: #0E544C;">
                                    $<?php echo number_format($totalEstimado, 2); ?>
                                </td>
                                <td></td>
                            </tr>
                        <?php endif; ?>
                    </tfoot>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- NUEVA SECCIÓN: Observaciones Generales de Compras -->
        <?php if (puedeCompletarSolicitudes() || !empty($solicitud['observaciones_compras'])): ?>
        <div class="observaciones-compras-section">
            <h2 class="section-title">
                <i class="fas fa-clipboard-list"></i> Observaciones de Compras
            </h2>
            
            <div class="observaciones-compras-container">
                <?php if (!empty($solicitud['observaciones_compras'])): ?>
                    <div class="observacion-existente">
                        <div class="observacion-contenido">
                            <?php echo nl2br(htmlspecialchars($solicitud['observaciones_compras'])); ?>
                        </div>
                        <div class="observacion-info">
                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($solicitud['compras_usuario_nombre']); ?></span>
                            <span><i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_observaciones_compras'])); ?></span>
                        </div>
                        <?php if (puedeCompletarSolicitudes()): ?>
                            <button type="button" class="btn btn-warning btn-sm" onclick="editarObservacionesCompras()">
                                <i class="fas fa-edit"></i> Editar Observaciones
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php if (puedeCompletarSolicitudes()): ?>
                        <div style="text-align: center; padding: 20px;">
                            <button type="button" class="btn btn-primary" onclick="agregarObservacionesCompras()">
                                <i class="fas fa-plus"></i> Agregar Observaciones Generales
                            </button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Firmas -->
        <div class="firmas-section">
            <h2 class="section-title"><i class="fas fa-signature"></i> Aprobación Gerencial</h2>
            <div class="firmas-grid">
                <div class="firma-box <?php echo !empty($solicitud['gerente_aprobador_nombre']) ? 'aprobada' : 'pendiente'; ?>">
                    <div class="firma-nombre">Gerencia</div>
                    
                    <?php if (!empty($solicitud['gerente_aprobador_nombre'])): ?>
                        <div style="margin-top: 10px;">
                            <div style="font-weight: bold;"><?php echo htmlspecialchars($solicitud['gerente_aprobador_nombre']); ?></div>
                            <div class="firma-fecha">
                                Aprobado: <?php echo date('d/m/Y', strtotime($solicitud['fecha_aprobacion'])); ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="firma-placeholder">
                            Pendiente de aprobación
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Historial -->
        <?php if (!empty($historial)): ?>
            <div class="historial-section" style="display:none;">
                <h2 class="section-title"><i class="fas fa-history"></i> Creado</h2>
                <div class="historial-list">
                    <?php foreach ($historial as $registro): 
                        $detalles = json_decode($registro['detalles'], true);
                    ?>
                        <div class="historial-item">
                            <div class="historial-item-header">
                                <div class="historial-usuario">
                                    <?php echo htmlspecialchars($registro['usuario_nombre']); ?>
                                </div>
                                <div class="historial-fecha">
                                    <?php echo date('d/m/Y H:i', strtotime($registro['fecha_accion'])); ?>
                                </div>
                            </div>
                            <div class="historial-accion">
                                <strong>Acción:</strong> 
                                <?php echo htmlspecialchars(ucfirst($registro['accion'])); ?>
                            </div>
                            <?php if (!empty($detalles['observaciones'])): ?>
                                <div class="historial-detalles">
                                    <strong>Observaciones:</strong> 
                                    <?php echo htmlspecialchars($detalles['observaciones']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal para acciones -->
    <div id="actionModal" class="modal">
        <div class="modal-content">
            <form method="post" id="actionForm">
                <input type="hidden" name="accion" id="accionInput">
                
                <h3 class="modal-title" id="modalTitle">Confirmar Acción</h3>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="observaciones_accion">Observaciones (opcional):</label>
                    <textarea id="observaciones_accion" name="observaciones_accion" 
                              placeholder="Explique la razón de esta acción..."></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="cerrarModal()">
                        Cancelar
                    </button>
                    <button type="submit" class="btn" id="modalActionBtn">
                        Confirmar
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal para foto ampliada - MEJORADO -->
<div id="fotoModal" class="modal">
    <button type="button" class="btn-close-modal" onclick="cerrarFotoModal()">
        <i class="fas fa-times"></i> Cerrar
    </button>
    <div class="modal-content">
        <div>
            <img id="fotoAmpliada" src="" alt="Foto ampliada">
        </div>
    </div>
</div>

    <script>
        function mostrarModal(accion) {
            const modal = document.getElementById('actionModal');
            const accionInput = document.getElementById('accionInput');
            const modalTitle = document.getElementById('modalTitle');
            const modalActionBtn = document.getElementById('modalActionBtn');
            
            let titulo = '';
            let textoBoton = '';
            let claseBoton = '';
            
            switch(accion) {
                case 'aprobar':
                    titulo = 'Aprobar Solicitud';
                    textoBoton = 'Aprobar';
                    claseBoton = 'btn-success';
                    break;
                case 'rechazar':
                    titulo = 'Rechazar Solicitud';
                    textoBoton = 'Rechazar';
                    claseBoton = 'btn-danger';
                    break;
                case 'en_proceso':
                    titulo = 'Marcar como En Proceso';
                    textoBoton = 'En Proceso';
                    claseBoton = 'btn-warning';
                    break;
                case 'completar':
                    titulo = 'Completar Solicitud';
                    textoBoton = 'Completar';
                    claseBoton = 'btn-primary';
                    break;
            }
            
            accionInput.value = accion;
            modalTitle.textContent = titulo;
            modalActionBtn.textContent = textoBoton;
            modalActionBtn.className = 'btn ' + claseBoton;
            
            modal.style.display = 'block';
        }
        
        function cerrarModal() {
            document.getElementById('actionModal').style.display = 'none';
        }
        
        function ampliarFoto(ruta) {
    const modal = document.getElementById('fotoModal');
    const img = document.getElementById('fotoAmpliada');
    
    img.src = ruta;
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    // Agregar funcionalidad de zoom con scroll
    img.style.cursor = 'zoom-in';
    img.onclick = function() {
        if (this.style.transform === 'scale(2)') {
            this.style.transform = 'scale(1)';
            this.style.cursor = 'zoom-in';
        } else {
            this.style.transform = 'scale(2)';
            this.style.cursor = 'zoom-out';
        }
    };
    
    // Cerrar con ESC
    const escHandler = function(e) {
        if (e.key === 'Escape') {
            cerrarFotoModal();
            document.removeEventListener('keydown', escHandler);
        }
    };
    document.addEventListener('keydown', escHandler);
}
        
        function cerrarFotoModal() {
            const modal = document.getElementById('fotoModal');
            modal.style.display = 'none';
            
            // Restaurar scroll del body
            document.body.style.overflow = 'auto';
        }
        
        // Cerrar modal al hacer clic fuera de la imagen
        document.getElementById('fotoModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarFotoModal();
            }
        });
        
        function imprimirSolicitud() {
            const printWindow = window.open('', '_blank');
            const contenido = document.querySelector('.container').innerHTML;
            
            printWindow.document.write('<html><head><title>Solicitud de Cotización</title>');
            printWindow.document.write('<style>');
            printWindow.document.write('body { font-family: Calibri, sans-serif; margin: 20px; }');
            printWindow.document.write('.container { max-width: 1200px; margin: 0 auto; }');
            printWindow.document.write('.header { border-bottom: 2px solid #51B8AC; padding-bottom: 15px; margin-bottom: 20px; }');
            printWindow.document.write('.titulo { color: #0E544C; }');
            printWindow.document.write('.solicitud-header { background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; margin-bottom: 20px; }');
            printWindow.document.write('.productos-table { width: 100%; border-collapse: collapse; margin: 20px 0; }');
            printWindow.document.write('.productos-table th, .productos-table td { border: 1px solid #ddd; padding: 8px; }');
            printWindow.document.write('.productos-table th { background-color: #0E544C; color: white; }');
            printWindow.document.write('.estado-badge { padding: 4px 8px; border-radius: 10px; font-size: 12px; }');
            printWindow.document.write('.firma-box { border: 1px solid #ddd; padding: 15px; margin: 10px 0; }');
            printWindow.document.write('@media print { .btn, .actions { display: none; } }');
            printWindow.document.write('</style>');
            printWindow.document.write('</head><body>');
            
            // Agregar encabezado de impresión
            printWindow.document.write('<div style="text-align: center; margin-bottom: 20px;">');
            printWindow.document.write('<h1 style="color: #0E544C;">SOLICITUD DE COTIZACIÓN</h1>');
            printWindow.document.write('<p>Impreso el: ' + new Date().toLocaleDateString() + ' ' + new Date().toLocaleTimeString() + '</p>');
            printWindow.document.write('</div>');
            
            printWindow.document.write(contenido);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.print();
        }
        
        // Cerrar modales al hacer clic fuera
        window.onclick = function(event) {
            const modal = document.getElementById('actionModal');
            const fotoModal = document.getElementById('fotoModal');
            
            if (event.target === modal) {
                modal.style.display = 'none';
            }
            
            if (event.target === fotoModal) {
                fotoModal.style.display = 'none';
            }
        };
        
        // Agregar nota a producto
        function agregarNotaProducto(productoId) {
            const container = document.getElementById('notasContainer' + productoId);
            
            container.innerHTML = `
                <div class="form-nota-inline">
                    <textarea id="notaTexto${productoId}" placeholder="Escriba las notas para este producto..." autofocus></textarea>
                    <div class="btn-group">
                        <button type="button" class="btn btn-success" onclick="guardarNotaProducto(${productoId})">
                            <i class="fas fa-save"></i> Guardar
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="cancelarNotaProducto(${productoId}, false)">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                    </div>
                </div>
            `;
            
            document.getElementById('notaTexto' + productoId).focus();
        }
        
        // Editar nota existente
        function editarNotaProducto(productoId) {
            const container = document.getElementById('notasContainer' + productoId);
            const notaActual = container.querySelector('.nota-existente div').innerText;
            
            container.innerHTML = `
                <div class="form-nota-inline">
                    <textarea id="notaTexto${productoId}" autofocus>${notaActual}</textarea>
                    <div class="btn-group">
                        <button type="button" class="btn btn-success" onclick="guardarNotaProducto(${productoId})">
                            <i class="fas fa-save"></i> Actualizar
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="cancelarNotaProducto(${productoId}, true)">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                    </div>
                </div>
            `;
            
            document.getElementById('notaTexto' + productoId).focus();
        }
        
        // Guardar nota de producto
        function guardarNotaProducto(productoId) {
            const nota = document.getElementById('notaTexto' + productoId).value.trim();
            
            if (!nota) {
                alert('Por favor escriba una nota antes de guardar');
                return;
            }
            
            if (!confirm('¿Guardar esta nota para el producto?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('producto_id', productoId);
            formData.append('nota', nota);
            
            fetch('ajax/guardar_nota_producto.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error al guardar la nota');
                console.error(error);
            });
        }
        
        // Cancelar edición de nota
        function cancelarNotaProducto(productoId, tieneNotaExistente) {
            if (tieneNotaExistente) {
                location.reload();
            } else {
                const container = document.getElementById('notasContainer' + productoId);
                container.innerHTML = `
                    <button type="button" class="btn-agregar-nota" onclick="agregarNotaProducto(${productoId})">
                        <i class="fas fa-plus"></i> Agregar Nota
                    </button>
                `;
            }
        }
        
        // Agregar observaciones generales de compras
        function agregarObservacionesCompras() {
            const container = document.querySelector('.observaciones-compras-container');
            
            container.innerHTML = `
                <div class="form-nota-inline">
                    <textarea id="observacionesComprasTexto" placeholder="Escriba las observaciones generales de Compras..." 
                              style="min-height: 120px;" autofocus></textarea>
                    <div class="btn-group">
                        <button type="button" class="btn btn-success" onclick="guardarObservacionesCompras()">
                            <i class="fas fa-save"></i> Guardar Observaciones
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="location.reload()">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                    </div>
                </div>
            `;
            
            document.getElementById('observacionesComprasTexto').focus();
        }
        
        // Editar observaciones generales
        function editarObservacionesCompras() {
            const container = document.querySelector('.observaciones-compras-container');
            const observacionActual = container.querySelector('.observacion-contenido').innerText;
            
            container.innerHTML = `
                <div class="form-nota-inline">
                    <textarea id="observacionesComprasTexto" style="min-height: 120px;" autofocus>${observacionActual}</textarea>
                    <div class="btn-group">
                        <button type="button" class="btn btn-success" onclick="guardarObservacionesCompras()">
                            <i class="fas fa-save"></i> Actualizar Observaciones
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="location.reload()">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                    </div>
                </div>
            `;
            
            document.getElementById('observacionesComprasTexto').focus();
        }
        
        // Guardar observaciones generales
        function guardarObservacionesCompras() {
            const observaciones = document.getElementById('observacionesComprasTexto').value.trim();
            
            if (!observaciones) {
                alert('Por favor escriba las observaciones antes de guardar');
                return;
            }
            
            if (!confirm('¿Guardar estas observaciones generales de Compras?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('solicitud_id', <?php echo $solicitudId; ?>);
            formData.append('observaciones', observaciones);
            
            fetch('ajax/guardar_observaciones_compras.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error al guardar las observaciones');
                console.error(error);
            });
        }
    </script>
</body>
</html>