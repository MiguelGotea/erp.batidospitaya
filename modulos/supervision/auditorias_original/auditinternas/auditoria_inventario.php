<?php
// Incluir configuración y verificar autenticación
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/layout/menu_lateral.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/layout/header_universal.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/permissions/permissions.php';
// Antes llamaba a ../funciones.php de auditora
// require_once 'config.php'; // Comentado por migración al core
require_once '../../../../core/helpers/config.php';

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar permisos — antes de que $conn sea sobreescrito por mysqli
$puede_ver         = tienePermiso('auditoria_efectivo', 'vista',      $cargoOperario);
$puede_nuevo       = tienePermiso('auditoria_efectivo', 'nuevo',      $cargoOperario);
$puede_nuevo_todos = tienePermiso('auditoria_efectivo', 'nuevo_todos', $cargoOperario);
$puede_editar      = tienePermiso('auditoria_efectivo', 'editar',     $cargoOperario);

if (!$puede_ver) {
    header('Location: /index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);

// Registrar el ID del auditor (usuario actual)
$auditor_id = $usuario['CodOperario'] ?? null;

date_default_timezone_set('America/Managua');

try {
    // $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); // Comentado por migración al core
    // Usaremos las constantes que ahora están en conexion.php (vía auth.php)
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); 
    
    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error); 
    }
    
    $conn->set_charset("utf8mb4");
    
    
    // Obtener operarios si ya se seleccionó una sucursal
    $operarios = [];
    if (isset($_GET['sucursal_id']) && is_numeric($_GET['sucursal_id'])) {
        $sucursal_id = $_GET['sucursal_id'];
        // Primero calculamos las fechas de inicio y fin de semana
        $lunesSemana = date('Y-m-d', strtotime('monday this week'));
        $domingoSemana = date('Y-m-d', strtotime('sunday this week'));
        
        $query = "SELECT o.CodOperario, 
                         CONCAT(o.Nombre, ' ', o.Apellido) AS nombre_completo, 
                         nc.Nombre AS cargo, 
                         anc.Fin,
                         nc.Nombre AS categoria,
                         COALESCE(nc.Peso, 0) AS peso_categoria,
                         nc.color AS color_categoria
                  FROM Operarios o
                  JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
                  JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
                  WHERE anc.Sucursal = ?
                  AND o.Operativo = 1
                  AND anc.CodNivelesCargos NOT IN (27)
                  AND (
                      anc.Fin IS NULL 
                      OR anc.Fin >= CURDATE() 
                      OR (anc.Fin BETWEEN ? AND ?)
                  )
                  ORDER BY anc.Fecha DESC, nombre_completo";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iss", $sucursal_id, $lunesSemana, $domingoSemana);
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Procesar resultados eliminando duplicados (quedarse con el más reciente por operario)
        $operarios_unicos = [];
        while ($row = $result->fetch_assoc()) {
            // Si el operario no está en el array, agregarlo (ya viene ordenado por fecha DESC)
            if (!isset($operarios_unicos[$row['CodOperario']])) {
                $operarios_unicos[$row['CodOperario']] = $row;
            }
        }
        
        // Convertir array asociativo a array indexado y ordenar por nombre
        $operarios = array_values($operarios_unicos);
        usort($operarios, function($a, $b) {
            return strcmp($a['nombre_completo'], $b['nombre_completo']);
        });
        
        $stmt->close();
    }
} catch (Exception $e) {
    die("Error al conectar con la base de datos: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$puede_nuevo) {
        header('Location: auditorias_consolidadas.php');
        exit();
    }
    $fecha_hora = date('Y-m-d H:i:s');
    $sucursal_id = $_POST['sucursal'];
    
    // Validar campos de texto
    $error_message = '';
    
    // Si no hay errores de validación, continuar
    if (empty($error_message)) {
        $sucursal_nombre = '';
        $query = "SELECT codigo, nombre FROM sucursales WHERE codigo = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $sucursal_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $sucursal_nombre = $row['nombre'];
        }
        $stmt->close();
        
        $comentarios = $_POST['comentarios'];
        
        $total_faltante = 0;
        foreach ($_POST['total'] as $total) {
            $total_faltante += floatval($total);
        }
        
        // Validar que se hayan capturado ambas fotos
        if (empty($_POST['photoData1'])) {
            $error_message = "Debe capturar la primera foto de evidencia.";
        } elseif (empty($_POST['photoData2'])) {
            $error_message = "Debe capturar la segunda foto de evidencia.";
        } else {
            $conn->begin_transaction();
            
            try {
                $stmt = $conn->prepare("INSERT INTO auditoria_inventario 
                   (fecha_hora, sucursal_id, sucursal, total_faltante, comentarios, auditor_id) 
                   VALUES (?, ?, ?, ?, ?, ?)");
                
                $stmt->bind_param("issdsi", 
                    $fecha_hora,
                    $sucursal_id,
                    $sucursal_nombre,
                    $total_faltante,
                    $comentarios,
                    $auditor_id
                );
                
                $stmt->execute();
                $auditoria_id = $conn->insert_id;
                $stmt->close();
                
                // Insertar los operarios relacionados
                //$operarios_seleccionados = $_POST['operarios'] ?? [];
                //foreach ($operarios_seleccionados as $operario_id) {
                    // Obtener información del operario
                //    $query = "SELECT o.Nombre, o.Apellido, nc.Nombre AS cargo 
                //              FROM Operarios o
                //              JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
                //              JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
                //              WHERE o.CodOperario = ? AND anc.Fin IS NULL";
                //    $stmt = $conn->prepare($query);
                //    $stmt->bind_param("i", $operario_id);
                //    $stmt->execute();
                //    $result = $stmt->get_result();
                    
                //    if ($row = $result->fetch_assoc()) {
                //        $nombre_completo = $row['Nombre'] . ' ' . $row['Apellido'];
                //        $cargo = $row['cargo'];
                        
                //        $stmt_insert = $conn->prepare("INSERT INTO auditoria_inventario_operarios 
                //                                      (auditoria_id, operario_id, operario_nombre, operario_cargo) 
                //                                      VALUES (?, ?, ?, ?)");
                        
                //        $stmt_insert->bind_param("iiss", 
                //            $auditoria_id,
                //            $operario_id,
                //            $nombre_completo,
                //            $cargo
                //        );
                        
                //        $stmt_insert->execute();
                //        $stmt_insert->close();
                //    }
                //    $stmt->close();
                //}
                
                foreach ($_POST['producto'] as $index => $producto) {
                    $stmt = $conn->prepare("INSERT INTO auditoria_inventario_detalle 
                                           (auditoria_id, producto, inventario_sistema, inventario_fisico, 
                                            diferencia, costo_unitario, total) 
                                           VALUES (?, ?, ?, ?, ?, ?, ?)");
                    
                    $stmt->bind_param("isiiidd", 
                        $auditoria_id,
                        $producto,
                        $_POST['inventario_sistema'][$index],
                        $_POST['inventario_fisico'][$index],
                        $_POST['diferencia'][$index],
                        $_POST['costo_unitario'][$index],
                        $_POST['total'][$index]
                    );
                    
                    $stmt->execute();
                    $stmt->close();
                }
                
                // Calcular montos por operario usando pesos de NivelesCargos
                $operarios_seleccionados = $_POST['operarios'] ?? [];
                $pesos_operarios = [];
                $suma_pesos = 0;
                
                // Obtener pesos de los operarios seleccionados desde NivelesCargos
                foreach ($operarios_seleccionados as $operario_id) {
                    // Obtener el cargo más reciente de esta sucursal específica
                    $query = "SELECT COALESCE(nc.Peso, 0) AS peso 
                              FROM AsignacionNivelesCargos anc
                              JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
                              WHERE anc.CodOperario = ?
                              AND anc.Sucursal = ?
                              AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                              ORDER BY anc.Fecha DESC
                              LIMIT 1";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ii", $operario_id, $sucursal_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    $peso = 0;
                    if ($row = $result->fetch_assoc()) {
                        $peso = (float)$row['peso'];
                    }
                    
                    $pesos_operarios[$operario_id] = $peso;
                    $suma_pesos += $peso;
                    $stmt->close();
                }
                
                // Calcular monto por operario
                $monto_base = ($suma_pesos > 0) ? ($total_faltante / $suma_pesos) : 0;
                
                // Insertar los operarios relacionados con sus montos
                foreach ($operarios_seleccionados as $operario_id) {
                    // Obtener información del operario con su cargo más reciente de esta sucursal
                    $query = "SELECT o.Nombre, o.Apellido, nc.Nombre AS cargo, nc.Nombre AS categoria
                              FROM Operarios o
                              JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
                              JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
                              WHERE o.CodOperario = ? 
                              AND anc.Sucursal = ?
                              AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                              ORDER BY anc.Fecha DESC
                              LIMIT 1";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ii", $operario_id, $sucursal_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($row = $result->fetch_assoc()) {
                        $nombre_completo = $row['Nombre'] . ' ' . $row['Apellido'];
                        $cargo = $row['cargo'];
                        $categoria = $row['categoria'];
                        $monto = $pesos_operarios[$operario_id] * $monto_base;
                        
                        // CONSULTA DIRECTA PARA OBTENER EL CÓDIGO DE CONTRATO
                        $cod_contrato = null;
                        $stmt_contrato = $conn->prepare("
                            SELECT CodContrato 
                            FROM Contratos 
                            WHERE cod_operario = ? 
                            ORDER BY inicio_contrato DESC, CodContrato DESC 
                            LIMIT 1
                        ");
                
                        if ($stmt_contrato) {
                            $stmt_contrato->bind_param("i", $operario_id);
                            $stmt_contrato->execute();
                            $result_contrato = $stmt_contrato->get_result();
                            
                            if ($row_contrato = $result_contrato->fetch_assoc()) {
                                $cod_contrato = $row_contrato['CodContrato'];
                                error_log("Contrato encontrado para operario $operario_id: $cod_contrato");
                            } else {
                                error_log("No se encontró contrato en consulta directa para: $operario_id");
                            }
                            $stmt_contrato->close();
                        } else {
                            error_log("Error preparando consulta de contrato: " . $conn->error);
                        }
                        
                        $stmt_insert = $conn->prepare("INSERT INTO auditoria_inventario_operarios 
                                                      (auditoria_id, operario_id, operario_nombre, operario_cargo, operario_categoria, monto, cod_contrato) 
                                                      VALUES (?, ?, ?, ?, ?, ?, ?)");
                        
                        $stmt_insert->bind_param("iisssdi", 
                            $auditoria_id,
                            $operario_id,
                            $nombre_completo,
                            $cargo,
                            $categoria,
                            $monto,
                            $cod_contrato
                        );
                        
                        $stmt_insert->execute();
                        $stmt_insert->close();
                    }
                    $stmt->close();
                }
                
                // Manejo de las fotos
                if (!file_exists('fotos_auditorias_inventario')) {
                    mkdir('fotos_auditorias_inventario', 0777, true);
                }
                
                // Foto 1
                $photoData1 = $_POST['photoData1'];
                $photoData1 = str_replace('data:image/jpeg;base64,', '', $photoData1);
                $photoData1 = str_replace(' ', '+', $photoData1);
                $image1 = base64_decode($photoData1);
                
                $imageName1 = 'auditoria_inventario_' . $auditoria_id . '_1_' . time() . '.jpg';
                $imagePath1 = 'fotos_auditorias_inventario/' . $imageName1;
                file_put_contents($imagePath1, $image1);
                
                // Foto 2
                $photoData2 = $_POST['photoData2'];
                $photoData2 = str_replace('data:image/jpeg;base64,', '', $photoData2);
                $photoData2 = str_replace(' ', '+', $photoData2);
                $image2 = base64_decode($photoData2);
                
                $imageName2 = 'auditoria_inventario_' . $auditoria_id . '_2_' . time() . '.jpg';
                $imagePath2 = 'fotos_auditorias_inventario/' . $imageName2;
                file_put_contents($imagePath2, $image2);
                
                // Actualizar rutas de fotos en la base de datos
                $stmt = $conn->prepare("UPDATE auditoria_inventario SET foto_path = ?, foto_path_2 = ? WHERE id = ?");
                $stmt->bind_param("ssi", $imagePath1, $imagePath2, $auditoria_id);
                $stmt->execute();
                $stmt->close();
                
                $conn->commit();
                $_SESSION['success_message'] = "Auditoría guardada correctamente.";
                header('Location: auditorias_consolidadas.php');
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Error al guardar la auditoría: " . $e->getMessage();
            }
        }
    }
}

// Obtener sucursales (filtradas por supervisor_asignado si no tiene nuevo_todos)
$sucursales = [];
try {
    if ($puede_nuevo_todos) {
        $query = "SELECT codigo, nombre FROM sucursales WHERE activa = 1 AND sucursal=1 ORDER BY nombre";
        $result = $conn->query($query);
    } else {
        $query = "SELECT codigo, nombre FROM sucursales WHERE activa = 1 AND sucursal=1 AND JSON_CONTAINS(supervisor_asignado, CAST(? AS JSON)) ORDER BY nombre";
        $stmt_suc = $conn->prepare($query);
        $stmt_suc->bind_param("i", $_SESSION['usuario_id']);
        $stmt_suc->execute();
        $result = $stmt_suc->get_result();
    }
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $sucursales[] = $row;
        }
    }
} catch (Exception $e) {
    die("Error al obtener sucursales: " . $e->getMessage());
}

$productos = [];
$query = "SELECT id, producto, costo, unidad_medida FROM productos ORDER BY producto";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $productos[] = $row;
    }
}

