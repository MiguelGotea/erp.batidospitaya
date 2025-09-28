<?php
session_start();
require_once 'models/Ticket.php';

// Validar que vengan los parámetros necesarios
if (!isset($_GET['cod_operario']) || !isset($_GET['cod_sucursal'])) {
    die("Acceso no autorizado");
}

$cod_operario = $_GET['cod_operario'];
$cod_sucursal = $_GET['cod_sucursal'];

$ticket = new Ticket();
$sucursales = $ticket->getSucursales();

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
            'tipo_formulario' => 'mantenimiento_general',
            'cod_operario' => $cod_operario,
            'cod_sucursal' => $cod_sucursal,
            'area_equipo' => $_POST['area'],
            'foto' => $foto
        ];
        
        $ticket_id = $ticket->create($data);
        
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        }
        #video, #canvas {
            max-width: 100%;
            height: auto;
        }
        .photo-options {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin: 10px 0;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="form-container">
            <div class="card shadow">
                <div class="card-header">
                    <h4 class="mb-0">
                        <i class="fas fa-tools me-2"></i>
                        Solicitud de Mantenimiento General
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
                            <div class="col-md-6 mb-3">
                                <label for="sucursal" class="form-label">Sucursal *</label>
                                <select class="form-select" id="sucursal" name="sucursal" required>
                                    <option value="">Seleccionar sucursal</option>
                                    <?php foreach ($sucursales as $sucursal): ?>
                                        <option value="<?= $sucursal['cod_sucursal'] ?>" 
                                                <?= ($sucursal['cod_sucursal'] == $cod_sucursal) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($sucursal['nombre_sucursal']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="area" class="form-label">Área Física *</label>
                                <input type="text" class="form-control" id="area" name="area" 
                                       placeholder="Ej: Oficina principal, Almacén, Recepción..." required>
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
                            <label class="form-label">Fotografía (Opcional)</label>
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
                                <img id="previewImg" src="" alt="Preview" class="img-thumbnail" style="max-width: 300px;">
                                <button type="button" class="btn btn-sm btn-danger ms-2" id="removePhoto">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="button" class="btn btn-secondary me-md-2" onclick="window.close()">
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
        
        // Manejar carga de archivo
        document.getElementById('btnFile').addEventListener('click', function() {
            document.getElementById('foto').click();
        });
        
        document.getElementById('foto').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    showPreview(e.target.result);
                };
                reader.readAsDataURL(file);
            }
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
            navigator.mediaDevices.getUserMedia({ video: true })
                .then(function(mediaStream) {
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
                .catch(function(err) {
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
    </script>
</body>
</html>