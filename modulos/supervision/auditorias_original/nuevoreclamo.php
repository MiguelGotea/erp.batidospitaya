<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
// Al inicio del archivo, verificar autenticación y acceso al módulo
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
require_once '../../../core/helpers/funciones.php'; // Antes llamaba a funciones.php de auditora
require_once 'conexion.php';

// Verificar acceso al módulo 'publico' (o el nombre que corresponda según tus permisos)
//verificarAccesoModulo('supervision');

//******************************Estándar para header******************************
verificarAutenticacion();

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo 'supervision'
//verificarAccesoCargo([11, 16, 22, 28]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([11, 16, 22, 28, 50, 49]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

// Configuración de zona horaria
date_default_timezone_set('America/Managua');
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'es');

// Al inicio del archivo, después de la conexión a BD
$sucursales_fisicas = obtenerTodasSucursales();

// Obtener grupos de reclamos
function obtenerGruposReclamos($conn) {
    try {
        $query = "SELECT id, nombre FROM reclamos_grupos ORDER BY nombre";
        return $conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al obtener grupos: " . $e->getMessage());
        return [];
    }
}

// Obtener tipos de reclamos (para precargar en JS)
function obtenerTodosTiposReclamos($conn) {
    try {
        $query = "SELECT id, grupo_id, nombre FROM reclamos_tipos ORDER BY nombre";
        return $conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al obtener tipos: " . $e->getMessage());
        return [];
    }
}

$gruposReclamos = obtenerGruposReclamos($conn);
$tiposReclamosJSON = json_encode(obtenerTodosTiposReclamos($conn));

// Obtener el próximo número de reclamo
function obtenerProximoNumeroReclamo($conn) {
    $query = "SELECT MAX(id) as max_id FROM reclamos";
    $stmt = $conn->query($query);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return ($result['max_id'] ?? 0) + 1;
}

// Obtener lista de gestores desde la base de datos
function obtenerGestoresReclamos($conn) {
    try {
        $query = "SELECT nombre FROM gestores_reclamos WHERE activo = TRUE ORDER BY nombre";
        $stmt = $conn->query($query);
        $gestores = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Asegurarnos que está en la lista
        if (!in_array('Keyli Mejía', $gestores)) {
            array_unshift($gestores, 'Keyli Mejía');
        }
        
        return $gestores;
    } catch (PDOException $e) {
        error_log("Error al obtener gestores: " . $e->getMessage());
        return ['Keyli Mejía']; // Valor por defecto si hay error
    }
}

$gestores = obtenerGestoresReclamos($conn);
$gestorActual = $datosFormulario['gestor_reclamo'] ?? 'Keyli Mejía';
$mostrarInput = ($gestorActual !== 'Keyli Mejía' && !in_array($gestorActual, $gestores));

