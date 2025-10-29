<?php
// Solo iniciar sesión si no está ya activa
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

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $fotos = [];
        
        // Manejar múltiples archivos subidos
        if (isset($_FILES['fotos']) && is_array($_FILES['fotos']['error'])) {
            $uploadDir = 'uploads/tickets/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            foreach ($_FILES['fotos']['error'] as $key => $error) {
                if ($error === UPLOAD_ERR_OK) {
                    $extension = pathinfo($_FILES['fotos']['name'][$key], PATHINFO_EXTENSION);
                    $filename = 'ticket_' . time() . '_' . $key . '.' . $extension;
                    
                    if (move_uploaded_file($_FILES['fotos']['tmp_name'][$key], $uploadDir . $filename)) {
                        $fotos[] = $filename;
                    }
                }
            }
        }
        
        // Manejar fotos desde cámara (base64) - múltiples
        if (!empty($_POST['fotos_camera'])) {
            $uploadDir = 'uploads/tickets/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fotos_camera = json_decode($_POST['fotos_camera'], true);
            
            if (is_array($fotos_camera)) {
                foreach ($fotos_camera as $index => $img_data) {
                    $img_data = str_replace('data:image/jpeg;base64,', '', $img_data);
                    $img_data = str_replace(' ', '+', $img_data);
                    $data = base64_decode($img_data);
                    
                    $filename = 'camera_' . time() . '_' . $index . '.jpg';
                    
                    if (file_put_contents($uploadDir . $filename, $data)) {
                        $fotos[] = $filename;
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
            'area_equipo' => $_POST['area'],
            'fotos' => $fotos
        ];
        
        $ticket_id = $ticket->create($data);
        
        echo "<script>
            alert('Ticket creado exitosamente. Código: TKT" . date('Ym') . str_pad($ticket_id, 4, '0', STR_PAD_LEFT) . "\\n" . count($fotos) . " foto(s) adjunta(s)');
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
        
        .card-header {
            background: linear-gradient(135deg, #51B8AC 0%, #0E544C 100%);
            color: white;
            border-radius: 8px 8px 0 0 !important;
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
        
        #video, #canvas {
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
        
        .form-control, .form-select {
            border-radius: 6px;
            border: 1px solid #ddd;
            padding: 8px 12px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #51B8AC;
            box-shadow: 0 0 0 0.2rem rgba(81, 184, 172, 0.25);
        }
        
        /* Estilos para galería de fotos */
        .photos-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .photo-item {
            position: relative;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
            aspect-ratio: 1;
        }
        
        .photo-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .photo-item .remove-photo {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(220, 53, 69, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .photo-item .remove-photo:hover {
            background: rgba(220, 53, 69, 1);
            transform: scale(1.1);
        }
        
        .photo-counter {
            display: inline-block;
            background: #51B8AC;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-left: 10px;
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
            
            .photos-gallery {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
                gap: 10px;
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
        
        a.btn{
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
    <div class="container">
        <header>
            <div class="header-container">
                <div class="logo-container">
                    <img src="../../assets/img/Logo.svg" alt="Batidos Pitaya" class="logo">
                </div>
                
                <div class="buttons-container">
                    <?php if ($esAdmin || verificarAccesoCargo([5, 14, 16, 35])): ?>
                        <a href="calendario.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'calendario.php' ? 'activo' : '' ?>">
                            <i class="fas fa-calendar-alt"></i> <span class="btn-text">Calendario</span>
                        </a>
                    <?php endif; ?>
                    
                    <a href="#" onclick="openMaintenanceForm()" class="btn-agregar activo">
                        <i class="fas fa-tools"></i> <span class="btn-text">Mantenimiento General</span>
                    </a>
                    
                    <a href="#" onclick="openEquipmentForm()" class="btn-agregar">
                        <i class="fas fa-laptop"></i> <span class="btn-text">Cambio de Equipos</span>
                    </a>
                    
                    <?php if ($esAdmin || verificarAccesoCargo([16, 5])): ?>
                        <a href="dashboard_sucursales.php?cod_operario=<?= $cod_operario ?>&cod_sucursal=<?= $cod_sucursal ?>" class="btn-agregar">
                            <i class="fas fa-sync-alt"></i> <span class="btn-text">Solicitudes</span>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($esAdmin || verificarAccesoCargo([14, 16, 35])): ?>
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
                        <small>
                            <?= htmlspecialchars($cargoUsuario) ?>
                        </small>
                    </div>
                    <a href="../index.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </header>

        <div class="form-container">
            <div class="card shadow">
                <div class="card-header">
                    <h4 class="mb-0">
                        <i class="fas fa-tools me-2"></i>
                        Nueva Solicitud de Mantenimiento
                    </h4>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= $error ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data" id="maintenanceForm">
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
                            <label for="area" class="form-label">Área Física *</label>
                            <input type="text" class="form-control" id="area" name="area" 
                                   placeholder="Ej: Area de preparacion, Almacén, Mueble de caja..." required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="titulo" class="form-label">Título del Problema *</label>
                            <input type="text" class="form-control" id="titulo" name="titulo" 
                                   placeholder="Resumen breve del problema" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción Detallada *</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="4" 
                                      placeholder="Describe detalladamente el problema o solicitud de mantenimiento..." required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">
                                Fotografías (Opcional)
                                <span class="photo-counter" id="photoCounter">0 fotos</span>
                            </label>
                            <div class="photo-options">
                                <button type="button" class="btn btn-outline-primary" id="btnFile">
                                    <i class="fas fa-upload me-2"></i>Subir Archivo(s)
                                </button>
                                <button type="button" class="btn btn-outline-success" id="btnCamera">
                                    <i class="fas fa-camera me-2"></i>Tomar Foto
                                </button>
                            </div>
                            
                            <input type="file" id="fotos" name="fotos[]" accept="image/*" multiple style="display: none;">
                            <input type="hidden" id="fotos_camera" name="fotos_camera">
                            
                            <div class="camera-preview" id="cameraPreview" style="display: none;">
                                <video id="video" autoplay></video>
                                <canvas id="canvas" style="display: none;"></canvas>
                            </div>
                            
                            <div class="photos-gallery" id="photosGallery"></div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let stream = null;
        let uploadedPhotos = []; // URLs de fotos subidas
        let cameraPhotos = []; // Base64 de fotos desde cámara
        
        // Actualizar contador de fotos
        function updatePhotoCounter() {
            const total = uploadedPhotos.length + cameraPhotos.length;
            document.getElementById('photoCounter').textContent = total + ' foto' + (total !== 1 ? 's' : '');
        }
        
        // Renderizar galería de fotos
        function renderGallery() {
            const gallery = document.getElementById('photosGallery');
            gallery.innerHTML = '';
            
            // Mostrar fotos subidas
            uploadedPhotos.forEach((photo, index) => {
                const photoItem = createPhotoItem(photo.url, 'upload', index);
                gallery.appendChild(photoItem);
            });
            
            // Mostrar fotos de cámara
            cameraPhotos.forEach((photo, index) => {
                const photoItem = createPhotoItem(photo, 'camera', index);
                gallery.appendChild(photoItem);
            });
            
            updatePhotoCounter();
        }
        
        // Crear elemento de foto
        function createPhotoItem(src, type, index) {
            const div = document.createElement('div');
            div.className = 'photo-item';
            
            const img = document.createElement('img');
            img.src = src;
            img.alt = 'Foto ' + (index + 1);
            
            const removeBtn = document.createElement('button');
            removeBtn.className = 'remove-photo';
            removeBtn.innerHTML = '<i class="fas fa-times"></i>';
            removeBtn.onclick = () => removePhoto(type, index);
            
            div.appendChild(img);
            div.appendChild(removeBtn);
            
            return div;
        }
        
        // Eliminar foto
        function removePhoto(type, index) {
            if (type === 'upload') {
                uploadedPhotos.splice(index, 1);
                // Actualizar input file
                updateFileInput();
            } else if (type === 'camera') {
                cameraPhotos.splice(index, 1);
                // Actualizar input hidden
                document.getElementById('fotos_camera').value = JSON.stringify(cameraPhotos);
            }
            renderGallery();
        }
        
        // Actualizar input file después de eliminar
        function updateFileInput() {
            const dataTransfer = new DataTransfer();
            uploadedPhotos.forEach(photo => {
                dataTransfer.items.add(photo.file);
            });
            document.getElementById('fotos').files = dataTransfer.files;
        }
        
        // Manejar carga de archivos
        document.getElementById('btnFile').addEventListener('click', function() {
            document.getElementById('fotos').click();
        });
        
        document.getElementById('fotos').addEventListener('change', function(e) {
            const files = Array.from(e.target.files);
            
            files.forEach(file => {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        uploadedPhotos.push({
                            file: file,
                            url: e.target.result
                        });
                        renderGallery();
                    };
                    reader.readAsDataURL(file);
                }
            });
        });
        
        // Manejar cámara
        document.getElementById('btnCamera').addEventListener('click', function() {
            if (stream) {
                stopCamera();
            } else {
                startCamera();
            }
        });
        
        function startCamera() {
            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                .then(function(mediaStream) {
                    stream = mediaStream;
                    const video = document.getElementById('video');
                    video.srcObject = stream;
                    document.getElementById('cameraPreview').style.display = 'none';
            document.getElementById('btnCamera').innerHTML = '<i class="fas fa-camera me-2"></i>Tomar Foto';
            const captureBtn = document.getElementById('captureBtn');
            if (captureBtn) captureBtn.remove();
        }
        
        // Manejar cambio de sucursal
        document.getElementById('selectSucursal')?.addEventListener('change', function() {
            const nuevaSucursal = this.value;
            const url = `formulario_mantenimiento.php?cod_operario=<?= $cod_operario ?>&cod_sucursal=${nuevaSucursal}`;
            window.location.href = url;
        });
        
        function goToDashboard() {
            const url = `dashboard_sucursales.php?cod_operario=<?= $cod_operario ?>&cod_sucursal=<?= $cod_sucursal ?>`;
            window.location.href = url;
        }
        
        function openEquipmentForm() {
            const url = `formulario_equipos.php?cod_operario=<?= $cod_operario ?>&cod_sucursal=<?= $cod_sucursal ?>`;
            window.location.href = url;
        }
        
        function openMaintenanceForm() {
            window.location.reload();
        }
        
        // Validación del formulario
        document.getElementById('maintenanceForm').addEventListener('submit', function(e) {
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
        
        // Prevenir envío múltiple del formulario
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('maintenanceForm');
            
            if (form) {
                let isSubmitting = false;
                
                form.addEventListener('submit', function(e) {
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
                    
                    // Re-habilitar después de 10 segundos por si hay error
                    setTimeout(() => {
                        if (isSubmitting) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                            isSubmitting = false;
                            alert('El proceso está tomando más tiempo de lo esperado. Por favor verifica tu conexión.');
                        }
                    }, 10000);
                });
                
                // Prevenir Enter en campos de texto
                const textInputs = form.querySelectorAll('input[type="text"], textarea, select');
                textInputs.forEach(input => {
                    input.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            return false;
                        }
                    });
                });
            }
        });
        
        // Limpiar cámara al cerrar/salir
        window.addEventListener('beforeunload', function() {
            if (stream) {
                stopCamera();
            }
        });
    </script>
</body>
</html>