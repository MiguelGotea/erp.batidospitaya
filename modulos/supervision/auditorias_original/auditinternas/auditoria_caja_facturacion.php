<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/layout/menu_lateral.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/layout/header_universal.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/permissions/permissions.php';
// Antes llamaba a ../funciones.php de auditora
// require_once 'config.php'; // Comentado por migración al core

// $conn = conectarDB(); // Comentado por migración al core
$conn = $conn ?? null; // Asegurar que $conn esté disponible si se usa más abajo
$db = $conn; // En este archivo se usa $db para la conexión

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso al módulo
if (!verificarAccesoCargo([16, 21])) {
    header('Location: /index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);

// Obtener sucursales para el select
$sucursales = [];
try {
    $stmt = $db->query("SELECT codigo, nombre FROM sucursales WHERE activa = 1 AND sucursal=1 ORDER BY nombre");
    $sucursales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al obtener sucursales: " . $e->getMessage());
}

// Función para obtener operarios activos por sucursal
function obtenerOperariosActivosPorSucursal($db, $sucursal_id) {
    $operarios = [];
    try {
        $stmt = $db->prepare("
            SELECT o.CodOperario, CONCAT(o.Nombre, ' ', o.Apellido) AS nombre_completo
            FROM Operarios o
            JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
            WHERE anc.Sucursal = ?
            AND o.Operativo = 1
            AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
            AND anc.CodNivelesCargos IN (2, 43, 44, 45, 46, 47)  -- Código para operarios/cajeros
            ORDER BY nombre_completo
        ");
        $stmt->execute([$sucursal_id]);
        $operarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Error al obtener operarios: " . $e->getMessage());
    }
    return $operarios;
}

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar que se haya enviado una foto
    if (empty($_POST['photoData'])) {
        die("Error: Debe tomar una foto de evidencia para guardar la auditoría");
    }
    
    // Verificar que hay comentarios
    if (empty(trim($_POST['comentarios']))) {
        die("Error: Debe ingresar comentarios para guardar la auditoría");
    }
    
    $sucursal_id = (int)$_POST['sucursal_id'];
    $cajero_id = (int)$_POST['cajero_id'];
    $monto_designado = round((float)$_POST['monto_designado'], 2);
    $total_conteo = round((float)$_POST['total_conteo'], 2);
    $comentarios = trim($_POST['comentarios']);
    
    // Calcular diferencia (solo mostrar si es negativa)
    $faltante_sobrante = min($total_conteo - $monto_designado, 0);
    
    // Verificar que la sucursal existe
    try {
        $stmt = $db->prepare("SELECT codigo, nombre FROM sucursales WHERE codigo = ?");
        $stmt->execute([$sucursal_id]);
        $sucursal = $stmt->fetch();
        
        if (!$sucursal) {
            die("Error: La sucursal seleccionada no existe en la base de datos");
        }
        
        $sucursal_nombre = $sucursal['nombre'];
    } catch (PDOException $e) {
        die("Error al verificar la sucursal: " . $e->getMessage());
    }
    
    // Verificar que el operario existe y pertenece a la sucursal
    try {
        $stmt = $db->prepare("
            SELECT o.CodOperario, CONCAT(o.Nombre, ' ', o.Apellido) AS nombre_completo
            FROM Operarios o
            JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
            WHERE o.CodOperario = ?
            AND anc.Sucursal = ?
            AND o.Operativo = 1
            AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
            LIMIT 1
        ");
        $stmt->execute([$cajero_id, $sucursal_id]);
        $cajero = $stmt->fetch();
        
        if (!$cajero) {
            die("Error: El cajero seleccionado no existe o no pertenece a esta sucursal");
        }
        
        $cajero_nombre = $cajero['nombre_completo'];
    } catch (PDOException $e) {
        die("Error al verificar el cajero: " . $e->getMessage());
    }
    
    // Insertar en la base de datos
    try {
        $db->beginTransaction();
        
        // CONSULTA DIRECTA PARA OBTENER EL CÓDIGO DE CONTRATO DEL CAJERO
        $cod_contrato_cajero = null;
        if (!empty($cajero_id)) {
            $stmt_contrato = $db->prepare("
                SELECT CodContrato 
                FROM Contratos 
                WHERE cod_operario = ? 
                ORDER BY inicio_contrato DESC, CodContrato DESC 
                LIMIT 1
            ");
    
            if ($stmt_contrato) {
                $stmt_contrato->execute([$cajero_id]);
                $result_contrato = $stmt_contrato->fetch(PDO::FETCH_ASSOC);
                
                if ($result_contrato && !empty($result_contrato['CodContrato'])) {
                    $cod_contrato_cajero = $result_contrato['CodContrato'];
                    error_log("Contrato encontrado para cajero $cajero_id: $cod_contrato_cajero");
                } else {
                    error_log("No se encontró contrato en consulta directa para cajero: $cajero_id");
                }
            } else {
                error_log("Error preparando consulta de contrato para cajero: " . $db->errorInfo()[2]);
            }
        }
        
        $stmt = $db->prepare("INSERT INTO auditoria_facturacion 
                            (fecha_hora, sucursal_id, sucursal, cajero, cajero_nombre, monto_designado, 
                            total_conteo, faltante_sobrante, comentarios, created_at, cod_contrato) 
                            VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
        
        $stmt->execute([
            $sucursal_id,
            $sucursal_nombre,
            $cajero_id,
            $cajero_nombre,
            $monto_designado,
            $total_conteo,
            $faltante_sobrante,
            $comentarios,
            $cod_contrato_cajero  // ← Aquí usamos el contrato del CAJERO
        ]);
        
        $auditoria_id = $db->lastInsertId();
        
        // Manejo de la foto (código existente)
        $photoData = $_POST['photoData'];
        $photoData = str_replace('data:image/jpeg;base64,', '', $photoData);
        $photoData = str_replace(' ', '+', $photoData);
        $image = base64_decode($photoData);
        
        $imageName = 'auditoria_' . $auditoria_id . '_' . time() . '.jpg';
        $imagePath = 'fotos_auditorias_caja_facturacion/' . $imageName;
        
        if (!file_exists('fotos_auditorias_caja_facturacion')) {
            mkdir('fotos_auditorias_caja_facturacion', 0777, true);
        }
        
        file_put_contents($imagePath, $image);
        
        $stmt = $db->prepare("UPDATE auditoria_facturacion SET foto_path = ? WHERE id = ?");
        $stmt->execute([$imagePath, $auditoria_id]);
        
        $db->commit();
        
        header("Location: auditorias_consolidadas.php?success=1");
        exit();
        
    } catch (PDOException $e) {
        $db->rollBack();
        die("Error al guardar la auditoría: " . $e->getMessage());
    }
}

// Mostrar mensaje de éxito si viene de redirección
$showSuccess = isset($_GET['success']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría de Facturación de Caja</title>
    <link rel="icon" href="/core/assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/auditoria_caja_facturacion.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Auditoría Caja Facturación'); ?>

            <div class="container-fluid p-3">
        <div class="container">
        <div class="success-message" id="successMessage" style="display: <?php echo $showSuccess ? 'block' : 'none'; ?>;">
            ¡La auditoría se ha guardado correctamente! Serás redirigido...
        </div>
        
        <h1 style="text-align:center;">Auditoría Caja de Facturación</h1>
        
        <!-- Modal de confirmación -->
        <div id="confirmModal" class="modal">
            <div class="modal-content">
                <p>¿Está seguro que desea guardar esta auditoría?</p>
                <div class="modal-buttons">
                    <button class="modal-btn modal-btn-cancel" id="cancelBtn">Cancelar</button>
                    <button class="modal-btn modal-btn-confirm" id="confirmBtn">Guardar</button>
                </div>
            </div>
        </div>
        
        <!-- Modal de alerta -->
        <div id="alertModal" class="alert-modal">
            <div class="alert-content">
                <p id="alertMessage"></p>
                <div class="alert-buttons">
                    <button class="alert-btn" id="alertOkBtn">Aceptar</button>
                </div>
            </div>
        </div>
        
        <form id="auditoriaForm" method="post">
            <div class="form-group">
                <label for="fecha">Fecha:</label>
                <input type="text" id="fecha" name="fecha" value="<?php echo formatFechaEspanol(); ?>" readonly>
            </div>
            
            <div class="form-group">
                <label for="sucursal_id">Sucursal:</label>
                <select id="sucursal_id" name="sucursal_id" required>
                    <option value="">Seleccione una sucursal</option>
                    <?php foreach ($sucursales as $sucursal): ?>
                        <option value="<?php echo htmlspecialchars($sucursal['codigo']); ?>">
                            <?php echo htmlspecialchars($sucursal['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" id="sucursal_nombre" name="sucursal_nombre">
            </div>
            
            <div class="form-group">
                <label for="cajero_id">Cajero/a:</label>
                <select id="cajero_id" name="cajero_id" required>
                    <option value="">Seleccione un cajero</option>
                    <!-- Se llenará dinámicamente con JavaScript -->
                </select>
            </div>
            
            <div class="form-group">
                <label for="monto_designado">Efectivo según Sistema (C$):</label>
                <input type="number" id="monto_designado" name="monto_designado" 
                     step="0.01" min="0" required
                     oninput="calcularDiferencia()">
            </div>
            
            <div class="form-group">
                <label for="total_conteo">Total Conteo (C$):</label>
                <input type="number" id="total_conteo" name="total_conteo" 
                     step="0.01" min="0" required
                     oninput="calcularDiferencia()">
            </div>
            
            <div class="totals">
                <table>
                    <tr>
                        <td><strong>Diferencia:</strong></td>
                        <td style="text-align: right;" id="faltante_sobrante">C$ 0.00</td>
                    </tr>
                </table>
            </div>
            
            <div class="form-group">
                <label for="comentarios">Comentarios:</label>
                <textarea id="comentarios" name="comentarios" rows="4" required></textarea>
            </div>
            
            <!-- Sección para la foto -->
            <div class="form-group">
                <div class="photo-section" id="photoSection">
                    <h3>Foto de Cierre de Caja <span style="color:red;">*</span></h3>
                    <!-- Estado de la foto -->
                    <div class="photo-status pending" id="photoStatus">Falta 1 foto por capturar</div>
                    
                    <!-- Selector de cámara -->
                    <select class="camera-selector" id="cameraSelector">
                        <option value="">Seleccionar cámara...</option>
                    </select>
                    
                    <!-- Vista previa de la cámara -->
                    <div class="photo-container">
                        <video id="videoPreview" class="photo-preview" autoplay playsinline></video>
                        <canvas id="photoCanvas" class="photo-canvas"></canvas>
                    </div>
                    
                    <!-- Controles de cámara -->
                    <div class="photo-buttons">
                        <button type="button" class="photo-btn" id="captureBtn">Tomar Foto</button>
                        <button type="button" class="photo-btn" id="retakeBtn" disabled>Volver a Tomar</button>
                    </div>
                    
                    <!-- Galería de foto capturada -->
                    <div id="photoGallery" class="photo-gallery"></div>
                    
                    <!-- Input oculto para la foto -->
                    <input type="hidden" id="photoData" name="photoData" required>
                </div>
            </div>
            
            <div class="button-container">
                <button type="button" class="btn btn-special" id="submitBtn" disabled>
                    Guardar Auditoría
                </button>
                <button type="button" 
                        class="btn btn-cancel" 
                        onclick="window.location.href='auditorias_consolidadas.php'">
                    Cancelar
                </button>
            </div>
        </form>
        </div><!-- /.container -->
            </div><!-- /.container-fluid -->
        </div><!-- /.sub-container -->
    </div><!-- /.main-container -->

    <script>
        function formatNumber(num) {
            return num.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        function calcularDiferencia() {
            const montoDesignado = parseFloat(document.getElementById('monto_designado').value) || 0;
            const totalConteo = parseFloat(document.getElementById('total_conteo').value) || 0;
            
            // Calcular diferencia (solo mostrar si es negativa)
            const diferencia = Math.min(totalConteo - montoDesignado, 0);
            
            const diferenciaElement = document.getElementById('faltante_sobrante');
            
            if (diferencia < 0) {
                // Mostrar valor absoluto (sin signo negativo) pero mantener el estilo de error
                diferenciaElement.textContent = 'C$ ' + Math.abs(diferencia).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                diferenciaElement.className = 'diferencia-negativa';
            } else {
                diferenciaElement.textContent = 'C$ 0.00';
                diferenciaElement.className = '';
            }
        }
        
        function actualizarMontoDesignado() {
            const sucursalSelect = document.getElementById('sucursal_id');
            const sucursalNombre = sucursalSelect.options[sucursalSelect.selectedIndex].text;
            document.getElementById('sucursal_nombre').value = sucursalNombre;
        }
        
        function validarSoloTexto(texto) {
            return /^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/.test(texto);
        }

        function validarCamposTexto() {
            let valido = true;
            const camposTexto = document.querySelectorAll('.texto-validado');
            
            camposTexto.forEach(campo => {
                if (!validarSoloTexto(campo.value)) {
                    campo.classList.add('error-input');
                    valido = false;
                } else {
                    campo.classList.remove('error-input');
                }
            });
            
            return valido;
        }
        
        function showAlert(message, focusElement = null) {
            const alertModal = document.getElementById('alertModal');
            const alertMessage = document.getElementById('alertMessage');
            
            alertMessage.textContent = message;
            alertModal.style.display = 'block';
            
            if (focusElement) {
                document.getElementById('alertOkBtn').onclick = function() {
                    alertModal.style.display = 'none';
                    focusElement.focus();
                };
            } else {
                document.getElementById('alertOkBtn').onclick = function() {
                    alertModal.style.display = 'none';
                };
            }
        }
        
        const modal = document.getElementById('confirmModal');
        const submitBtn = document.getElementById('submitBtn');
        const confirmBtn = document.getElementById('confirmBtn');
        const cancelBtn = document.getElementById('cancelBtn');
        const form = document.getElementById('auditoriaForm');
        const comentariosInput = document.getElementById('comentarios');
        
        submitBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (!validarCamposTexto()) {
                showAlert('Los campos de nombre solo pueden contener letras y espacios');
                return;
            }
            
            if (document.getElementById('sucursal_id').value === '') {
                showAlert('Por favor seleccione una sucursal', document.getElementById('sucursal_id'));
                return;
            }
            
            if (comentariosInput.value.trim() === '') {
                showAlert('Por favor ingrese comentarios antes de guardar la auditoría', comentariosInput);
                return;
            }
            
            modal.style.display = 'block';
        });
        
        confirmBtn.addEventListener('click', function() {
            modal.style.display = 'none';
            
            if (!photoManager.hasPhoto()) {
                showAlert('Debe tomar una foto de evidencia antes de guardar la auditoría.');
                document.getElementById('photoSection').classList.add('required');
                return;
            }
            
            if (comentariosInput.value.trim() === '') {
                showAlert('Por favor ingrese comentarios antes de guardar la auditoría', comentariosInput);
                return;
            }
            
            form.submit();
        });
        
        cancelBtn.addEventListener('click', function() {
            modal.style.display = 'none';
        });
        
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
            if (event.target === document.getElementById('alertModal')) {
                document.getElementById('alertModal').style.display = 'none';
            }
        });

        if (window.location.search.includes('success=1')) {
            document.getElementById('successMessage').style.display = 'block';
            setTimeout(function() {
                window.location.href = 'auditorias_consolidadas.php';
            }, 3000);
            history.replaceState(null, null, window.location.pathname);
        }
        
        // Clase para manejar la cámara y foto
        class PhotoManager {
            constructor() {
                this.stream = null;
                this.capturedPhoto = null;
                this.devices = [];
                this.currentDeviceId = null;
                
                this.videoElement = document.getElementById('videoPreview');
                this.canvasElement = document.getElementById('photoCanvas');
                this.captureBtn = document.getElementById('captureBtn');
                this.retakeBtn = document.getElementById('retakeBtn');
                this.cameraSelector = document.getElementById('cameraSelector');
                this.photoGallery = document.getElementById('photoGallery');
                this.photoStatus = document.getElementById('photoStatus');
                this.photoDataInput = document.getElementById('photoData');
                this.submitBtn = document.getElementById('submitBtn');
                this.photoSection = document.getElementById('photoSection');
                
                this.init();
            }
            
            async init() {
                await this.listCameras();
                this.setupEventListeners();
                
                // Iniciar automáticamente la primera cámara disponible
                if (this.devices.length > 0) {
                    this.currentDeviceId = this.devices[0].deviceId;
                    this.cameraSelector.value = this.currentDeviceId;
                    this.startCamera(this.currentDeviceId);
                }
            }
            
            async listCameras() {
                try {
                    const devices = await navigator.mediaDevices.enumerateDevices();
                    this.devices = devices.filter(device => device.kind === 'videoinput');
                    
                    this.cameraSelector.innerHTML = '<option value="">Seleccionar cámara...</option>';
                    
                    this.devices.forEach((device, index) => {
                        const option = document.createElement('option');
                        option.value = device.deviceId;
                        option.text = device.label || `Cámara ${index + 1}`;
                        this.cameraSelector.appendChild(option);
                    });
                } catch (error) {
                    console.error("Error al listar las cámaras: ", error);
                    showAlert("No se pudieron listar las cámaras disponibles.");
                }
            }
            
            setupEventListeners() {
                this.cameraSelector.addEventListener('change', () => {
                    this.currentDeviceId = this.cameraSelector.value;
                    this.startCamera(this.currentDeviceId);
                });
                
                this.captureBtn.addEventListener('click', () => this.capturePhoto());
                this.retakeBtn.addEventListener('click', () => this.retakePhoto());
            }
            
            async startCamera(deviceId) {
                try {
                    if (this.stream) {
                        this.stream.getTracks().forEach(track => track.stop());
                    }
                    
                    const constraints = {
                        video: {
                            deviceId: deviceId ? { exact: deviceId } : undefined,
                            width: { ideal: 1280 },
                            height: { ideal: 720 },
                            facingMode: 'environment' // Preferir cámara trasera
                        },
                        audio: false
                    };
                    
                    this.stream = await navigator.mediaDevices.getUserMedia(constraints);
                    this.videoElement.srcObject = this.stream;
                    
                    this.captureBtn.disabled = false;
                    this.retakeBtn.disabled = true;
                    
                    this.videoElement.style.display = 'block';
                } catch (error) {
                    console.error("Error al iniciar la cámara: ", error);
                    showAlert("No se pudo acceder a la cámara seleccionada.");
                }
            }
            
            capturePhoto() {
                const width = this.videoElement.videoWidth;
                const height = this.videoElement.videoHeight;
                this.canvasElement.width = width;
                this.canvasElement.height = height;
                
                const ctx = this.canvasElement.getContext('2d');
                ctx.drawImage(this.videoElement, 0, 0, width, height);
                
                this.capturedPhoto = this.canvasElement.toDataURL('image/jpeg', 0.8);
                this.photoDataInput.value = this.capturedPhoto;
                
                this.updateGallery();
                this.updateStatus();
                
                this.captureBtn.disabled = true;
                this.retakeBtn.disabled = false;
                this.submitBtn.disabled = false;
                this.photoSection.classList.remove('required');
            }
            
            retakePhoto() {
                this.capturedPhoto = null;
                this.photoDataInput.value = '';
                
                this.updateGallery();
                this.updateStatus();
                
                this.captureBtn.disabled = false;
                this.retakeBtn.disabled = true;
                this.submitBtn.disabled = true;
                this.videoElement.style.display = 'block';
            }
            
            updateGallery() {
                this.photoGallery.innerHTML = '';
                
                if (this.capturedPhoto) {
                    const photoContainer = document.createElement('div');
                    photoContainer.className = 'photo-thumbnail';
                    
                    const img = document.createElement('img');
                    img.src = this.capturedPhoto;
                    
                    const removeBtn = document.createElement('button');
                    removeBtn.className = 'remove-photo';
                    removeBtn.innerHTML = '×';
                    removeBtn.onclick = (e) => {
                        e.preventDefault();
                        this.retakePhoto();
                    };
                    
                    photoContainer.appendChild(img);
                    photoContainer.appendChild(removeBtn);
                    this.photoGallery.appendChild(photoContainer);
                }
            }
            
            updateStatus() {
                if (this.hasPhoto()) {
                    this.photoStatus.textContent = 'Foto capturada correctamente';
                    this.photoStatus.className = 'photo-status complete';
                } else {
                    this.photoStatus.textContent = 'Falta 1 foto por capturar';
                    this.photoStatus.className = 'photo-status pending';
                }
            }
            
            hasPhoto() {
                return this.capturedPhoto !== null;
            }
            
            cleanup() {
                if (this.stream) {
                    this.stream.getTracks().forEach(track => track.stop());
                    this.videoElement.srcObject = null;
                    this.stream = null;
                }
            }
        }
        
        // Nueva función para cargar operarios según sucursal seleccionada
        async function cargarOperariosPorSucursal(sucursalId) {
            const selectOperarios = document.getElementById('cajero_id');
            
            if (!sucursalId) {
                selectOperarios.innerHTML = '<option value="">Seleccione un cajero</option>';
                return;
            }
            
            try {
                const response = await fetch(`obtener_operarios.php?sucursal_id=${sucursalId}`);
                const operarios = await response.json();
                
                let options = '<option value="">Seleccione un cajero</option>';
                operarios.forEach(op => {
                    options += `<option value="${op.CodOperario}">${op.nombre_completo}</option>`;
                });
                
                selectOperarios.innerHTML = options;
            } catch (error) {
                console.error('Error al cargar operarios:', error);
                showAlert('Error al cargar la lista de cajeros');
            }
        }
        
        // Escuchar cambios en el select de sucursal
        document.getElementById('sucursal_id').addEventListener('change', function() {
            const sucursalId = this.value;
            cargarOperariosPorSucursal(sucursalId);
            actualizarMontoDesignado();
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            calcularDiferencia();
            
            // Validación en tiempo real para campos de texto
            document.querySelectorAll('.texto-validado').forEach(campo => {
                campo.addEventListener('input', function() {
                    if (!validarSoloTexto(this.value)) {
                        this.classList.add('error-input');
                    } else {
                        this.classList.remove('error-input');
                    }
                });
            });
            
            // Inicializar el administrador de fotos
            const photoManager = new PhotoManager();
            
            // Configurar el botón de confirmación para que envíe el formulario
            document.getElementById('confirmBtn').addEventListener('click', function() {
                modal.style.display = 'none';
                
                // Validar que todos los campos requeridos estén completos
                if (!photoManager.hasPhoto()) {
                    showAlert('Debe tomar una foto de evidencia antes de guardar la auditoría.');
                    document.getElementById('photoSection').classList.add('required');
                    return;
                }
                
                if (comentariosInput.value.trim() === '') {
                    showAlert('Por favor ingrese comentarios antes de guardar la auditoría', comentariosInput);
                    return;
                }
                
                // Crear un input hidden para forzar el envío del formulario
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'confirmed';
                hiddenInput.value = '1';
                form.appendChild(hiddenInput);
                
                // Enviar el formulario
                form.submit();
            });
            
            // Limpiar cámara al salir de la página
            window.addEventListener('beforeunload', () => {
                photoManager.cleanup();
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
