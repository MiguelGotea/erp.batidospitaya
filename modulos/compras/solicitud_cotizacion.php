<?php
require_once '../../core/auth/auth.php';
require_once '../../core/permissions/permissions.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once 'includes/funciones_compras.php';

verificarAutenticacion();

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('solicitud_cotizacion', 'vista', $cargoOperario)) {
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
                $fotoNombre = null;

                // Manejar la foto si se subió
                $fileInputName = 'foto_referencia[' . $producto['index'] . ']';
                if (isset($_FILES['foto_referencia']['name'][$producto['index']]) &&
                !empty($_FILES['foto_referencia']['name'][$producto['index']])) {

                    $fileTmpName = $_FILES['foto_referencia']['tmp_name'][$producto['index']];
                    $fileName = $_FILES['foto_referencia']['name'][$producto['index']];
                    $fileSize = $_FILES['foto_referencia']['size'][$producto['index']];
                    $fileError = $_FILES['foto_referencia']['error'][$producto['index']];

                    // Validar que no haya errores
                    if ($fileError === UPLOAD_ERR_OK) {
                        // Validar tipo de archivo
                        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                        $fileType = mime_content_type($fileTmpName);

                        if (in_array($fileType, $allowedTypes)) {
                            // Validar tamaño (max 5MB)
                            if ($fileSize <= 5 * 1024 * 1024) {
                                // Generar nombre único para la foto
                                $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                                $fotoNombre = 'foto_' . $solicitudId . '_' . $orden . '_' . time() . '.' . $fileExtension;
                                $uploadPath = $uploadDir . $fotoNombre;

                                // Mover la foto
                                if (move_uploaded_file($fileTmpName, $uploadPath)) {
                                    // Redimensionar si es necesario
                                    list($width, $height) = getimagesize($uploadPath);
                                    if ($width > 1024 || $height > 1024) {
                                        redimensionarImagen($uploadPath, $uploadPath, 1024, 1024);
                                    }
                                }
                                else {
                                    $fotoNombre = null;
                                    error_log("Error al mover archivo: " . $uploadPath);
                                }
                            }
                            else {
                                throw new Exception("La foto es demasiado grande (máximo 5MB)");
                            }
                        }
                        else {
                            throw new Exception("Tipo de archivo no permitido. Solo se aceptan imágenes JPG, PNG o GIF");
                        }
                    }
                    else {
                        error_log("Error en subida de archivo: " . $fileError);
                    }
                }

                $stmtProducto = $conn->prepare("
                    INSERT INTO solicitudes_cotizacion_productos 
                    (solicitud_id, producto_descripcion, cantidad, precio_unitario, foto_referencia, orden) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                $stmtProducto->execute([
                    $solicitudId,
                    $producto['descripcion'],
                    $producto['cantidad'],
                    $producto['precio_unitario'],
                    $fotoNombre,
                    $orden
                ]);

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
            header('Location: ver_solicitud_cotizacion.php?id=' . $solicitudId);
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
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_SESSION['success']);
    unset($_SESSION['success']); ?>
            </div>
        <?php
endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($_SESSION['error']);
    unset($_SESSION['error']); ?>
            </div>
        <?php
endif; ?>
        
        <form id="solicitudForm" method="post" enctype="multipart/form-data">
            <div class="form-section">
                <h3>Información de la Solicitud</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="solicitante">Solicitante:</label>
                        <input type="text" id="solicitante" value="<?php echo htmlspecialchars($nombreSolicitante); ?>" 
                               class="readonly-field" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="fecha_solicitud">Fecha:</label>
                        <input type="text" id="fecha_solicitud" value="<?php echo date('d/m/Y'); ?>" 
                               class="readonly-field" readonly>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="observaciones">Observaciones Generales:</label>
                    <textarea id="observaciones" name="observaciones" 
                              placeholder="Observaciones adicionales sobre la solicitud..."></textarea>
                </div>
            </div>
            
            <div class="form-section">
                <h3>Productos a Cotizar</h3>
                
                <table class="productos-table" id="productosTable">
                    <thead>
                        <tr>
                            <th style="width: 35%;">Productos</th>
                            <th style="width: 25%;">Foto de Referencia</th>
                            <th style="width: 15%;">Cantidad</th>
                            <th style="width: 15%;">Precio Unitario (C$)</th>
                            <th style="width: 10%;"></th>
                        </tr>
                    </thead>
                    <tbody id="productosBody">
                        <!-- Fila inicial -->
                        <tr class="producto-row">
                            <td>
                                <input type="text" name="producto_descripcion[]" 
                                       placeholder="Descripción del producto" 
                                       class="producto-desc" required style="width: 100%;">
                            </td>
                            <td>
                                <input type="file" name="foto_referencia[]" 
                                       class="foto-input" accept="image/*" 
                                       onchange="previewFoto(this)">
                                <div class="foto-preview">
                                    <img src="" alt="Vista previa">
                                </div>
                            </td>
                            <td>
                                <input type="number" name="cantidad[]" 
                                       value="1" min="1" class="cantidad" 
                                       style="width: 80px;" required>
                            </td>
                            <td>
                                <input type="number" name="precio_unitario[]" 
                                       value="0.00" min="0" step="0.01" 
                                       class="precio" style="width: 100px;">
                            </td>
                            <td style="text-align: center;">
                                <button type="button" class="btn-remove" onclick="removeRow(this)" disabled>
                                    <i class="fas fa-times"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <button type="button" class="btn-add-row" onclick="addRow()">
                    <i class="fas fa-plus"></i> Agregar Producto
                </button>
            </div>
            
            <div class="actions">
                <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Guardar Solicitud
                </button>
            </div>
        </form>
            </div><!-- /.container-fluid -->
        </div><!-- /.sub-container -->
    </div><!-- /.main-container -->

    <script>
        let rowCounter = 1;
        
        function addRow() {
            const tbody = document.getElementById('productosBody');
            const newRow = document.createElement('tr');
            newRow.className = 'producto-row';
            
            newRow.innerHTML = `
                <td>
                    <input type="text" name="producto_descripcion[]" 
                           placeholder="Descripción del producto" 
                           class="producto-desc" required style="width: 100%;">
                </td>
                <td>
                    <input type="file" name="foto_referencia[]" 
                           class="foto-input" accept="image/*" 
                           onchange="previewFoto(this)">
                    <div class="foto-preview">
                        <img src="" alt="Vista previa">
                    </div>
                </td>
                <td>
                    <input type="number" name="cantidad[]" 
                           value="1" min="1" class="cantidad" 
                           style="width: 80px;" required>
                </td>
                <td>
                    <input type="number" name="precio_unitario[]" 
                           value="0.00" min="0" step="0.01" 
                           class="precio" style="width: 100px;">
                </td>
                <td style="text-align: center;">
                    <button type="button" class="btn-remove" onclick="removeRow(this)">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            `;
            
            tbody.appendChild(newRow);
            rowCounter++;
            updateRemoveButtons();
        }
        
        function removeRow(button) {
            const row = button.closest('.producto-row');
            if (row && document.querySelectorAll('.producto-row').length > 1) {
                row.remove();
                updateRemoveButtons();
            }
        }
        
        function updateRemoveButtons() {
            const rows = document.querySelectorAll('.producto-row');
            const removeButtons = document.querySelectorAll('.btn-remove');
            
            removeButtons.forEach((btn, index) => {
                if (rows.length > 1) {
                    btn.disabled = false;
                    btn.style.opacity = '1';
                    btn.style.cursor = 'pointer';
                } else {
                    btn.disabled = true;
                    btn.style.opacity = '0.5';
                    btn.style.cursor = 'not-allowed';
                }
            });
        }
        
        function previewFoto(input) {
            const previewContainer = input.nextElementSibling;
            const previewImg = previewContainer.querySelector('img');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    previewContainer.style.display = 'block';
                };
                
                reader.readAsDataURL(input.files[0]);
            } else {
                previewContainer.style.display = 'none';
                previewImg.src = '';
            }
        }
        
        // Validar formulario antes de enviar
        document.getElementById('solicitudForm').addEventListener('submit', function(e) {
            // Validar que haya al menos un producto con descripción
            const productosDesc = document.querySelectorAll('.producto-desc');
            let tieneProductos = false;
            
            productosDesc.forEach(input => {
                if (input.value.trim() !== '') {
                    tieneProductos = true;
                }
            });
            
            if (!tieneProductos) {
                e.preventDefault();
                alert('Debe agregar al menos un producto con descripción');
                return false;
            }
            
            // Validar que las cantidades sean mayores a 0
            const cantidades = document.querySelectorAll('.cantidad');
            let cantidadesValidas = true;
            
            cantidades.forEach(input => {
                if (parseInt(input.value) <= 0) {
                    cantidadesValidas = false;
                    input.style.borderColor = 'red';
                } else {
                    input.style.borderColor = '';
                }
            });
            
            if (!cantidadesValidas) {
                e.preventDefault();
                alert('Todas las cantidades deben ser mayores a 0');
                return false;
            }
            
            // Mostrar confirmación
            if (!confirm('¿Está seguro de crear esta solicitud de cotización?')) {
                e.preventDefault();
                return false;
            }
            
            return true;
        });
        
        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            updateRemoveButtons();
        });
    </script>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

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