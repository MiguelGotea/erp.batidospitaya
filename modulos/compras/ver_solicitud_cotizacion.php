<?php
require_once '../../core/auth/auth.php';
require_once '../../core/permissions/permissions.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once 'includes/funciones_compras.php';

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
$usuarioId = $_SESSION['usuario_id'];

// Obtener información del usuario actual

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

    // Obtener todas las fotos de todos los productos de esta solicitud
    $stmtFotos = $conn->prepare("
        SELECT f.* 
        FROM solicitudes_cotizacion_fotos f
        JOIN solicitudes_cotizacion_productos p ON f.producto_id = p.id
        WHERE p.solicitud_id = ?
    ");
    $stmtFotos->execute([$solicitudId]);
    $todasLasFotos = $stmtFotos->fetchAll(PDO::FETCH_ASSOC);
    
    // Agrupar fotos por producto_id
    $fotosPorProducto = [];
    foreach ($todasLasFotos as $foto) {
        $fotosPorProducto[$foto['producto_id']][] = $foto['foto_nombre'];
    }


}
catch (Exception $e) {
    $_SESSION['error'] = 'Error al cargar la solicitud: ' . $e->getMessage();
    header('Location: historial_solicitudes_cotizacion.php');
    exit();
}

// Procesar acciones si se envía el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    $observaciones = trim($_POST['observaciones_accion'] ?? '');
    $usuarioId = $_SESSION['usuario_id'];
    $usuarioNombre = trim($usuario['Nombre'] . ' ' . $usuario['Apellido']);

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
                }
                elseif (in_array(49, $cargosUsuario)) {
                    $campoGerencia = 'aprobado_2';
                }
                else {
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


            case 'completar':
                // Verificar si es compras (9) o gerencia (16, 49)
                $puedeCompletar = puedeCompletarSolicitudes() || puedeAprobarSolicitudes();
                if (!$puedeCompletar) {
                    throw new Exception('No tiene permisos para completar solicitudes');
                }
                $nuevoEstado = 'completada';
                $accionHistorial = 'completada';

                // Actualizar observaciones generales de compras al completar
                $stmtUpdateCompras = $conn->prepare("
                    UPDATE solicitudes_cotizacion 
                    SET observaciones_compras = ?,
                        compras_usuario_id = ?,
                        compras_usuario_nombre = ?,
                        fecha_observaciones_compras = NOW()
                    WHERE id = ?
                ");
                $stmtUpdateCompras->execute([
                    $observaciones,
                    $usuarioId,
                    $usuarioNombre,
                    $solicitudId
                ]);
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

        $_SESSION['success'] = 'Solicitud actualizada exitosamente';
        header('Location: ver_solicitud_cotizacion.php?id=' . $solicitudId);
        exit();

    }
    catch (Exception $e) {
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/ver_solicitud_cotizacion.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Ver Solicitud de Cotización'); ?>

            <div class="container-fluid p-3">
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_SESSION['success']);
    unset($_SESSION['success']); ?>
            </div>
        <?php
endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($_SESSION['error']);
    unset($_SESSION['error']); ?>
            </div>
        <?php
endif; ?>
        
        <div class="solicitud-header">
            <?php
            $estadoClase = 'estado-' . $solicitud['estado'];
            $estadoTexto = ucfirst(str_replace('_', ' ', $solicitud['estado']));
            if ($solicitud['estado'] === 'completada') $estadoTexto = 'Finalizada';
            ?>
            
            <div class="header-top">
                <div class="header-title-container">
                    <h2>
                        <i class="fas fa-file-invoice"></i> 
                        Solicitud #<?php echo $solicitudId; ?>
                    </h2>
                    <div style="color: #666; font-size: 14px; margin-top: 5px;">
                        <i class="fas fa-calendar-alt me-1"></i> 
                        Creada el <?php echo date('d/m/Y', strtotime($solicitud['fecha_solicitud'])); ?>
                    </div>
                </div>

                <div class="header-status-actions">
                    <span class="estado-badge <?php echo $estadoClase; ?>">
                        <?php echo htmlspecialchars($estadoTexto); ?>
                    </span>
                    
                    <div class="action-buttons-top">
                        <?php if (esGerente()): ?>
                            <?php if ($solicitud['estado'] === 'pendiente'): ?>
                                <button type="button" class="btn btn-success" onclick="mostrarModal('aprobar')">
                                    <i class="fas fa-check"></i> Aprobar
                                </button>
                                <button type="button" class="btn btn-danger" onclick="mostrarModal('rechazar')">
                                    <i class="fas fa-times"></i> Rechazar
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if (puedeCompletarSolicitudes()): ?>
                            <?php if ($solicitud['estado'] === 'aprobada'): ?>
                                <button type="button" class="btn btn-primary" onclick="mostrarModal('completar')">
                                    <i class="fas fa-flag-checkered"></i> Finalizar
                                </button>
                            <?php endif; ?>
                            <?php if ($solicitud['estado'] === 'aprobada' || ($solicitud['estado'] === 'pendiente' && $solicitud['solicitante_id'] == $usuarioId)): ?>
                                <button type="button" class="btn btn-danger" onclick="mostrarModal('cancelar')">
                                    <i class="fas fa-ban"></i> Cancelar
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="header-grid">
                <div class="info-item">
                    <div class="info-label">Solicitante:</div>
                    <div class="info-value">
                        <i class="fas fa-user-circle me-1" style="color: #0E544C;"></i>
                        <?php echo htmlspecialchars($solicitud['solicitante_nombre']); ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Última Actualización:</div>
                    <div class="info-value">
                        <i class="fas fa-sync-alt me-1" style="color: #666;"></i>
                        <?php echo date('d/m/Y H:i', strtotime($solicitud['updated_at'])); ?>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">Aprobación Gerencial:</div>
                    <div class="info-value">
                        <?php if (!empty($solicitud['gerente_aprobador_nombre'])): ?>
                            <div style="font-weight: bold; color: #155724;">
                                <i class="fas fa-check-circle me-1"></i>
                                <?php echo htmlspecialchars($solicitud['gerente_aprobador_nombre']); ?>
                            </div>
                            <div style="font-size: 0.85em; color: #666; margin-left: 20px;">
                                <?php echo date('d/m/Y', strtotime($solicitud['fecha_aprobacion'])); ?>
                            </div>
                        <?php else: ?>
                            <span class="text-muted italic" style="font-size: 0.9em;">
                                <i class="fas fa-clock me-1"></i> Pendiente de Revisión
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($solicitud['observaciones'])): ?>
                <div class="info-item mt-3">
                    <div class="info-label">Observaciones del Solicitante:</div>
                    <div class="info-value" style="background-color: #fcfcfc; padding: 12px; border: 1px dashed #ddd; border-radius: 6px; font-style: italic;">
                        <?php echo nl2br(htmlspecialchars($solicitud['observaciones'])); ?>
                    </div>
                </div>
            <?php endif; ?>
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
            <?php
