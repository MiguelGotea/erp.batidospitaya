<?php
// Al inicio del archivo, verificar autenticación y acceso al módulo
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
require_once '../../../core/layout/header_universal.php';
require_once '../../../core/layout/menu_lateral.php';
require_once '../../../core/permissions/permissions.php';

//******************************Estándar para header******************************

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
// Verificar acceso al módulo
if (!tienePermiso('historial_reclamos', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

// Verificar que se haya proporcionado un ID de reclamo
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index_reclamos_publico.php");
    exit();
}

$reclamo_id = intval($_GET['id']);

try {
    // Obtener información básica del reclamo
    $sqlReclamo = "SELECT *, DATE_FORMAT(CONVERT_TZ(fecha_hora, '+00:00', '-06:00'), '%d-%b-%y %h:%i %p') as fecha_registro_formateada FROM reclamos WHERE id = :id";
    $stmtReclamo = $conn->prepare($sqlReclamo);
    $stmtReclamo->bindParam(':id', $reclamo_id, PDO::PARAM_INT);
    $stmtReclamo->execute();
    $reclamo = $stmtReclamo->fetch(PDO::FETCH_ASSOC);

    if (!$reclamo) {
        header("Location: index_reclamos_publico.php");
        exit();
    }

    // Obtener productos del reclamo
    $sqlProductos = "SELECT producto, precio FROM reclamos_productos WHERE reclamo_id = :id";
    $stmtProductos = $conn->prepare($sqlProductos);
    $stmtProductos->bindParam(':id', $reclamo_id, PDO::PARAM_INT);
    $stmtProductos->execute();
    $productos = $stmtProductos->fetchAll(PDO::FETCH_ASSOC);

    // Obtener imágenes del reclamo
    $sqlImagenes = "SELECT ruta_imagen FROM reclamos_imagenes WHERE reclamo_id = :id";
    $stmtImagenes = $conn->prepare($sqlImagenes);
    $stmtImagenes->bindParam(':id', $reclamo_id, PDO::PARAM_INT);
    $stmtImagenes->execute();
    $imagenes = $stmtImagenes->fetchAll(PDO::FETCH_ASSOC);

    // Obtener videos del reclamo
    $sqlVideos = "SELECT ruta_video FROM reclamos_videos WHERE reclamo_id = :id";
    $stmtVideos = $conn->prepare($sqlVideos);
    $stmtVideos->bindParam(':id', $reclamo_id, PDO::PARAM_INT);
    $stmtVideos->execute();
    $videos = $stmtVideos->fetchAll(PDO::FETCH_ASSOC);

    // Obtener reporte de investigación si existe
    $sqlReporte = "SELECT * FROM reportes_investigacion WHERE reclamo_id = :id";
    $stmtReporte = $conn->prepare($sqlReporte);
    $stmtReporte->bindParam(':id', $reclamo_id, PDO::PARAM_INT);
    $stmtReporte->execute();
    $reporte = $stmtReporte->fetch(PDO::FETCH_ASSOC);

    // Obtener colaboradores involucrados si existe reporte
    $colaboradores = [];
    if ($reporte) {
        $sqlColaboradores = "SELECT * FROM reportes_colaboradores WHERE reporte_id = :reporte_id";
        $stmtColaboradores = $conn->prepare($sqlColaboradores);
        $stmtColaboradores->bindParam(':reporte_id', $reporte['id'], PDO::PARAM_INT);
        $stmtColaboradores->execute();
        $colaboradores = $stmtColaboradores->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    die("Error en la consulta: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Reclamo #<?php echo $reclamo_id; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>"> <!-- contiene main, sub container * y body -->
    <link rel="icon" href="icon12.png" type="image/png">
    <style>



.buttons-container {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: center;
    flex-grow: 1;
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
}

.user-info {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-left: auto;
}

.btn-agregar {
    background-color: transparent;
    color: #51B8AC;
    border: 1px solid #51B8AC;
    text-decoration: none;
    padding: 6px 10px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
    white-space: nowrap;
    font-size: 14px;
    flex-shrink: 0;
}

.btn-agregar.activo {
    background-color: #51B8AC;
    color: white;
    font-weight: normal;
}

.btn-agregar:hover {
    background-color: #0E544C;
    color: white;
    border-color: #0E544C;
}

        .btn-volver {
            background-color: #51B8AC;
            color: white;
            border: none;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-volver:hover {
            background-color: #0E544C;
        }

        .contenedor-principal {
            width: 100%;
            margin: 20px auto;
            padding: 0 20px;
        }

        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 30px;
        }

        .card-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            color: #0E544C;
            margin: 0;
        }

        .card-body {
            line-height: 1.6;
        }

        .info-group {
            margin-bottom: 15px;
        }

        .info-label {
            font-weight: bold;
            color: #555;
            display: block;
            margin-bottom: 5px;
        }

        .info-value {
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 4px;
            border-left: 3px solid #51B8AC;
        }

        .productos-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .productos-table th, 
        .productos-table td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }

        .productos-table th {
            background-color: #51B8AC;
            color: white;
        }

        .productos-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .colaboradores-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .colaboradores-table th, 
        .colaboradores-table td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }

        .colaboradores-table th {
            background-color: #51B8AC;
            color: white;
        }

        .colaboradores-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .no-data {
            text-align: center;
            color: #777;
            padding: 20px;
            font-style: italic;
        }

        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
        }

        .badge-pendiente {
            background-color: #FFC107;
            color: #333;
        }

        .badge-resuelto {
            background-color: #28A745;
            color: white;
        }

        .galeria-media {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
        }

        .media-evidencia {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 4px;
            cursor: pointer;
            transition: transform 0.3s;
            position: relative;
        }

        .media-evidencia:hover {
            transform: scale(1.05);
        }

        .media-icon {
            position: absolute;
            top: 5px;
            left: 5px;
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
        }

        .modal-media {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            overflow: auto;
        }

        .modal-content-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            width: 100%;
        }

        .modal-contenido {
            max-width: 90%;
            max-height: 80%;
            margin-top: 50px;
        }

        .modal-video {
            width: 80%;
            max-width: 800px;
            height: auto;
            max-height: 80vh;
        }

        .modal-caption {
            color: white;
            text-align: center;
            margin-top: 15px;
            max-width: 80%;
        }

        .cerrar-modal {
            position: absolute;
            top: 15px;
            right: 35px;
            color: white;
            font-size: 30px;
            font-weight: bold;
            cursor: pointer;
        }

        .media-controls {
            position: absolute;
            bottom: 10px;
            left: 0;
            right: 0;
            display: flex;
            justify-content: center;
            gap: 15px;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .media-evidencia:hover .media-controls {
            opacity: 1;
        }

        .control-btn {
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        @media (max-width: 768px) {
    .header-container {
        flex-direction: row;
        align-items: center;
        gap: 10px;
    }
    
    .buttons-container {
        position: static;
        transform: none;
        order: 3;
        width: 100%;
        justify-content: center;
        margin-top: 10px;
    }
    
    .logo-container {
        order: 1;
        margin-right: 0;
    }
    
    .user-info {
        order: 2;
        margin-left: auto;
    }
    
    .btn-agregar {
        padding: 6px 10px;
        font-size: 13px;
    }
    
            .card {
                padding: 15px;
            }
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .productos-table th, 
            .productos-table td,
            .colaboradores-table th, 
            .colaboradores-table td {
                padding: 8px;
            }

            .media-evidencia {
                width: 100px;
                height: 100px;
            }

            .modal-video {
                width: 95%;
            }
        }

@media (max-width: 480px) {
    .btn-agregar {
        flex-grow: 1;
        justify-content: center;
        white-space: normal;
        text-align: center;
        padding: 8px 5px;
    }
    
    .user-info {
        flex-direction: column;
        align-items: flex-end;
    }
}
    </style>
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    <div class="main-container">   <!-- ya existe en el css de menu lateral -->
        <div class="sub-container"> <!-- ya existe en el css de menu lateral -->
                    <?php echo renderHeader($usuario, false, 'Detalle de Reclamo'); ?> <!-- Dejar vacio si Bienvenido.. -->
            <div class="contenedor-principal">
        
                
                <!-- Tarjeta de información básica del reclamo -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Reclamo #<?php echo $reclamo_id; ?></h2>
                        <span class="badge <?php echo $reporte ? 'badge-resuelto' : 'badge-pendiente'; ?>">
                            <?php echo $reporte ? 'CERRADO' : 'ABIERTO'; ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="info-group">
                            <span class="info-label">Fecha de Registro:</span>
                            <div class="info-value">
                                <?php echo htmlspecialchars($reclamo['fecha_registro_formateada']); ?>
                            </div>
                        </div>
                        
                        <div class="info-group">
                            <span class="info-label">Medio de Compra:</span>
                            <div class="info-value">
                                <?php echo htmlspecialchars($reclamo['medio_compra'] ?? '--'); ?>
                            </div>
                        </div>
                        
                        <div class="info-group">
                            <span class="info-label">Sucursal:</span>
                            <div class="info-value">
                                <?php echo htmlspecialchars($reclamo['sucursal']); ?>
                            </div>
                        </div>
                        
                        <div class="info-group">
                            <span class="info-label">Tipo de Reclamo:</span>
                            <div class="info-value">
                                <?php echo htmlspecialchars($reclamo['tipo_reclamo'] ?? '--'); ?>
                            </div>
                        </div>
                        
                        <div class="info-group">
                            <span class="info-label">Descripción:</span>
                            <div class="info-value">
                                <?php echo nl2br(htmlspecialchars($reclamo['descripcion'])); ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($productos)): ?>
                        <div class="info-group">
                            <span class="info-label">Producto(s) en Reclamo:</span>
                            <table class="productos-table">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Precio</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($productos as $producto): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($producto['producto']); ?></td>
                                        <td>C$ <?php echo number_format($producto['precio'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($imagenes) || !empty($videos)): ?>
                        <div class="info-group">
                            <span class="info-label">Evidencia (Fotos/Videos):</span>
                            <div class="galeria-media">
                                <?php foreach ($imagenes as $imagen): ?>
                                <div style="position: relative;">
                                    <img src="<?php echo htmlspecialchars($imagen['ruta_imagen']); ?>" 
                                         alt="Evidencia del reclamo" 
                                         class="media-evidencia" 
                                         onclick="mostrarMediaModal('<?php echo htmlspecialchars($imagen['ruta_imagen']); ?>', 'image', 'Evidencia del reclamo')">
                                    <div class="media-icon"><i class="fas fa-camera"></i></div>
                                </div>
                                <?php endforeach; ?>
                                
                                <?php foreach ($videos as $video): ?>
                                <div style="position: relative;">
                                    <video class="media-evidencia" 
                                           onclick="mostrarMediaModal('<?php echo htmlspecialchars($video['ruta_video']); ?>', 'video', 'Video evidencia del reclamo')"
                                           oncontextmenu="return false;"
                                           disablePictureInPicture>
                                        <source src="<?php echo htmlspecialchars($video['ruta_video']); ?>" type="video/mp4">
                                        Tu navegador no soporta videos HTML5.
                                    </video>
                                    <div class="media-icon"><i class="fas fa-video"></i></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($reclamo['accion_inmediata'])): ?>
                        <div class="info-group">
                            <span class="info-label">Acción Inmediata:</span>
                            <div class="info-value">
                                <?php echo nl2br(htmlspecialchars($reclamo['accion_inmediata'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
        
                <!-- Tarjeta de investigación (si existe) -->
                <?php if ($reporte): ?>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Reporte de Investigación Final</h2>
                    </div>
                    <div class="card-body">
                        <div class="info-group">
                            <span class="info-label">Fecha de Resolución:</span>
                            <div class="info-value">
                                <?php 
                                    $fecha_resolucion = new DateTime($reporte['fecha_resolucion']);
                                    $meses = [
                                        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                                        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                                        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
                                    ];
                                    $mes_numero_res = (int)$fecha_resolucion->format('n');
                                    echo $fecha_resolucion->format('d') . ' de ' . $meses[$mes_numero_res] . ' de ' . $fecha_resolucion->format('Y');
                                ?>
                            </div>
                        </div>
                        
                        <div class="info-group" style="display:none;">
                            <span class="info-label">Resolución:</span>
                            <div class="info-value">
                                <?php echo htmlspecialchars($reporte['resolucion']); ?>
                            </div>
                        </div>
                        
                        <div class="info-group">
                            <span class="info-label">Tipo de Reclamo Final:</span>
                            <div class="info-value">
                                <?php echo nl2br(htmlspecialchars($reporte['tipo_reclamo_operaciones'])); ?>
                            </div>
                        </div>
                        
                        <div class="info-group">
                            <span class="info-label">Investigación:</span>
                            <div class="info-value">
                                <?php echo nl2br(htmlspecialchars($reporte['investigacion'])); ?>
                            </div>
                        </div>
                        
                        <div class="info-group">
                            <span class="info-label">Plan de Acción:</span>
                            <div class="info-value">
                                <?php echo nl2br(htmlspecialchars($reporte['plan_accion'])); ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($colaboradores)): ?>
                        <div class="info-group" style="display:none;">
                            <span class="info-label">Colaboradores Involucrados:</span>
                            <table class="colaboradores-table">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Monto de Responsabilidad</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($colaboradores as $colaborador): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($colaborador['colaborador']); ?></td>
                                        <td>C$ <?php echo number_format($colaborador['monto_responsabilidad'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <p class="no-data">Este reclamo aún no tiene un reporte de investigación asociado.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal para medios (imágenes y videos) -->
    <div id="modalMedia" class="modal-media">
        <span class="cerrar-modal" onclick="cerrarModal()">&times;</span>
        <div class="modal-content-container">
            <div id="mediaContenido"></div>
            <div id="mediaCaption" class="modal-caption"></div>
        </div>
    </div>

    <script>
        // Función para mostrar la imagen o video en modal
        function mostrarMediaModal(src, type, caption) {
            var modal = document.getElementById("modalMedia");
            var mediaContenido = document.getElementById("mediaContenido");
            var mediaCaption = document.getElementById("mediaCaption");
            
            modal.style.display = "block";
            mediaContenido.innerHTML = "";
            mediaCaption.textContent = caption || "";
            
            if (type === 'image') {
                var img = document.createElement("img");
                img.src = src;
                img.className = "modal-contenido";
                img.oncontextmenu = function() { return false; }; // Deshabilitar menú contextual
                img.ondragstart = function() { return false; }; // Deshabilitar arrastre
                mediaContenido.appendChild(img);
            } else if (type === 'video') {
                var video = document.createElement("video");
                video.src = src;
                video.className = "modal-contenido modal-video";
                video.controls = true;
                video.controlsList = "nodownload"; // Ocultar opción de descarga
                video.autoplay = true;
                video.disablePictureInPicture = true; // Deshabilitar Picture-in-Picture
                video.oncontextmenu = function() { return false; }; // Deshabilitar menú contextual
                video.ondragstart = function() { return false; }; // Deshabilitar arrastre
                
                // Bloquear eventos de teclado para evitar descargas con Ctrl+S, etc.
                video.addEventListener('keydown', function(e) {
                    if (e.ctrlKey || e.metaKey) {
                        e.preventDefault();
                        return false;
                    }
                });
                
                mediaContenido.appendChild(video);
            }
        }

        // Función para cerrar el modal
        function cerrarModal() {
            var modal = document.getElementById("modalMedia");
            modal.style.display = "none";
            
            // Detener cualquier video que esté reproduciéndose
            var videos = modal.getElementsByTagName('video');
            for (var i = 0; i < videos.length; i++) {
                videos[i].pause();
            }
        }

        // Cerrar modal al hacer clic fuera del contenido
        window.onclick = function(event) {
            var modal = document.getElementById("modalMedia");
            if (event.target == modal) {
                cerrarModal();
            }
        }

        // Deshabilitar clic derecho en imágenes y videos
        document.addEventListener('contextmenu', function(e) {
            if (e.target.tagName === 'IMG' || e.target.tagName === 'VIDEO') {
                e.preventDefault();
            }
        }, false);

        // Deshabilitar arrastrar imágenes y videos
        document.addEventListener('dragstart', function(e) {
            if (e.target.tagName === 'IMG' || e.target.tagName === 'VIDEO') {
                e.preventDefault();
            }
        }, false);

        // Bloquear atajos de teclado para descargas
        document.addEventListener('keydown', function(e) {
            // Bloquear Ctrl+S, Ctrl+Shift+S, etc.
            if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
