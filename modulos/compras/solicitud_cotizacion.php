<?php
require_once '../../core/auth/auth.php';
require_once '../../core/permissions/permissions.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once 'includes/funciones_compras.php';

verificarAutenticacion();

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('historial_solicitudes_cotizacion', 'boton_nueva', $cargoOperario)) {
    header('Location: /index.php');
    exit();
}

// Obtener información del usuario actual
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Obtener el nombre completo del usuario
$nombreSolicitante = $esAdmin ? 
    $usuario['nombre'] :
    trim($usuario['Nombre'] . ' ' . $usuario['Apellido']);
$solicitanteId = $_SESSION['usuario_id'];

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $observaciones = trim($_POST['observaciones'] ?? '');

    // Obtener productos del formulario
    $productos = [];
    if (isset($_POST['producto_descripcion']) && is_array($_POST['producto_descripcion'])) {
        foreach ($_POST['producto_descripcion'] as $index => $descripcion) {
            if (!empty(trim($descripcion))) {
                $productos[] = [
                    'descripcion' => trim($descripcion),
                    'cantidad' => intval($_POST['cantidad'][$index] ?? 1),
                    'precio_unitario' => floatval($_POST['precio_unitario'][$index] ?? 0),
                    'index' => $index
                ];
            }
        }
    }

    // Validar que haya al menos un producto
    if (empty($productos)) {
        $_SESSION['error'] = 'Debe agregar al menos un producto';
    }
    else {
        try {
            $conn->beginTransaction();

            // Generar código único para la solicitud (SC-YYYY-MM-NNN)
            $fechaActual = date('Y-m-d');
            $yearMonth = date('Ym');

            // Obtener el último número de secuencia para este mes
            $stmt = $conn->prepare("
                SELECT MAX(SUBSTRING_INDEX(codigo, '-', -1)) as ultimo_num 
                FROM solicitudes_cotizacion 
                WHERE codigo LIKE ?
            ");
            $likePattern = "SC-" . $yearMonth . "-%";
            $stmt->execute([$likePattern]);
            $result = $stmt->fetch();

            $ultimoNum = $result['ultimo_num'] ?? 0;
            $nuevoNum = intval($ultimoNum) + 1;
            $codigoSolicitud = "SC-" . $yearMonth . "-" . str_pad($nuevoNum, 3, '0', STR_PAD_LEFT);

            // Insertar la solicitud principal
            $stmt = $conn->prepare("
                INSERT INTO solicitudes_cotizacion 
                (codigo, version, solicitante_id, solicitante_nombre, fecha_solicitud, observaciones) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $codigoSolicitud,
                1,
                $solicitanteId,
                $nombreSolicitante,
                $fechaActual,
                $observaciones
            ]);

            $solicitudId = $conn->lastInsertId();

            // Crear directorio para fotos si no existe
            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/modulos/compras/uploads/cotizaciones/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Insertar los productos
            $orden = 1;
            foreach ($productos as $producto) {
                // Insertar el producto primero para obtener su ID
                $stmtProducto = $conn->prepare("
                    INSERT INTO solicitudes_cotizacion_productos 
                    (solicitud_id, producto_descripcion, cantidad, precio_unitario, orden) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                $stmtProducto->execute([
                    $solicitudId,
                    $producto['descripcion'],
                    $producto['cantidad'],
                    $producto['precio_unitario'],
                    $orden
                ]);
                
                $productoId = $conn->lastInsertId();
                
                // Manejar las fotos (Múltiples)
                if (isset($_FILES['foto_referencia']['name'][$producto['index']]) &&
                    is_array($_FILES['foto_referencia']['name'][$producto['index']])) {
                    
                    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/modulos/compras/uploads/cotizaciones/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    $totalFotos = count($_FILES['foto_referencia']['name'][$producto['index']]);
                    
                    for ($i = 0; $i < $totalFotos; $i++) {
                        if ($_FILES['foto_referencia']['error'][$producto['index']][$i] === UPLOAD_ERR_OK) {
                            $fileTmpName = $_FILES['foto_referencia']['tmp_name'][$producto['index']][$i];
                            $fileName = $_FILES['foto_referencia']['name'][$producto['index']][$i];
                            $fileSize = $_FILES['foto_referencia']['size'][$producto['index']][$i];
                            
                            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                            $fileType = mime_content_type($fileTmpName);
                            
                            if (in_array($fileType, $allowedTypes) && $fileSize <= 5 * 1024 * 1024) {
                                $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                                $fotoNombre = 'foto_' . $solicitudId . '_' . $productoId . '_' . $i . '_' . time() . '.' . $fileExtension;
                                $uploadPath = $uploadDir . $fotoNombre;
                                
                                if (move_uploaded_file($fileTmpName, $uploadPath)) {
                                    // Redimensionar
                                    list($width, $height) = getimagesize($uploadPath);
                                    if ($width > 1024 || $height > 1024) {
                                        redimensionarImagen($uploadPath, $uploadPath, 1024, 1024);
                                    }
                                    
                                    // Insertar en la tabla de fotos (Opción B)
                                    $stmtFoto = $conn->prepare("
                                        INSERT INTO solicitudes_cotizacion_fotos (producto_id, foto_nombre) 
                                        VALUES (?, ?)
                                    ");
                                    $stmtFoto->execute([$productoId, $fotoNombre]);
                                }
                            }
                        }
                    }
                }
                
                $orden++;
            }

            // Registrar en el historial
            $stmtHistorial = $conn->prepare("
                INSERT INTO solicitudes_cotizacion_historial 
                (solicitud_id, usuario_id, usuario_nombre, accion, detalles) 
                VALUES (?, ?, ?, ?, ?)
            ");

            $detallesHistorial = json_encode([
                'productos' => count($productos),
                'observaciones' => $observaciones
            ]);

            $stmtHistorial->execute([
                $solicitudId,
                $solicitanteId,
                $nombreSolicitante,
                'creada',
                $detallesHistorial
            ]);

            $conn->commit();

            $_SESSION['success'] = 'Solicitud de cotización creada exitosamente: ' . $codigoSolicitud;
            header('Location: historial_solicitudes_cotizacion.php');
            exit();

        }
        catch (Exception $e) {
            $conn->rollBack();
            $_SESSION['error'] = 'Error al crear la solicitud: ' . $e->getMessage();
        }
    }
}

// Función para redimensionar imágenes
function redimensionarImagen($origen, $destino, $anchoMax, $altoMax)
{
    list($ancho, $alto, $tipo) = getimagesize($origen);

    // Calcular nuevas dimensiones manteniendo proporción
    $ratio = $ancho / $alto;
    if ($ancho > $alto) {
        $nuevoAncho = $anchoMax;
        $nuevoAlto = $anchoMax / $ratio;
    }
    else {
        $nuevoAlto = $altoMax;
        $nuevoAncho = $altoMax * $ratio;
    }

    // Crear imagen según el tipo
    switch ($tipo) {
        case IMAGETYPE_JPEG:
            $imagen = imagecreatefromjpeg($origen);
            break;
        case IMAGETYPE_PNG:
            $imagen = imagecreatefrompng($origen);
            break;
        case IMAGETYPE_GIF:
            $imagen = imagecreatefromgif($origen);
            break;
        default:
            return false;
    }

    // Crear nueva imagen
    $nuevaImagen = imagecreatetruecolor($nuevoAncho, $nuevoAlto);

    // Preservar transparencia para PNG y GIF
    if ($tipo == IMAGETYPE_PNG || $tipo == IMAGETYPE_GIF) {
        imagecolortransparent($nuevaImagen, imagecolorallocatealpha($nuevaImagen, 0, 0, 0, 127));
        imagealphablending($nuevaImagen, false);
        imagesavealpha($nuevaImagen, true);
    }

    // Redimensionar
    imagecopyresampled($nuevaImagen, $imagen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

    // Guardar según el tipo
    switch ($tipo) {
        case IMAGETYPE_JPEG:
            imagejpeg($nuevaImagen, $destino, 85);
            break;
        case IMAGETYPE_PNG:
            imagepng($nuevaImagen, $destino, 9);
            break;
        case IMAGETYPE_GIF:
            imagegif($nuevaImagen, $destino);
            break;
    }

    // Liberar memoria
    imagedestroy($imagen);
    imagedestroy($nuevaImagen);

    return true;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de Cotización</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/solicitud_cotizacion.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    
    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Solicitud de Cotización'); ?>
            
            <div class="container-fluid p-3">
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form id="solicitudForm" method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                    
                    <!-- Card: Información General -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="fas fa-file-invoice"></i> Información de la Solicitud
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control bg-light" id="solicitante" 
                                               value="<?php echo htmlspecialchars($nombreSolicitante); ?>" readonly>
                                        <label for="solicitante"><i class="fas fa-user me-2"></i>Solicitante</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control bg-light" id="fecha_solicitud" 
                                               value="<?php echo date('d/m/Y'); ?>" readonly>
                                        <label for="fecha_solicitud"><i class="fas fa-calendar-alt me-2"></i>Fecha</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control bg-light" id="codigo_previo" 
                                               value="SC-<?php echo date('Ym'); ?>-XXX" readonly>
                                        <label for="codigo_previo"><i class="fas fa-hashtag me-2"></i>Código (Auto)</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-floating">
                                        <textarea class="form-control" id="observaciones" name="observaciones" 
                                                  placeholder="Observaciones generales..." style="height: 100px"></textarea>
                                        <label for="observaciones"><i class="fas fa-comment-alt me-2"></i>Observaciones Generales</label>
                                    </div>
                                    <div class="form-text mt-2 text-muted">
                                        Informa sobre detalles globales de la cotización que no correspondan a un producto específico.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card: Productos -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-boxes"></i> Productos a Cotizar
                            </h5>
                            <button type="button" class="btn btn-pitaya-secondary btn-sm" onclick="addRow()">
                                <i class="fas fa-plus me-1"></i> Agregar Producto
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table productos-table" id="productosTable">
                                    <thead>
                                        <tr>
                                            <th style="width: 40%;">Descripción del Producto</th>
                                            <th style="width: 25%;">Foto de Referencia</th>
                                            <th style="width: 15%;">Cantidad</th>
                                            <th style="width: 15%;">Precio Est. (C$)</th>
                                            <th style="width: 5%;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="productosBody">
                                        <!-- Fila modelo (index 0) -->
                                        <tr class="producto-row">
                                            <td data-label="Descripción">
                                                <input type="text" name="producto_descripcion[]" 
                                                       class="form-control form-control-sm producto-desc" 
                                                       placeholder="Ej: Impresora Epson L3210" required>
                                            </td>
                                            <td data-label="Fotos">
                                                <div class="foto-manager">
                                                    <div class="foto-actions">
                                                        <button type="button" class="btn-foto-action" onclick="triggerFileInput(this, 'file')">
                                                            <i class="fas fa-file-image"></i> Archivos
                                                        </button>
                                                        <button type="button" class="btn-foto-action" onclick="triggerFileInput(this, 'camera')">
                                                            <i class="fas fa-camera"></i> Cámara
                                                        </button>
                                                    </div>
                                                    
                                                    <!-- Inputs ocultos para archivos -->
                                                    <!-- Input centralizado (con name) para enviar todos los archivos de la fila -->
                                                    <input type="file" name="foto_referencia[0][]" 
                                                           class="foto-input d-none" 
                                                           accept="image/*" multiple onchange="handleFotosSelect(this)">
                                                    
                                                    <!-- Input auxiliar para cámara (sin name para evitar duplicados en el envío) -->
                                                    <input type="file" 
                                                           class="camera-input d-none" 
                                                           accept="image/*" capture="environment" onchange="handleFotosSelect(this)">
                                                    
                                                    <div class="galeria-wrapper">
                                                        <!-- Miniaturas se generan aquí via JS -->
                                                    </div>
                                                </div>
                                            </td>
                                            <td data-label="Cantidad">
                                                <input type="number" name="cantidad[]" 
                                                       value="1" min="1" class="form-control form-control-sm cantidad text-center" required>
                                            </td>
                                            <td data-label="Precio Unitario">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">C$</span>
                                                    <input type="number" name="precio_unitario[]" 
                                                           value="0.00" min="0" step="0.01" class="form-control precio text-end">
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn-remove" onclick="removeRow(this)" disabled>
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="card border-0 bg-transparent shadow-none">
                        <div class="card-body p-0">
                            <div class="text-end">
                                <button type="submit" class="btn btn-pitaya-primary shadow">
                                    <i class="fas fa-paper-plane me-2"></i> Enviar Solicitud de Cotización
                                </button>
                            </div>
                        </div>
                    </div>

                </form>
            </div><!-- /.container-fluid -->
        </div><!-- /.sub-container -->
    </div><!-- /.main-container -->

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        let rowCounter = 1;
        // Objeto para almacenar los DataTransfer de cada fila (gestiona la acumulación de archivos)
        const rowDataTransfers = {};
        
        // Inicializar el DataTransfer para la fila 0
        rowDataTransfers[0] = new DataTransfer();
        
        function addRow() {
            const tbody = document.getElementById('productosBody');
            const newRow = document.createElement('tr');
            newRow.className = 'producto-row';
            const rowIndex = document.querySelectorAll('.producto-row').length;
            
            newRow.innerHTML = `
                <td data-label="Descripción">
                    <input type="text" name="producto_descripcion[]" 
                           class="form-control form-control-sm producto-desc" 
                           placeholder="Ej: Impresora Epson L3210" required>
                </td>
                <td data-label="Fotos">
                    <div class="foto-manager">
                        <div class="foto-actions">
                            <button type="button" class="btn-foto-action" onclick="triggerFileInput(this, 'file')">
                                <i class="fas fa-file-image"></i> Archivos
                            </button>
                            <button type="button" class="btn-foto-action" onclick="triggerFileInput(this, 'camera')">
                                <i class="fas fa-camera"></i> Cámara
                            </button>
                        </div>
                        
                        <!-- Input centralizado -->
                        <input type="file" name="foto_referencia[${rowIndex}][]" 
                               class="foto-input d-none" 
                               accept="image/*" multiple onchange="handleFotosSelect(this)">
                        
                        <!-- Input auxiliar para cámara -->
                        <input type="file" 
                               class="camera-input d-none" 
                               accept="image/*" capture="environment" onchange="handleFotosSelect(this)">
                        
                        <div class="galeria-wrapper"></div>
                    </div>
                </td>
                <td data-label="Cantidad">
                    <input type="number" name="cantidad[]" 
                           value="1" min="1" class="form-control form-control-sm cantidad text-center" required>
                </td>
                <td data-label="Precio Unitario">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">C$</span>
                        <input type="number" name="precio_unitario[]" 
                               value="0.00" min="0" step="0.01" class="form-control precio text-end">
                    </div>
                </td>
                <td class="text-center">
                    <button type="button" class="btn-remove" onclick="removeRow(this)">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            `;
            
            tbody.appendChild(newRow);
            
            // Inicializar acumulador de archivos para la nueva fila
            rowDataTransfers[rowIndex] = new DataTransfer();
            
            rowCounter++;
            updateRemoveButtons();
        }
        
        function removeRow(button) {
            const row = button.closest('.producto-row');
            if (row && document.querySelectorAll('.producto-row').length > 1) {
                // Obtener el índice real de la fila desde los nombres de los inputs
                const input = row.querySelector('.foto-input');
                const match = input.name.match(/\[(\d+)\]/);
                if (match) delete rowDataTransfers[match[1]];
                
                row.remove();
                updateRemoveButtons();
            }
        }
        
        function updateRemoveButtons() {
            const rows = document.querySelectorAll('.producto-row');
            const removeButtons = document.querySelectorAll('.btn-remove');
            
            removeButtons.forEach((btn, index) => {
                btn.disabled = rows.length <= 1;
            });
        }
        
        function triggerFileInput(btn, type) {
            const container = btn.closest('.foto-manager');
            const input = type === 'file' ? 
                container.querySelector('.foto-input') : 
                container.querySelector('.camera-input');
            input.click();
        }

        function handleFotosSelect(input) {
            const manager = input.closest('.foto-manager');
            const galeria = manager.querySelector('.galeria-wrapper');
            const rowIndexMatch = input.name.match(/\[(\d+)\]/);
            if (!rowIndexMatch) return;
            
            const rowIndex = rowIndexMatch[1];
            if (!rowDataTransfers[rowIndex]) rowDataTransfers[rowIndex] = new DataTransfer();
            
            const dt = rowDataTransfers[rowIndex];
            
            if (input.files && input.files.length > 0) {
                Array.from(input.files).forEach((file) => {
                    if (!file.type.startsWith('image/')) return;
                    
                    // Solo agregar si no existe ya un archivo con el mismo nombre y tamaño (evitar duplicados simples)
                    const seaDuplicado = Array.from(dt.files).some(f => f.name === file.name && f.size === file.size);
                    if (!seaDuplicado) {
                        dt.items.add(file);
                        
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const div = document.createElement('div');
                            div.className = 'foto-item';
                            div.dataset.fileName = file.name;
                            div.dataset.fileSize = file.size;
                            div.innerHTML = `
                                <img src="${e.target.result}" alt="Preview">
                                <span class="btn-remove-foto" onclick="removeFotoThumb(this, ${rowIndex})" title="Quitar">
                                    <i class="fas fa-times"></i>
                                </span>
                            `;
                            galeria.appendChild(div);
                        };
                        reader.readAsDataURL(file);
                    }
                });
                
                // Sincronizar el input oficial (con name) con el acumulador central
                manager.querySelector('.foto-input').files = dt.files;
            }
        }

        function removeFotoThumb(btn, rowIndex) {
            const item = btn.closest('.foto-item');
            const fileName = item.dataset.fileName;
            const fileSize = parseInt(item.dataset.fileSize);
            const manager = item.closest('.foto-manager');
            const fileInput = manager.querySelector('.foto-input');
            const cameraInput = manager.querySelector('.camera-input');
            
            const dt = rowDataTransfers[rowIndex];
            if (!dt) return;

            // Crear un nuevo DataTransfer para filtrar el archivo eliminado
            const newDt = new DataTransfer();
            Array.from(dt.files).forEach(file => {
                if (file.name !== fileName || file.size !== fileSize) {
                    newDt.items.add(file);
                }
            });

            // Actualizar el acumulador global y el input oficial
            rowDataTransfers[rowIndex] = newDt;
            fileInput.files = newDt.files;
            
            item.remove();
        }

        // Inicialización y Validación
        document.addEventListener('DOMContentLoaded', function() {
            updateRemoveButtons();

            const form = document.getElementById('solicitudForm');
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                    Swal.fire({
                        icon: 'error',
                        title: 'Formulario Incompleto',
                        text: 'Por favor, complete todos los campos requeridos correctamente.',
                        confirmButtonColor: '#0E544C'
                    });
                } else {
                    // Validación personalizada de productos
                    const productosDesc = document.querySelectorAll('.producto-desc');
                    let tieneProductos = false;
                    productosDesc.forEach(input => {
                        if (input.value.trim() !== '') {
                            tieneProductos = true;
                        }
                    });

                    if (!tieneProductos) {
                        event.preventDefault();
                        Swal.fire({
                            icon: 'warning',
                            title: 'Sin productos',
                            text: 'Debe agregar al menos un producto con descripción.',
                            confirmButtonColor: '#0E544C'
                        });
                        return;
                    }

                    // Confirmación final
                    event.preventDefault();
                    Swal.fire({
                        title: '¿Confirmar Envío?',
                        text: "¿Desea crear esta solicitud de cotización ahora?",
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#0E544C',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Sí, enviar',
                        cancelButtonText: 'Revisar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            form.submit();
                        }
                    });
                }
                form.classList.add('was-validated');
            }, false);
        });
    </script>

    <!-- Modal de Ayuda -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1" 
         aria-labelledby="pageHelpModalLabel" aria-hidden="true" 
         data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="pageHelpModalLabel">
                        <i class="fas fa-info-circle me-2"></i>
                        Guía de Solicitud de Cotización
                    </h5>
                    <button type="button" class="btn-close btn-close-white" 
                            data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-primary border-bottom pb-2 fw-bold">
                                        <i class="fas fa-plus-circle me-2"></i> Crear Solicitud
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Complete la información general y agregue los productos que desea cotizar. Puede incluir observaciones generales para todo el documento.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-success border-bottom pb-2 fw-bold">
                                        <i class="fas fa-boxes me-2"></i> Productos y Fotos
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Para cada producto, indique la cantidad y opcionalmente el precio unitario estimado. Es <strong>altamente recomendable</strong> subir una foto de referencia para cada ítem.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-warning border-bottom pb-2 fw-bold">
                                        <i class="fas fa-exclamation-triangle me-2"></i> Límites de Archivo
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Las fotos de referencia deben estar en formato JPG, PNG o GIF y no superar los <strong>5MB</strong> por archivo.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-info border-bottom pb-2 fw-bold">
                                        <i class="fas fa-history me-2"></i> Seguimiento
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Una vez guardada, la solicitud pasará a estado <strong>Pendiente</strong> y deberá ser aprobada por Gerencia antes de ser procesada por el departamento de Compras.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>