<?php
// Incluir configuración y verificar autenticación
require_once '../auth.php';
require_once '../../../../core/helpers/funciones.php'; // Antes llamaba a ../funciones.php de auditor�a
require_once 'config.php';

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

// Registrar el ID del auditor (usuario actual)
$auditor_id = $usuario['CodOperario'] ?? null;

date_default_timezone_set('America/Managua');

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
    // Verificar y modificar la tabla si es necesario
    $check_column = $conn->query("SHOW COLUMNS FROM `auditoria_inventario` LIKE 'foto_path_2'");
    if ($check_column->num_rows == 0) {
        $conn->query("ALTER TABLE `auditoria_inventario` ADD COLUMN `foto_path_2` varchar(255) DEFAULT NULL AFTER `foto_path`");
    }
    
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

$sucursales = [];
try {
    $query = "SELECT codigo, nombre FROM sucursales WHERE activa = 1 AND sucursal=1 ORDER BY nombre";
    $result = $conn->query($query);
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
    <link href="https://fonts.googleapis.com/css2?family=Calibri&display=swap" rel="stylesheet">
    <link rel="icon" href="icon12.png" type="image/png">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <style>
        * {
            font-size: clamp(11px, 2vw, 16px) !important;
            font-family: 'Calibri', sans-serif;
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }
        
        html, body {
            overflow-x: hidden;
            width: 100%;
            height: 100%;
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

        .header-container img {
            height: 50px;
            margin-right: 15px;
        }
        
        .header-container h1 {
            color: #000;
            margin: 0;
        }
        
        .form-group {
            margin-bottom: 15px;
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
        }
        
        .table-container {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            min-width: 600px;
        }
        
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
            white-space: nowrap;
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
        
        .add-row {
            margin: 0;
            padding: 8px 15px;
            background-color: #0E544C;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .participantes-container {
            margin-top: 10px;
        }
        
        .participante-input {
            display: flex;
            margin-bottom: 5px;
        }
        
        .participante-input input {
            flex-grow: 1;
            margin-right: 5px;
        }
        
        .remove-participante, .remove-row {
            background-color: #ff6b6b;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            padding: 6px 10px;
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
        
        #productosTable tfoot tr:first-child td {
            border: none;
            padding-top: 15px;
        }
        
        .invalid, .error-input {
            border: 1px solid red !important;
        }
        
        .diferencia-positiva {
            background-color: #ffdddd !important;
        }
        
        .btn {
            background-color: #0E544C;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            flex: 1;
            min-width: 120px;
            white-space: nowrap;
            text-align: center;
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
        
        .camera-section {
            margin-top: 20px;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 4px;
        }
        
        .camera-section.required {
            border: 2px solid red;
            animation: pulse 1s infinite;
        }
        
        @keyframes pulse {
            0% { border-color: red; }
            50% { border-color: #ff9999; }
            100% { border-color: red; }
        }
        
        .camera-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        #cameraPreview {
            width: 100%;
            max-width: 400px;
            background-color: #f1f1f1;
            margin-bottom: 10px;
            display: none;
        }
        
        #capturedImage {
            max-width: 100%;
            display: none;
            margin-top: 10px;
        }
        
        #photoCanvas {
            display: none;
        }
        
        .camera-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .camera-btn {
            padding: 8px 15px;
            background-color: #0E544C;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            flex: 1;
            min-width: 120px;
        }
        
        .camera-btn:hover {
            opacity: 0.9;
        }
        
        #photoData {
            display: none;
        }
        
        .button-container {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .btn-cancelar {
            background-color: #6c757d !important;
            color: white !important;
            padding: 10px 15px !important;
            border-radius: 4px !important;
            flex: 1;
            min-width: 120px;
            text-align: center;
        }
        
        .btn-cancelar:hover {
            background-color: #5a6268 !important;
            color: white !important;
        }
        
        .readonly-input {
            background-color: #f5f5f5;
        }
        
        .custom-alert {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0,0,0,0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        
        .alert-content {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .alert-buttons {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 10px;
        }
        
        .alert-btn {
            padding: 8px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .alert-btn-ok {
            background-color: #0E544C;
            color: white;
        }
        
        @media (max-width: 768px) {
    .header-container {
        flex-direction: row;
        align-items: center;
        gap: 10px;
    }
            
            .header-container h1 {
                text-align: left;
                width: auto;
                margin-left: 10px;
            }
            
            .logo {
                height: 35px;
                top: 15px;
            }
            
            .btn, .btn-cancelar, .camera-btn, .add-row {
                width: 100%;
            }
            
            .remove-row {
                padding: 5px 8px;
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
    
            .container {
                padding: 10px;
            }
            
            .header-container h1 {
                margin-left: 5px;
            }
            
            .logo {
                height: 30px;
                left: 10px;
                top: 12px;
            }
            
            th, td {
                padding: 5px;
            }
            
            .btn, .btn-cancelar, .camera-btn, .add-row {
                padding: 12px;
            }
            
            .remove-row {
                padding: 4px 6px;
            }
        }
        
        /* Nuevos estilos para la sección de fotos */
        .photo-section {
            margin-top: 20px;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        .photo-section.required {
            border: 2px solid red;
            animation: pulse 1s infinite;
        }
        
        .photo-container {
            display: flex;
            align-items: center;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .photo-preview {
            width: 100%;
            max-width: 400px;
            background-color: #f1f1f1;
            margin-bottom: 10px;
        }
        
        .photo-canvas {
            display: none;
        }
        
        .photo-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .photo-btn {
            padding: 8px 15px;
            background-color: #0E544C;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            flex: 1;
            min-width: 120px;
        }
        
        .photo-btn:hover {
            opacity: 0.9;
        }
        
        .camera-selector {
            margin-bottom: 10px;
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .photo-gallery {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
            justify-content: center;
        }
        
        .photo-thumbnail {
            position: relative;
            width: 100px;
            height: 100px;
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .photo-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .remove-photo {
            position: absolute;
            top: 2px;
            right: 2px;
            background: red;
            color: white;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .photo-status {
            text-align: center;
            margin: 10px 0;
            font-weight: bold;
        }
        
        .photo-status.complete {
            color: green;
        }
        
        .photo-status.pending {
            color: orange;
        }
        
        /* Agregar estos estilos en la sección CSS */
        .readonly-cell {
            background-color: #f5f5f5;
            color: #555;
        }
        
        .diferencia-positiva {
            background-color: #ddffdd !important;
            color: #006400;
        }
        
        .diferencia-negativa {
            background-color: #ffdddd !important;
            color: #8b0000;
        }
        
        /* Aplicar a todas las celdas de solo lectura */
        .diferencia, .costo-unitario, .total {
            background-color: #f5f5f5;
            color: #555;
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
        background-color: #e2e6ea; /* Un gris un poco más oscuro */
        cursor: pointer; /* Opcional, si querés indicar que es clickeable */
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
    
    .operario-categoria {
        font-size: clamp(8px, 1.5vw, 12px) !important;
        font-weight: bold;
        margin-left: 5px;
        padding: 2px 5px;
        border-radius: 3px;
        background-color: rgba(0,0,0,0.1);
    }
    </style>
</head>
<body>
    <div class="container">
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
        
        <h1 style="text-align:center;">Auditoría de Inventario</h1>
        
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
            <button type="submit" class="btn" id="guardarBtn">Guardar Auditoría</button>
            <button type="button" class="btn-cancelar" onclick="window.location.href='auditorias_consolidadas.php'">Cancelar</button>
        </div>
    </form>
</div>

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
</body>
</html>
