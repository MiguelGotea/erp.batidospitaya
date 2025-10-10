<?php
// Solo iniciar sesión si no está ya activa
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'models/Ticket.php';
require_once 'models/Chat.php';

// Validar parámetros
if (!isset($_GET['ticket_id']) || !isset($_GET['emisor'])) {
    die("Parámetros requeridos faltantes");
}

$ticket_id = intval($_GET['ticket_id']);
$emisor_tipo = $_GET['emisor']; // 'mantenimiento' o 'solicitante'

$ticket_model = new Ticket();
$chat_model = new Chat();

$ticket = $ticket_model->getById($ticket_id);
if (!$ticket) {
    die("Ticket no encontrado");
}

// Determinar nombre del emisor
$emisor_nombre = '';
if ($emisor_tipo === 'mantenimiento') {
    $emisor_nombre = 'Área de Mantenimiento';
} else {
    // Obtener nombre del operario
    global $db;
    $operario = $db->fetchOne("SELECT Nombre FROM Operarios WHERE CodOperario = ?", [$ticket['cod_operario']]);
    $emisor_nombre = $operario['Nombre'] ?? 'Usuario';
}

// Procesar envío de mensaje
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $mensaje = trim($_POST['mensaje'] ?? '');
        $foto = null;
        
        // Manejar subida de foto
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/chat/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $extension = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $foto = 'chat_' . $ticket_id . '_' . time() . '.' . $extension;
            move_uploaded_file($_FILES['foto']['tmp_name'], $uploadDir . $foto);
        }
        
        // Manejar foto desde cámara
        if (!empty($_POST['foto_camera'])) {
            $uploadDir = 'uploads/chat/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $img_data = $_POST['foto_camera'];
            $img_data = str_replace('data:image/jpeg;base64,', '', $img_data);
            $img_data = str_replace(' ', '+', $img_data);
            $data = base64_decode($img_data);
            
            $foto = 'chat_camera_' . $ticket_id . '_' . time() . '.jpg';
            file_put_contents($uploadDir . $foto, $data);
        }
        
        if (!empty($mensaje) || $foto) {
            $chat_model->addMessage($ticket_id, $emisor_tipo, $emisor_nombre, $mensaje, $foto);
            
            // Redirect para evitar reenvío
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
        
    } catch (Exception $e) {
        $error = "Error al enviar mensaje: " . $e->getMessage();
    }
}