$productos_json = json_encode($productos);

function obtenerColorCategoria($color_bd) {
    // Si viene color de la base de datos, usarlo
    if (!empty($color_bd) && $color_bd !== '#000000') {
        return $color_bd;
    }
    
    // Fallback a color por defecto
    return '#999999';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Auditoría de Inventario</title>
    <link rel="icon" href="/core/assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="css/auditoria_inventario.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Auditoría de Inventario'); ?>

            <div class="container-fluid p-3">
        <div class="container">
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="message success"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <form id="auditoriaForm" method="post" action="">
            <div class="form-group">
                <label for="fecha_hora">Fecha:</label>
                <input type="text" id="fecha_hora" name="fecha_hora" value="<?php echo formatFechaEspanol(); ?>" readonly>
            </div>
            
            <div class="form-group">
                <label for="sucursal">Sucursal:</label>
                <select id="sucursal" name="sucursal" required>
                    <option value="">Seleccione una sucursal</option>
                    <?php foreach ($sucursales as $sucursal): ?>
                        <option value="<?php echo htmlspecialchars($sucursal['codigo']); ?>">
                            <?php echo htmlspecialchars($sucursal['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Nueva sección para selección de colaboradores -->
            <div class="form-group">
                <label>Colaboradores Relacionados:</label>
                <div id="operariosContainer">
                    <?php if (!empty($operarios)): ?>
                        <div class="operarios-list">
                            <?php foreach ($operarios as $operario): ?>
                                <div class="operario-item">
                                    <label>
                                        <input type="checkbox" name="operarios[]" value="<?php echo $operario['CodOperario']; ?>">
                                        <?php echo htmlspecialchars($operario['nombre_completo']); ?>
                                        <span class="operario-cargo">(<?php echo htmlspecialchars($operario['cargo']); ?>)</span>
                                        <span class="operario-categoria" style="color: <?php echo obtenerColorCategoria($operario['color_categoria'] ?? null); ?>">
                                            [●]
                                        </span>
                                        <?php if (isset($operario['Fin']) && $operario['Fin'] < date('Y-m-d')): ?>
                                            <span class="operario-fin" style="color: red; font-size: 0.8em;">(Terminó el <?php echo date('d/m/Y', strtotime($operario['Fin'])); ?>)</span>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p id="sinOperariosMsg" style="color:#a30202; text-align:center; font-weight:bold; text-decoration: underline; user-select: none;">
                            Seleccione una sucursal para ver la lista de colaboradores
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="form-group">
                <label>Productos:</label>
                <div style="text-align: left;">
                    <button type="button" class="btn add-row" id="addProducto">Agregar Producto</button>
                </div>
                <div class="table-container">
                    <table id="productosTable">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Inv. Sistema</th>
                                <th>Inv. Físico</th>
                                <th>Diferencia</th>
                                <th>Costo Unit.</th>
                                <th>Total</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody id="productosBody">
                            <tr>
                                <td><input type="text" name="producto[]" class="producto-input" required></td>
                                <td><input type="number" name="inventario_sistema[]" class="inventario-sistema" min="0" required></td>
                                <td><input type="number" name="inventario_fisico[]" class="inventario-fisico" min="0" required></td>
                                <td><input type="number" name="diferencia[]" class="diferencia" readonly></td>
                                <td><input type="number" name="costo_unitario[]" class="costo-unitario" step="0.01" required readonly></td>
                                <td><input type="number" name="total[]" class="total" readonly></td>
                                <td><button type="button" class="remove-row"><i class="fas fa-times"></i></button></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="5">Total Faltante:</td>
                                <td id="totalFaltante">0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            <div class="form-group">
                <label for="comentarios">Comentarios del Auditor:</label>
                <textarea id="comentarios" name="comentarios" rows="4" required></textarea>
            </div>
            
            <!-- Nueva sección para las fotos -->
        <div class="form-group">
            <div class="photo-section" id="photoSection">
                <h3>Fotos de Evidencia <span style="color:red;">*</span></h3>
                <!-- Estado de las fotos -->
                <div class="photo-status pending" id="photoStatus">Faltan 2 fotos por capturar: Insumos Importantes y de Mostrador</div>
                
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
                
                <!-- Galería de fotos capturadas -->
                <div id="photoGallery" class="photo-gallery"></div>
                
                <!-- Inputs ocultos para las fotos -->
                <input type="hidden" id="photoData1" name="photoData1" required>
                <input type="hidden" id="photoData2" name="photoData2" required>
            </div>
        </div>
        
        <div class="button-container">
            <?php if ($puede_nuevo): ?>
            <button type="submit" class="btn" id="guardarBtn">Guardar Auditoría</button>
            <?php endif; ?>
            <button type="button" class="btn-cancelar" onclick="window.location.href='auditorias_consolidadas.php'">Cancelar</button>
        </div>
    </form>
    </div><!-- /.container -->
            </div><!-- /.container-fluid -->
        </div><!-- /.sub-container -->
    </div><!-- /.main-container -->

    <!-- Modal para alertas -->
<div class="custom-alert" id="customAlert">
    <div class="alert-content">
        <div id="alertMessage"></div>
        <div class="alert-buttons">
            <button class="alert-btn alert-btn-ok" id="alertOk">Aceptar</button>
        </div>
    </div>
</div>

    <script>
    const productos = <?php echo $productos_json; ?>;
    
    const productosAutocomplete = productos.map(producto => ({
        label: producto.producto + (producto.unidad_medida ? ' (' + producto.unidad_medida + ')' : ''),
        value: producto.producto,
        costo: producto.costo,
        id: producto.id,
        unidad_medida: producto.unidad_medida || ''
    }));
    
    // Función para mostrar alertas personalizadas
    function showAlert(message, isConfirm = false) {
        const alert = document.getElementById('customAlert');
        const messageElement = document.getElementById('alertMessage');
        
        messageElement.innerHTML = message.replace(/\n/g, '<br>');
        alert.style.display = 'flex';
        
        const okButton = document.getElementById('alertOk');
        
        if (isConfirm) {
            okButton.style.display = 'none';
            messageElement.innerHTML += '<br><br><div style="margin-top: 15px; display: flex; justify-content: center; gap: 10px;">' +
                '<button class="alert-btn" onclick="document.getElementById(\'customAlert\').style.display=\'none\'; document.getElementById(\'auditoriaForm\').submit();">Sí</button>' +
                '<button class="alert-btn" onclick="document.getElementById(\'customAlert\').style.display=\'none\';">No</button>' +
                '</div>';
        } else {
            okButton.style.display = 'block';
            okButton.onclick = function() {
                alert.style.display = 'none';
            };
        }
        
        // Cierra al hacer clic fuera del contenido
        alert.onclick = function(e) {
                if (e.target === alert && !isConfirm) {
                    alert.style.display = 'none';
                }
            };
        }
        
        // Clase para manejar las cámaras y fotos
        class PhotoManager {
            constructor() {
                this.stream = null;
                this.capturedPhotos = [];
                this.devices = [];
                this.currentDeviceId = null;
                
                this.videoElement = document.getElementById('videoPreview');
                this.canvasElement = document.getElementById('photoCanvas');
                this.captureBtn = document.getElementById('captureBtn');
                this.retakeBtn = document.getElementById('retakeBtn');
                this.cameraSelector = document.getElementById('cameraSelector');
                this.photoGallery = document.getElementById('photoGallery');
                this.photoStatus = document.getElementById('photoStatus');
                this.photoDataInput1 = document.getElementById('photoData1');
                this.photoDataInput2 = document.getElementById('photoData2');
                
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
                } catch (error) {
                    console.error("Error al iniciar la cámara: ", error);
                    showAlert("No se pudo acceder a la cámara seleccionada.");
                }
            }
            
            capturePhoto() {
                if (this.capturedPhotos.length >= 2) {
                    showAlert("Ya ha capturado el máximo de 2 fotos.");
                    return;
                }
                
                const width = this.videoElement.videoWidth;
                const height = this.videoElement.videoHeight;
                this.canvasElement.width = width;
                this.canvasElement.height = height;
                
                const ctx = this.canvasElement.getContext('2d');
                ctx.drawImage(this.videoElement, 0, 0, width, height);
                
                const photoData = this.canvasElement.toDataURL('image/jpeg', 0.8);
                this.capturedPhotos.push(photoData);
                
                this.updatePhotoInputs();
                this.updateGallery();
                this.updateStatus();
                
                this.retakeBtn.disabled = false;
                
                if (this.capturedPhotos.length >= 2) {
                    this.captureBtn.disabled = true;
                }
            }
            
            retakePhoto() {
                if (this.capturedPhotos.length === 0) return;
                
                this.capturedPhotos.pop();
                this.updatePhotoInputs();
                this.updateGallery();
                this.updateStatus();
                
                this.retakeBtn.disabled = this.capturedPhotos.length === 0;
                this.captureBtn.disabled = false;
            }
            
            removePhoto(index) {
                this.capturedPhotos.splice(index, 1);
                this.updatePhotoInputs();
                this.updateGallery();
                this.updateStatus();
                
                this.retakeBtn.disabled = this.capturedPhotos.length === 0;
                this.captureBtn.disabled = this.capturedPhotos.length >= 2;
            }
            
            updatePhotoInputs() {
                this.photoDataInput1.value = this.capturedPhotos[0] || '';
                this.photoDataInput2.value = this.capturedPhotos[1] || '';
            }
            
            updateGallery() {
                this.photoGallery.innerHTML = '';
                
                this.capturedPhotos.forEach((photo, index) => {
                    const photoContainer = document.createElement('div');
                    photoContainer.className = 'photo-thumbnail';
                    
                    const img = document.createElement('img');
                    img.src = photo;
                    
                    const removeBtn = document.createElement('button');
                    removeBtn.className = 'remove-photo';
                    removeBtn.innerHTML = '×';
                    removeBtn.onclick = (e) => {
                        e.preventDefault();
                        this.removePhoto(index);
                    };
                    
                    photoContainer.appendChild(img);
                    photoContainer.appendChild(removeBtn);
                    this.photoGallery.appendChild(photoContainer);
                });
            }
            
            updateStatus() {
                const remaining = 2 - this.capturedPhotos.length;
                
                if (remaining === 0) {
                    this.photoStatus.textContent = 'Todas las fotos capturadas';
                    this.photoStatus.className = 'photo-status complete';
                    document.getElementById('photoSection').classList.remove('required');
                } else {
                    this.photoStatus.textContent = `Falta ${remaining} foto por capturar: Mostrador`;
                    this.photoStatus.className = 'photo-status pending';
                    
                    if (remaining === 2) {
                        document.getElementById('photoSection').classList.add('required');
                    }
                }
            }
            
            cleanup() {
                if (this.stream) {
                    this.stream.getTracks().forEach(track => track.stop());
                    this.videoElement.srcObject = null;
                    this.stream = null;
                }
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
                // Inicializar el administrador de fotos
                const photoManager = new PhotoManager();
                
                // Limpiar cámara al salir de la página
                window.addEventListener('beforeunload', () => {
                    photoManager.cleanup();
                });
                
                // Función para validar que solo contenga letras y espacios
            function validarSoloTexto(texto) {
                return /^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/.test(texto);
            }
            
            // Agregar fila de producto
            document.getElementById('addProducto').addEventListener('click', function() {
                const tbody = document.getElementById('productosBody');
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><input type="text" name="producto[]" class="producto-input" required></td>
                    <td><input type="number" name="inventario_sistema[]" class="inventario-sistema" min="0" required></td>
                    <td><input type="number" name="inventario_fisico[]" class="inventario-fisico" min="0" required></td>
                    <td><input type="number" name="diferencia[]" class="diferencia" readonly></td>
                    <td><input type="number" name="costo_unitario[]" class="costo-unitario" step="0.01" required readonly></td>
                    <td><input type="number" name="total[]" class="total" readonly></td>
                    <td><button type="button" class="remove-row"><i class="fas fa-times"></i></button></td>
                `;
                tbody.appendChild(tr);
                
                setupAutocomplete(tr);
                addRowEvents(tr);
                
                tr.querySelector('.remove-row').addEventListener('click', function() {
                    if (document.querySelectorAll('#productosBody tr').length > 1) {
                        tbody.removeChild(tr);
                        calcularTotales();
                    } else {
                        showAlert('Debe haber al menos un producto');
                    }
                });
            });
            
            function setupAutocomplete(row) {
                const productoInput = row.querySelector('.producto-input');
                const costoInput = row.querySelector('.costo-unitario');
                
                $(productoInput).autocomplete({
                    source: productosAutocomplete,
                    minLength: 2,
                    select: function(event, ui) {
                        // Mantener el nombre completo con unidad de medida
                        $(this).val(ui.item.label);
                        costoInput.value = ui.item.costo;
                        
                        const fisicoInput = row.querySelector('.inventario-fisico');
                        const sistemaInput = row.querySelector('.inventario-sistema');
                        
                        if (fisicoInput.value && sistemaInput.value) {
                            const event = new Event('input');
                            fisicoInput.dispatchEvent(event);
                        }
                        
                        $(this).removeClass('invalid');
                        return false;
                    }
                });
            }
        
            // Actualizar operarios al cambiar sucursal
            document.getElementById('sucursal').addEventListener('change', function() {
                const sucursalId = this.value;
                if (sucursalId) {
                    // Redirigir con el parámetro de sucursal para recargar la página
                    window.location.href = `auditoria_inventario.php?sucursal_id=${sucursalId}`;
                }
            });
            
            // Si hay una sucursal seleccionada, asegurarse de que esté seleccionada en el dropdown
            const urlParams = new URLSearchParams(window.location.search);
            const sucursalId = urlParams.get('sucursal_id');
            if (sucursalId) {
                document.getElementById('sucursal').value = sucursalId;
            }
        
            function addRowEvents(row) {
                const fisicoInput = row.querySelector('.inventario-fisico');
                const sistemaInput = row.querySelector('.inventario-sistema');
                const diferenciaInput = row.querySelector('.diferencia');
                const costoInput = row.querySelector('.costo-unitario');
                const totalInput = row.querySelector('.total');
                
                function validarNumero(input) {
                    if (parseFloat(input.value) < 0) {
                        input.value = 0;
                        showAlert('No se permiten valores negativos');
                    }
                }
                
                function calcularDiferencia() {
                    validarNumero(fisicoInput);
                    validarNumero(sistemaInput);
                    
                    const fisico = parseFloat(fisicoInput.value) || 0;
                    const sistema = parseFloat(sistemaInput.value) || 0;
                    const diferencia = fisico - sistema;
                    diferenciaInput.value = diferencia;
                    
                    const costo = parseFloat(costoInput.value) || 0;
                    let total = 0;
                    
                    // Solo calculamos total negativo cuando hay faltante (Físico < Sistema)
                    if (fisico < sistema) {
                        total = (sistema - fisico) * costo * -1;
                    }
                    
                    totalInput.value = total.toFixed(2);
                    
                    // Aplicar estilos según la diferencia
                    if (diferencia < 0) {
                        diferenciaInput.classList.add('diferencia-negativa');
                        diferenciaInput.classList.remove('diferencia-positiva');
                    } else if (diferencia > 0) {
                        diferenciaInput.classList.add('diferencia-positiva');
                        diferenciaInput.classList.remove('diferencia-negativa');
                    } else {
                        diferenciaInput.classList.remove('diferencia-negativa', 'diferencia-positiva');
                    }
                    
                    calcularTotales();
                }
                
                fisicoInput.addEventListener('input', calcularDiferencia);
                sistemaInput.addEventListener('input', calcularDiferencia);
                costoInput.addEventListener('input', calcularDiferencia);
                
                // Validación adicional al perder el foco
                fisicoInput.addEventListener('blur', function() {
                    validarNumero(this);
                    calcularDiferencia();
                });
                
                sistemaInput.addEventListener('blur', function() {
                    validarNumero(this);
                    calcularDiferencia();
                });
            }
        
            function calcularTotales() {
                let totalFaltante = 0;
                document.querySelectorAll('.total').forEach(input => {
                    totalFaltante += parseFloat(input.value) || 0;
                });
                
                // Mostrar el valor absoluto (sin signo negativo) aunque el valor sea negativo
                document.getElementById('totalFaltante').textContent = Math.abs(totalFaltante).toFixed(2);
            }
            
            // Validación en tiempo real para participantes existentes
            document.addEventListener('input', function(e) {
                if (e.target.name === 'participantes[]') {
                    if (!validarSoloTexto(e.target.value)) {
                        e.target.classList.add('error-input');
                    } else {
                        e.target.classList.remove('error-input');
                    }
                }
            });
        
            // Configurar autocompletado y eventos para las filas iniciales
            document.querySelectorAll('#productosBody tr').forEach(row => {
                setupAutocomplete(row);
                addRowEvents(row);
                
                row.querySelector('.remove-row').addEventListener('click', function() {
                    if (document.querySelectorAll('#productosBody tr').length > 1) {
                        document.getElementById('productosBody').removeChild(row);
                        calcularTotales();
                    } else {
                        showAlert('Debe haber al menos un producto');
                    }
                });
            });
        
            // Validación de productos al perder el foco
            document.addEventListener('input', function(e) {
                if (e.target.classList.contains('producto-input')) {
                    if (e.target.value.trim() === '') {
                        e.target.classList.add('invalid');
                    } else {
                        e.target.classList.remove('invalid');
                    }
                }
            });
            
            document.getElementById('auditoriaForm').addEventListener('submit', function(e) {
                let valid = true;
                let errorMessage = '';
                
                // Validar productos
                const productoInputs = document.querySelectorAll('.producto-input');
                productoInputs.forEach(input => {
                    if (input.value.trim() === '') {
                        input.classList.add('invalid');
                        valid = false;
                    } else {
                        input.classList.remove('invalid');
                    }
                });
                
                if (document.getElementById('sucursal').value === '') {
                    document.getElementById('sucursal').classList.add('invalid');
                    valid = false;
                } else {
                    document.getElementById('sucursal').classList.remove('invalid');
                }
                
                // Validar fotos
                if (document.getElementById('photoData1').value === '' || 
                    document.getElementById('photoData2').value === '') {
                    document.getElementById('photoSection').classList.add('required');
                    valid = false;
                    errorMessage += 'Debe capturar 2 fotos de evidencia.\n';
                } else {
                    document.getElementById('photoSection').classList.remove('required');
                }
                
                if (!valid) {
                    e.preventDefault();
                    if (errorMessage) {
                        showAlert(errorMessage);
                    } else {
                        showAlert('Por favor complete todos los campos requeridos antes de guardar.');
                    }
                    return;
                }
                
                e.preventDefault();
                showAlert('¿Está seguro que desea guardar esta auditoría de inventario?', true);
            });
        });
</script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
