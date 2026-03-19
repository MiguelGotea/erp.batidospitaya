<?php
require_once __DIR__ . '/models/Ticket.php';
require_once __DIR__ . '/../../core/auth/auth.php';
require_once __DIR__ . '/../../core/layout/header_universal.php';
// Incluir el menú lateral
require_once __DIR__ . '/../../core/layout/menu_lateral.php';

require_once __DIR__ . '/../../core/permissions/permissions.php';

//******************************Estándar para header******************************
verificarAutenticacion();

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
$equipos = $ticket->getEquipos();

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $foto = null;

        // Manejar subida de archivo
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/tickets/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $extension = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $foto = 'ticket_' . time() . '.' . $extension;
            move_uploaded_file($_FILES['foto']['tmp_name'], $uploadDir . $foto);
        }

        // Manejar foto desde cámara (base64)
        if (!empty($_POST['foto_camera'])) {
            $uploadDir = 'uploads/tickets/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $img_data = $_POST['foto_camera'];
            $img_data = str_replace('data:image/jpeg;base64,', '', $img_data);
            $img_data = str_replace(' ', '+', $img_data);
            $data = base64_decode($img_data);

            $foto = 'camera_' . time() . '.jpg';
            file_put_contents($uploadDir . $foto, $data);
        }

        $data = [
            'titulo' => $_POST['titulo'],
            'descripcion' => $_POST['descripcion'],
            'tipo_formulario' => 'cambio_equipos',
            'cod_operario' => $cod_operario,
            'cod_sucursal' => $cod_sucursal,
            'area_equipo' => $_POST['equipo'],
            'foto' => $foto
        ];

        $ticket_id = $ticket->create($data);

        echo "<script>
            alert('Solicitud de cambio de equipo creada exitosamente.');
            window.close();
        </script>";

    } catch (Exception $e) {
        $error = "Error al crear la solicitud: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de Cambio de Equipos</title>

    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/form_modern.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
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
            <?php echo renderHeader($usuario, false, 'Solicitud de Equipos'); ?>

            <div class="container-fluid p-3">
                <div class="form-modern-container">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger shadow-sm mb-4">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= $error ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" id="equipmentForm">
                        <div class="row">
                            <!-- Columna Principal (70%) -->
                            <div class="col-lg-8 mb-4">
                                <div class="form-section-card">
                                    <div class="card-body-modern">
                                        <div class="section-header">
                                            <i class="fas fa-desktop"></i>
                                            <h5>Detalles del Equipo</h5>
                                        </div>

                                        <div class="row">
                                            <!-- Selector de Sucursal (solo para quienes ven todas) -->
                                            <?php if (tienePermiso('historial_solicitudes_mantenimiento', 'vista_todas_sucursales', $cargoOperario)): ?>
                                                <div class="col-md-6 mb-3">
                                                    <label for="selectSucursal" class="form-label">Sucursal del Equipo *</label>
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
                                                <label for="equipo" class="form-label">Tipo de Equipo *</label>
                                                <select class="form-select" id="equipo" name="equipo" required>
                                                    <option value="">Seleccionar equipo</option>
                                                    <?php foreach ($equipos as $equipo): ?>
                                                        <option value="<?= htmlspecialchars($equipo['marca']) ?>"
                                                            data-descripcion="<?= htmlspecialchars($equipo['caracteristicas']) ?>">
                                                            <?= htmlspecialchars($equipo['marca']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <div id="equipoDescripcion" class="form-text mt-2 text-primary fw-bold"></div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="titulo" class="form-label">Título de la Solicitud *</label>
                                            <input type="text" class="form-control" id="titulo" name="titulo"
                                                placeholder="Ej: Reparación de impresora, Cambio de computadora..." required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="descripcion" class="form-label">Descripción del Problema *</label>
                                            <textarea class="form-control" id="descripcion" name="descripcion" rows="5"
                                                placeholder="Describe el problema específico del equipo, síntomas, errores..."
                                                required></textarea>
                                            <div class="form-text mt-2 text-muted">
                                                <i class="fas fa-lightbulb me-1"></i>
                                                Indica cuándo comenzó y si el equipo funciona parcialmente.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Columna Lateral (30%) -->
                            <div class="col-lg-4">
                                <!-- Info Box -->
                                <div class="info-box-status">
                                    <h6><i class="fas fa-info-circle"></i> Información Importante</h6>
                                    <p>Esta solicitud es exclusiva para equipos codificados. Selecciona el equipo exacto para agilizar el soporte.</p>
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
                                            <p class="small text-muted mb-3">Sube o toma una foto del daño</p>
                                            
                                            <div class="d-grid gap-2">
                                                <button type="button" class="btn btn-outline-primary btn-sm" id="btnFile">
                                                    <i class="fas fa-upload me-2"></i>Subir Archivo
                                                </button>
                                                <button type="button" class="btn btn-outline-success btn-sm" id="btnCamera">
                                                    <i class="fas fa-camera me-2"></i>Tomar Foto
                                                </button>
                                            </div>

                                            <input type="file" id="foto" name="foto" accept="image/*" style="display: none;">
                                            <input type="hidden" id="foto_camera" name="foto_camera">

                                            <div class="camera-preview mx-auto" id="cameraPreview" style="display: none;">
                                                <video id="video" autoplay></video>
                                                <canvas id="canvas" style="display: none;"></canvas>
                                            </div>

                                            <div id="photoPreview" style="display: none; margin-top: 15px;">
                                                <div class="photo-preview-item">
                                                    <img id="previewImg" src="" alt="Preview" class="img-thumbnail"
                                                        style="max-width: 100%;">
                                                    <div class="remove-btn" id="removePhoto">
                                                        <i class="fas fa-times"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-4 pt-3 border-top">
                                            <div class="d-grid gap-2">
                                                <button type="submit" class="btn btn-primary-pitaya">
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
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let stream = null;

        // Manejar cambio de equipo
        document.getElementById('equipo').addEventListener('change', function () {
            const selectedOption = this.options[this.selectedIndex];
            const descripcion = selectedOption.getAttribute('data-descripcion');
            document.getElementById('equipoDescripcion').textContent = descripcion || '';

            // Auto-llenar título si está vacío
            //if (this.value && !document.getElementById('titulo').value) {
            //    document.getElementById('titulo').value = 'Solicitud para ' + this.value;
            //}
        });

        // Manejar carga de archivo
        document.getElementById('btnFile').addEventListener('click', function () {
            document.getElementById('foto').click();
        });

        document.getElementById('foto').addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    showPreview(e.target.result);
                };
                reader.readAsDataURL(file);
            }
        });

        // Manejar cámara
        document.getElementById('btnCamera').addEventListener('click', function () {
            if (stream) {
                stopCamera();
            } else {
                startCamera();
            }
        });

        function startCamera() {
            navigator.mediaDevices.getUserMedia({ video: true })
                .then(function (mediaStream) {
                    stream = mediaStream;
                    const video = document.getElementById('video');
                    video.srcObject = stream;
                    document.getElementById('cameraPreview').style.display = 'block';
                    document.getElementById('btnCamera').innerHTML = '<i class="fas fa-camera me-2"></i>Capturar';

                    // Agregar botón de captura
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
            const video = document.getElementById('video');
            const canvas = document.getElementById('canvas');
            const context = canvas.getContext('2d');

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            context.drawImage(video, 0, 0);

            const dataURL = canvas.toDataURL('image/jpeg');
            document.getElementById('foto_camera').value = dataURL;

            showPreview(dataURL);
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

        function showPreview(src) {
            document.getElementById('previewImg').src = src;
            document.getElementById('photoPreview').style.display = 'block';
            document.getElementById('cameraPreview').style.display = 'none';
        }

        document.getElementById('removePhoto').addEventListener('click', function () {
            document.getElementById('photoPreview').style.display = 'none';
            document.getElementById('foto').value = '';
            document.getElementById('foto_camera').value = '';
            stopCamera();
        });

        // Validación del formulario
        document.getElementById('equipmentForm').addEventListener('submit', function (e) {
            const titulo = document.getElementById('titulo').value.trim();
            const descripcion = document.getElementById('descripcion').value.trim();
            const equipo = document.getElementById('equipo').value;

            if (!equipo) {
                e.preventDefault();
                alert('Debe seleccionar un tipo de equipo');
                return;
            }

            if (titulo.length < 5) {
                e.preventDefault();
                alert('El título debe tener al menos 5 caracteres');
                return;
            }

            if (descripcion.length < 15) {
                e.preventDefault();
                alert('La descripción debe ser más detallada (al menos 15 caracteres)');
                return;
            }
        });

        // Manejar cambio de sucursal
        document.getElementById('selectSucursal')?.addEventListener('change', function () {
            const nuevaSucursal = this.value;
            const url = `formulario_equipos.php?cod_operario=<?= $cod_operario ?>&cod_sucursal=${nuevaSucursal}`;
            window.location.href = url;
        });

        function goToDashboard() {
            window.location.href = 'historial_solicitudes.php';
        }

        function openMaintenanceForm() {
            const url = `formulario_mantenimiento.php?cod_operario=<?= $cod_operario ?>&cod_sucursal=<?= $cod_sucursal ?>`;
            window.location.href = url;
        }

        // Prevenir envío del formulario con Enter
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('equipmentForm'); // Para equipos

            if (form) {
                let isSubmitting = false;

                form.addEventListener('submit', function (e) {
                    if (isSubmitting) {
                        e.preventDefault();
                        return false;
                    }

                    const submitBtn = form.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;

                    // Deshabilitar botón y mostrar estado de carga
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Guardando...';
                    isSubmitting = true;

                    // Re-habilitar después de 5 segundos por si hay error (seguridad)
                    setTimeout(() => {
                        if (isSubmitting) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                            isSubmitting = false;
                            alert('El proceso está tomando más tiempo de lo esperado. Por favor verifica tu conexión.');
                        }
                    }, 10000);
                });

                // También prevenir envío con Enter
                form.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        return false;
                    }
                });

                // Prevenir Enter en campos específicos
                const textInputs = form.querySelectorAll('input[type="text"], textarea, select');
                textInputs.forEach(input => {
                    input.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            return false;
                        }
                    });
                });
            }
        });
    </script>
</body>

</html>