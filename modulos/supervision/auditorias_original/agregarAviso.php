<?php
// Includes estándar del ERP
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
require_once '../../../core/helpers/funciones.php'; // Antes llamaba a funciones.php de auditora
require_once 'conexion.php';
require_once '../../../core/layout/menu_lateral.php';
require_once '../../../core/layout/header_universal.php';

//******************************Estándar para header******************************
verificarAutenticacion();

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo 'supervision'
verificarAccesoCargo([11, 13, 16, 39, 30, 37, 42, 26]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([11, 13, 16, 39, 30, 37, 42, 26]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
$cargoOperario = $usuario['CodNivelesCargos'];
//******************************Estándar para header, termina******************************

// Configurar zona horaria para Nicaragua (UTC-6)
date_default_timezone_set('America/Managua');

// Función para formatear fecha en español con corrección horaria
function formatFechaEspanolAviso($fecha = 'now')
{
    $meses = [
        1 => 'ene',
        2 => 'feb',
        3 => 'mar',
        4 => 'abr',
        5 => 'may',
        6 => 'jun',
        7 => 'jul',
        8 => 'ago',
        9 => 'sep',
        10 => 'oct',
        11 => 'nov',
        12 => 'dic'
    ];

    $date = new DateTime($fecha, new DateTimeZone('UTC'));
    $date->modify('-6 hours'); // Compensar las 6 horas adicionales

    return $date->format('d') . '-' . $meses[$date->format('n')] . '-' . $date->format('y') . ' ' . $date->format('h:i a');
}

$pagina_actual = basename($_SERVER['PHP_SELF']);
$es_pagina_avisos = $pagina_actual == 'index_avisos.php';
$es_pagina_auditorias = $pagina_actual == 'index.php';

// Obtener sucursales para el select
$sucursales = [];
try {
    $stmt = $conn->query("SELECT codigo, nombre FROM sucursales WHERE codigo NOT IN ('0', '14') AND activa = 1 ORDER BY nombre");
    $sucursales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al obtener sucursales: " . $e->getMessage());
}

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $selectedBranches = $_POST['branches'] ?? [];

    // Validaciones básicas
    $errors = [];
    if (empty($title)) {
        $errors[] = "El título es requerido";
    }
    if (empty($content)) {
        $errors[] = "El contenido es requerido";
    }
    if (empty($selectedBranches)) {
        $errors[] = "Debe seleccionar al menos una sucursal";
    }

    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            // Insertar el aviso
            $stmt = $conn->prepare("
                INSERT INTO announcements (title, content, created_by, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$title, $content, $_SESSION['usuario_id'] ?? null]);
            $announcementId = $conn->lastInsertId();

            // En la inserción de sucursales
            $stmt = $conn->prepare("
                INSERT INTO announcement_branches (announcement_id, branch_id) 
                VALUES (?, ?)
            ");
            foreach ($selectedBranches as $branchCodigo) {
                $stmt->execute([$announcementId, $branchCodigo]);
            }

            // Procesar archivos adjuntos
            if (!empty($_FILES['documents']['name'][0]) || !empty($_FILES['media']['name'][0])) {
                $uploadDir = 'uploads/avisos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                // Función para procesar archivos
                function processUploadedFiles($files, $announcementId, $conn, $uploadDir)
                {
                    $allowedDocs = [
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-powerpoint',
                        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'text/plain'
                    ];

                    $allowedMedia = [
                        'image/jpeg',
                        'image/png',
                        'image/gif',
                        'image/webp',
                        'video/mp4',
                        'video/webm',
                        'video/quicktime'
                    ];

                    $stmt = $conn->prepare("
                        INSERT INTO attachments 
                        (announcement_id, file_name, file_path, file_type, file_size) 
                        VALUES (?, ?, ?, ?, ?)
                    ");

                    for ($i = 0; $i < count($files['name']); $i++) {
                        if ($files['error'][$i] !== UPLOAD_ERR_OK)
                            continue;

                        $fileType = mime_content_type($files['tmp_name'][$i]);
                        $fileExt = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                        $newFilename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\.\-]/', '_', $files['name'][$i]);
                        $destination = $uploadDir . $newFilename;

                        if (move_uploaded_file($files['tmp_name'][$i], $destination)) {
                            $stmt->execute([
                                $announcementId,
                                $files['name'][$i],
                                $destination,
                                $fileType,
                                $files['size'][$i]
                            ]);
                        }
                    }
                }

                // Procesar documentos
                if (!empty($_FILES['documents']['name'][0])) {
                    processUploadedFiles($_FILES['documents'], $announcementId, $conn, $uploadDir);
                }

                // Procesar multimedia
                if (!empty($_FILES['media']['name'][0])) {
                    processUploadedFiles($_FILES['media'], $announcementId, $conn, $uploadDir);
                }
            }

            $conn->commit();

            // Redirigir al listado con mensaje de éxito
            $_SESSION['success_message'] = "Aviso publicado exitosamente";
            header("Location: index_avisos.php");
            exit();

        } catch (Exception $e) {
            $conn->rollBack();
            error_log("Error al guardar aviso: " . $e->getMessage());
            $errors[] = "Ocurrió un error al guardar el aviso. Por favor intente nuevamente.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Aviso - Batidos Pitaya</title>
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="icon" href="icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

    <style>
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            max-width: 1200px;
            padding: 0 10px;
            box-sizing: border-box;
            margin: 20px auto;
            flex-wrap: wrap;
        }

        .logo-container {
            margin-right: 20px;
            flex-shrink: 0;
        }

        .buttons-container {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
            flex-grow: 1;
        }

        .btn-agregar {
            background-color: transparent;
            color: #51B8AC;
            border: 1px solid #51B8AC;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .btn-agregar.activo {
            background-color: #51B8AC;
            color: white;
            font-weight: normal;
        }

        .btn-agregar:hover {
            background-color: #51B8AC;
            color: white;
        }

        .contenedor-principal {
            width: 100%;
            max-width: 800px;
            margin: 20px auto;
            padding: 0 15px;
        }

        .form-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            padding: 20px;
            text-align: left;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #0E544C;
        }

        .form-group input[type="text"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .form-group textarea {
            min-height: 150px;
            resize: vertical;
        }

        .current-time {
            color: #666;
            margin-bottom: 15px;
        }

        .file-upload-section {
            margin-bottom: 20px;
        }

        .file-upload-title {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            color: #0E544C;
            font-weight: bold;
        }

        .file-upload-title i {
            margin-right: 8px;
        }

        .file-upload-box {
            border: 2px dashed #51B8AC;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .file-upload-box:hover {
            background-color: #f0f9f8;
        }

        .file-upload-box input[type="file"] {
            display: none;
        }

        .file-upload-label {
            display: block;
            cursor: pointer;
        }

        .file-upload-label i {
            color: #51B8AC;
            margin-bottom: 8px;
        }

        .file-preview {
            margin-top: 15px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .file-preview-item {
            background-color: #f0f0f0;
            padding: 8px 12px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .file-preview-item i {
            color: #51B8AC;
        }

        .file-preview-item .remove-file {
            color: #ff6b6b;
            cursor: pointer;
        }

        .branches-select {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }

        .branch-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .branch-checkbox input {
            margin: 0;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
        }

        .btn-primary {
            background-color: #51B8AC;
            color: white;
        }

        .btn-primary:hover {
            background-color: #0E544C;
        }

        .btn-secondary {
            background-color: #f0f0f0;
            color: #333;
        }

        .btn-secondary:hover {
            background-color: #ddd;
        }

        .error-message {
            color: #ff6b6b;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #fff0f0;
            border-radius: 4px;
            border-left: 4px solid #ff6b6b;
        }

        /* Estilos para la galería Instagram */
        .instagram-gallery {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 15px;
        }

        .gallery-item {
            position: relative;
            width: 100%;
            padding-bottom: 100%;
            /* Cuadrados perfectos */
            overflow: hidden;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .gallery-item img,
        .gallery-item video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .gallery-item .remove-media {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(255, 0, 0, 0.7);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .gallery-item .play-icon {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            text-shadow: 0 0 5px rgba(0, 0, 0, 0.5);
        }

        /* Agrega esto en tu sección de estilos */
        #select_all_branches+label {
            color: #0E544C;
            font-weight: bold;
        }

        .branch-checkbox-item+label {
            font-weight: normal;
        }

        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .buttons-container {
                width: 100%;
                justify-content: flex-start;
                gap: 8px;
            }

            .branches-select {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }

            .instagram-gallery {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .form-actions {
                flex-direction: column;
                gap: 10px;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Nuevo Aviso'); ?>
            <div class="contenedor-principal">

                <?php if (!empty($errors)): ?>
                    <div class="error-message">
                        <strong>Error:</strong>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="form-container">
                    <form id="avisoForm" method="POST" enctype="multipart/form-data">
                        <!-- Fecha y hora actual -->
                        <div class="current-time">
                            <i class="far fa-clock"></i> Fecha y hora:
                            <span id="currentDateTime"><?= formatFechaEspanolAviso() ?></span>
                        </div>

                        <!-- Título -->
                        <div class="form-group">
                            <label for="title">Título del aviso:</label>
                            <input type="text" id="title" name="title" required
                                placeholder="Ingrese el título del aviso" maxlength="255"
                                value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                        </div>

                        <!-- Contenido -->
                        <div class="form-group">
                            <label for="content">Contenido:</label>
                            <textarea style="text-align:left !important;" id="content" name="content"
                                required></textarea>
                        </div>

                        <!-- Sucursales -->
                        <div class="form-group">
                            <label>Sucursales destinatarias:</label>
                            <div class="branches-select">
                                <!-- Checkbox "Todas" -->
                                <div class="branch-checkbox" style="grid-column: 1 / -1;">
                                    <input type="checkbox" id="select_all_branches">
                                    <label for="select_all_branches" style="font-weight: bold;">TODAS LAS
                                        SUCURSALES</label>
                                </div>

                                <?php foreach ($sucursales as $sucursal): ?>
                                    <div class="branch-checkbox">
                                        <input type="checkbox" id="branch_<?= $sucursal['codigo'] ?>" name="branches[]"
                                            value="<?= $sucursal['codigo'] ?>" class="branch-checkbox-item"
                                            <?= (isset($_POST['branches']) && in_array($sucursal['codigo'], $_POST['branches'])) ? 'checked' : '' ?>>
                                        <label
                                            for="branch_<?= $sucursal['codigo'] ?>"><?= htmlspecialchars($sucursal['nombre']) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Documentos adjuntos -->
                        <div class="file-upload-section">
                            <div class="file-upload-title">
                                <i class="fas fa-file-alt"></i> Documentos adjuntos (PDF, Word, Excel, PowerPoint)
                            </div>
                            <div class="file-upload-box">
                                <label class="file-upload-label">
                                    <input type="file" id="documents" name="documents[]" multiple
                                        accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt">
                                    <i class="fas fa-plus-circle"></i>
                                    <div>Haga clic para agregar documentos</div>
                                </label>
                            </div>
                            <div class="file-preview" id="documents-preview"></div>
                        </div>

                        <!-- Imágenes/Videos -->
                        <div class="file-upload-section">
                            <div class="file-upload-title">
                                <i class="fas fa-images"></i> Galería de Imágenes/Videos (Instagram Style)
                            </div>
                            <div class="file-upload-box">
                                <label class="file-upload-label">
                                    <input type="file" id="media" name="media[]" multiple accept="image/*,video/*">
                                    <i class="fas fa-plus-circle"></i>
                                    <div>Arrastra o haz clic para agregar fotos/videos</div>
                                </label>
                            </div>

                            <!-- Galería estilo Instagram -->
                            <div class="instagram-gallery" id="media-preview">
                                <!-- Las previsualizaciones se agregarán aquí -->
                            </div>
                        </div>

                        <!-- Botones de acción -->
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary"
                                onclick="window.location.href='index_avisos.php'">
                                <i class="fas fa-times"></i> Cancelar
                            </button>
                            <button type="submit" class="btn btn-primary" onclick="return confirmSubmit()">
                                <i class="fas fa-paper-plane"></i> Publicar Aviso
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                // Objetos para almacenar los archivos seleccionados
                let selectedDocuments = [];
                let selectedMedia = [];

                // Función para actualizar fecha y hora en tiempo real
                function updateDateTime() {
                    // Hacer una petición al servidor para obtener la fecha formateada correctamente
                    fetch('get_current_time.php')
                        .then(response => response.text())
                        .then(data => {
                            document.getElementById('currentDateTime').textContent = data;
                        })
                        .catch(error => console.error('Error:', error));
                }

                // Función para manejar el checkbox "Todas"
                function setupSelectAllBranches() {
                    const selectAll = document.getElementById('select_all_branches');
                    const branchCheckboxes = document.querySelectorAll('.branch-checkbox-item');

                    // Cuando se cambia el estado de "Todas"
                    selectAll.addEventListener('change', function () {
                        branchCheckboxes.forEach(checkbox => {
                            checkbox.checked = this.checked;
                        });
                    });

                    // Cuando se cambia cualquier checkbox individual
                    branchCheckboxes.forEach(checkbox => {
                        checkbox.addEventListener('change', function () {
                            if (!this.checked && selectAll.checked) {
                                selectAll.checked = false;
                            } else {
                                // Verificar si todos están seleccionados
                                const allChecked = Array.from(branchCheckboxes).every(cb => cb.checked);
                                selectAll.checked = allChecked;
                            }
                        });
                    });

                    // Inicializar estado de "Todas" según los checkboxes actuales
                    const allCheckedInitially = Array.from(branchCheckboxes).every(cb => cb.checked);
                    selectAll.checked = allCheckedInitially;
                }

                // Actualizar el input file con los archivos acumulados
                function updateFileInput(inputId, files) {
                    const input = document.getElementById(inputId);
                    const dataTransfer = new DataTransfer();

                    files.forEach(file => {
                        dataTransfer.items.add(file);
                    });

                    input.files = dataTransfer.files;
                }

                // Actualizar la vista previa de documentos
                function updateDocumentsPreview() {
                    const preview = document.getElementById('documents-preview');
                    preview.innerHTML = '';

                    selectedDocuments.forEach((file, index) => {
                        const fileItem = document.createElement('div');
                        fileItem.className = 'file-preview-item';

                        let iconClass = 'fa-file';
                        if (file.type.includes('pdf')) iconClass = 'fa-file-pdf';
                        else if (file.type.includes('word')) iconClass = 'fa-file-word';
                        else if (file.type.includes('excel') || file.type.includes('spreadsheet')) iconClass = 'fa-file-excel';
                        else if (file.type.includes('powerpoint') || file.type.includes('presentation')) iconClass = 'fa-file-powerpoint';

                        fileItem.innerHTML = `
                    <i class="fas ${iconClass}"></i>
                    <span>${file.name}</span>
                    <i class="fas fa-times remove-file" onclick="removeDocument(${index})"></i>
                `;

                        preview.appendChild(fileItem);
                    });
                }

                // Actualizar la vista previa de multimedia
                function updateMediaPreview() {
                    const preview = document.getElementById('media-preview');
                    preview.innerHTML = '';

                    selectedMedia.forEach((file, index) => {
                        const galleryItem = document.createElement('div');
                        galleryItem.className = 'gallery-item';

                        if (file.type.includes('image')) {
                            const img = document.createElement('img');
                            img.src = URL.createObjectURL(file);
                            galleryItem.appendChild(img);
                        } else if (file.type.includes('video')) {
                            const video = document.createElement('video');
                            video.src = URL.createObjectURL(file);
                            video.setAttribute('controls', '');
                            galleryItem.appendChild(video);

                            const playIcon = document.createElement('i');
                            playIcon.className = 'fas fa-play play-icon';
                            galleryItem.appendChild(playIcon);
                        }

                        const removeBtn = document.createElement('div');
                        removeBtn.className = 'remove-media';
                        removeBtn.innerHTML = '×';
                        removeBtn.onclick = function () {
                            removeMedia(index);
                        };
                        galleryItem.appendChild(removeBtn);

                        preview.appendChild(galleryItem);
                    });
                }

                // Mostrar previsualización de documentos
                function handleDocumentsPreview(e) {
                    const newFiles = Array.from(e.target.files);
                    selectedDocuments = [...selectedDocuments, ...newFiles];
                    updateDocumentsPreview();
                    updateFileInput('documents', selectedDocuments);
                }

                // Mostrar previsualización de galería Instagram
                function handleMediaPreview(e) {
                    const newFiles = Array.from(e.target.files);
                    selectedMedia = [...selectedMedia, ...newFiles];
                    updateMediaPreview();
                    updateFileInput('media', selectedMedia);
                }

                // Eliminar un documento
                function removeDocument(index) {
                    selectedDocuments.splice(index, 1);
                    updateDocumentsPreview();
                    updateFileInput('documents', selectedDocuments);
                }

                // Eliminar un archivo multimedia
                function removeMedia(index) {
                    selectedMedia.splice(index, 1);
                    updateMediaPreview();
                    updateFileInput('media', selectedMedia);
                }

                // Validación y envío del formulario
                function validateAndSubmit(event) {
                    event.preventDefault();

                    const title = document.getElementById('title').value.trim();
                    const content = document.getElementById('content').value.trim();
                    const branches = document.querySelectorAll('input[name="branches[]"]:checked');

                    const errors = [];

                    if (!title) errors.push("El título es requerido");
                    if (!content) errors.push("El contenido es requerido");
                    if (branches.length === 0) errors.push("Debe seleccionar al menos una sucursal");

                    if (errors.length > 0) {
                        const errorContainer = document.createElement('div');
                        errorContainer.className = 'error-message';
                        errorContainer.innerHTML = `
                    <strong>Error:</strong>
                    <ul>
                        ${errors.map(error => `<li>${error}</li>`).join('')}
                    </ul>
                `;

                        const oldError = document.querySelector('.error-message');
                        if (oldError) oldError.remove();

                        const formContainer = document.querySelector('.form-container');
                        formContainer.insertBefore(errorContainer, formContainer.firstChild);

                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        return false;
                    }

                    if (confirm('¿Está seguro que desea publicar este aviso?')) {
                        document.getElementById('avisoForm').submit();
                    }

                    return false;
                }

                // Inicialización cuando el DOM está listo
                document.addEventListener('DOMContentLoaded', function () {
                    // Configuración de eventos
                    setInterval(updateDateTime, 60000);
                    updateDateTime();

                    setupSelectAllBranches();

                    // Inicializar los arrays con archivos ya seleccionados (si los hay)
                    const documentsInput = document.getElementById('documents');
                    if (documentsInput.files.length > 0) {
                        selectedDocuments = Array.from(documentsInput.files);
                        updateDocumentsPreview();
                    }

                    const mediaInput = document.getElementById('media');
                    if (mediaInput.files.length > 0) {
                        selectedMedia = Array.from(mediaInput.files);
                        updateMediaPreview();
                    }

                    // Configurar event listeners
                    documentsInput.addEventListener('change', handleDocumentsPreview);
                    mediaInput.addEventListener('change', handleMediaPreview);
                    document.querySelector('.btn-primary').onclick = validateAndSubmit;
                });

                // Convertir título a mayúsculas al escribir
                document.getElementById('title').addEventListener('input', function (e) {
                    this.value = this.value.toUpperCase();
                });
            </script>
        </div>
    </div>
</body>

</html>