else: ?>
                <table class="productos-table">
                    <thead>
                        <tr>
                            <th style="width: 40%;">Producto</th>
                            <th style="width: 15%;">Referencia</th>
                            <th style="width: 10%;">Cantidad</th>
                            <th style="width: 15%;">Precio Unitario (C$)</th>
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
                                    <div class="producto-nombre">
                                        <?php echo htmlspecialchars($producto['producto_descripcion']); ?>
                                    </div>
                                </td>
                                <td class="foto-container">
                                    <?php 
                                    $fotos = $fotosPorProducto[$producto['id']] ?? [];
                                    if (empty($fotos) && !empty($producto['foto_referencia'])) {
                                        // Compatibilidad con el sistema anterior (foto única)
                                        $fotos = [$producto['foto_referencia']];
                                    }

                                    if (!empty($fotos)): ?>
                                        <div class="galeria-fotos">
                                            <?php foreach ($fotos as $foto): 
                                                $rutaFotoWeb = '/modulos/compras/uploads/cotizaciones/' . $foto;
                                                $rutaFotoServidor = $_SERVER['DOCUMENT_ROOT'] . $rutaFotoWeb;
                                                
                                                if (file_exists($rutaFotoServidor)):
                                            ?>
                                                <div class="foto-thumb" onclick="ampliarFoto(<?php echo htmlspecialchars(json_encode($fotos)); ?>, <?php echo array_search($foto, $fotos); ?>)">
                                                    <img src="<?php echo htmlspecialchars($rutaFotoWeb); ?>" 
                                                         alt="Ref" 
                                                         onerror="this.parentElement.style.display='none';">
                                                </div>
                                            <?php endif; endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="no-foto">
                                            <i class="fas fa-image"></i> Sin imágenes
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($producto['cantidad']); ?></td>
                                <td>
                                    C$<?php echo number_format($producto['precio_unitario'], 2); ?>
                                    <?php if ($producto['precio_unitario'] > 0): ?>
                                        <br><small style="color: #666;">
                                            Subtotal: C$<?php echo number_format($subtotal, 2); ?>
                                        </small>
                                    <?php
        endif; ?>
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
                                                <?php
            endif; ?>
                                            </div>
                                        <?php
        else: ?>
                                            <?php if (puedeCompletarSolicitudes()): ?>
                                                <button type="button" class="btn-agregar-nota" 
                                                        onclick="agregarNotaProducto(<?php echo $producto['id']; ?>)">
                                                    <i class="fas fa-plus"></i> Agregar Nota
                                                </button>
                                            <?php
            else: ?>
                                                <span style="color: #999; font-style: italic; font-size: 13px;">Sin notas</span>
                                            <?php
            endif; ?>
                                        <?php
        endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php
    endforeach; ?>
                    </tbody>
                    <tfoot>
                        <?php if ($totalEstimado > 0): ?>
                            <tr style="background-color: #f8f9fa;">
                                <td colspan="3" style="text-align: right; font-weight: bold;">Total Estimado:</td>
                                <td style="font-weight: bold; color: #0E544C;">
                                    C$<?php echo number_format($totalEstimado, 2); ?>
                                </td>
                                <td></td>
                            </tr>
                        <?php
    endif; ?>
                    </tfoot>
                </table>
            <?php