$proximoNumero = obtenerProximoNumeroReclamo($conn);

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Validar campos requeridos
        $camposRequeridos = [
            'gestor_reclamo' => 'Gestor(a) de Reclamo',
            'fuente' => 'Fuente',
            'sucursal_codigo' => 'Sucursal',
            'fecha_reclamo' => 'Fecha del Reclamo',
            'fecha_evento' => 'Fecha del Evento',
            'hora_evento' => 'Hora del Evento',
            'medio_compra' => 'Medio de Compra',
            'productos' => 'Productos en Reclamo',
            'grupo_id' => 'Grupo de Reclamo',
            'tipo_reclamo_id' => 'Tipo de Reclamo',
            'descripcion' => 'Descripción del Reclamo',
            'investigacion_preliminar' => 'Investigación Preliminar'
        ];
        
        // Ya no necesitamos buscar el código, lo tenemos directamente
        $sucursal_codigo = $_POST['sucursal_codigo'];
        
        // Obtener el nombre de la sucursal para mantener compatibilidad
        $stmt_nombre = $conn->prepare("SELECT nombre FROM sucursales WHERE codigo = ? LIMIT 1");
        $stmt_nombre->execute([$sucursal_codigo]);
        $sucursal_nombre = $stmt_nombre->fetchColumn();
        
        if (!$sucursal_nombre) {
            // Manejar error si no se encuentra la sucursal
            $sucursal_nombre = 'Desconocida';
        }
        
        $errores = [];
        $datosFormulario = [];
        
        foreach ($camposRequeridos as $campo => $nombre) {
            if (empty($_POST[$campo])) {
                $errores[] = "El campo $nombre es requerido";
            } else {
                $datosFormulario[$campo] = $_POST[$campo];
            }
        }
        
        // Validar productos (debe tener al menos un producto)
        if (empty($_POST['productos']) || empty(json_decode($_POST['productos'], true))) {
            $errores[] = "Debe agregar al menos un producto";
        }
        
        // Validar al menos una foto o video
        if (empty($_FILES['fotos']['name'][0]) && empty($_FILES['videos']['name'][0])) {
            $errores[] = "Debe agregar al menos una foto o video de evidencia";
        }
        
        // Si hay errores, mostrarlos
        if (!empty($errores)) {
            $_SESSION['errores'] = $errores;
            $_SESSION['datos_formulario'] = $datosFormulario;
            header("Location: {$_SERVER['PHP_SELF']}");
            exit();
        }
        
        // Crear directorios si no existen
        if (!file_exists('uploads/reclamos')) {
            mkdir('uploads/reclamos', 0777, true);
        }
        if (!file_exists('uploads/reclamos/videos')) {
            mkdir('uploads/reclamos/videos', 0777, true);
        }
        
        // Procesar imágenes
        $imagenes = [];
        if (!empty($_FILES['fotos']['name'][0])) {
            $totalImagenes = count($_FILES['fotos']['name']);
            
            for ($i = 0; $i < $totalImagenes; $i++) {
                if ($_FILES['fotos']['error'][$i] === UPLOAD_ERR_OK) {
                    // Validar que sea una imagen
                    $mime = mime_content_type($_FILES['fotos']['tmp_name'][$i]);
                    if (strpos($mime, 'image/') === 0) {
                        $nombreTemporal = $_FILES['fotos']['tmp_name'][$i];
                        $nombreArchivo = uniqid() . '_' . basename($_FILES['fotos']['name'][$i]);
                        $rutaDestino = 'uploads/reclamos/' . $nombreArchivo;
                        
                        // Mover el archivo a la carpeta de uploads
                        if (move_uploaded_file($nombreTemporal, $rutaDestino)) {
                            $imagenes[] = $rutaDestino;
                        }
                    }
                }
            }
        }
        
        // Procesar videos
        $videos = [];
        if (!empty($_FILES['videos']['name'][0])) {
            $totalVideos = count($_FILES['videos']['name']);
            
            for ($i = 0; $i < $totalVideos; $i++) {
                if ($_FILES['videos']['error'][$i] === UPLOAD_ERR_OK) {
                    // Validar que sea un video
                    $mime = mime_content_type($_FILES['videos']['tmp_name'][$i]);
                    if (strpos($mime, 'video/') === 0) {
                        $nombreTemporal = $_FILES['videos']['tmp_name'][$i];
                        $nombreArchivo = uniqid() . '_' . basename($_FILES['videos']['name'][$i]);
                        $rutaDestino = 'uploads/reclamos/videos/' . $nombreArchivo;
                        
                        // Mover el archivo a la carpeta de uploads
                        if (move_uploaded_file($nombreTemporal, $rutaDestino)) {
                            $videos[] = $rutaDestino;
                        }
                    }
                }
            }
        }
        
        // Insertar en la base de datos
        $conn->beginTransaction();
        
        // Obtener el nombre del tipo de reclamo para mantener compatibilidad legado
        $stmt_tipo_txt = $conn->prepare("SELECT nombre FROM reclamos_tipos WHERE id = ? LIMIT 1");
        $stmt_tipo_txt->execute([$_POST['tipo_reclamo_id']]);
        $tipo_reclamo_texto = $stmt_tipo_txt->fetchColumn();

        // Insertar reclamo
        $query = "INSERT INTO reclamos (
            gestor_reclamo, fuente, sucursal, sucursal_codigo, fecha_reclamo, fecha_evento, hora_evento, 
            medio_compra, grupo_id, tipo_reclamo_id, tipo_reclamo, descripcion, investigacion_preliminar, 
            fecha_registro, hora_registro
        ) VALUES (
            :gestor_reclamo, :fuente, :sucursal, :sucursal_codigo, :fecha_reclamo, :fecha_evento, :hora_evento, 
            :medio_compra, :grupo_id, :tipo_reclamo_id, :tipo_reclamo, :descripcion, :investigacion_preliminar,
            CURDATE(), DATE_FORMAT(NOW(), '%h:%i %p')
        )";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':gestor_reclamo' => $_POST['gestor_reclamo'],
            ':fuente' => $_POST['fuente'],
            ':sucursal' => $sucursal_nombre,        // Nombre para compatibilidad
            ':sucursal_codigo' => $sucursal_codigo, // Código para nuevo sistema
            ':fecha_reclamo' => $_POST['fecha_reclamo'],
            ':fecha_evento' => $_POST['fecha_evento'],
            ':hora_evento' => $_POST['hora_evento'],
            ':medio_compra' => $_POST['medio_compra'],
            ':grupo_id' => $_POST['grupo_id'],
            ':tipo_reclamo_id' => $_POST['tipo_reclamo_id'],
            ':tipo_reclamo' => $tipo_reclamo_texto,
            ':descripcion' => $_POST['descripcion'],
            ':investigacion_preliminar' => $_POST['investigacion_preliminar'] ?? null
        ]);
        
        $reclamoId = $conn->lastInsertId();
        
        // Insertar productos
        $productos = json_decode($_POST['productos'], true);
        $queryProducto = "INSERT INTO reclamos_productos (reclamo_id, producto, precio) VALUES (:reclamo_id, :producto, :precio)";
        $stmtProducto = $conn->prepare($queryProducto);
        
        foreach ($productos as $producto) {
            $stmtProducto->execute([
                ':reclamo_id' => $reclamoId,
                ':producto' => $producto['producto'],
                ':precio' => $producto['precio']
            ]);
        }
        
        // Insertar imágenes
        if (!empty($imagenes)) {
            $queryImagen = "INSERT INTO reclamos_imagenes (reclamo_id, ruta_imagen) VALUES (:reclamo_id, :ruta_imagen)";
            $stmtImagen = $conn->prepare($queryImagen);
            
            foreach ($imagenes as $imagen) {
                $stmtImagen->execute([
                    ':reclamo_id' => $reclamoId,
                    ':ruta_imagen' => $imagen
                ]);
            }
        }
        
        // Insertar videos
        if (!empty($videos)) {
            $queryVideo = "INSERT INTO reclamos_videos (reclamo_id, ruta_video) VALUES (:reclamo_id, :ruta_video)";
            $stmtVideo = $conn->prepare($queryVideo);
            
            foreach ($videos as $video) {
                $stmtVideo->execute([
                    ':reclamo_id' => $reclamoId,
                    ':ruta_video' => $video
                ]);
            }
        }
        
        $conn->commit();
        
        // Éxito - redirigir a página de confirmación
        $_SESSION['reclamo_exitoso'] = true;
        $_SESSION['reclamo_id'] = $reclamoId;
        
        try {
            // Obtener mes y año ACTUAL (fecha de registro, no del evento)
            $mes_actual = date('n');
            $anio_actual = date('Y');
            
            // Verificar si ya existe un registro para esta sucursal/mes/año
            $queryKPI = "SELECT id, reclamos_totales, reclamos_cantidad FROM kpi_reclamos 
                         WHERE mes = :mes AND anio = :anio AND cod_sucursal = :cod_sucursal 
                         LIMIT 1";
            $stmtKPI = $conn->prepare($queryKPI);
            $stmtKPI->execute([
                ':mes' => $mes_actual,
                ':anio' => $anio_actual,
                ':cod_sucursal' => $sucursal_codigo
            ]);
        
            $registroKPI = $stmtKPI->fetch(PDO::FETCH_ASSOC);
        
            if ($registroKPI) {
                // Si EXISTE: actualizar (+1) ambos contadores
                $nuevaCantidadTotal = $registroKPI['reclamos_totales'] + 1;
                $nuevaCantidad = $registroKPI['reclamos_cantidad'] + 1;
                
                $queryUpdate = "UPDATE kpi_reclamos 
                               SET reclamos_totales = :nueva_cantidad_total, 
                                   reclamos_cantidad = :nueva_cantidad 
                               WHERE id = :id";
                $stmtUpdate = $conn->prepare($queryUpdate);
                $stmtUpdate->execute([
                    ':nueva_cantidad_total' => $nuevaCantidadTotal,
                    ':nueva_cantidad' => $nuevaCantidad,
                    ':id' => $registroKPI['id']
                ]);
            } else {
                // Si NO EXISTE: crear nuevo registro con ambos contadores en 1
                $queryInsert = "INSERT INTO kpi_reclamos 
                               (sucursal, cod_sucursal, mes, anio, reclamos_totales, reclamos_cantidad) 
                               VALUES 
                               (:sucursal, :cod_sucursal, :mes, :anio, 1, 1)";
                $stmtInsert = $conn->prepare($queryInsert);
                $stmtInsert->execute([
                    ':sucursal' => $sucursal_nombre,
                    ':cod_sucursal' => $sucursal_codigo,
                    ':mes' => $mes_actual,
                    ':anio' => $anio_actual
                ]);
            }
        } catch (Exception $e) {
            // Solo loggear el error pero no detener el proceso
            error_log("Error al actualizar KPI de reclamos: " . $e->getMessage());
        }

        header("Location: confirmacion_reclamo.php");
        exit();
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        // Para debugging - mostrar el error real
        error_log("Error al procesar reclamo: " . $e->getMessage());
        error_log("Trace: " . $e->getTraceAsString());
        
        $_SESSION['errores'] = [
            "Ocurrió un error al procesar el reclamo: " . $e->getMessage(),
            "Por favor intente nuevamente."
        ];
        $_SESSION['datos_formulario'] = $_POST;
        header("Location: {$_SERVER['PHP_SELF']}");
        exit();
    }
}

