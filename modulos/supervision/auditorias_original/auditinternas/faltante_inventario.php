<?php
// Incluir configuración y verificar autenticación
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/layout/menu_lateral.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/layout/header_universal.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/permissions/permissions.php';
// Antes llamaba a ../funciones.php de auditora
// require_once 'config.php'; // Comentado por migración al core

// Verificar acceso al módulo 'supervision'
//verificarAccesoModulo('supervision');

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

date_default_timezone_set('America/Managua');

try {
    // $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); // Comentado por migración al core
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
    // Verificar y crear tablas si no existen
    $check_tables = $conn->query("SHOW TABLES LIKE 'faltante_inventario'");
    if ($check_tables->num_rows == 0) {
        $conn->query("CREATE TABLE `faltante_inventario` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `fecha` datetime NOT NULL,
            `sucursal_id` int(11) NOT NULL,
            `sucursal` varchar(50) NOT NULL,
            `total_faltante` decimal(10,2) DEFAULT 0.00,
            `comentarios` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        $conn->query("CREATE TABLE `faltante_inventario_detalle` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `faltante_id` int(11) NOT NULL,
            `producto` varchar(255) NOT NULL,
            `cantidad` int(11) NOT NULL,
            `costo_unitario` decimal(10,2) NOT NULL,
            `total` decimal(10,2) NOT NULL,
            PRIMARY KEY (`id`),
            KEY `faltante_id` (`faltante_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Crear tabla para relacionar faltantes con operarios
        $conn->query("CREATE TABLE `faltante_inventario_operarios` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `faltante_id` int(11) NOT NULL,
            `operario_id` int(11) NOT NULL,
            `operario_nombre` varchar(100) NOT NULL,
            `operario_cargo` varchar(100) NOT NULL,
            `operario_categoria` varchar(50) DEFAULT NULL,
            `monto` decimal(10,2) DEFAULT 0.00,
            PRIMARY KEY (`id`),
            KEY `faltante_id` (`faltante_id`),
            KEY `operario_id` (`operario_id`),
            CONSTRAINT `fk_faltante_operario` FOREIGN KEY (`operario_id`) REFERENCES `Operarios` (`CodOperario`),
            CONSTRAINT `fk_faltante_operario_faltante` FOREIGN KEY (`faltante_id`) REFERENCES `faltante_inventario` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
} catch (Exception $e) {
    die("Error al conectar con la base de datos: " . $e->getMessage());
}

// Obtener operarios si ya se seleccionó una sucursal
$operarios = [];
if (isset($_GET['sucursal_id']) && is_numeric($_GET['sucursal_id'])) {
    $sucursal_id = $_GET['sucursal_id'];
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

// Procesar formulario si se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$puede_nuevo) {
        header('Location: auditorias_consolidadas.php');
        exit();
    }
    $fecha = date('Y-m-d H:i:s');
    $sucursal_id = $_POST['sucursal_id'];
    $registrador_id = $_SESSION['usuario_id']; // Nuevo: ID del usuario que registra
    
    $sucursal_nombre = '';
    $query = "SELECT codigo, nombre FROM sucursales WHERE codigo = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $sucursal_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $sucursal_nombre = $row['nombre'];
    } else {
        die("Error: La sucursal seleccionada no existe en la base de datos");
    }
    $stmt->close();
    
    $comentarios = !empty($_POST['comentarios']) ? $_POST['comentarios'] : null;
    
    $total_faltante = 0;
    foreach ($_POST['productos'] as $producto) {
        $total_faltante += floatval($producto['cantidad']) * floatval($producto['costo_unitario']);
    }
    
    $conn->begin_transaction();
    
    try {
        $stmt = $conn->prepare("INSERT INTO faltante_inventario 
                               (fecha, sucursal_id, sucursal, total_faltante, comentarios, registrador_id) 
                               VALUES (?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("sisdsi", 
            $fecha,
            $sucursal_id,
            $sucursal_nombre,
            $total_faltante,
            $comentarios,
            $registrador_id
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error al guardar el faltante: " . $stmt->error);
        }
        
        $faltante_id = $conn->insert_id;
        $stmt->close();
        
        // Insertar los operarios relacionados (sin montos)
        //$operarios_seleccionados = $_POST['operarios'] ?? [];
        //foreach ($operarios_seleccionados as $operario_id) {
        //    // Obtener información del operario
        //    $query = "SELECT o.Nombre, o.Apellido, nc.Nombre AS cargo 
        //              FROM Operarios o
        //              JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
        //              JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
        //              WHERE o.CodOperario = ? AND anc.Fin IS NULL";
        //    $stmt = $conn->prepare($query);
        //    $stmt->bind_param("i", $operario_id);
        //    $stmt->execute();
        //    $result = $stmt->get_result();
        //    
        //    if ($row = $result->fetch_assoc()) {
        //        $nombre_completo = $row['Nombre'] . ' ' . $row['Apellido'];
        //        $cargo = $row['cargo'];
        //        
        //        $stmt_insert = $conn->prepare("INSERT INTO faltante_inventario_operarios 
        //                                      (faltante_id, operario_id, operario_nombre, operario_cargo) 
        //                                      VALUES (?, ?, ?, ?)");
        //        
        //        $stmt_insert->bind_param("iiss", 
        //            $faltante_id,
        //            $operario_id,
        //            $nombre_completo,
        //            $cargo
        //        );
        //        
        //        $stmt_insert->execute();
        //        $stmt_insert->close();
        //    }
        //    $stmt->close();
        //}
        
        foreach ($_POST['productos'] as $producto) {
            $stmt = $conn->prepare("INSERT INTO faltante_inventario_detalle 
                                   (faltante_id, producto, cantidad, costo_unitario, total) 
                                   VALUES (?, ?, ?, ?, ?)");
            
            $total = floatval($producto['cantidad']) * floatval($producto['costo_unitario']);
            $stmt->bind_param("isidd", 
                $faltante_id,
                $producto['nombre'],
                $producto['cantidad'],
                $producto['costo_unitario'],
                $total
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Error al guardar el detalle: " . $stmt->error);
            }
            
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
        
        // Insertar los operarios relacionados con sus montos (MANTENER ESTA INSERCIÓN)
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
                
                // Obtener código de contrato del operario involucrado - CONSULTA DIRECTA
                $cod_contrato_operario = null;
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
                        $cod_contrato_operario = $row_contrato['CodContrato'];
                        error_log("Contrato encontrado directamente: " . $cod_contrato_operario);
                    } else {
                        error_log("No se encontró contrato en consulta directa para: " . $operario_id);
                    }
                    $stmt_contrato->close();
                } else {
                    error_log("Error preparando consulta de contrato: " . $conn->error);
                }
                
                $stmt_insert = $conn->prepare("INSERT INTO faltante_inventario_operarios 
                                              (faltante_id, operario_id, operario_nombre, operario_cargo, operario_categoria, monto, cod_contrato) 
                                              VALUES (?, ?, ?, ?, ?, ?, ?)");
                
                $stmt_insert->bind_param("iisssdi", 
                    $faltante_id,
                    $operario_id,
                    $nombre_completo,
                    $cargo,
                    $categoria,
                    $monto,
                    $cod_contrato_operario
                );
                
                $stmt_insert->execute();
                $stmt_insert->close();
            }
            $stmt->close();
        }
        
        $conn->commit();
        $_SESSION['success_message'] = "Registro de faltante guardado correctamente.";
        header('Location: auditorias_consolidadas.php');
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error al guardar el registro: " . $e->getMessage();
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

// Obtener la sucursal seleccionada (si existe)
$sucursal_seleccionada = isset($_GET['sucursal_id']) ? $_GET['sucursal_id'] : (isset($_POST['sucursal_id']) ? $_POST['sucursal_id'] : '');

// Obtener productos para autocompletar
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
    <title>Registro de Faltante de Inventario</title>
    <link rel="icon" href="/core/assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="css/faltante_inventario.css?v=<?php echo mt_rand(1, 10000); ?>">


</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Registro de Faltante de Inventario'); ?>

            <div class="container-fluid p-3">
        <div class="container">
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="message success"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <form id="faltanteForm" method="post" action="">
            <div class="form-group">
                <label for="fecha">Fecha:</label>
                <input type="text" id="fecha" name="fecha" value="<?php echo formatFechaEspanol(); ?>" readonly>
            </div>
            
            <div class="form-group">
                <label for="sucursal_id">Sucursal:</label>
                <select id="sucursal_id" name="sucursal_id" required>
                    <option value="">Seleccione una sucursal</option>
                    <?php foreach ($sucursales as $sucursal): ?>
                        <option value="<?php echo htmlspecialchars($sucursal['codigo']); ?>" 
                            <?php echo ($sucursal['codigo'] == $sucursal_seleccionada) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sucursal['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="sucursal" id="sucursal">
            </div>
            
            <!-- Sección para selección de colaboradores -->
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
                <label>Productos Faltantes:</label>
                <div class="add-row-container">
                    <button type="button" class="btn add-row" id="addProducto">Agregar Producto</button>
                </div>
                <table id="productosTable">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Costo Unit.</th>
                            <th>Total</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody id="productosBody">
                        <tr>
                            <td><input type="text" name="productos[0][nombre]" class="producto-input" required></td>
                            <td><input type="number" name="productos[0][cantidad]" class="cantidad" min="1" step="1" required></td>
                            <td><input type="number" name="productos[0][costo_unitario]" class="costo-unitario" min="0" step="0.01" required></td>
                            <td><input type="number" name="productos[0][total]" class="total" readonly></td>
                            <td><button type="button" class="remove-row"><i class="fas fa-times"></i></button></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="3">Total Faltante:</td>
                            <td id="totalFaltante">0.00</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <div class="form-group">
                <label for="comentarios">Comentarios:</label>
                <textarea id="comentarios" name="comentarios" rows="4" required></textarea>
            </div>
            
            <div class="button-container">
                <?php if ($puede_nuevo): ?>
                <button type="submit" class="btn" id="guardarBtn">Guardar Faltante</button>
                <?php endif; ?>
                <button type="button" class="btn btn-cancelar" onclick="window.location.href='auditorias_consolidadas.php'">Cancelar</button>
            </div>
        </form>
        </div><!-- /.container -->
            </div><!-- /.container-fluid -->
        </div><!-- /.sub-container -->
    </div><!-- /.main-container -->

    <!-- Modal para mensajes -->
    <div id="modal" class="modal">
        <div class="modal-content">
            <div class="modal-message" id="modalMessage"></div>
            <div class="modal-buttons">
                <button type="button" class="btn btn-cancelar" id="modalCancelBtn">Cancelar</button>
                <button type="button" class="btn" id="modalConfirmBtn">Aceptar</button>
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
        
        document.addEventListener('DOMContentLoaded', function() {
            // Elementos del modal
            const modal = document.getElementById('modal');
            const modalMessage = document.getElementById('modalMessage');
            const modalConfirmBtn = document.getElementById('modalConfirmBtn');
            const modalCancelBtn = document.getElementById('modalCancelBtn');
            
            // Función para mostrar modal
            function showModal(message, isConfirm = false, callback = null) {
                modalMessage.textContent = message;
                modal.style.display = 'block';
                
                if (isConfirm) {
                    modalCancelBtn.style.display = 'inline-block';
                    modalConfirmBtn.style.display = 'inline-block';
                    
                    modalConfirmBtn.onclick = function() {
                        modal.style.display = 'none';
                        if (callback) callback(true);
                    };
                    
                    modalCancelBtn.onclick = function() {
                        modal.style.display = 'none';
                        if (callback) callback(false);
                    };
                } else {
                    modalCancelBtn.style.display = 'none';
                    modalConfirmBtn.style.display = 'inline-block';
                    modalConfirmBtn.textContent = 'Aceptar';
                    
                    modalConfirmBtn.onclick = function() {
                        modal.style.display = 'none';
                    };
                }
            }
            
            // Actualizar operarios al cambiar sucursal
            document.getElementById('sucursal_id').addEventListener('change', function() {
                const sucursalId = this.value;
                if (sucursalId) {
                    // Crear un formulario temporal para enviar todos los datos
                    const formData = new FormData(document.getElementById('faltanteForm'));
                    
                    // Convertir FormData a objeto para pasarlo como parámetro
                    const params = new URLSearchParams();
                    for (const [key, value] of formData.entries()) {
                        // No incluimos el parámetro 'operarios[]' para evitar problemas
                        if (key !== 'operarios[]') {
                            params.append(key, value);
                        }
                    }
                    
                    // Agregar el parámetro de sucursal_id
                    params.append('sucursal_id', sucursalId);
                    
                    // Redirigir manteniendo los datos del formulario
                    window.location.href = `faltante_inventario.php?${params.toString()}`;
                }
            });
            
            // Agregar fila de producto
            document.getElementById('addProducto').addEventListener('click', function() {
                const tbody = document.getElementById('productosBody');
                const rowCount = tbody.querySelectorAll('tr').length;
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><input type="text" name="productos[${rowCount}][nombre]" class="producto-input" required></td>
                    <td><input type="number" name="productos[${rowCount}][cantidad]" class="cantidad" min="1" step="1" required></td>
                    <td><input type="number" name="productos[${rowCount}][costo_unitario]" class="costo-unitario" min="0" step="0.01" required></td>
                    <td><input type="number" name="productos[${rowCount}][total]" class="total" readonly></td>
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
                        showModal('Debe haber al menos un producto');
                    }
                });
            });
            
            function setupAutocomplete(row) {
                const productoInput = row.querySelector('.producto-input');
                const costoInput = row.querySelector('.costo-unitario');
                
                // Hacer el campo de costo readonly y aplicar estilos
                costoInput.readOnly = true;
                costoInput.classList.add('readonly-field');
                
                $(productoInput).autocomplete({
                    source: productosAutocomplete,
                    minLength: 2,
                    select: function(event, ui) {
                        // Mantener el nombre completo con unidad de medida
                        $(this).val(ui.item.label);
                        costoInput.value = ui.item.costo;
                        
                        // Calcular total si ya hay cantidad
                        const cantidadInput = row.querySelector('.cantidad');
                        if (cantidadInput.value) {
                            const event = new Event('input');
                            cantidadInput.dispatchEvent(event);
                        }
                        
                        $(this).removeClass('invalid');
                        return false;
                    }
                });
            }
            
            function addRowEvents(row) {
                const cantidadInput = row.querySelector('.cantidad');
                const costoInput = row.querySelector('.costo-unitario');
                const totalInput = row.querySelector('.total');
                
                function calcularTotal() {
                    const cantidad = parseFloat(cantidadInput.value) || 0;
                    const costo = parseFloat(costoInput.value) || 0;
                    const total = cantidad * costo;
                    totalInput.value = total.toFixed(2);
                    
                    calcularTotales();
                }
                
                cantidadInput.addEventListener('input', calcularTotal);
                costoInput.addEventListener('input', calcularTotal);
            }
            
            function calcularTotales() {
                let totalFaltante = 0;
                document.querySelectorAll('.total').forEach(input => {
                    totalFaltante += parseFloat(input.value) || 0;
                });
                document.getElementById('totalFaltante').textContent = totalFaltante.toFixed(2);
            }
            
            // Configurar eventos para la fila inicial
            document.querySelectorAll('#productosBody tr').forEach(row => {
                setupAutocomplete(row);
                addRowEvents(row);
                
                row.querySelector('.remove-row').addEventListener('click', function() {
                    if (document.querySelectorAll('#productosBody tr').length > 1) {
                        document.getElementById('productosBody').removeChild(row);
                        calcularTotales();
                    } else {
                        showModal('Debe haber al menos un producto');
                    }
                });
            });
            
            // Actualizar campo oculto de sucursal cuando cambia el select
            document.getElementById('sucursal_id').addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                document.getElementById('sucursal').value = selectedOption.text;
            });
            
            // Validar formulario antes de enviar
            document.getElementById('faltanteForm').addEventListener('submit', function(e) {
                let valid = true;
                
                // Validar campos requeridos
                const requiredInputs = document.querySelectorAll('[required]');
                requiredInputs.forEach(input => {
                    if (!input.value.trim()) {
                        input.classList.add('invalid');
                        valid = false;
                    } else {
                        input.classList.remove('invalid');
                    }
                });
                
                // Validar comentarios
                const comentarios = document.getElementById('comentarios').value;
                if (!comentarios.trim()) {
                    showModal('Debe ingresar un comentario');
                    document.getElementById('comentarios').classList.add('invalid');
                    valid = false;
                }
                
                if (!valid) {
                    e.preventDefault();
                    return;
                }
                
                e.preventDefault();
                showModal('¿Está seguro que desea guardar este registro de faltante?', true, function(confirmed) {
                    if (confirmed) {
                        document.getElementById('faltanteForm').submit();
                    }
                });
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
