<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
require_once '../../../../core/helpers/funciones.php'; // Antes llamaba a ../funciones.php de auditora
require_once 'config.php';

$db = conectarDB();

//******************************Estándar para header******************************
verificarAutenticacion();

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo 'supervision'
verificarAccesoCargo([16, 21]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([16, 21]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

// Obtener sucursales para el select
$sucursales = [];
try {
    $stmt = $db->query("SELECT codigo, nombre FROM sucursales WHERE activa = 1 ORDER BY nombre");
    $sucursales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al obtener sucursales: " . $e->getMessage());
}

// Obtener operarios para autocompletar
$operarios_autocomplete = [];
try {
    $query = "SELECT 
                o.CodOperario, 
                CONCAT(
                    COALESCE(o.Nombre, ''), ' ',
                    COALESCE(o.Nombre2, ''), ' ',
                    COALESCE(o.Apellido, ''), ' ',
                    COALESCE(o.Apellido2, '')
                ) AS nombre_completo
              FROM Operarios o
              WHERE o.Operativo = 1
              ORDER BY nombre_completo";
    $stmt = $db->query($query);
    $operarios_autocomplete = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al obtener operarios: " . $e->getMessage());
}

$operarios_json = json_encode($operarios_autocomplete);

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha = date('Y-m-d');
    $sucursal_id = (int)$_POST['sucursal_id'];
    $operario_id = (int)$_POST['operario_id'];
    $observaciones = trim($_POST['observaciones'] ?? '');
    $usuario_id = $_SESSION['usuario_id'];
    
    // Obtener los checks de los items
    $items = [
        1 => isset($_POST['item_1']) ? 1 : 0,
        2 => isset($_POST['item_2']) ? 1 : 0,
        3 => isset($_POST['item_3']) ? 1 : 0,
        4 => isset($_POST['item_4']) ? 1 : 0,
        5 => isset($_POST['item_5']) ? 1 : 0,
        6 => isset($_POST['item_6']) ? 1 : 0,
        7 => isset($_POST['item_7']) ? 1 : 0,
        8 => isset($_POST['item_8']) ? 1 : 0,
        9 => isset($_POST['item_9']) ? 1 : 0,
        10 => isset($_POST['item_10']) ? 1 : 0,
        11 => isset($_POST['item_11']) ? 1 : 0,
        12 => isset($_POST['item_12']) ? 1 : 0,
    ];
    
    // Calcular porcentaje de cumplimiento
    $total_items = count($items);
    $items_cumplidos = array_sum($items);
    $porcentaje_cumplimiento = $total_items > 0 ? round(($items_cumplidos / $total_items) * 100) : 0;
    
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
    
    // Verificar que el operario existe
    try {
        $stmt = $db->prepare("
            SELECT o.CodOperario, 
                   CONCAT(
                    COALESCE(o.Nombre, ''), ' ',
                    COALESCE(o.Nombre2, ''), ' ',
                    COALESCE(o.Apellido, ''), ' ',
                    COALESCE(o.Apellido2, '')
                   ) AS nombre_completo
            FROM Operarios o
            WHERE o.CodOperario = ?
            LIMIT 1
        ");
        $stmt->execute([$operario_id]);
        $operario = $stmt->fetch();
        
        if (!$operario) {
            die("Error: El colaborador seleccionado no existe");
        }
        
        $operario_nombre = trim($operario['nombre_completo']);
    } catch (PDOException $e) {
        die("Error al verificar el colaborador: " . $e->getMessage());
    }
    
    // Verificar que existe la tabla o crearla
    $check_table = $db->query("SHOW TABLES LIKE 'auditoria_procesos'");
    if ($check_table->rowCount() == 0) {
        // Crear la tabla
        $create_table_sql = "CREATE TABLE auditoria_procesos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            fecha DATE NOT NULL,
            sucursal_id INT NOT NULL,
            sucursal_nombre VARCHAR(255) NOT NULL,
            operario_id INT NOT NULL,
            operario_nombre VARCHAR(255) NOT NULL,
            item_1 TINYINT DEFAULT 0,
            item_2 TINYINT DEFAULT 0,
            item_3 TINYINT DEFAULT 0,
            item_4 TINYINT DEFAULT 0,
            item_5 TINYINT DEFAULT 0,
            item_6 TINYINT DEFAULT 0,
            item_7 TINYINT DEFAULT 0,
            item_8 TINYINT DEFAULT 0,
            item_9 TINYINT DEFAULT 0,
            item_10 TINYINT DEFAULT 0,
            item_11 TINYINT DEFAULT 0,
            item_12 TINYINT DEFAULT 0,
            porcentaje_cumplimiento INT DEFAULT 0,
            observaciones TEXT,
            usuario_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sucursal_id) REFERENCES sucursales(codigo),
            FOREIGN KEY (operario_id) REFERENCES Operarios(CodOperario)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->exec($create_table_sql);
    }
    
    // Insertar en la base de datos
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("INSERT INTO auditoria_procesos 
                            (fecha, sucursal_id, sucursal_nombre, operario_id, operario_nombre,
                            item_1, item_2, item_3, item_4, item_5, item_6, item_7, item_8,
                            item_9, item_10, item_11, item_12, porcentaje_cumplimiento,
                            observaciones, usuario_id, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        
        $stmt->execute([
            $fecha,
            $sucursal_id,
            $sucursal_nombre,
            $operario_id,
            $operario_nombre,
            $items[1], $items[2], $items[3], $items[4], $items[5], $items[6],
            $items[7], $items[8], $items[9], $items[10], $items[11], $items[12],
            $porcentaje_cumplimiento,
            $observaciones,
            $usuario_id
        ]);
        
        $db->commit();
        
        header("Location: auditoria_proceso.php?success=1");
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
    <title>Auditoría de Procesos</title>
    <link rel="icon" href="icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <style>
        * {
            font-family: 'Calibri', sans-serif;
            box-sizing: border-box;
            font-size: clamp(11px, 2vw, 16px) !important;
        }
        
        html, body {
            overflow-x: hidden;
            width: 100%;
            margin: 0;
            padding: 0;
            background-color: #F6F6F6;
        }
        
        .container {
            width: 100%;
            max-width: 100%;
            margin: 0 auto;
            padding: 15px;
            background-color: white;
            min-height: 100vh;
            box-sizing: border-box;
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
        
        h1 {
            color: black;
            margin: 20px 0;
            text-align: center;
            width: 100%;
        }
        
        .form-group {
            margin-bottom: 15px;
            width: 100%;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .checkbox-group {
            margin-bottom: 10px;
            padding: 10px;
            border: 1px solid #eee;
            border-radius: 4px;
            background-color: #f9f9f9;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            padding: 5px;
        }
        
        .checkbox-item label {
            margin: 0;
            padding-left: 10px;
            flex-grow: 1;
            font-weight: normal;
            cursor: pointer;
        }
        
        .checkbox-item input[type="checkbox"] {
            width: auto;
            margin: 0;
            cursor: pointer;
        }
        
        .checkbox-item:hover {
            background-color: #f0f0f0;
        }
        
        .btn {
            background-color: #0E544C;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            margin-bottom: 10px;
        }
        
        .btn-special {
            background-color: #0E544C;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: <?php echo $showSuccess ? 'block' : 'none'; ?>;
        }
        
        .stats-box {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
        }
        
        .stats-box h3 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .percentage {
            font-size: 24px;
            font-weight: bold;
            color: #0E544C;
        }
        
        .button-container {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
            justify-content: center;
            width: 100%;
        }
        
        .btn-cancelar {
            background-color: #6c757d !important;
            color: white !important;
        }
        
        .btn-cancelar:hover {
            background-color: #5a6268 !important;
            color: white !important;
        }
        
        /* Estilos para autocomplete */
        .ui-autocomplete {
            max-height: 200px;
            overflow-y: auto;
            overflow-x: hidden;
            z-index: 1000;
        }
        
        .ui-menu-item {
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        
        .ui-menu-item:hover {
            background-color: #0E544C;
            color: white;
        }
        
        .invalid {
            border: 1px solid red !important;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 400px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
            gap: 10px;
        }
        
        .modal-message {
            margin-bottom: 20px;
        }
        
        @media (min-width: 768px) {
            .btn {
                width: auto;
                margin-bottom: 0;
            }
            
            .button-container {
                flex-direction: row;
            }
            
            .checkbox-group {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
        }
        
        @media (max-width: 768px) {
            .header-container {
                flex-direction: row;
                align-items: center;
                gap: 10px;
            }
            
            .modal-content {
                margin: 30% auto;
                width: 90%;
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
            
            .checkbox-group {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-message" id="successMessage" style="display: <?php echo $showSuccess ? 'block' : 'none'; ?>;">
            ¡La auditoría de procesos se ha guardado correctamente! Serás redirigido...
        </div>
        
        <header>
            <div class="header-container">
                <div class="logo-container">
                    <img src="../Logo.svg" alt="Batidos Pitaya" class="logo">
                </div>
                
                <div class="buttons-container">
                    <a href="../index.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == '../index.php' ? 'activo' : '' ?>">
                        <i class="fas fa-clipboard-check"></i> <span class="btn-text">Historial</span>
                    </a>
                    
                    <?php if (verificarAccesoCargo([16])): ?>
                        <a href="auditoria_caja_facturacion.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'auditoria_caja_facturacion.php' ? 'activo' : '' ?>"><i class="fas fa-cash-register"></i> Auditoría Caja Facturación</a>
                        <a href="auditoria_caja_chica.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'auditoria_caja_chica.php' ? 'activo' : '' ?>"><i class="fas fa-wallet"></i> Auditoría Caja Chica</a>
                        <a href="auditoria_inventario.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'auditoria_inventario.php' ? 'activo' : '' ?>"><i class="fas fa-boxes"></i> Auditoría Inventario</a>
                        <a href="auditoria_proceso.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'auditoria_proceso.php' ? 'activo' : '' ?>"><i class="fas fa-clipboard-check"></i> Auditoría Procesos</a>
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
        
        <h1>Auditoría de Procesos</h1>
        
        <!-- Modal de confirmación -->
        <div id="confirmModal" class="modal">
            <div class="modal-content">
                <p id="modalMessage">¿Está seguro que desea guardar esta auditoría?</p>
                <div class="modal-buttons">
                    <button class="btn btn-cancelar" id="cancelBtn">Cancelar</button>
                    <button class="btn" id="confirmBtn">Guardar</button>
                </div>
            </div>
        </div>
        
        <form id="auditoriaForm" method="post">
            <div class="form-group">
                <label for="fecha">Fecha:</label>
                <input type="text" id="fecha" name="fecha" value="<?php echo date('d/m/Y'); ?>" readonly class="readonly-field">
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
            </div>
            
            <div class="form-group">
                <label for="operario_input">Colaborador:</label>
                <input type="text" id="operario_input" class="colaborador-input" placeholder="Buscar colaborador..." required>
                <input type="hidden" id="operario_id" name="operario_id" required>
                <small style="display:none;">Empiece a escribir el nombre del colaborador y seleccione de la lista</small>
            </div>
            
            <div class="form-group">
                <h3>Items de Verificación:</h3>
                <div class="checkbox-group" id="itemsGroup">
                    <div class="checkbox-item">
                        <input type="checkbox" id="item_1" name="item_1" value="1">
                        <label for="item_1">Lavarse las manos antes de la preparación de los productos</label>
                    </div>
                    
                    <div class="checkbox-item">
                        <input type="checkbox" id="item_2" name="item_2" value="1">
                        <label for="item_2">Se prepara los productos una vez facturado</label>
                    </div>
                    
                    <div class="checkbox-item">
                        <input type="checkbox" id="item_3" name="item_3" value="1">
                        <label for="item_3">Hace más de 2 recorridos en la preparación</label>
                    </div>
                    
                    <div class="checkbox-item">
                        <input type="checkbox" id="item_4" name="item_4" value="1">
                        <label for="item_4">Aplica líquido con el vaso medidor (leche, naranja y agua)</label>
                    </div>
                    
                    <div class="checkbox-item">
                        <input type="checkbox" id="item_5" name="item_5" value="1">
                        <label for="item_5">Medí el azúcar con el jigger</label>
                    </div>
                    
                    <div class="checkbox-item">
                        <input type="checkbox" id="item_6" name="item_6" value="1">
                        <label for="item_6">Sigue el proceso de embasado cuando el motor está operando</label>
                    </div>
                    
                    <div class="checkbox-item">
                        <input type="checkbox" id="item_7" name="item_7" value="1">
                        <label for="item_7">Se entrega batido con la consistencia establecida por operaciones</label>
                    </div>
                    
                    <div class="checkbox-item">
                        <input type="checkbox" id="item_8" name="item_8" value="1">
                        <label for="item_8">Limpia la estación de trabajo después de preparar</label>
                    </div>
                    
                    <div class="checkbox-item">
                        <input type="checkbox" id="item_9" name="item_9" value="1">
                        <label for="item_9">Sigue el proceso de decoración (Waffle)</label>
                    </div>
                    
                    <div class="checkbox-item">
                        <input type="checkbox" id="item_10" name="item_10" value="1">
                        <label for="item_10">Usa la waflera en la temperatura y tiempo establecida (3.5 g)</label>
                    </div>
                    
                    <div class="checkbox-item">
                        <input type="checkbox" id="item_11" name="item_11" value="1">
                        <label for="item_11">Usa la tabla para picar frutas</label>
                    </div>
                    
                    <div class="checkbox-item">
                        <input type="checkbox" id="item_12" name="item_12" value="1">
                        <label for="item_12">Coloca papel toalla al finalizar la preparación de los wafles</label>
                    </div>
                </div>
            </div>
            
            <div id="statsContainer" class="stats-box" style="display: none;">
                <h3>Cumplimiento:</h3>
                <div class="percentage" id="porcentajeCumplimiento">0%</div>
                <p id="itemsCumplidos">0 de 12 items cumplidos</p>
            </div>
            
            <div class="form-group">
                <label for="observaciones">Observaciones:</label>
                <textarea id="observaciones" name="observaciones" rows="4" placeholder="Observaciones adicionales..."></textarea>
            </div>
            
            <div class="button-container">
                <button type="button" class="btn" id="submitBtn">
                    Guardar Auditoría
                </button>
                <button type="button" class="btn btn-cancelar" onclick="window.location.href='../index.php'">
                    Cancelar
                </button>
            </div>
        </form>
    </div>

    <script>
        const operarios = <?php echo $operarios_json; ?>;
        
        // Preparar datos para autocomplete
        const operariosAutocomplete = operarios.map(operario => ({
            label: operario.nombre_completo,
            value: operario.nombre_completo,
            id: operario.CodOperario
        }));
        
        // Función para calcular porcentaje de cumplimiento
        function calcularCumplimiento() {
            const totalItems = 12;
            const checkboxes = document.querySelectorAll('#itemsGroup input[type="checkbox"]');
            let itemsCumplidos = 0;
            
            checkboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    itemsCumplidos++;
                }
            });
            
            const porcentaje = Math.round((itemsCumplidos / totalItems) * 100);
            
            // Actualizar display
            const statsContainer = document.getElementById('statsContainer');
            const porcentajeElement = document.getElementById('porcentajeCumplimiento');
            const itemsCumplidosElement = document.getElementById('itemsCumplidos');
            
            porcentajeElement.textContent = porcentaje + '%';
            itemsCumplidosElement.textContent = itemsCumplidos + ' de ' + totalItems + ' items cumplidos';
            
            // Mostrar u ocultar contenedor de estadísticas
            if (itemsCumplidos > 0) {
                statsContainer.style.display = 'block';
            } else {
                statsContainer.style.display = 'none';
            }
            
            return { itemsCumplidos, porcentaje };
        }
        
        // Función para validar formulario
        function validarFormulario() {
            const sucursalId = document.getElementById('sucursal_id').value;
            const operarioId = document.getElementById('operario_id').value;
            const operarioInput = document.getElementById('operario_input').value;
            
            let valido = true;
            let mensajesError = [];
            
            // Validar sucursal
            if (!sucursalId) {
                valido = false;
                mensajesError.push('Debe seleccionar una sucursal');
                document.getElementById('sucursal_id').classList.add('invalid');
            } else {
                document.getElementById('sucursal_id').classList.remove('invalid');
            }
            
            // Validar colaborador
            if (!operarioId || !operarioInput.trim()) {
                valido = false;
                mensajesError.push('Debe seleccionar un colaborador de la lista');
                document.getElementById('operario_input').classList.add('invalid');
            } else {
                document.getElementById('operario_input').classList.remove('invalid');
            }
            
            return { valido, mensajesError };
        }
        
        // Función para mostrar modal
        function showModal(message, isConfirm = false) {
            const modal = document.getElementById('confirmModal');
            const modalMessage = document.getElementById('modalMessage');
            
            modalMessage.textContent = message;
            modal.style.display = 'block';
            
            return new Promise((resolve) => {
                document.getElementById('confirmBtn').onclick = function() {
                    modal.style.display = 'none';
                    resolve(true);
                };
                
                document.getElementById('cancelBtn').onclick = function() {
                    modal.style.display = 'none';
                    resolve(false);
                };
                
                window.onclick = function(event) {
                    if (event.target === modal) {
                        modal.style.display = 'none';
                        resolve(false);
                    }
                };
            });
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Configurar autocomplete para colaborador
            $('#operario_input').autocomplete({
                source: operariosAutocomplete,
                minLength: 2,
                select: function(event, ui) {
                    $(this).val(ui.item.value);
                    $('#operario_id').val(ui.item.id);
                    $(this).removeClass('invalid');
                    return false;
                },
                change: function(event, ui) {
                    // Si el usuario escribe manualmente y no selecciona de la lista
                    if (!ui.item) {
                        const inputValue = $(this).val();
                        const found = operariosAutocomplete.find(op => 
                            op.value.toLowerCase() === inputValue.toLowerCase()
                        );
                        
                        if (found) {
                            $('#operario_id').val(found.id);
                            $(this).removeClass('invalid');
                        } else {
                            $('#operario_id').val('');
                            $(this).addClass('invalid');
                        }
                    }
                }
            });
            
            // Calcular cumplimiento cada vez que cambie un checkbox
            document.querySelectorAll('#itemsGroup input[type="checkbox"]').forEach(checkbox => {
                checkbox.addEventListener('change', calcularCumplimiento);
            });
            
            // Manejar envío del formulario
            document.getElementById('submitBtn').addEventListener('click', async function(e) {
                e.preventDefault();
                
                // Validar formulario
                const { valido, mensajesError } = validarFormulario();
                
                if (!valido) {
                    const mensaje = mensajesError.join('\n');
                    await showModal(mensaje);
                    return;
                }
                
                // Calcular estadísticas para mostrar en confirmación
                const { itemsCumplidos, porcentaje } = calcularCumplimiento();
                
                // Mostrar confirmación
                const confirmMessage = `
                    ¿Está seguro que desea guardar esta auditoría?
                    
                    Detalles:
                    - Items cumplidos: ${itemsCumplidos}/12
                    - Porcentaje: ${porcentaje}%
                    
                    Esta acción no se puede deshacer.
                `;
                
                const confirmado = await showModal(confirmMessage, true);
                
                if (confirmado) {
                    // Deshabilitar botón para evitar doble envío
                    this.disabled = true;
                    this.textContent = 'Guardando...';
                    
                    // Enviar formulario
                    document.getElementById('auditoriaForm').submit();
                }
            });
            
            // Redirección automática después de éxito
            if (window.location.search.includes('success=1')) {
                setTimeout(function() {
                    window.location.href = '../index.php';
                }, 3000);
                
                // Limpiar parámetro de la URL
                history.replaceState(null, null, window.location.pathname);
            }
            
            // Validación en tiempo real
            document.getElementById('sucursal_id').addEventListener('change', function() {
                if (this.value) {
                    this.classList.remove('invalid');
                }
            });
            
            document.getElementById('operario_input').addEventListener('blur', function() {
                const operarioId = document.getElementById('operario_id').value;
                if (!operarioId && this.value.trim()) {
                    this.classList.add('invalid');
                }
            });
        });
    </script>
</body>
</html>
