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

        $data = [
            'titulo' => $_POST['titulo'],
            'descripcion' => $_POST['descripcion'],
            'tipo_formulario' => 'mantenimiento_general',
            'cod_operario' => $cod_operario,
            'cod_sucursal' => $cod_sucursal,
            'area_equipo' => $_POST['area']
        ];

        $ticket_id = $ticket->create($data);

        // Guardar las fotos asociadas al ticket
        if (!empty($fotos)) {
            $ticket->addFotos($ticket_id, $fotos);
        }

        echo "<script>
            alert('Ticket creado exitosamente. Código: TKT" . date('Ym') . str_pad($ticket_id, 4, '0', STR_PAD_LEFT) . "');
            window.close();
        </script>";

    } catch (Exception $e) {
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let stream = null;
        let fotosSeleccionadas = []; // Array para almacenar fotos (files o base64)
        const MAX_FOTOS = 5;

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

        document.getElementById('removePhoto').addEventListener('click', function () {
            document.getElementById('photoPreview').style.display = 'none';
            document.getElementById('foto').value = '';
            document.getElementById('foto_camera').value = '';
            stopCamera();
        });

        // Validación del formulario
        document.getElementById('maintenanceForm').addEventListener('submit', function (e) {
            const titulo = document.getElementById('titulo').value.trim();
            const descripcion = document.getElementById('descripcion').value.trim();

            if (titulo.length < 5) {
                e.preventDefault();
                alert('El título debe tener al menos 5 caracteres');
                return;
            }

            if (descripcion.length < 10) {
                e.preventDefault();
                alert('La descripción debe tener al menos 10 caracteres');
                return;
            }
        });


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

        // Prevenir envío múltiple del formulario
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('maintenanceForm'); // Para mantenimiento

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