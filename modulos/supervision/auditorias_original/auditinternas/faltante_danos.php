<?php
// Incluir configuración y verificar autenticación
require_once '../auth.php';
require_once '../funciones.php';
require_once 'config.php';

// Verificar acceso al módulo 'supervision'
//verificarAccesoModulo('supervision');

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

date_default_timezone_set('America/Managua');

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
    // Verificar y crear tablas si no existen
    $check_tables = $conn->query("SHOW TABLES LIKE 'faltante_danos'");
    if ($check_tables->num_rows == 0) {
        $conn->query("CREATE TABLE `faltante_danos` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `fecha` datetime NOT NULL,
            `sucursal_codigo` int(11) NOT NULL,
            `sucursal_nombre` varchar(50) NOT NULL,
            `producto_danado` varchar(255) NOT NULL,
            `valor_faltante` decimal(10,2) NOT NULL,
            `comentarios` text DEFAULT NULL,
            `registrador_id` int(11) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        $conn->query("CREATE TABLE `faltante_danos_operarios` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `faltante_id` int(11) NOT NULL,
            `operario_id` int(11) NOT NULL,
            `operario_nombre` varchar(100) NOT NULL,
            `operario_cargo` varchar(100) NOT NULL,
            `operario_categoria` varchar(50) DEFAULT NULL,
            `monto` decimal(10,2) DEFAULT 0.00,
            PRIMARY KEY (`id`),
            KEY `faltante_id` (`faltante_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
} catch (Exception $e) {
    die("Error al conectar con la base de datos: " . $e->getMessage());
}

// Obtener operarios si ya se seleccionó una sucursal
$operarios = [];
if (isset($_GET['sucursal_codigo']) && is_numeric($_GET['sucursal_codigo'])) {
    $sucursal_codigo = $_GET['sucursal_codigo'];
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
    $stmt->bind_param("iss", $sucursal_codigo, $lunesSemana, $domingoSemana);
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
    $fecha = date('Y-m-d H:i:s');
    $sucursal_codigo = $_POST['sucursal_codigo'];
    
    $sucursal_nombre = '';
    $query = "SELECT nombre FROM sucursales WHERE codigo = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $sucursal_codigo);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $sucursal_nombre = $row['nombre'];
    }
    $stmt->close();
    
    $producto_danado = trim($_POST['producto_danado']);
    $valor_faltante = floatval($_POST['valor_faltante']);
    $comentarios = !empty($_POST['comentarios']) ? $_POST['comentarios'] : null;
    $operarios_seleccionados = $_POST['operarios'] ?? [];
    
    $conn->begin_transaction();
    
    try {
        // Insertar el registro principal de faltante por daños
        $stmt = $conn->prepare("INSERT INTO faltante_danos 
                               (fecha, sucursal_codigo, sucursal_nombre, producto_danado, valor_faltante, comentarios, registrador_id) 
                               VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("sissssi", 
            $fecha,
            $sucursal_codigo,
            $sucursal_nombre,
            $producto_danado,
            $valor_faltante,
            $comentarios,
            $_SESSION['usuario_id']
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error al guardar el faltante: " . $stmt->error);
        }
        
        $faltante_id = $conn->insert_id;
        $stmt->close();
        
        // Insertar los operarios relacionados
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
            $stmt->bind_param("ii", $operario_id, $sucursal_codigo);
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
        $monto_base = ($suma_pesos > 0) ? ($valor_faltante / $suma_pesos) : 0;
        
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
            $stmt->bind_param("ii", $operario_id, $sucursal_codigo);
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
                
                $stmt_insert = $conn->prepare("INSERT INTO faltante_danos_operarios 
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
                
                if (!$stmt_insert->execute()) {
                    throw new Exception("Error al guardar los operarios: " . $stmt_insert->error);
                }
                
                $stmt_insert->close();
            }
            $stmt->close();
        }
        
        $conn->commit();
        $_SESSION['success_message'] = "Registro de faltante por daños guardado correctamente.";
        header('Location: auditorias_consolidadas.php');
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error al guardar el registro: " . $e->getMessage();
    }
}

// Obtener sucursales
$sucursales = [];
$query = "SELECT codigo, nombre FROM sucursales WHERE activa = 1 AND sucursal=1 ORDER BY nombre";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $sucursales[] = $row;
    }
}

// Obtener la sucursal seleccionada (si existe)
$sucursal_seleccionada = isset($_GET['sucursal_codigo']) ? $_GET['sucursal_codigo'] : (isset($_POST['sucursal_codigo']) ? $_POST['sucursal_codigo'] : '');

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
    <title>Registro de Faltante por Daños</title>
    <link href="https://fonts.googleapis.com/css2?family=Calibri&display=swap" rel="stylesheet">
    <link rel="icon" href="icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <style>
        * {
            font-size: clamp(11px, 2vw, 16px) !important;
        }
        
        body {
            font-family: 'Calibri', sans-serif;
            background-color: #F6F6F6;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        
        .container {
            width: 100%;
            min-height: 100vh;
            margin: 0;
            padding: 15px;
            background-color: white;
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

        .header-content {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            flex-direction: column;
        }
        
        h1 {
            color: black;
            margin: 0;
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        th {
            background-color: #0E544C;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .total-row {
            font-weight: bold;
            background-color: #0E544C;
            color: white;
        }
        .message {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
        .invalid {
            border: 1px solid red !important;
        }
        .btn-cancelar {
            background-color: #6c757d !important;
            color: white !important;
        }
        .btn-cancelar:hover {
            background-color: #5a6268 !important;
            color: white !important;
        }
        .button-container {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
            justify-content: center;
            width: 100%;
        }
        /* Estilos para el modal */
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
            border-radius: 5px;
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
        /* Estilo para checkboxes */
        .operario-checkbox {
            margin: 0 auto;
            display: block;
        }
        
        /* Estilos para la lista de operarios */
        .operarios-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 3px;
        }
        
        .operario-item {
            padding: 5px;
            background-color: #f8f9fa;
            border-radius: 4px;
            border: none;
        }
        
        .operario-item:hover {
            background-color: #e2e6ea;
            cursor: pointer;
        }
        
        .operario-item label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: normal;
            cursor: pointer;
        }
        
        .operario-item input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        
        .operario-cargo {
            color: #b3a800;
            font-size: 0.9em;
            font-size: clamp(8px, 1.5vw, 12px) !important;
        }
        
        /* Estilo para cuando no hay operarios */
        #sinOperariosMsg {
            padding: 10px;
            background-color: #fff3cd;
            border-radius: 4px;
            text-align: center;
        }
        
        /* Agregar al final del CSS */
        #resumenCalculo {
            margin-top: 15px;
            padding: 10px;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            display: none;
        }
        
        #resumenCalculo div {
            margin: 5px 0;
        }
        
        @media (min-width: 768px) {
            .container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
                min-height: auto;
            }
            .header {
                padding: 15px 20px;
                border-radius: 8px 8px 0 0;
                margin: -20px -20px 20px -20px;
            }
            .header-content {
                flex-direction: row;
                justify-content: space-between;
            }
            h1 {
                text-align: center;
                flex-grow: 1;
            }
            .logo {
                margin-bottom: 0;
            }
            .btn {
                width: auto;
                margin-bottom: 0;
            }
            .button-container {
                flex-direction: row;
            }
            
            #resumenCalculo div {
                margin: 0;
                flex: 1;
            }
        }

@media (max-width: 768px) {
    .header-container {
        flex-direction: row;
        align-items: center;
        gap: 10px;
    }
}

        /* Ajustes para móviles */
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
    
            .button-container {
                flex-direction: row;
            }
            
            .button-container .btn {
                flex: 1;
                min-width: 120px;
            }
        }
        
        .operario-categoria {
            font-size: clamp(8px, 1.5vw, 12px) !important;
            font-weight: bold;
            margin-left: 5px;
            padding: 2px 5px;
            border-radius: 3px;
            background-color: rgba(0,0,0,0.1);
        }
        
        /* Estilos para el resumen de cálculo */
        #resumenCalculo {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-top: 15px;
        }
        
        #resumenCalculo div {
            margin: 5px 0;
        }
        
        @media (min-width: 768px) {
            #resumenCalculo div {
                display: flex;
                justify-content: space-between;
                flex-wrap: wrap;
            }
            
            #resumenCalculo div > div {
                flex: 1;
                margin: 0 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <header>
                <div class="header-container">
                    <div class="logo-container">
                        <img src="../Logo.svg" alt="Batidos Pitaya" class="logo">
                    </div>
                    
                    <div class="buttons-container">
                        <a href="auditorias_consolidadas.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'auditorias_consolidadas.php' ? 'activo' : '' ?>">
                            <i class="fas fa-money-bill-wave"></i> <span class="btn-text">Historial</span>
                        </a>
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
                        <a href="auditorias_consolidadas.php" class="btn-logout">
                            <i class="fas fa-sign-out-alt"></i>
                        </a>
                    </div>
                </div>
            </header>
            
            <h1 style="text-align:center;">Registro de Faltante por Daños</h1>
        </div>
        
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
                <label for="sucursal_codigo">Sucursal:</label>
                <select id="sucursal_codigo" name="sucursal_codigo" required>
                    <option value="">Seleccione una sucursal</option>
                    <?php foreach ($sucursales as $sucursal): ?>
                        <option value="<?php echo $sucursal['codigo']; ?>" <?php echo ($sucursal['codigo'] == $sucursal_seleccionada) ? 'selected' : ''; ?>>
                            <?php echo $sucursal['nombre']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="sucursal_nombre" id="sucursal_nombre">
            </div>
            
            <!-- Sección para selección de colaboradores -->
            <div class="form-group">
                <div id="operariosContainer">
                    <?php if (!empty($operarios)): ?>
                        <div class="operarios-list">
                            <?php foreach ($operarios as $operario): ?>
                                <div class="operario-item">
                                    <label>
                                        <input type="checkbox" name="operarios[]" value="<?php echo $operario['CodOperario']; ?>" 
                                               class="operario-checkbox" data-peso="<?php echo $operario['peso_categoria']; ?>">
                                        <?php echo htmlspecialchars($operario['nombre_completo'] ?? ''); ?>
                                        <span class="operario-cargo">(<?php echo htmlspecialchars($operario['cargo'] ?? ''); ?>)</span>
                                        <span class="operario-categoria" style="color: <?php echo obtenerColorCategoria($operario['color_categoria'] ?? null); ?>">
                                            [●]
                                        </span>
                                        <?php if (isset($operario['fin']) && $operario['fin'] < date('Y-m-d')): ?>
                                            <span style="color: red; font-size: 0.8em;">(Terminó el <?php echo date('d/m/Y', strtotime($operario['fin'])); ?>)</span>
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
                <label for="producto_danado">Producto Dañado:</label>
                <input type="text" id="producto_danado" name="producto_danado" required>
            </div>
            
            <div class="form-group">
                <label for="valor_faltante">Valor Faltante de Daño (C$):</label>
                <input type="number" id="valor_faltante" name="valor_faltante" min="0" step="0.01" required>
            </div>
            
            <!-- Resumen de cálculo -->
            <div id="resumenCalculo">
                <div style="display: flex; justify-content: space-between; flex-wrap: wrap;">
                    <div>
                        <strong>Colaboradores seleccionados:</strong> 
                        <span id="cantidadOperarios">0</span>
                    </div>
                    <div>
                        <strong>Monto total:</strong> 
                        C$ <span id="montoTotal">0.00</span>
                    </div>
                    <div style="display:none;">
                        <strong>Monto por Colaborador/a:</strong> 
                        C$ <span id="montoPorOperario">0.00</span>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="comentarios">Comentarios:</label>
                <textarea id="comentarios" name="comentarios" rows="4" required></textarea>
            </div>
            
            <div class="button-container">
                <button type="submit" class="btn" id="guardarBtn">Guardar Faltante</button>
                <button type="button" class="btn btn-cancelar" onclick="window.location.href='auditorias_consolidadas.php'">Cancelar</button>
            </div>
        </form>
    </div>

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
            document.getElementById('sucursal_codigo').addEventListener('change', function() {
                const sucursalCodigo = this.value;
                if (sucursalCodigo) {
                    // Redirigir con el parámetro de sucursal
                    window.location.href = `faltante_danos.php?sucursal_codigo=${sucursalCodigo}`;
                }
            });
            
            // Actualizar campo oculto de sucursal cuando cambia el select
            document.getElementById('sucursal_codigo').addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                document.getElementById('sucursal_nombre').value = selectedOption.text;
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
                
                // Validar que al menos un operario esté seleccionado
                const operariosSeleccionados = document.querySelectorAll('input[name="operarios[]"]:checked').length;
                if (operariosSeleccionados === 0) {
                    showModal('Debe seleccionar al menos un colaborador/a');
                    valid = false;
                }
                
                if (!valid) {
                    e.preventDefault();
                    return;
                }
                
                e.preventDefault();
                showModal('¿Está seguro que desea guardar este registro de faltante por daños?', true, function(confirmed) {
                    if (confirmed) {
                        document.getElementById('faltanteForm').submit();
                    }
                });
            });
            
            // Función para actualizar el cálculo del monto por operario
            function actualizarCalculoMonto() {
                const valorFaltante = parseFloat(document.getElementById('valor_faltante').value) || 0;
                const operariosCheckboxes = document.querySelectorAll('input[name="operarios[]"]:checked');
                
                const resumenCalculo = document.getElementById('resumenCalculo');
                const cantidadOperariosSpan = document.getElementById('cantidadOperarios');
                const montoTotalSpan = document.getElementById('montoTotal');
                const montoPorOperarioSpan = document.getElementById('montoPorOperario');
                
                if (operariosCheckboxes.length > 0) {
                    // Calcular suma de pesos
                    let sumaPesos = 0;
                    operariosCheckboxes.forEach(checkbox => {
                        const operarioId = checkbox.value;
                        const peso = parseFloat(checkbox.dataset.peso) || 0;
                        sumaPesos += peso;
                    });
                    
                    cantidadOperariosSpan.textContent = operariosCheckboxes.length;
                    montoTotalSpan.textContent = valorFaltante.toFixed(2);
                    
                    // Calcular monto por operario (solo si hay pesos)
                    if (sumaPesos > 0) {
                        const montoBase = valorFaltante / sumaPesos;
                        montoPorOperarioSpan.textContent = montoBase.toFixed(2);
                    } else {
                        montoPorOperarioSpan.textContent = (valorFaltante / operariosCheckboxes.length).toFixed(2);
                    }
                    
                    resumenCalculo.style.display = 'block';
                } else {
                    resumenCalculo.style.display = 'none';
                }
            }
            
            // Event listeners para actualizar el cálculo
            document.getElementById('valor_faltante').addEventListener('input', actualizarCalculoMonto);
            
            // Delegación de eventos para los checkboxes de operarios
            document.getElementById('operariosContainer').addEventListener('change', function(e) {
                if (e.target.matches('input[name="operarios[]"]')) {
                    actualizarCalculoMonto();
                }
            });
            
            // Llamar inicialmente para establecer el estado
            actualizarCalculoMonto();
        });
    </script>
</body>
</html>