<?php
require_once __DIR__ . '/models/Ticket.php';
require_once __DIR__ . '/../../core/auth/auth.php';
require_once __DIR__ . '/../../core/layout/menu_lateral.php';
require_once __DIR__ . '/../../core/layout/header_universal.php';

require_once __DIR__ . '/../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'] ?? null;

// Verificar acceso a formularios de mantenimiento (Uso de nuevo_registro)
if (!$cargoOperario || !tienePermiso('historial_solicitudes_mantenimiento', 'nuevo_registro', $cargoOperario)) {
    header('Location: ../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

// Obtener sucursales permitidas para el usuario (nueva función)
$sucursalesPermitidas = obtenerSucursalesPermitidasMantenimiento($_SESSION['usuario_id']);

if (empty($sucursalesPermitidas)) {
    die("No tienes sucursales asignadas. Contacta al administrador.");
}

// Validar parámetros y determinar sucursal actual (usar nueva función)
if (!isset($_GET['cod_sucursal']) || !verificarAccesoSucursalMantenimiento($_SESSION['usuario_id'], $_GET['cod_sucursal'])) {
    $cod_sucursal = $sucursalesPermitidas[0]['codigo'];
} else {
    $cod_sucursal = $_GET['cod_sucursal'];
}

// El código de operario siempre debe ser el del usuario logueado
$cod_operario = $_SESSION['usuario_id'];

// Verificar que el usuario tenga acceso a esta sucursal (usar nueva función)
if (!verificarAccesoSucursalMantenimiento($cod_operario, $cod_sucursal)) {
    die("No tienes acceso a esta sucursal.");
}

$ticket = new Ticket();
$sucursales = $sucursalesPermitidas; // Usar solo las sucursales permitidas

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $fotos = [];

        // Manejar múltiples archivos subidos
        if (isset($_FILES['fotos']) && !empty($_FILES['fotos']['name'][0])) {
            $uploadDir = 'uploads/tickets/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $totalFiles = count($_FILES['fotos']['name']);
            for ($i = 0; $i < $totalFiles; $i++) {
                if ($_FILES['fotos']['error'][$i] === UPLOAD_ERR_OK) {
                    $extension = pathinfo($_FILES['fotos']['name'][$i], PATHINFO_EXTENSION);
                    $foto = 'ticket_' . time() . '_' . $i . '.' . $extension;
                    if (move_uploaded_file($_FILES['fotos']['tmp_name'][$i], $uploadDir . $foto)) {
                        $fotos[] = $foto;
                    }
                }
            }
        }

        // Manejar fotos desde cámara (base64)
        if (!empty($_POST['fotos_camera'])) {
            $uploadDir = 'uploads/tickets/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fotosCamera = json_decode($_POST['fotos_camera'], true);
            if (is_array($fotosCamera)) {
                foreach ($fotosCamera as $index => $img_data) {
                    $img_data = str_replace('data:image/jpeg;base64,', '', $img_data);
                    $img_data = str_replace(' ', '+', $img_data);
                    $data = base64_decode($img_data);

                    $foto = 'camera_' . time() . '_' . $index . '.jpg';
                    if (file_put_contents($uploadDir . $foto, $data)) {
                        $fotos[] = $foto;
                    }
                }
            }
        }

        $ticketData = [
            'titulo'          => $_POST['titulo'],
            'descripcion'     => $_POST['descripcion'],
            'tipo_formulario' => 'mantenimiento_general',
            'cod_operario'    => $cod_operario,
            'cod_sucursal'    => $cod_sucursal,
            'area_equipo'     => $_POST['area']
        ];

        // Campos IA opcionales
        if (!empty($_POST['ia_nivel_urgencia'])) {
            $ticketData['nivel_urgencia']  = (int)$_POST['ia_nivel_urgencia'];
            $ticketData['tiempo_estimado'] = (int)($_POST['ia_tiempo_estimado'] ?? 0);
            $ticketData['resolucion']      = trim($_POST['ia_resolucion'] ?? '');
        }

        $ticket_id = $ticket->create($ticketData);

        // Guardar las fotos asociadas al ticket
        if (!empty($fotos)) {
            $ticket->addFotos($ticket_id, $fotos);
        }

        // Respuesta JSON para AJAX
        if (!empty($_POST['_ajax'])) {
            $codigo = 'TKT' . date('Ym') . str_pad($ticket_id, 4, '0', STR_PAD_LEFT);
            ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'ticket_id' => $ticket_id, 'codigo' => $codigo]);
            exit();
        }

        // Fallback tradicional
        echo "<script>
            alert('Ticket creado exitosamente. Código: TKT" . date('Ym') . str_pad($ticket_id, 4, '0', STR_PAD_LEFT) . "');
            window.close();
        </script>";

    } catch (Exception $e) {
        if (!empty($_POST['_ajax'])) {
            ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
        $error = "Error al crear el ticket: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de Mantenimiento General</title>

    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/form_modern.css">
    <!-- Library for HEIC support -->
    <script src="https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js"></script>
    <style>
        * {
            box-sizing: border-box;
            font-family: 'Calibri', sans-serif;
            font-size: clamp(11px, 2vw, 16px) !important;
        }

        .photo-preview-item {
            position: relative;
            display: inline-block;
            margin: 5px;
        }

        .photo-preview-item .remove-btn {
            position: absolute;
            top: -10px;
            right: -10px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
            border: 2px solid white;
        }

        .camera-preview {
            width: 100%;
            max-width: 400px;
            margin: 10px 0;
            border-radius: 12px;
            overflow: hidden;
            border: 3px solid #51B8AC;
        }

        #video {
            width: 100%;
            background: #000;
        }
    </style>
</head>

<body>
    <!-- Renderizar menú lateral -->
    <?php echo renderMenuLateral($cargoOperario); ?>

    <!-- Contenido principal -->
    <div class="main-container">
        <div class="sub-container">
            <!-- Renderizar header universal -->
            <?php echo renderHeader($usuario, false, 'Solicitud de Mantenimiento'); ?>
            
            <div class="container-fluid p-3">
                <div class="form-modern-container">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger shadow-sm mb-4">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= $error ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" id="maintenanceForm">
                        <div class="row">
                            <!-- Columna Principal (70%) -->
                            <div class="col-lg-8 mb-4">
                                <div class="form-section-card">
                                    <div class="card-body-modern">
                                        <div class="section-header">
                                            <i class="fas fa-edit"></i>
                                            <h5>Detalles de la Solicitud</h5>
                                        </div>

                                        <div class="row">
                                            <!-- Sucursal (Oculto o Selector) -->
                                            <div class="mb-3" style="display:none;">
                                                <label for="sucursal" class="form-label">Sucursal *</label>
                                                <select class="form-select" id="sucursal" name="sucursal" required
                                                    <?= count($sucursales) == 1 ? 'disabled' : '' ?>>
                                                    <?php foreach ($sucursales as $sucursalItem): ?>
                                                        <option value="<?= $sucursalItem['codigo'] ?>"
                                                            <?= ($sucursalItem['codigo'] == $cod_sucursal) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($sucursalItem['nombre']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <?php if (count($sucursales) == 1): ?>
                                                    <input type="hidden" name="sucursal" value="<?= $sucursales[0]['codigo'] ?>">
                                                <?php endif; ?>
                                            </div>

                                            <?php if (tienePermiso('historial_solicitudes_mantenimiento', 'vista_todas_sucursales', $cargoOperario)): ?>
                                                <div class="col-md-6 mb-3">
                                                    <label for="selectSucursal" class="form-label">Sucursal del Problema *</label>
                                                    <select id="selectSucursal" class="form-select" 
                                                            onchange="window.location.href='?cod_sucursal=' + this.value">
                                                        <?php foreach ($sucursalesPermitidas as $suc): ?>
                                                            <option value="<?= $suc['codigo'] ?>" <?= $suc['codigo'] == $cod_sucursal ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($suc['nombre']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            <?php endif; ?>

                                            <div class="<?= tienePermiso('historial_solicitudes_mantenimiento', 'vista_todas_sucursales', $cargoOperario) ? 'col-md-6' : 'col-12' ?> mb-3">
                                                <label for="area" class="form-label">Área Física *</label>
                                                <input type="text" class="form-control" id="area" name="area"
                                                    placeholder="Ej: Area de preparación, Almacén, Caja..." required>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="titulo" class="form-label">Título del Problema *</label>
                                            <input type="text" class="form-control" id="titulo" name="titulo"
                                                placeholder="Resumen breve del problema" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="descripcion" class="form-label">Descripción Detallada *</label>
                                            <textarea class="form-control" id="descripcion" name="descripcion" rows="5"
                                                placeholder="Describe detalladamente el problema o solicitud de mantenimiento..."
                                                required></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Columna Lateral (30%) -->
                            <div class="col-lg-4">
                                <!-- Info Box -->
                                <div class="info-box-status">
                                    <h6><i class="fas fa-info-circle"></i> Instrucciones</h6>
                                    <p>Describe el problema lo más claro posible. Las fotos ayudan al equipo técnico a diagnosticar más rápido.</p>
                                </div>

                                <!-- Multimedia Card -->
                                <div class="form-section-card">
                                    <div class="card-body-modern">
                                        <div class="section-header">
                                            <i class="fas fa-camera"></i>
                                            <h5>Multimedia</h5>
                                        </div>

                                        <div class="photo-upload-zone">
                                            <i class="fas fa-images fa-2x mb-3 text-muted"></i>
                                            <p class="small text-muted mb-3">Sube hasta 5 fotos del problema</p>
                                            
                                            <div class="d-grid gap-2">
                                                <button type="button" class="btn btn-outline-primary btn-sm" id="btnFile">
                                                    <i class="fas fa-upload me-2"></i>Subir Archivos
                                                </button>
                                                <button type="button" class="btn btn-outline-success btn-sm" id="btnCamera">
                                                    <i class="fas fa-camera me-2"></i>Tomar Foto
                                                </button>
                                            </div>

                                            <input type="file" id="fotos" name="fotos[]" accept="image/*" multiple
                                                style="display: none;">
                                            <input type="hidden" id="fotos_camera" name="fotos_camera">

                                            <div class="camera-preview mx-auto" id="cameraPreview" style="display: none;">
                                                <video id="video" autoplay></video>
                                                <canvas id="canvas" style="display: none;"></canvas>
                                            </div>

                                            <div id="photosPreview" style="display: none; margin-top: 15px;">
                                                <div id="photosList" class="row g-2 justify-content-center"></div>
                                            </div>
                                        </div>

                                        <!-- Campos ocultos para datos de IA -->
                                        <input type="hidden" name="_ajax" value="1">
                                        <input type="hidden" id="ia_nivel_urgencia"  name="ia_nivel_urgencia">
                                        <input type="hidden" id="ia_tiempo_estimado" name="ia_tiempo_estimado">
                                        <input type="hidden" id="ia_resolucion"      name="ia_resolucion">

                                        <div class="mt-4 pt-3 border-top">
                                            <div class="d-grid gap-2">
                                                <button type="button" id="btnEnviarSolicitud" class="btn btn-primary-pitaya" onclick="iniciarEnvio()">
                                                    <i class="fas fa-paper-plane me-2"></i>Enviar Solicitud
                                                </button>
                                                <button type="button" class="btn btn-secondary-pitaya btn-sm" onclick="goToDashboard()">
                                                    <i class="fas fa-times me-2"></i>Cancelar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal resultado ticket -->
    <div class="modal fade" id="modalTicketCreado" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border:none; border-radius:16px; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                <div id="modalTicketHeader" style="background:linear-gradient(135deg,#0E544C,#1a8a7e); padding:1.5rem 1.75rem; color:white;">
                    <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:0.5rem;">
                        <div style="width:42px;height:42px;border-radius:50%;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;font-size:1.3rem;">✓</div>
                        <div>
                            <div style="font-size:0.8rem;opacity:0.8;letter-spacing:0.05em;">TICKET CREADO</div>
                            <div id="modalCodigoTicket" style="font-size:1.3rem;font-weight:700;">TKT...</div>
                        </div>
                    </div>
                </div>
                <div class="modal-body" style="padding:1.5rem 1.75rem; background:#f8fafb;">
                    <div id="modalIAResultado"></div>
                </div>
                <div class="modal-footer" style="border:none; padding:1rem 1.75rem; background:#f8fafb;">
                    <button type="button" class="btn btn-primary-pitaya w-100" onclick="cerrarYVolver()">
                        <i class="fas fa-check me-2"></i>Entendido
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let stream = null;
        let fotosSeleccionadas = [];
        const MAX_FOTOS = 5;
        const coloresUrgencia = { 1:'#28a745', 2:'#ffc107', 3:'#fd7e14', 4:'#dc3545' };
        const textosUrgencia  = { 1:'No Urgente', 2:'Medio', 3:'Urgente', 4:'Crítico' };

        // ── Flujo principal de envío ──────────────────────────────────────────
        async function iniciarEnvio() {
            const titulo      = document.getElementById('titulo').value.trim();
            const descripcion = document.getElementById('descripcion').value.trim();
            const area        = document.getElementById('area').value.trim();

            if (titulo.length < 5) { alert('El título debe tener al menos 5 caracteres'); return; }
            if (descripcion.length < 10) { alert('La descripción debe tener al menos 10 caracteres'); return; }

            const btn = document.getElementById('btnEnviarSolicitud');
            btn.disabled = true;

            // ── Paso 1: Análisis IA ───────────────────────────────────────────
            btn.innerHTML = '<i class="fas fa-robot me-2"></i>Analizando con IA...';

            let iaData = null;
            try {
                const iaResp = await fetch('ajax/formulario_consulta_ia.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ titulo, descripcion, area_equipo: area })
                });
                if (iaResp.ok) {
                    const iaJson = await iaResp.json();
                    if (iaJson.success) iaData = iaJson;
                }
            } catch(e) { /* continuar sin IA */ }

            // Rellenar campos ocultos con datos IA
            if (iaData) {
                document.getElementById('ia_nivel_urgencia').value  = iaData.nivel_urgencia;
                document.getElementById('ia_tiempo_estimado').value = iaData.tiempo_estimado;
                document.getElementById('ia_resolucion').value      = iaData.resolucion;
            }

            // ── Paso 2: Enviar formulario via AJAX ────────────────────────────
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Guardando...';

            try {
                const form = document.getElementById('maintenanceForm');
                updateHiddenInputs(); // sincronizar fotos
                const fd = new FormData(form);

                const saveResp = await fetch(window.location.href, {
                    method: 'POST',
                    body: fd
                });
                const saveJson = await saveResp.json();

                if (!saveJson.success) {
                    alert('Error al guardar el ticket: ' + (saveJson.message || 'Error desconocido'));
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Enviar Solicitud';
                    return;
                }

                // ── Paso 3: Mostrar modal de resultado ────────────────────────
                mostrarModalResultado(saveJson.codigo, iaData);

            } catch(err) {
                alert('Error de conexión. Verifica tu red e intenta nuevamente.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Enviar Solicitud';
            }
        }

        function mostrarModalResultado(codigo, ia) {
            document.getElementById('modalCodigoTicket').textContent = codigo;

            let html = '';
            if (ia) {
                const color  = coloresUrgencia[ia.nivel_urgencia] || '#6c757d';
                const texto  = textosUrgencia[ia.nivel_urgencia]  || 'N/A';
                const tiempo = ia.tiempo_estimado > 0 ? ia.tiempo_estimado + 'H' : 'Tercerizado';
                html = `
                <div style="margin-bottom:1rem;">
                    <div style="font-size:0.75rem;font-weight:600;color:#666;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.5rem;">Análisis IA <span style="background:#e8f5e9;color:#2e7d32;padding:2px 8px;border-radius:20px;font-size:0.7rem;">✓ ${(ia.proveedor||'IA').toUpperCase()}</span></div>
                    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:0.75rem;">
                        <span style="background:${color};color:white;padding:4px 12px;border-radius:20px;font-size:0.8rem;font-weight:600;">${texto}</span>
                        <span style="background:#e3f2fd;color:#1565c0;padding:4px 12px;border-radius:20px;font-size:0.8rem;font-weight:600;"><i class="fas fa-clock me-1"></i>${tiempo}</span>
                    </div>
                    <div style="background:white;border:1px solid #e0e0e0;border-radius:10px;padding:0.85rem 1rem;font-size:0.85rem;color:#444;line-height:1.5;">${ia.resolucion.replace(/·/g,'<br>·')}</div>
                </div>`;
            } else {
                html = '<p class="text-muted small mb-0"><i class="fas fa-info-circle me-1"></i>Ticket guardado correctamente. El análisis IA podrá aplicarse desde el historial.</p>';
            }

            document.getElementById('modalIAResultado').innerHTML = html;
            const modal = new bootstrap.Modal(document.getElementById('modalTicketCreado'));
            modal.show();
        }

        function cerrarYVolver() {
            bootstrap.Modal.getInstance(document.getElementById('modalTicketCreado')).hide();
            window.close();
        }

        // Manejar carga de archivos
        document.getElementById('btnFile').addEventListener('click', function () {
            document.getElementById('fotos').click();
        });


        document.getElementById('fotos').addEventListener('change', function (e) {
            const files = Array.from(e.target.files);

            if (fotosSeleccionadas.length + files.length > MAX_FOTOS) {
                alert(`Solo puedes agregar hasta ${MAX_FOTOS} fotos en total`);
                return;
            }

            files.forEach(file => {
                const isHeic = file.name.toLowerCase().endsWith('.heic') || file.name.toLowerCase().endsWith('.heif');
                
                if (isHeic) {
                    heic2any({ 
                        blob: file, 
                        toType: "image/jpeg",
                        quality: 0.6
                    }).then(conversionResult => {
                        const convertedBlob = Array.isArray(conversionResult) ? conversionResult[0] : conversionResult;
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            fotosSeleccionadas.push({
                                tipo: 'file',
                                data: e.target.result,
                                file: new File([convertedBlob], file.name.replace(/\.(heic|heif)$/i, '.jpg'), { type: 'image/jpeg' })
                            });
                            updatePhotosPreview();
                        };
                        reader.readAsDataURL(convertedBlob);
                    }).catch(e => {
                        console.error("Error converting HEIC:", e);
                        alert("Error al procesar la imagen HEIC");
                    });
                } else {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        fotosSeleccionadas.push({
                            tipo: 'file',
                            data: e.target.result,
                            file: file
                        });
                        updatePhotosPreview();
                    };
                    reader.readAsDataURL(file);
                }
            });
        });

        // Manejar cámara
        document.getElementById('btnCamera').addEventListener('click', function () {
            if (fotosSeleccionadas.length >= MAX_FOTOS) {
                alert(`Ya has alcanzado el límite de ${MAX_FOTOS} fotos`);
                return;
            }

            if (stream) {
                stopCamera();
            } else {
                startCamera();
            }
        });

        function startCamera() {
            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                .then(function (mediaStream) {
                    stream = mediaStream;
                    const video = document.getElementById('video');
                    video.srcObject = stream;
                    document.getElementById('cameraPreview').style.display = 'block';
                    document.getElementById('btnCamera').innerHTML = '<i class="fas fa-camera me-2"></i>Capturar';

                    if (!document.getElementById('captureBtn')) {
                        const captureBtn = document.createElement('button');
                        captureBtn.type = 'button';
                        captureBtn.id = 'captureBtn';
                        captureBtn.className = 'btn btn-success mt-2';
                        captureBtn.innerHTML = '<i class="fas fa-camera me-2"></i>Capturar Foto';
                        captureBtn.addEventListener('click', capturePhoto);
                        document.getElementById('cameraPreview').appendChild(captureBtn);
                    }
                })
                .catch(function (err) {
                    alert('Error al acceder a la cámara: ' + err.message);
                });
        }

        function capturePhoto() {
            if (fotosSeleccionadas.length >= MAX_FOTOS) {
                alert(`Ya has alcanzado el límite de ${MAX_FOTOS} fotos`);
                stopCamera();
                return;
            }

            const video = document.getElementById('video');
            const canvas = document.getElementById('canvas');
            const context = canvas.getContext('2d');

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            context.drawImage(video, 0, 0);

            const dataURL = canvas.toDataURL('image/jpeg');

            fotosSeleccionadas.push({
                tipo: 'camera',
                data: dataURL
            });

            updatePhotosPreview();
            stopCamera();
        }

        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
            document.getElementById('cameraPreview').style.display = 'none';
            document.getElementById('btnCamera').innerHTML = '<i class="fas fa-camera me-2"></i>Tomar Foto';
            const captureBtn = document.getElementById('captureBtn');
            if (captureBtn) captureBtn.remove();
        }

        function updatePhotosPreview() {
            const previewContainer = document.getElementById('photosPreview');
            const photosList = document.getElementById('photosList');

            if (fotosSeleccionadas.length === 0) {
                previewContainer.style.display = 'none';
                return;
            }

            previewContainer.style.display = 'block';
            photosList.innerHTML = '';

            fotosSeleccionadas.forEach((foto, index) => {
                const col = document.createElement('div');
                col.className = 'col-6 col-md-4 col-lg-3';
                col.innerHTML = `
                    <div class="position-relative">
                        <img src="${foto.data}" class="img-thumbnail w-100" style="height: 150px; object-fit: cover;">
                        <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1" 
                                onclick="removePhoto(${index})" title="Eliminar">
                            <i class="fas fa-times"></i>
                        </button>
                        <div class="badge bg-primary position-absolute bottom-0 start-0 m-1">
                            ${index + 1}
                        </div>
                    </div>
                `;
                photosList.appendChild(col);
            });

            // Actualizar hidden inputs
            updateHiddenInputs();
        }

        function removePhoto(index) {
            fotosSeleccionadas.splice(index, 1);
            updatePhotosPreview();
        }

        function updateHiddenInputs() {
            // Crear DataTransfer para los archivos
            const dt = new DataTransfer();
            const fotosCamera = [];

            fotosSeleccionadas.forEach(foto => {
                if (foto.tipo === 'file') {
                    dt.items.add(foto.file);
                } else if (foto.tipo === 'camera') {
                    fotosCamera.push(foto.data);
                }
            });

            document.getElementById('fotos').files = dt.files;
            document.getElementById('fotos_camera').value = JSON.stringify(fotosCamera);
        }

        function showPreview(src) {
            document.getElementById('previewImg').src = src;
            document.getElementById('photoPreview').style.display = 'block';
            document.getElementById('cameraPreview').style.display = 'none';
        }


        // Manejar cambio de sucursal
        document.getElementById('selectSucursal')?.addEventListener('change', function () {
            const nuevaSucursal = this.value;
            const url = `formulario_mantenimiento.php?cod_operario=<?= $cod_operario ?>&cod_sucursal=${nuevaSucursal}`;
            window.location.href = url;
        });

        function goToDashboard() {
            window.location.href = 'historial_solicitudes.php';
        }

        function openEquipmentForm() {
            const url = `formulario_equipos.php?cod_operario=<?= $cod_operario ?>&cod_sucursal=<?= $cod_sucursal ?>`;
            window.location.href = url;
        }
    </script>

</body>

</html>