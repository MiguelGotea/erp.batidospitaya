<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'models/Ticket.php';
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

//******************************Estándar para header******************************
verificarAutenticacion();

$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo Mantenimiento (Código 14)
verificarAccesoCargo([5, 11, 14, 16, 35]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([5, 11, 14, 16, 35]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
$cargoUsuariocodigo = obtenerCargoCodigoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

$ticket = new Ticket();

// Filtrar tickets según el cargo del usuario
$colaboradoresDisponibles = $ticket->getColaboradoresAsignados(); // Solo los asignados

// Filtro de colaborador
$colaborador_filtro = isset($_GET['colaborador']) ? intval($_GET['colaborador']) : null;

// Obtener tickets del colaborador
$tickets = [];
if ($colaborador_filtro) {
    if ($cargoUsuariocodigo == 14) {
        $tickets = $ticket->getTicketsPorColaborador($colaborador_filtro, date('Y-m-d'));
    } else {
    $tickets = $ticket->getTicketsPorColaborador($colaborador_filtro, "2016-01-01");
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda de Colaboradores</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Calibri', sans-serif;
            font-size: clamp(11px, 2vw, 16px) !important;
        }
        
        body {
            background-color: #F6F6F6;
            color: #333;
            padding: 5px;
        }
        
        .container {
            max-width: 100%;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 10px;
        }
        
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            padding: 0 5px;
            box-sizing: border-box;
            margin: 1px auto;
            flex-wrap: wrap;
        }

        .logo {
            height: 50px;
        }

        .logo-container {
            flex-shrink: 0;
            margin-right: auto;
        }

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

        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background-color: #51B8AC;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .btn-logout {
            background: #51B8AC;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn-logout:hover {
            background: #0E544C;
        }
        
        .filter-section {
            background: linear-gradient(135deg, #51B8AC 0%, #0E544C 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .ticket-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .ticket-card {
            background: white;
            border-left: 5px solid;
            border-radius: 8px;
            padding: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s;
            cursor: pointer;
        }
        
        .ticket-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .ticket-urgencia-1 { border-left-color: #28a745; }
        .ticket-urgencia-2 { border-left-color: #ffc107; }
        .ticket-urgencia-3 { border-left-color: #fd7e14; }
        .ticket-urgencia-4 { border-left-color: #dc3545; }
        .ticket-equipos { border-left-color: #dc3545; }
        
        .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
            gap: 10px;
        }
        
        .ticket-date {
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75em;
            font-weight: bold;
            color: #0E544C;
            white-space: nowrap;
        }
        
        .status-badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.75em;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .status-agendado { background: #fd7e14; color: white; }
        .status-finalizado { background: #198754; color: white; }
        
        .ticket-title {
            font-size: 0.95em;
            font-weight: 600;
            margin-bottom: 4px;
            color: #0E544C;
        }
        
        .ticket-sucursal {
            font-size: 0.8em;
            color: #6c757d;
            margin-bottom: 4px;
        }
        
        .ticket-desc {
            font-size: 0.85em;
            color: #495057;
            margin-bottom: 6px;
            line-height: 1.3;
            max-height: 2.6em;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        .ticket-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px solid #e9ecef;
        }
        
        .fotos-preview {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }
        
        .foto-thumb {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .foto-thumb:hover {
            transform: scale(1.1);
        }
        
        .btn-finalizar {
            background: #28a745;
            color: white;
            border: none;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 0.8em;
            cursor: pointer;
            transition: background 0.2s;
            white-space: nowrap;
        }
        
        .btn-finalizar:hover {
            background: #218838;
        }
        
        .ticket-finalizado {
            opacity: 0.7;
            background: #f8f9fa;
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
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-container">
                <div class="logo-container">
                    <img src="../../assets/img/Logo.svg" alt="Batidos Pitaya" class="logo">
                </div>
                
                <div class="buttons-container">                   
                    <?php if ($esAdmin || verificarAccesoCargo([14, 16, 35])): ?>
                        <a href="agenda_colaborador.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'agenda_colaborador.php' ? 'activo' : '' ?>">
                            <i class="fas fa-tasks"></i> <span class="btn-text">Agenda</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($esAdmin || verificarAccesoCargo([5, 11, 16, 35])): ?>
                        <a href="calendario.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'calendario.php' ? 'activo' : '' ?>">
                            <i class="fas fa-calendar-alt"></i> <span class="btn-text">Calendario</span>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($esAdmin || verificarAccesoCargo([5, 16, 35])): ?>
                        <a href="formulario_mantenimiento.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'formulario_mantenimiento.php' ? 'activo' : '' ?>">
                            <i class="fas fa-tools"></i> <span class="btn-text">Mantenimiento</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($esAdmin || verificarAccesoCargo([5, 16, 35])): ?>
                        <a href="formulario_equipos.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'formulario_equipos.php' ? 'activo' : '' ?>">
                            <i class="fas fa-laptop"></i> <span class="btn-text">Equipos</span>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($esAdmin || verificarAccesoCargo([16, 5])): ?>
                        <a href="dashboard_sucursales.php?cod_operario=<?= $cod_operario ?>&cod_sucursal=<?= $cod_sucursal ?>" class="btn-agregar">
                            <i class="fas fa-sync-alt"></i> <span class="btn-text">Solicitudes</span>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($esAdmin || verificarAccesoCargo([11, 14, 16, 35])): ?>
                        <a href="dashboard_mantenimiento.php?cod_operario=<?= $cod_operario ?>&cod_sucursal=<?= $cod_sucursal ?>" class="btn-agregar">
                            <i class="fas fa-sync-alt"></i> <span class="btn-text">Solicitudes</span>
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="user-info">
                    <div class="user-avatar">
                        <?= $esAdmin ? 
                            strtoupper(substr($usuario['nombre'], 0, 1)) : 
                            strtoupper(substr($usuario['Nombre'], 0, 1)) ?>
                    </div>
                    <div>
                        <div>
                            <?= $esAdmin ? 
                                htmlspecialchars($usuario['nombre']) : 
                                htmlspecialchars($usuario['Nombre'].' '.$usuario['Apellido']) ?>
                        </div>
                        <small><?= htmlspecialchars($cargoUsuario) ?></small>
                    </div>
                    <a href="../index.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </header>

        <div class="filter-section">
            <h4 class="mb-3">
                <i class="fas fa-filter me-2"></i>
                Filtrar Agenda por Colaborador
            </h4>
            <form method="GET" class="row g-3">
                <div class="col-md-8">
                    <select name="colaborador" class="form-select" required>
                        <option value="">Seleccionar colaborador...</option>
                        <?php foreach ($colaboradoresDisponibles as $col): ?>
                            <option value="<?= $col['CodOperario'] ?>" 
                                    <?= $colaborador_filtro == $col['CodOperario'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($col['Nombre'] . ' ' . ($col['Apellido'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-light w-100">
                        <i class="fas fa-search me-2"></i>Buscar
                    </button>
                </div>
            </form>
        </div>

        <?php if ($colaborador_filtro): ?>

            <?php if (empty($tickets)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No hay tickets asignados a este colaborador</p>
                </div>
            <?php else: ?>
                <div class="ticket-list">
                    <?php foreach ($tickets as $t): ?>
                        <?php
                        $borderClass = $t['tipo_formulario'] === 'cambio_equipos' ? 
                            'ticket-equipos' : 
                            'ticket-urgencia-' . ($t['nivel_urgencia'] ?? '0');
                        $finalizado = $t['status'] === 'finalizado';
                        $fotos = $ticket->getFotos($t['id']);
                        ?>
                        <div class="ticket-card <?= $borderClass ?> <?= $finalizado ? 'ticket-finalizado' : '' ?>">
                            <div class="ticket-header">
                                <div class="ticket-date">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?= date('d/m/Y', strtotime($t['fecha_inicio'])) ?>
                                    <?php if ($t['fecha_final'] != $t['fecha_inicio']): ?>
                                        - <?= date('d/m', strtotime($t['fecha_final'])) ?>
                                    <?php endif; ?>
                                </div>
                                
                                <i class="fas fa-map-marker-alt me-1"></i>
                                <?= htmlspecialchars($t['nombre_sucursal']) ?>
                            </div>
                            
                            <div class="ticket-title">
                                <i class="fas fa-<?= $t['tipo_formulario'] === 'cambio_equipos' ? 'laptop' : 'tools' ?> me-1"></i>
                                <?= htmlspecialchars($t['titulo']) ?>
                            </div>
                            
                            <div class="ticket-sucursal">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                <?= htmlspecialchars($t['nombre_sucursal']) ?>
                            </div>
                            
                            <div class="ticket-desc">
                                <?= htmlspecialchars($t['descripcion']) ?>
                            </div>
                            
                            <div class="ticket-footer">
                                <?php if (!empty($fotos)): ?>
                                    <div class="fotos-preview">
                                        <?php foreach (array_slice($fotos, 0, 3) as $foto): ?>
                                            <img src="uploads/tickets/<?= $foto['foto'] ?>" 
                                                 class="foto-thumb" 
                                                 onclick="mostrarFotosTicket(<?= $t['id'] ?>)">
                                        <?php endforeach; ?>
                                        <?php if (count($fotos) > 3): ?>
                                            <div class="foto-thumb d-flex align-items-center justify-content-center" 
                                                 style="background: #f8f9fa; border: 1px solid #dee2e6; font-size: 0.75em; font-weight: bold; color: #6c757d;"
                                                 onclick="mostrarFotosTicket(<?= $t['id'] ?>)">
                                                +<?= count($fotos) - 3 ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!$finalizado): ?>
                                    <button class="btn-finalizar" onclick="abrirModalFinalizar(<?= $t['id'] ?>)">
                                        <i class="fas fa-check-circle me-1"></i>Finalizar
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-user-friends fa-3x text-muted mb-3"></i>
                <p class="text-muted">Selecciona un colaborador para ver su agenda</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal para ver fotos -->
    <div class="modal fade" id="fotosModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-images me-2"></i>
                        Fotografías del Ticket
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="fotosModalBody"></div>
            </div>
        </div>
    </div>

    <!-- Modal para finalizar ticket -->
    <div class="modal fade" id="finalizarModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-check-circle me-2"></i>
                        Finalizar Ticket
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formFinalizar" enctype="multipart/form-data">
                        <input type="hidden" id="ticket_id_fin" name="ticket_id">
                        
                        <div class="mb-3">
                            <label for="detalle_trabajo" class="form-label">
                                <strong>Detalle del Trabajo Realizado *</strong>
                            </label>
                            <textarea class="form-control" id="detalle_trabajo" name="detalle_trabajo" 
                                      rows="3" required 
                                      placeholder="Describe el trabajo que se realizó..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="materiales_usados" class="form-label">
                                <strong>Materiales Utilizados *</strong>
                            </label>
                            <textarea class="form-control" id="materiales_usados" name="materiales_usados" 
                                      rows="3" required 
                                      placeholder="Lista los materiales que se utilizaron..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <strong>Fotos del Trabajo Finalizado (Opcional)</strong>
                            </label>
                            <div class="photo-options mb-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="btnFileFin">
                                    <i class="fas fa-upload me-2"></i>Subir Archivos
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success" id="btnCameraFin">
                                    <i class="fas fa-camera me-2"></i>Tomar Foto
                                </button>
                            </div>
                            
                            <input type="file" id="fotos_fin" name="fotos_fin[]" accept="image/*" multiple style="display: none;">
                            <input type="hidden" id="fotos_camera_fin" name="fotos_camera_fin">
                            
                            <div class="camera-preview" id="cameraPreviewFin" style="display: none; max-width: 300px; margin: 10px 0;">
                                <video id="videoFin" autoplay style="width: 100%; border-radius: 8px;"></video>
                                <canvas id="canvasFin" style="display: none;"></canvas>
                            </div>
                            
                            <div id="photosPreviewFin" style="display: none; margin-top: 10px;">
                                <div id="photosListFin" class="row g-2"></div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" onclick="finalizarTicket()">
                        <i class="fas fa-check-circle me-2"></i>Finalizar Ticket
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        let streamFin = null;
        let fotosFin = [];
        const MAX_FOTOS_FIN = 5;

        function mostrarFotosTicket(ticketId) {
            $.ajax({
                url: 'ajax/get_ticket_photos.php',
                method: 'GET',
                data: { ticket_id: ticketId },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.fotos.length > 0) {
                        let html = '<div id="photosCarousel" class="carousel slide" data-bs-ride="false">';
                        html += '<div class="carousel-inner">';
                        
                        response.fotos.forEach((foto, index) => {
                            html += `<div class="carousel-item ${index === 0 ? 'active' : ''}">
                                <img src="uploads/tickets/${foto.foto}" class="d-block w-100" style="max-height: 500px; object-fit: contain;">
                                <div class="text-center mt-2">
                                    <small class="text-muted">Foto ${index + 1} de ${response.fotos.length}</small>
                                </div>
                            </div>`;
                        });
                        
                        html += '</div>';
                        
                        if (response.fotos.length > 1) {
                            html += `<button class="carousel-control-prev" type="button" data-bs-target="#photosCarousel" data-bs-slide="prev">
                                        <span class="carousel-control-prev-icon"></span>
                                    </button>
                                    <button class="carousel-control-next" type="button" data-bs-target="#photosCarousel" data-bs-slide="next">
                                        <span class="carousel-control-next-icon"></span>
                                    </button>`;
                        }
                        
                        html += '</div>';
                        
                        $('#fotosModalBody').html(html);
                        new bootstrap.Modal(document.getElementById('fotosModal')).show();
                    }
                }
            });
        }

        function abrirModalFinalizar(ticketId) {
            document.getElementById('ticket_id_fin').value = ticketId;
            document.getElementById('formFinalizar').reset();
            fotosFin = [];
            updatePhotosPreviewFin();
            new bootstrap.Modal(document.getElementById('finalizarModal')).show();
        }

        // Manejo de fotos finalización
        document.getElementById('btnFileFin')?.addEventListener('click', function() {
            document.getElementById('fotos_fin').click();
        });

        document.getElementById('fotos_fin')?.addEventListener('change', function(e) {
            const files = Array.from(e.target.files);
            
            if (fotosFin.length + files.length > MAX_FOTOS_FIN) {
                alert(`Solo puedes agregar hasta ${MAX_FOTOS_FIN} fotos`);
                return;
            }
            
            files.forEach(file => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    fotosFin.push({
                        tipo: 'file',
                        data: e.target.result,
                        file: file
                    });
                    updatePhotosPreviewFin();
                };
                reader.readAsDataURL(file);
            });
        });

        document.getElementById('btnCameraFin')?.addEventListener('click', function() {
            if (fotosFin.length >= MAX_FOTOS_FIN) {
                alert(`Ya has alcanzado el límite de ${MAX_FOTOS_FIN} fotos`);
                return;
            }
            
            if (streamFin) {
                stopCameraFin();
            } else {
                startCameraFin();
            }
        });

        function startCameraFin() {
            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                .then(function(mediaStream) {
                    streamFin = mediaStream;
                    const video = document.getElementById('videoFin');
                    video.srcObject = streamFin;
                    document.getElementById('cameraPreviewFin').style.display = 'block';
                    
                    if (!document.getElementById('captureBtnFin')) {
                        const captureBtn = document.createElement('button');
                        captureBtn.type = 'button';
                        captureBtn.id = 'captureBtnFin';
                        captureBtn.className = 'btn btn-success btn-sm mt-2 w-100';
                        captureBtn.innerHTML = '<i class="fas fa-camera me-2"></i>Capturar Foto';
                        captureBtn.addEventListener('click', capturePhotoFin);
                        document.getElementById('cameraPreviewFin').appendChild(captureBtn);
                    }
                })
                .catch(function(err) {
                    alert('Error al acceder a la cámara: ' + err.message);
                });
        }

        function capturePhotoFin() {
            if (fotosFin.length >= MAX_FOTOS_FIN) {
                alert(`Ya has alcanzado el límite de ${MAX_FOTOS_FIN} fotos`);
                stopCameraFin();
                return;
            }
            
            const video = document.getElementById('videoFin');
            const canvas = document.getElementById('canvasFin');
            const context = canvas.getContext('2d');
            
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            context.drawImage(video, 0, 0);
            
            const dataURL = canvas.toDataURL('image/jpeg');
            
            fotosFin.push({
                tipo: 'camera',
                data: dataURL
            });
            
            updatePhotosPreviewFin();
            stopCameraFin();
        }

        function stopCameraFin() {
            if (streamFin) {
                streamFin.getTracks().forEach(track => track.stop());
                streamFin = null;
            }
            document.getElementById('cameraPreviewFin').style.display = 'none';
            const captureBtn = document.getElementById('captureBtnFin');
            if (captureBtn) captureBtn.remove();
        }

        function updatePhotosPreviewFin() {
            const previewContainer = document.getElementById('photosPreviewFin');
            const photosList = document.getElementById('photosListFin');
            
            if (fotosFin.length === 0) {
                previewContainer.style.display = 'none';
                return;
            }
            
            previewContainer.style.display = 'block';
            photosList.innerHTML = '';
            
            fotosFin.forEach((foto, index) => {
                const col = document.createElement('div');
                col.className = 'col-6 col-md-4';
                col.innerHTML = `
                    <div class="position-relative">
                        <img src="${foto.data}" class="img-thumbnail w-100" style="height: 120px; object-fit: cover;">
                        <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1" 
                                onclick="removeFotoFin(${index})">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
                photosList.appendChild(col);
            });
            
            updateHiddenInputsFin();
        }

        function removeFotoFin(index) {
            fotosFin.splice(index, 1);
            updatePhotosPreviewFin();
        }

        function updateHiddenInputsFin() {
            const dt = new DataTransfer();
            const fotosCamera = [];
            
            fotosFin.forEach(foto => {
                if (foto.tipo === 'file') {
                    dt.items.add(foto.file);
                } else if (foto.tipo === 'camera') {
                    fotosCamera.push(foto.data);
                }
            });
            
            document.getElementById('fotos_fin').files = dt.files;
            document.getElementById('fotos_camera_fin').value = JSON.stringify(fotosCamera);
        }

        function finalizarTicket() {
            const form = document.getElementById('formFinalizar');
            const formData = new FormData(form);
            
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            const btnSubmit = event.target;
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Finalizando...';
            
            $.ajax({
                url: 'ajax/finalizar_ticket.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#finalizarModal').modal('hide');
                        alert('✅ Ticket finalizado correctamente');
                        location.reload();
                    } else {
                        alert('❌ Error: ' + response.message);
                        btnSubmit.disabled = false;
                        btnSubmit.innerHTML = '<i class="fas fa-check-circle me-2"></i>Finalizar Ticket';
                    }
                },
                error: function(xhr) {
                    console.error('Error:', xhr.responseText);
                    alert('❌ Error al finalizar el ticket');
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = '<i class="fas fa-check-circle me-2"></i>Finalizar Ticket';
                }
            });
        }
    </script>
</body>
</html>