endif; ?>
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
                        <?php
        endif; ?>
                    </div>
                <?php
    else: ?>
                    <?php if (puedeCompletarSolicitudes()): ?>
                        <div style="text-align: center; padding: 20px;">
                            <button type="button" class="btn btn-primary" onclick="agregarObservacionesCompras()">
                                <i class="fas fa-plus"></i> Agregar Observaciones Generales
                            </button>
                        </div>
                    <?php
        endif; ?>
                <?php
    endif; ?>
            </div>
        </div>
        <?php
endif; ?>
        
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
                            <?php
        endif; ?>
                        </div>
                    <?php
    endforeach; ?>
                </div>
            </div>
        <?php
endif; ?>
            </div><!-- /.container-fluid -->
        </div><!-- /.sub-container -->
    </div><!-- /.main-container -->
    
    
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
            <button type="button" class="carousel-control prev" id="btnPrevFoto" onclick="prevFoto()">
                <i class="fas fa-chevron-left"></i>
            </button>
            
            <img id="fotoAmpliada" src="" alt="Foto ampliada">
            
            <button type="button" class="carousel-control next" id="btnNextFoto" onclick="nextFoto()">
                <i class="fas fa-chevron-right"></i>
            </button>
            
            <div class="foto-counter" id="fotoCounter">1 de 1</div>
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
                case 'completar':
                    titulo = 'Finalizar Solicitud';
                    textoBoton = 'Finalizar';
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
        
        let currentImages = [];
        let currentIndex = 0;

        function ampliarFoto(images, index) {
            currentImages = Array.isArray(images) ? images : [images];
            currentIndex = index;
            
            const modal = document.getElementById('fotoModal');
            actualizarVisor();
            
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            
            // Cerrar con ESC y navegar con flechas
            const keyboardHandler = function(e) {
                if (e.key === 'Escape') {
                    cerrarFotoModal();
                    document.removeEventListener('keydown', keyboardHandler);
                } else if (e.key === 'ArrowRight') {
                    nextFoto();
                } else if (e.key === 'ArrowLeft') {
                    prevFoto();
                }
            };
            document.addEventListener('keydown', keyboardHandler);
        }

        function actualizarVisor() {
            const img = document.getElementById('fotoAmpliada');
            const counter = document.getElementById('fotoCounter');
            const btnPrev = document.getElementById('btnPrevFoto');
            const btnNext = document.getElementById('btnNextFoto');
            
            const ruta = '/modulos/compras/uploads/cotizaciones/' + currentImages[currentIndex];
            img.src = ruta;
            counter.textContent = `${currentIndex + 1} de ${currentImages.length}`;
            
            // Mostrar/Ocultar controles según cantidad
            if (currentImages.length > 1) {
                btnPrev.style.display = 'flex';
                btnNext.style.display = 'flex';
            } else {
                btnPrev.style.display = 'none';
                btnNext.style.display = 'none';
            }
        }

        function nextFoto() {
            if (currentImages.length <= 1) return;
            currentIndex = (currentIndex + 1) % currentImages.length;
            actualizarVisor();
        }

        function prevFoto() {
            if (currentImages.length <= 1) return;
            currentIndex = (currentIndex - 1 + currentImages.length) % currentImages.length;
            actualizarVisor();
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

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Modal de Ayuda -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1"
         aria-labelledby="pageHelpModalLabel" aria-hidden="true"
         data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="pageHelpModalLabel">
                        <i class="fas fa-info-circle me-2"></i>
                        Guía — Ver Solicitud de Cotización
                    </h5>
                    <button type="button" class="btn-close btn-close-white"
                            data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-primary border-bottom pb-2 fw-bold">
                                        <i class="fas fa-eye me-2"></i> ¿Qué muestra esta página?
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Detalle completo de una solicitud de cotización: datos generales, productos solicitados con foto de referencia, aprobaciones gerenciales e historial de cambios.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-success border-bottom pb-2 fw-bold">
                                        <i class="fas fa-check-circle me-2"></i> Acciones disponibles
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Gerencia puede <strong>Aprobar</strong> o <strong>Rechazar</strong> solicitudes pendientes. Compras puede agregar notas por producto y observaciones generales.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-warning border-bottom pb-2 fw-bold">
                                        <i class="fas fa-exclamation-triangle me-2"></i> Estados posibles
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        <strong>Pendiente</strong> → <strong>Aprobada / Rechazada</strong> → <strong>En Proceso</strong> → <strong>Completada</strong>. Una solicitud cancelada no puede reactivarse.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-info border-bottom pb-2 fw-bold">
                                        <i class="fas fa-image me-2"></i> Fotos de referencia
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Haga clic sobre la imagen en miniatura para ampliarla. Dentro del visor puede hacer clic sobre la imagen para hacer zoom.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
