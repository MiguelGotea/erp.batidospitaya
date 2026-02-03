<?php
// Solo iniciar sesión si no está ya activa
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'models/Ticket.php';
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';
// Incluir el header universal
require_once '../../includes/header_universal.php';
// Incluir el menú lateral
require_once '../../includes/menu_lateral.php';

//******************************Estándar para header******************************
verificarAutenticacion();

$usuario = obtenerUsuarioActual();
// Obtener cargo del operario para el menú
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso a formularios de mantenimiento (Código 14 y 19)
if (!verificarAccesoFormulariosMantenimiento($_SESSION['usuario_id'])) {
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
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
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 100%;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 10px;
        }


        .title {
            color: #0E544C;
            font-size: 1.5rem !important;
            margin-bottom: 20px;
        }

        .form-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .btn-primary {
            background-color: #51B8AC;
            border-color: #51B8AC;
        }

        .btn-primary:hover {
            background-color: #0E544C;
            border-color: #0E544C;
        }

        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }

        .btn-outline-primary {
            color: #51B8AC;
            border-color: #51B8AC;
        }

        .btn-outline-primary:hover {
            background-color: #51B8AC;
            border-color: #51B8AC;
            color: white;
        }

        .btn-outline-success {
            color: #28a745;
            border-color: #28a745;
        }

        .btn-outline-success:hover {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
        }

        .camera-preview {
            width: 100%;
            max-width: 300px;
            height: 200px;
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 10px 0;
            border-radius: 8px;
        }

        #video,
        #canvas {
            max-width: 100%;
            height: auto;
            border-radius: 6px;
        }

        .photo-options {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin: 10px 0;
        }

        .equipment-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            border-left: 4px solid #51B8AC;
        }

        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .form-label {
            font-weight: bold;
            color: #0E544C;
            margin-bottom: 5px;
        }

        .form-control,
        .form-select {
            border-radius: 6px;
            border: 1px solid #ddd;
            padding: 8px 12px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #51B8AC;
            box-shadow: 0 0 0 0.2rem rgba(81, 184, 172, 0.25);
        }

        .form-text {
            color: #6c757d;
            font-size: 0.875em;
            margin-top: 0.25rem;
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

            .form-container {
                padding: 10px;
            }

            .photo-options {
                flex-direction: column;
            }

            .photo-options .btn {
                width: 100%;
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

        a.btn {
            text-decoration: none;
        }

        .img-thumbnail {
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }

        .sucursal-selector {
            margin-right: 15px;
        }

        .sucursal-selector .form-select {
            border-color: #51B8AC;
            color: #0E544C;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .sucursal-selector {
                order: 1;
                width: 100%;
                margin-bottom: 10px;
            }

            .sucursal-selector .form-select {
                max-width: 100% !important;
            }
        }
    </style>
</head>

<body>
    <!-- Renderizar menú lateral -->
    <?php echo renderMenuLateral($cargoOperario); ?>

    <!-- Contenido principal -->
    <div class="main-container"> <!-- ya existe en el css de menu lateral -->
        <div class="contenedor-principal"> <!-- ya existe en el css de menu lateral -->
            <!-- todo el contenido existente -->
            <div class="container">
                <!-- Renderizar header universal -->
                <?php echo renderHeader($usuario, false, 'Solicitud de Equipos'); ?>

                <h1 class="title" style="display:none;">
                    <i class="fas fa-laptop me-2"></i>
                    Solicitud de Cambio de Equipos
                </h1>

                <div class="form-container">
                    <div class="card shadow">
                        <div class="card-body">
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?= $error ?>
                                </div>
                            <?php endif; ?>

                            <div class="equipment-info">
                                <h6><i class="fas fa-info-circle me-2"></i>Información Importante</h6>
                                <p class="mb-0">Esta solicitud es para cambio, reparación o mantenimiento unicamente de
                                    los equipos que se encuentren codificados.
                                    Selecciona el equipo específico y describe detalladamente el problema.</p>
                            </div>

                            <form method="POST" enctype="multipart/form-data" id="equipmentForm">
                                <div class="row">
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

                                    <!-- Selector de Sucursal (solo mostrar si tiene más de una sucursal) -->
                                    <?php if (count($sucursalesPermitidas) > 1): ?>
                                        <div class="mb-3">
                                            <label for="sucursal" class="form-label">Sucursal *</label>
                                            <select id="selectSucursal" class="form-select form-select-sm">
                                                <?php foreach ($sucursalesPermitidas as $suc): ?>
                                                    <option value="<?= $suc['codigo'] ?>" <?= $suc['codigo'] == $cod_sucursal ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($suc['nombre']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endif; ?>

                                    <div class="mb-3">
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
                                        <div id="equipoDescripcion" class="form-text"></div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="titulo" class="form-label">Título de la Solicitud *</label>
                                    <input type="text" class="form-control" id="titulo" name="titulo"
                                        placeholder="Ej: Reparación de impresora, Cambio de computadora..." required>
                                </div>

                                <div class="mb-3">
                                    <label for="descripcion" class="form-label">Descripción del Problema *</label>
                                    <textarea class="form-control" id="descripcion" name="descripcion" rows="4"
                                        placeholder="Describe el problema específico del equipo, síntomas, mensajes de error, etc..."
                                        required></textarea>
                                    <div class="form-text">
                                        <i class="fas fa-lightbulb me-1"></i>
                                        Incluye detalles como: ¿Cuándo comenzó el problema? ¿Qué mensajes de error
                                        aparecen?
                                        ¿El equipo funciona parcialmente o no funciona en absoluto?
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Fotografía del Equipo/Problema (Opcional)</label>
                                    <div class="photo-options">
                                        <button type="button" class="btn btn-outline-primary" id="btnFile">
                                            <i class="fas fa-upload me-2"></i>Subir Archivo
                                        </button>
                                        <button type="button" class="btn btn-outline-success" id="btnCamera">
                                            <i class="fas fa-camera me-2"></i>Tomar Foto
                                        </button>
                                    </div>

                                    <input type="file" id="foto" name="foto" accept="image/*" style="display: none;">
                                    <input type="hidden" id="foto_camera" name="foto_camera">

                                    <div class="camera-preview" id="cameraPreview" style="display: none;">
                                        <video id="video" autoplay></video>
                                        <canvas id="canvas" style="display: none;"></canvas>
                                    </div>

                                    <div id="photoPreview" style="display: none;">
                                        <img id="previewImg" src="" alt="Preview" class="img-thumbnail"
                                            style="max-width: 300px;">
                                        <button type="button" class="btn btn-sm btn-danger ms-2" id="removePhoto">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button type="button" class="btn btn-secondary me-md-2" onclick="goToDashboard()">
                                        <i class="fas fa-times me-2"></i>Cancelar
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane me-2"></i>Enviar Solicitud
                                    </button>
                                </div>
                            </form>
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
            const url = `dashboard_sucursales.php?cod_operario=<?= $cod_operario ?>&cod_sucursal=<?= $cod_sucursal ?>`;
            window.location.href = url;
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