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
                    
                    <a href="#" onclick="location.reload()" class="btn-agregar" style="display:none;">
                        <i class="fas fa-sync-alt"></i> <span class="btn-text">Actualizar</span>
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

        <h1 style="display:none;" class="title">
            <i class="fas fa-tools me-2"></i>
            Solicitud de Mantenimiento General
        </h1>

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
                            <?php if (count($sucursalesPermitidas) > 1):?>
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
                            <label class="form-label">Fotografías (Opcional - Máximo 5)</label>
                            <div class="photo-options">
                                <button type="button" class="btn btn-outline-primary" id="btnFile">
                                    <i class="fas fa-upload me-2"></i>Subir Archivos
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
                            
                            <div id="photosPreview" style="display: none; margin-top: 15px;">
                                <label class="form-label"><strong>Fotos seleccionadas:</strong></label>
                                <div id="photosList" class="row g-2"></div>
                            </div>
                            
                            <small class="text-muted d-block mt-2">
                                <i class="fas fa-info-circle me-1"></i>
                                Puedes subir hasta 5 fotos. Formatos aceptados: JPG, PNG
                            </small>
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
        let fotosSeleccionadas = []; // Array para almacenar fotos (files o base64)
        const MAX_FOTOS = 5;
        
        // Manejar carga de archivos
        document.getElementById('btnFile').addEventListener('click', function() {
            document.getElementById('fotos').click();
        });

        
        document.getElementById('fotos').addEventListener('change', function(e) {
            const files = Array.from(e.target.files);
            
            if (fotosSeleccionadas.length + files.length > MAX_FOTOS) {
                alert(`Solo puedes agregar hasta ${MAX_FOTOS} fotos en total`);
                return;
            }
            
            files.forEach(file => {
                const reader = new FileReader();
                reader.onload = function(e) {
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
        document.getElementById('btnCamera').addEventListener('click', function() {
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
                .then(function(mediaStream) {
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
                .catch(function(err) {
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
        
        document.getElementById('removePhoto').addEventListener('click', function() {
            document.getElementById('photoPreview').style.display = 'none';
            document.getElementById('foto').value = '';
            document.getElementById('foto_camera').value = '';
            stopCamera();
        });
        
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

        // Mostrar en consola cuando se interactúa con el selector de sucursal
        document.getElementById('selectSucursal')?.addEventListener('mousedown', function() {
            const sucursalSeleccionada = this.value;
            console.log('🚀 EVENTO SUCURSAL - Dropdown abierto');
            console.log('👤 Usuario:', '<?= $cod_operario ?>');
            console.log('🏪 Sucursal actual:', '<?= $cod_sucursal ?>');
            console.log('📋 Sucursal seleccionada:', sucursalSeleccionada);
            console.log('🌐 Página:', 'formulario_mantenimiento');
            console.log('⏰ Timestamp:', new Date().toLocaleString());
            console.log('----------------------------------------');
        });

        // También puedes agregar el evento focus para cuando se selecciona con teclado
        document.getElementById('selectSucursal')?.addEventListener('focus', function() {
            console.log('🎯 Selector de sucursal enfocado (teclado)');
        });

        // Manejar cambio de sucursal
        document.getElementById('selectSucursal')?.addEventListener('change', function() {
            const nuevaSucursal = this.value;
            const url = `formulario_mantenimiento.php?cod_operario=<?= $cod_operario ?>&cod_sucursal=${nuevaSucursal}`;
            window.location.href = url;
            console.log(url);
        });
        
        function goToDashboard() {
            const url = `dashboard_sucursales.php?cod_operario=<?= $cod_operario ?>&cod_sucursal=<?= $cod_sucursal ?>`;
            window.location.href = url;
        }
        
        function openEquipmentForm() {
            const url = `formulario_equipos.php?cod_operario=<?= $cod_operario ?>&cod_sucursal=<?= $cod_sucursal ?>`;
            window.location.href = url;
        }
        
        // Prevenir envío múltiple del formulario
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('maintenanceForm'); // Para mantenimiento
            
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
                form.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        return false;
                    }
                });
                
                // Prevenir Enter en campos específicos
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
    </script>
</body>
</html>