// Recuperar datos del formulario si hubo error
$datosFormulario = $_SESSION['datos_formulario'] ?? [];
$errores = $_SESSION['errores'] ?? [];
unset($_SESSION['datos_formulario'], $_SESSION['errores']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Nuevo Reclamo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="icon12.png" type="image/png">
    <style>
        * {
            box-sizing: border-box;
            font-family: 'Calibri', sans-serif;
        }
        
        body {
            background-color: #F6F6F6;
            margin: 0;
            padding: 20px;
            color: #333;
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

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        h1 {
            color: #0E544C;
            text-align: center;
            margin-bottom: 25px;
            font-size: 24px;
        }
        
        .form-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
        }
        
        .required:after {
            content: " *";
            color: red;
        }
        
        input[type="text"],
        input[type="date"],
        input[type="time"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .error {
            color: red;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
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
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .productos-container {
            margin-bottom: 20px;
        }
        
        .producto-item {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }
        
        .producto-item input {
            flex: 1;
        }
        
        .btn-remove-producto {
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 10px;
            cursor: pointer;
        }
        
        .btn-add-producto {
            background-color: #0E544C;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 10px 15px;
            cursor: pointer;
            margin-bottom: 20px;
        }
        
        .media-preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        
        .media-preview {
            position: relative;
            width: 100px;
            height: 100px;
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .media-preview img,
        .media-preview video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .media-icon {
            position: absolute;
            top: 5px;
            left: 5px;
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
        }
        
        .remove-media {
            position: absolute;
            top: 5px;
            right: 5px;
            background-color: rgba(220, 53, 69, 0.8);
            color: white;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 30px;
        }
        
        /* Estilos para los inputs de archivos */
        .file-upload-container {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }
        
        .file-upload-group {
            position: relative;
        }
        
        .file-upload-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100px;
            height: 100px;
            border: 2px dashed #51B8AC;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
            background-color: #f8f9fa;
            color: #51B8AC;
        }
        
        .file-upload-label:hover {
            background-color: #e9f7f5;
            border-color: #0E544C;
        }
        
        .file-upload-label i {
            font-size: 24px;
            margin-bottom: 8px;
        }
        
        .file-upload-label span {
            font-size: 12px;
            text-align: center;
        }
        
        .file-upload-input {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-info {
            margin-top: 10px;
            font-size: 14px;
            color: #666;
        }
        
        .logo-container img {
            max-width: 75px;
            height: auto;
        }
        
        @media (max-width: 768px) {
    .header-container {
        flex-direction: row;
        align-items: center;
        gap: 10px;
    }
    
    .buttons-container {
        position: static;
        transform: none;
        order: 3;
        width: 100%;
        justify-content: center;
        margin-top: 10px;
    }
    
    .logo-container {
        order: 1;
        margin-right: 0;
    }
    
    .user-info {
        order: 2;
        margin-left: auto;
    }
    
    .btn-agregar {
        padding: 6px 10px;
        font-size: 13px;
    }
    
            .container {
                padding: 15px;
            }
            
            h1 {
                font-size: 20px;
            }
            
            .producto-item {
                flex-direction: column;
                gap: 5px;
            }
            
            .producto-item input {
                width: 100%;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .form-actions .btn {
                width: 100%;
            }
            
            .file-upload-container {
                flex-direction: column;
            }
        }
        
        .gestor-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        #gestorContainer {
            position: relative;
        }
        
        /* Estilos para los campos de fecha */
        .date-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        
        .date-display {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            background-color: white;
        }
        
        .calendar-icon {
            position: absolute;
            right: 10px;
            top: 35px;
            color: #51B8AC;
            pointer-events: none;
        }
        
        .form-group {
            position: relative;
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
}
    </style>
</head>
<body>
    <header>
            <div class="header-container">
                <div class="logo-container">
                    <img src="Logo.svg" alt="Batidos Pitaya" class="logo">
                </div>
                
                <div class="buttons-container">
                    <a href="nuevoreclamo.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'nuevoreclamo.php' ? 'activo' : '' ?>">
                        <i class="fas fa-tasks"></i> <span class="btn-text">Nuevo Reclamo</span>
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
                    <a href="../../../index.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </header>
    
    <div class="container">
        <h1><strong>REPORTE DE NUEVO RECLAMO</strong></h1>
        
        <?php if (!empty($errores)): ?>
            <div style="color: red; margin-bottom: 20px; padding: 10px; background-color: #ffeeee; border-radius: 4px;">
                <strong>Errores:</strong>
                <ul>
                    <?php foreach ($errores as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form id="reclamoForm" method="POST" enctype="multipart/form-data">
            <div class="form-header">
                <div class="form-group" style="flex: 1;">
                    <label>Fecha del Reporte</label>
                    <input type="text" value="<?php echo formatoFechaEspanol(); ?>" readonly>
                </div>
                
                <div class="form-group" style="flex: 1;">
                    <label>Código de Reclamo</label>
                    <input style="display:none;" type="text" value="RCL-<?php echo str_pad($proximoNumero, 5, '0', STR_PAD_LEFT); ?>" readonly>
                    <input type="text" value="<?php echo $proximoNumero; ?>" readonly>
                </div>
            </div>
            
            <!-- Nuevo campo Gestor(a) de Reclamo -->
            <div class="form-group">
                <label class="required">Gestor(a) de Reclamo</label>
                <div id="gestorContainer">
                    <!-- Campo oculto que se enviará con el formulario -->
                    <input type="hidden" name="gestor_reclamo" id="gestorHidden" value="<?php echo htmlspecialchars($gestorActual); ?>">
                    
                    <!-- Selector de gestores -->
                    <select id="gestorSelect" class="gestor-control" <?php echo $mostrarInput ? 'style="display:none;"' : ''; ?>>
                        <option value="">Seleccione un gestor</option>
                        <?php foreach ($gestores as $gestor): ?>
                            <option value="<?php echo htmlspecialchars($gestor); ?>" <?php echo ($gestorActual === $gestor) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($gestor); ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="otro" <?php echo $mostrarInput ? 'selected' : ''; ?>>Otro...</option>
                    </select>
                    
                    <!-- Campo de texto para "Otro" -->
                    <input type="text" id="gestorInput" class="gestor-control" 
                           value="<?php echo $mostrarInput ? htmlspecialchars($gestorActual) : ''; ?>" 
                           placeholder="Escriba el nombre del gestor"
                           <?php echo !$mostrarInput ? 'style="display:none;"' : ''; ?>>
                </div>
            </div>
            
            <div class="form-group">
                <label class="required">Fuente</label>
                <select name="fuente" required>
                    <option value="">Seleccione una opción</option>
                    <option value="Whatsapp" <?php echo ($datosFormulario['fuente'] ?? '') === 'Whatsapp' ? 'selected' : ''; ?>>Whatsapp</option>
                    <option value="Google Business" <?php echo ($datosFormulario['fuente'] ?? '') === 'Google Business' ? 'selected' : ''; ?>>Google Business</option>
                    <option value="Pedidos Ya" <?php echo ($datosFormulario['fuente'] ?? '') === 'Pedidos Ya' ? 'selected' : ''; ?>>Pedidos Ya</option>
                    <option value="Facebook" <?php echo ($datosFormulario['fuente'] ?? '') === 'Facebook' ? 'selected' : ''; ?>>Facebook</option>
                    <option value="Instagram" <?php echo ($datosFormulario['fuente'] ?? '') === 'Instagram' ? 'selected' : ''; ?>>Instagram</option>
                    <option value="Correo" <?php echo ($datosFormulario['fuente'] ?? '') === 'Correo' ? 'selected' : ''; ?>>Correo</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="required">Sucursal</label>
                <select name="sucursal_codigo" required>  <!-- Cambiar name a sucursal_codigo -->
                    <option value="">Seleccione una opción</option>
                    <?php foreach ($sucursales_fisicas as $sucursal): ?>
                        <option value="<?php echo htmlspecialchars($sucursal['codigo']); ?>" 
                            <?php echo (($datosFormulario['sucursal_codigo'] ?? '') === $sucursal['codigo']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sucursal['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-header">
                <div class="form-group" style="flex: 1; position: relative;">
                    <label class="required">Fecha del Reclamo</label>
                    <input type="text" name="fecha_reclamo_display" id="fechaReclamoDisplay" 
                           value="<?php echo !empty($datosFormulario['fecha_reclamo']) ? 
                               formatoFechaEspanol($datosFormulario['fecha_reclamo']) : 
                               formatoFechaEspanol(); ?>" 
                           class="date-display" readonly>
                    <input type="date" name="fecha_reclamo" id="fechaReclamoInput" 
                           value="<?php echo htmlspecialchars($datosFormulario['fecha_reclamo'] ?? date('Y-m-d')); ?>" 
                           class="date-input" required>
                    <i class="fas fa-calendar-alt calendar-icon"></i>
                </div>
                
                <div class="form-group" style="flex: 1; position: relative;">
                    <label class="required">Fecha del Evento</label>
                    <input type="text" name="fecha_evento_display" id="fechaEventoDisplay" 
                           value="<?php echo !empty($datosFormulario['fecha_evento']) ? 
                               formatoFechaEspanol($datosFormulario['fecha_evento']) : 
                               formatoFechaEspanol(); ?>" 
                           class="date-display" readonly>
                    <input type="date" name="fecha_evento" id="fechaEventoInput" 
                           value="<?php echo htmlspecialchars($datosFormulario['fecha_evento'] ?? date('Y-m-d')); ?>" 
                           class="date-input" required>
                    <i class="fas fa-calendar-alt calendar-icon"></i>
                </div>
                
                <div class="form-group" style="flex: 1;">
                    <label class="required">Hora del Evento</label>
                    <input type="time" name="hora_evento" value="<?php echo htmlspecialchars($datosFormulario['hora_evento'] ?? ''); ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label class="required">Medio de Compra</label>
                <select name="medio_compra" required>
                    <option value="">Seleccione una opción</option>
                    <option value="Directo en Local" <?php echo ($datosFormulario['medio_compra'] ?? '') === 'Directo en Local' ? 'selected' : ''; ?>>Directo en Local</option>
                    <option value="Delivery Coordinado por Whatsapp" <?php echo ($datosFormulario['medio_compra'] ?? '') === 'Delivery Coordinado por Whatsapp' ? 'selected' : ''; ?>>Delivery Coordinado por Whatsapp</option>
                    <option value="Pedidos Ya" <?php echo ($datosFormulario['medio_compra'] ?? '') === 'Pedidos Ya' ? 'selected' : ''; ?>>Pedidos Ya</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="required">Productos en Reclamo + Precio</label>
                <div class="productos-container" id="productosContainer">
                    <!-- Los productos se agregarán aquí dinámicamente -->
                </div>
                <button type="button" class="btn-add-producto" id="btnAddProducto">
                    <i class="fas fa-plus"></i> Agregar Producto
                </button>
                <input type="hidden" name="productos" id="productosHidden" value="<?php echo htmlspecialchars($datosFormulario['productos'] ?? '[]'); ?>">
            </div>
            
            <div class="form-group">
                <label class="required">Grupo de Reclamo</label>
                <select name="grupo_id" id="grupo_id" required>
                    <option value="">Seleccione un grupo</option>
                    <?php foreach ($gruposReclamos as $grupo): ?>
                        <option value="<?php echo $grupo['id']; ?>" <?php echo ($datosFormulario['grupo_id'] ?? '') == $grupo['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($grupo['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="required">Tipo de Reclamo</label>
                <select name="tipo_reclamo_id" id="tipo_reclamo_id" required>
                    <option value="">Seleccione un tipo</option>
                    <!-- Se poblará dinámicamente -->
                </select>
            </div>
            
            <div class="form-group">
                <label class="required">Descripción del Reclamo</label>
                <textarea name="descripcion" required><?php echo htmlspecialchars($datosFormulario['descripcion'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label class="required">Investigación Preliminar</label>
                <textarea name="investigacion_preliminar" required><?php echo htmlspecialchars($datosFormulario['investigacion_preliminar'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label class="required">Evidencia (Fotos o Videos)</label>
                <div class="file-upload-container">
                    <div class="file-upload-group">
                        <label class="file-upload-label" for="fotosInput">
                            <i class="fas fa-camera"></i>
                            <span>Agregar fotos</span>
                        </label>
                        <input type="file" name="fotos[]" id="fotosInput" class="file-upload-input" multiple accept="image/*">
                    </div>
                    
                    <div class="file-upload-group">
                        <label class="file-upload-label" for="videosInput">
                            <i class="fas fa-video"></i>
                            <span>Agregar videos</span>
                        </label>
                        <input type="file" name="videos[]" id="videosInput" class="file-upload-input" multiple accept="video/*">
                    </div>
                </div>
                <div class="file-info" id="fileInfo">No se han seleccionado archivos</div>
                <div class="media-preview-container" id="mediaPreviewContainer">
                    <!-- Las previsualizaciones de medios se agregarán aquí -->
                </div>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="confirmCancel()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar Reclamo</button>
            </div>
        </form>
    </div>

    <script>
        // Datos de tipos de reclamos desde PHP
        const tiposReclamos = <?php echo $tiposReclamosJSON; ?>;
        
        // Elementos de categorización
        const grupoSelect = document.getElementById('grupo_id');
        const tipoSelect = document.getElementById('tipo_reclamo_id');
        
        function actualizarTipos() {
            const grupoId = grupoSelect.value;
            const tipoIdOriginal = "<?php echo $datosFormulario['tipo_reclamo_id'] ?? ''; ?>";
            
            // Limpiar tipos actuales
            tipoSelect.innerHTML = '<option value="">Seleccione un tipo</option>';
            
            if (grupoId) {
                const tiposFiltrados = tiposReclamos.filter(t => t.grupo_id == grupoId);
                
                tiposFiltrados.forEach(tipo => {
                    const option = document.createElement('option');
                    option.value = tipo.id;
                    option.textContent = tipo.nombre;
                    if (tipo.id == tipoIdOriginal) {
                        option.selected = true;
                    }
                    tipoSelect.appendChild(option);
                });
            }
        }
        
        grupoSelect.addEventListener('change', actualizarTipos);
        
        // Inicializar tipos si ya hay un grupo seleccionado (por ejemplo, después de error de validación)
        if (grupoSelect.value) {
            actualizarTipos();
        }

        // Productos
        const productosContainer = document.getElementById('productosContainer');
        const productosHidden = document.getElementById('productosHidden');
        const btnAddProducto = document.getElementById('btnAddProducto');
        
        // Cargar productos si ya existían
        let productos = JSON.parse(productosHidden.value);
        
        function renderProductos() {
            productosContainer.innerHTML = '';
            
            productos.forEach((producto, index) => {
                const productoDiv = document.createElement('div');
                productoDiv.className = 'producto-item';
                
                productoDiv.innerHTML = `
                    <input type="text" placeholder="Producto" class="producto-nombre" value="${producto.producto}" data-index="${index}">
                    <input type="number" placeholder="Precio (C$)" class="producto-precio" value="${producto.precio}" data-index="${index}" step="0.01" min="0">
                    <button type="button" class="btn-remove-producto" data-index="${index}">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                
                productosContainer.appendChild(productoDiv);
            });
            
            // Actualizar el campo hidden
            productosHidden.value = JSON.stringify(productos);
            
            // Agregar event listeners a los nuevos inputs
            document.querySelectorAll('.producto-nombre').forEach(input => {
                input.addEventListener('change', updateProductos);
            });
            
            document.querySelectorAll('.producto-precio').forEach(input => {
                input.addEventListener('change', updateProductos);
                input.addEventListener('blur', function() {
                    if (this.value.trim() === '') {
                        this.value = '0';
                        updateProductos.call(this);
                    }
                });
            });
            
            document.querySelectorAll('.btn-remove-producto').forEach(btn => {
                btn.addEventListener('click', removeProducto);
            });
        }
        
        function updateProductos() {
            const index = parseInt(this.dataset.index);
            const field = this.classList.contains('producto-nombre') ? 'producto' : 'precio';
            
            let value;
            if (field === 'precio') {
                value = this.value.trim() === '' ? 0 : parseFloat(this.value) || 0;
                this.value = value;
            } else {
                value = this.value;
            }
            
            productos[index][field] = value;
            productosHidden.value = JSON.stringify(productos);
        }
        
        function addProducto() {
            productos.push({ producto: '', precio: 0 });
            renderProductos();
        }
        
        function removeProducto() {
            const index = parseInt(this.dataset.index);
            productos.splice(index, 1);
            renderProductos();
        }
        
        btnAddProducto.addEventListener('click', addProducto);
        
        // Inicializar productos si no hay ninguno
        if (productos.length === 0) {
            addProducto();
        } else {
            renderProductos();
        }
        
        // Previsualización de medios (fotos y videos)
        const fotosInput = document.getElementById('fotosInput');
        const videosInput = document.getElementById('videosInput');
        const mediaPreviewContainer = document.getElementById('mediaPreviewContainer');
        const fileInfo = document.getElementById('fileInfo');
        
        // Array para mantener un registro de todos los archivos seleccionados
        let selectedFiles = {
            fotos: [],
            videos: []
        };
        
        // Función para manejar la selección de archivos
        function handleFileSelection(type, files) {
            if (files && files.length > 0) {
                Array.from(files).forEach(file => {
                    // Verificar si el archivo ya existe en el array
                    const fileExists = selectedFiles[type].some(f => 
                        f.name === file.name && 
                        f.size === file.size && 
                        f.lastModified === file.lastModified
                    );
                    
                    if (!fileExists) {
                        selectedFiles[type].push(file);
                    }
                });
                
                // Actualizar el input de archivos con todos los archivos seleccionados
                const dataTransfer = new DataTransfer();
                selectedFiles[type].forEach(file => {
                    dataTransfer.items.add(file);
                });
                
                if (type === 'fotos') {
                    fotosInput.files = dataTransfer.files;
                } else {
                    videosInput.files = dataTransfer.files;
                }
                
                // Actualizar la información del archivo
                updateFileInfo();
                
                // Actualizar las previsualizaciones
                updateMediaPreviews();
            }
        }
        
        // Event listeners para los inputs de archivos
        fotosInput.addEventListener('change', function() {
            handleFileSelection('fotos', this.files);
        });
        
        videosInput.addEventListener('change', function() {
            handleFileSelection('videos', this.files);
        });
        
        function updateFileInfo() {
            const totalFotos = selectedFiles.fotos.length;
            const totalVideos = selectedFiles.videos.length;
            const totalArchivos = totalFotos + totalVideos;
            
            if (totalArchivos === 0) {
                fileInfo.textContent = 'No se han seleccionado archivos';
            } else {
                let infoText = `${totalArchivos} archivo(s) seleccionado(s): `;
                
                if (totalFotos > 0) {
                    infoText += `${totalFotos} foto(s)`;
                }
                
                if (totalVideos > 0) {
                    if (totalFotos > 0) infoText += ', ';
                    infoText += `${totalVideos} video(s)`;
                }
                
                fileInfo.textContent = infoText;
            }
        }
        
        function updateMediaPreviews() {
            mediaPreviewContainer.innerHTML = '';
            
            // Procesar fotos
            selectedFiles.fotos.forEach((file, index) => {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const mediaPreview = document.createElement('div');
                    mediaPreview.className = 'media-preview';
                    
                    mediaPreview.innerHTML = `
                        <img src="${e.target.result}" alt="Preview">
                        <div class="media-icon"><i class="fas fa-camera"></i></div>
                        <button type="button" class="remove-media" data-type="fotos" data-index="${index}">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    
                    mediaPreviewContainer.appendChild(mediaPreview);
                    
                    // Agregar evento para eliminar imagen
                    mediaPreview.querySelector('.remove-media').addEventListener('click', function() {
                        const type = this.dataset.type;
                        const removeIndex = parseInt(this.dataset.index);
                        selectedFiles[type].splice(removeIndex, 1);
                        
                        // Actualizar el input de archivos
                        const dataTransfer = new DataTransfer();
                        selectedFiles[type].forEach(file => {
                            dataTransfer.items.add(file);
                        });
                        
                        if (type === 'fotos') {
                            fotosInput.files = dataTransfer.files;
                        } else {
                            videosInput.files = dataTransfer.files;
                        }
                        
                        // Actualizar la información y las previsualizaciones
                        updateFileInfo();
                        updateMediaPreviews();
                    });
                };
                
                reader.readAsDataURL(file);
            });
            
            // Procesar videos
            selectedFiles.videos.forEach((file, index) => {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const mediaPreview = document.createElement('div');
                    mediaPreview.className = 'media-preview';
                    
                    mediaPreview.innerHTML = `
                        <video src="${e.target.result}" muted loop></video>
                        <div class="media-icon"><i class="fas fa-video"></i></div>
                        <button type="button" class="remove-media" data-type="videos" data-index="${index}">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    
                    mediaPreviewContainer.appendChild(mediaPreview);
                    
                    // Agregar evento para eliminar video
                    mediaPreview.querySelector('.remove-media').addEventListener('click', function() {
                        const type = this.dataset.type;
                        const removeIndex = parseInt(this.dataset.index);
                        selectedFiles[type].splice(removeIndex, 1);
                        
                        // Actualizar el input de archivos
                        const dataTransfer = new DataTransfer();
                        selectedFiles[type].forEach(file => {
                            dataTransfer.items.add(file);
                        });
                        
                        if (type === 'fotos') {
                            fotosInput.files = dataTransfer.files;
                        } else {
                            videosInput.files = dataTransfer.files;
                        }
                        
                        // Actualizar la información y las previsualizaciones
                        updateFileInfo();
                        updateMediaPreviews();
                    });
                };
                
                reader.readAsDataURL(file);
            });
        }
        
        // Confirmación antes de enviar o cancelar
        function confirmCancel() {
            if (confirm('¿Está seguro que desea cancelar? Los datos ingresados se perderán.')) {
                window.location.href = 'index.php';
            }
        }
        
        document.getElementById('reclamoForm').addEventListener('submit', function(e) {
            // Validar que al menos un producto tenga nombre
            const productosValidos = productos.filter(p => p.producto.trim() !== '');
            
            if (productosValidos.length === 0) {
                e.preventDefault();
                alert('Debe agregar al menos un producto válido');
                return;
            }
            
            // Validar que ningún precio esté vacío
            const preciosInvalidos = productos.some(p => p.precio === null || p.precio === undefined || p.precio === '');
            
            if (preciosInvalidos) {
                e.preventDefault();
                alert('El campo de Precio no puede estar vacío. Si el producto no tiene costo, ingrese 0.');
                return;
            }
            
            // Validar que haya al menos una foto o video seleccionado
            if (selectedFiles.fotos.length === 0 && selectedFiles.videos.length === 0) {
                e.preventDefault();
                alert('Debe agregar al menos una foto o video de evidencia');
                return;
            }
            
            if (!confirm('¿Está seguro que desea guardar este reclamo?')) {
                e.preventDefault();
            }
        });
        
        // Mostrar errores específicos
        <?php if (!empty($errores)): ?>
            setTimeout(() => {
                const firstErrorField = document.querySelector('[name="<?php echo array_key_first($datosFormulario); ?>"]');
                if (firstErrorField) {
                    firstErrorField.focus();
                    
                    if (firstErrorField.tagName === 'SELECT') {
                        firstErrorField.size = firstErrorField.options.length;
                        firstErrorField.addEventListener('blur', function() {
                            this.size = 1;
                        });
                    }
                }
            }, 100);
        <?php endif; ?>
        
        // Control del selector de gestor
        const gestorSelect = document.getElementById('gestorSelect');
        const gestorInput = document.getElementById('gestorInput');
        const gestorHidden = document.getElementById('gestorHidden');
        
        function actualizarGestor() {
            if (gestorSelect.value === 'otro') {
                gestorSelect.style.display = 'none';
                gestorInput.style.display = 'block';
                gestorInput.focus();
                
                if (gestorInput.value.trim() !== '') {
                    gestorHidden.value = gestorInput.value;
                }
            } else if (gestorSelect.value !== '') {
                gestorInput.style.display = 'none';
                gestorSelect.style.display = 'block';
                gestorHidden.value = gestorSelect.value;
            }
        }
        
        gestorSelect.addEventListener('change', actualizarGestor);
        gestorInput.addEventListener('input', function() {
            gestorHidden.value = this.value;
        });
        
        actualizarGestor();
        
        // Función para formatear fechas
        function formatDate(inputDate) {
            if (!inputDate) return '';
            
            const dateParts = inputDate.split('-');
            if (dateParts.length !== 3) return '';
            
            const year = parseInt(dateParts[0]);
            const month = parseInt(dateParts[1]) - 1;
            const day = parseInt(dateParts[2]);
            
            const date = new Date(year, month, day);
            if (isNaN(date.getTime())) {
                return '';
            }
            
            const months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
            const formattedDay = date.getDate().toString().padStart(2, '0');
            const formattedMonth = months[date.getMonth()];
            const formattedYear = date.getFullYear();
            
            return `${formattedDay}-${formattedMonth}-${formattedYear}`;
        }
        
        // Función para parsear fechas en formato dd-mmm-aaaa
        function parseDisplayDate(displayDate) {
            if (!displayDate) return null;
            
            const monthMap = {
                'ene': '01', 'feb': '02', 'mar': '03', 'abr': '04', 
                'may': '05', 'jun': '06', 'jul': '07', 'ago': '08', 
                'sep': '09', 'oct': '10', 'nov': '11', 'dic': '12'
            };
            
            const parts = displayDate.toLowerCase().split('-');
            if (parts.length !== 3) return null;
            
            const day = parts[0].padStart(2, '0');
            const month = monthMap[parts[1]] || '01';
            const year = parts[2];
            
            const tempDate = new Date(`${year}-${month}-${day}`);
            if (isNaN(tempDate.getTime())) return null;
            
            return `${year}-${month}-${day}`;
        }
        
        // Inicializar datepickers para las fechas
        document.addEventListener('DOMContentLoaded', function() {
            // Configurar para Fecha del Reclamo
            const fechaReclamoDisplay = document.getElementById('fechaReclamoDisplay');
            const fechaReclamoInput = document.getElementById('fechaReclamoInput');
            
            // Configurar para Fecha del Evento
            const fechaEventoDisplay = document.getElementById('fechaEventoDisplay');
            const fechaEventoInput = document.getElementById('fechaEventoInput');
            
            // Actualizar display cuando cambia el input date
            fechaReclamoInput.addEventListener('change', function() {
                if (this.value) {
                    fechaReclamoDisplay.value = formatDate(this.value);
                }
            });
            
            fechaEventoInput.addEventListener('change', function() {
                if (this.value) {
                    fechaEventoDisplay.value = formatDate(this.value);
                }
            });
            
            // Convertir el valor display al formato date cuando se hace clic
            fechaReclamoDisplay.addEventListener('click', function() {
                const dateValue = parseDisplayDate(this.value) || new Date().toISOString().split('T')[0];
                fechaReclamoInput.value = dateValue;
                fechaReclamoInput.click();
            });
            
            fechaEventoDisplay.addEventListener('click', function() {
                const dateValue = parseDisplayDate(this.value) || new Date().toISOString().split('T')[0];
                fechaEventoInput.value = dateValue;
                fechaEventoInput.click();
            });
            
            // También permitir que el ícono del calendario abra el datepicker
            document.querySelectorAll('.calendar-icon').forEach(icon => {
                icon.addEventListener('click', function() {
                    const inputId = this.previousElementSibling.id;
                    document.getElementById(inputId).click();
                });
            });
        });
    </script>
</body>
</html>