// Obtener mensajes
$mensajes = $chat_model->getMessages($ticket_id);
$mensaje_pinned = $chat_model->getPinnedMessage($ticket_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - <?= htmlspecialchars($ticket['codigo']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --pitaya-primary: #51B8AC;
            --pitaya-secondary: #0E544C;
            --pitaya-light: #F6F6F6;
        }
        
        body {
            font-family: 'Calibri', sans-serif;
            height: 100vh;
            overflow: hidden;
        }
        
        .chat-header {
            background: linear-gradient(135deg, var(--pitaya-primary) 0%, var(--pitaya-secondary) 100%);
            color: white;
            padding: 15px;
            flex-shrink: 0;
        }
        
        .message.own .message-content {
            background: var(--pitaya-primary);
            color: white;
        }
        
        .btn-primary {
            background-color: var(--pitaya-primary);
            border-color: var(--pitaya-primary);
        }
        .btn-primary:hover {
            background-color: var(--pitaya-secondary);
            border-color: var(--pitaya-secondary);
        }
        .btn-success {
            background-color: var(--pitaya-secondary);
            border-color: var(--pitaya-secondary);
        }
        
        .chat-container {
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .chat-header {
            background: linear-gradient(135deg, var(--pitaya-primary) 0%, var(--pitaya-secondary) 100%);
            color: white;
            padding: 15px;
            flex-shrink: 0;
        }
        
        .pinned-message {
            background: #fff3cd;
            border: 1px solid #ffecb5;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 15px;
            position: relative;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
            background: #f8f9fa;
        }
        
        .message {
            margin-bottom: 15px;
            display: flex;
            align-items: flex-start;
        }
        
        .message.own {
            flex-direction: row-reverse;
        }
        
        .message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            flex-shrink: 0;
            margin: 0 10px;
        }
        
        .avatar-mantenimiento {
            background: #6f42c1;
        }
        
        .avatar-solicitante {
            background: #20c997;
        }
        
        .message-content {
            max-width: 70%;
            background: white;
            border-radius: 15px;
            padding: 10px 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: relative;
        }
        
        .message.own .message-content {
            background: #007bff;
            color: white;
        }
        
        .message-time {
            font-size: 0.8em;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .message.own .message-time {
            color: rgba(255,255,255,0.8);
        }
        
        .message-photo {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            margin-top: 5px;
            cursor: pointer;
        }
        
        .chat-input {
            background: white;
            padding: 15px;
            border-top: 1px solid #dee2e6;
            flex-shrink: 0;
        }
        
        .message-actions {
            position: absolute;
            top: -25px;
            right: 10px;
            display: none;
        }
        
        .message:hover .message-actions {
            display: block;
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
        
        .typing-indicator {
            display: none;
            padding: 10px;
            font-style: italic;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <!-- Header -->
        <div class="chat-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1">
                        <i class="fas fa-comments me-2"></i>
                        Chat - <?= htmlspecialchars($ticket['codigo']) ?>
                    </h5>
                    <small><?= htmlspecialchars($ticket['titulo']) ?></small>
                </div>
                <div>
                    <span class="badge bg-light text-dark me-2">
                        <?= ucfirst(str_replace('_', ' ', $ticket['tipo_formulario'])) ?>
                    </span>
                    <span class="badge bg-warning">
                        <?= ucfirst($ticket['status']) ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Mensaje Pinned -->
        <?php if ($mensaje_pinned): ?>
            <div class="pinned-message">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <i class="fas fa-thumbtack me-2"></i>
                        <strong><?= htmlspecialchars($mensaje_pinned['emisor_nombre']) ?>:</strong>
                        <p class="mb-0 mt-1"><?= nl2br(htmlspecialchars($mensaje_pinned['mensaje'])) ?></p>
                        <?php if ($mensaje_pinned['foto']): ?>
                            <img src="uploads/chat/<?= $mensaje_pinned['foto'] ?>" alt="Foto" 
                                 class="message-photo mt-2" onclick="showPhotoModal('uploads/chat/<?= $mensaje_pinned['foto'] ?>')">
                        <?php endif; ?>
                    </div>
                    <?php if ($emisor_tipo === 'mantenimiento'): ?>
                        <button class="btn btn-sm btn-outline-secondary" onclick="unpinMessage(<?= $mensaje_pinned['id'] ?>)">
                            <i class="fas fa-times"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Mensajes -->
        <div class="chat-messages" id="chatMessages">
            <?php foreach ($mensajes as $mensaje): ?>
                <div class="message <?= $mensaje['emisor_tipo'] === $emisor_tipo ? 'own' : '' ?>" data-message-id="<?= $mensaje['id'] ?>">
                    <div class="message-avatar <?= $mensaje['emisor_tipo'] === 'mantenimiento' ? 'avatar-mantenimiento' : 'avatar-solicitante' ?>">
                        <?= $mensaje['emisor_tipo'] === 'mantenimiento' ? 'M' : 'U' ?>
                    </div>
                    <div class="message-content">
                        <?php if ($emisor_tipo === 'mantenimiento'): ?>
                            <div class="message-actions">
                                <button class="btn btn-sm btn-outline-warning" onclick="pinMessage(<?= $mensaje['id'] ?>)" title="Fijar mensaje">
                                    <i class="fas fa-thumbtack"></i>
                                </button>
                            </div>
                        <?php endif; ?>
                        
                        <div class="fw-bold"><?= htmlspecialchars($mensaje['emisor_nombre']) ?></div>
                        
                        <?php if ($mensaje['mensaje']): ?>
                            <div><?= nl2br(htmlspecialchars($mensaje['mensaje'])) ?></div>
                        <?php endif; ?>
                        
                        <?php if ($mensaje['foto']): ?>
                            <img src="uploads/chat/<?= $mensaje['foto'] ?>" alt="Foto" 
                                 class="message-photo" onclick="showPhotoModal('uploads/chat/<?= $mensaje['foto'] ?>')">
                        <?php endif; ?>
                        
                        <div class="message-time">
                            <?= date('d/m/Y H:i', strtotime($mensaje['created_at'])) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Indicador de escritura -->
        <div class="typing-indicator" id="typingIndicator">
            <i class="fas fa-ellipsis-h"></i> El usuario está escribiendo...
        </div>
        
        <!-- Input de chat -->
        <div class="chat-input">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-sm mb-3">
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" id="chatForm">
                <div class="row align-items-end">
                    <div class="col">
                        <textarea class="form-control" id="mensaje" name="mensaje" 
                                  placeholder="Escribe tu mensaje..." rows="2" 
                                  onkeypress="handleKeyPress(event)"></textarea>
                        <input type="hidden" id="foto_camera" name="foto_camera">
                        <input type="file" id="foto" name="foto" accept="image/*" style="display: none;">
                    </div>
                    <div class="col-auto">
                        <div class="btn-group-vertical" role="group">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('foto').click()" title="Subir foto">
                                <i class="fas fa-image"></i>
                            </button>
                            <button type="button" class="btn btn-outline-success btn-sm" onclick="toggleCamera()" title="Tomar foto">
                                <i class="fas fa-camera"></i>
                            </button>
                            <button type="submit" class="btn btn-primary btn-sm" title="Enviar">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Preview de foto seleccionada -->
                <div id="photoPreview" style="display: none;" class="mt-2">
                    <img id="previewImg" src="" alt="Preview" class="img-thumbnail" style="max-width: 200px;">
                    <button type="button" class="btn btn-sm btn-danger ms-2" onclick="removePhoto()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <!-- Cámara -->
                <div class="camera-preview mt-2" id="cameraPreview" style="display: none;">
                    <video id="video" autoplay></video>
                    <canvas id="canvas" style="display: none;"></canvas>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal para mostrar fotos -->
    <div class="modal fade" id="photoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Imagen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalPhoto" src="" alt="Foto" class="img-fluid">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        let stream = null;
        let lastMessageCount = <?= count($mensajes) ?>;
        
        // Auto-scroll al final
        function scrollToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Scroll al cargar
        window.addEventListener('load', function() {
            scrollToBottom();
        });
        
        // Manejar envío con Enter (Shift+Enter para nueva línea)
        function handleKeyPress(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                document.getElementById('chatForm').submit();
            }
        }
        
        // Manejar archivo de imagen
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
        
        // Cámara
        function toggleCamera() {
            if (stream) {
                stopCamera();
            } else {
                startCamera();
            }
        }
        
        function startCamera() {
            navigator.mediaDevices.getUserMedia({ video: true })
                .then(function(mediaStream) {
                    stream = mediaStream;
                    const video = document.getElementById('video');
                    video.srcObject = stream;
                    document.getElementById('cameraPreview').style.display = 'block';
                    
                    // Agregar botón de captura
                    if (!document.getElementById('captureBtn')) {
                        const captureBtn = document.createElement('button');
                        captureBtn.type = 'button';
                        captureBtn.id = 'captureBtn';
                        captureBtn.className = 'btn btn-success mt-2';
                        captureBtn.innerHTML = '<i class="fas fa-camera me-2"></i>Capturar';
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
            const captureBtn = document.getElementById('captureBtn');
            if (captureBtn) captureBtn.remove();
        }
        
        function showPreview(src) {
            document.getElementById('previewImg').src = src;
            document.getElementById('photoPreview').style.display = 'block';
        }
        
        function removePhoto() {
            document.getElementById('photoPreview').style.display = 'none';
            document.getElementById('foto').value = '';
            document.getElementById('foto_camera').value = '';
            stopCamera();
        }
        
        function showPhotoModal(src) {
            document.getElementById('modalPhoto').src = src;
            new bootstrap.Modal(document.getElementById('photoModal')).show();
        }
        
        // Fijar mensaje
        function pinMessage(messageId) {
            $.ajax({
                url: 'ajax/pin_message.php',
                method: 'POST',
                data: {
                    message_id: messageId,
                    ticket_id: <?= $ticket_id ?>
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error en la comunicación con el servidor');
                }
            });
        }
        
        function unpinMessage(messageId) {
            $.ajax({
                url: 'ajax/unpin_message.php',
                method: 'POST',
                data: { message_id: messageId },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error en la comunicación con el servidor');
                }
            });
        }
        
        // Actualizar chat cada 3 segundos
        function checkNewMessages() {
            $.ajax({
                url: 'ajax/get_new_messages.php',
                method: 'GET',
                data: {
                    ticket_id: <?= $ticket_id ?>,
                    last_count: lastMessageCount
                },
                success: function(response) {
                    if (response.has_new) {
                        // Solo recargar si hay mensajes nuevos
                        location.reload();
                    }
                }
            });
        }
        
        // Verificar mensajes nuevos cada 3 segundos
        setInterval(checkNewMessages, 3000);
        
        // Validación antes de enviar
        document.getElementById('chatForm').addEventListener('submit', function(e) {
            const mensaje = document.getElementById('mensaje').value.trim();
            const tieneArchivo = document.getElementById('foto').files.length > 0;
            const tieneCamera = document.getElementById('foto_camera').value !== '';
            
            if (!mensaje && !tieneArchivo && !tieneCamera) {
                e.preventDefault();
                alert('Debe escribir un mensaje o adjuntar una foto');
            }
        });
    </script>
</body>
</html>