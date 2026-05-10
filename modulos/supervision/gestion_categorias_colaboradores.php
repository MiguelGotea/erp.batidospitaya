<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

require_once '../../core/auth/auth.php'; // Se centralizó el acceso a auth, db y funciones

// Obtener usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

if (!$esAdmin && !verificarAccesoCargo([16])) {
    header('Location: /index.php');
    exit();
}

// Configurar directorio para fotos
$directorioFotos = 'uploads/categorias_examenes/';
if (!file_exists($directorioFotos)) {
    mkdir($directorioFotos, 0777, true);
}

// Procesar acciones
$mensaje = '';
$error = '';

// Procesar formulario de categorías
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_categoria'])) {
    try {
        if ($_POST['accion_categoria'] === 'crear') {
            // Crear nueva categoría
            $stmt = $conn->prepare("INSERT INTO CategoriasOperarios (NombreCategoria, Peso) VALUES (?, ?)");
            $stmt->execute([$_POST['nombre_categoria'], $_POST['peso']]);
            $mensaje = 'Categoría creada exitosamente';
        } elseif ($_POST['accion_categoria'] === 'editar') {
            // Editar categoría existente
            $stmt = $conn->prepare("UPDATE CategoriasOperarios SET NombreCategoria = ?, Peso = ? WHERE idCategoria = ?");
            $stmt->execute([$_POST['nombre_categoria'], $_POST['peso'], $_POST['id_categoria']]);
            $mensaje = 'Categoría actualizada exitosamente';
        } elseif ($_POST['accion_categoria'] === 'eliminar') {
            // Eliminar categoría (solo si no está asignada a operarios)
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM OperariosCategorias WHERE idCategoria = ?");
            $stmt->execute([$_POST['id_categoria']]);
            $result = $stmt->fetch();
            
            if ($result['total'] > 0) {
                $error = 'No se puede eliminar la categoría porque está asignada a colaboradores';
            } else {
                $stmt = $conn->prepare("DELETE FROM CategoriasOperarios WHERE idCategoria = ?");
                $stmt->execute([$_POST['id_categoria']]);
                $mensaje = 'Categoría eliminada exitosamente';
            }
        }
    } catch (PDOException $e) {
        $error = 'Error al procesar la categoría: ' . $e->getMessage();
    }
}

// Procesar formulario de asignación de categorías a operarios
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_asignacion'])) {
    try {
        // Procesar foto del examen si se subió
        $nombreArchivoFoto = null;
        if (isset($_FILES['foto_examen']) && $_FILES['foto_examen']['error'] === UPLOAD_ERR_OK) {
            $extension = pathinfo($_FILES['foto_examen']['name'], PATHINFO_EXTENSION);
            $nombreArchivoFoto = 'examen_' . uniqid() . '.' . $extension;
            $rutaArchivo = $directorioFotos . $nombreArchivoFoto;
            
            if (!move_uploaded_file($_FILES['foto_examen']['tmp_name'], $rutaArchivo)) {
                throw new Exception("Error al subir la foto del examen");
            }
        }
        
        if ($_POST['accion_asignacion'] === 'asignar') {
            // Asignar categoría a operario
            $fechaInicio = $_POST['fecha_inicio'] ?? date('Y-m-d');
            // Cambio importante aquí: convertir cadena vacía a NULL
            $fechaFin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
            
            $stmt = $conn->prepare("
                INSERT INTO OperariosCategorias 
                (CodOperario, idCategoria, FechaInicio, FechaFin, FotoExamen) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['cod_operario'],
                $_POST['id_categoria'],
                $fechaInicio,
                $fechaFin,
                $nombreArchivoFoto
            ]);
            $mensaje = 'Categoría asignada al colaborador exitosamente';
        } elseif ($_POST['accion_asignacion'] === 'actualizar') {
            // Actualizar asignación existente
            // Cambio importante aquí: convertir cadena vacía a NULL
            $fechaFin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
            
            // Si se subió nueva foto, actualizarla
            if ($nombreArchivoFoto) {
                // Primero obtener la foto anterior para eliminarla
                $stmt = $conn->prepare("SELECT FotoExamen FROM OperariosCategorias WHERE id = ?");
                $stmt->execute([$_POST['id_asignacion']]);
                $fotoAnterior = $stmt->fetchColumn();
                
                if ($fotoAnterior && file_exists($directorioFotos . $fotoAnterior)) {
                    unlink($directorioFotos . $fotoAnterior);
                }
                
                $stmt = $conn->prepare("
                    UPDATE OperariosCategorias 
                    SET idCategoria = ?, FechaFin = ?, FotoExamen = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['id_categoria'],
                    $fechaFin,
                    $nombreArchivoFoto,
                    $_POST['id_asignacion']
                ]);
            } else {
                $stmt = $conn->prepare("
                    UPDATE OperariosCategorias 
                    SET idCategoria = ?, FechaFin = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['id_categoria'],
                    $fechaFin,
                    $_POST['id_asignacion']
                ]);
            }
            $mensaje = 'Asignación de categoría actualizada exitosamente';
        } elseif ($_POST['accion_asignacion'] === 'eliminar') {
            // Eliminar asignación - primero eliminar la foto asociada
            $stmt = $conn->prepare("SELECT FotoExamen FROM OperariosCategorias WHERE id = ?");
            $stmt->execute([$_POST['id_asignacion']]);
            $fotoExamen = $stmt->fetchColumn();
            
            if ($fotoExamen && file_exists($directorioFotos . $fotoExamen)) {
                unlink($directorioFotos . $fotoExamen);
            }
            
            $stmt = $conn->prepare("DELETE FROM OperariosCategorias WHERE id = ?");
            $stmt->execute([$_POST['id_asignacion']]);
            $mensaje = 'Asignación de categoría eliminada exitosamente';
        }
    } catch (Exception $e) {
        $error = 'Error al procesar la asignación: ' . $e->getMessage();
        
        // Si se subió un archivo pero hubo error, eliminarlo
        if (isset($nombreArchivoFoto) && file_exists($directorioFotos . $nombreArchivoFoto)) {
            unlink($directorioFotos . $nombreArchivoFoto);
        }
    }
}

// Obtener todas las categorías
$stmt = $conn->query("SELECT * FROM CategoriasOperarios ORDER BY Peso");
$categorias = $stmt->fetchAll();

// Obtener todas las asignaciones de categorías con información de operarios
$hoy = date('Y-m-d');

// Primero obtenemos la asignación de cargo más reciente para cada operario
$asignaciones = $conn->query("
    SELECT 
        oc.id, 
        oc.CodOperario, 
        oc.idCategoria, 
        oc.FechaInicio, 
        oc.FechaFin, 
        oc.FotoExamen,
        o.Nombre, 
        o.Nombre2, 
        o.Apellido, 
        o.Apellido2,
        co.NombreCategoria, 
        co.Peso,
        CASE 
            WHEN oc.FechaFin IS NULL OR oc.FechaFin >= '$hoy' THEN 'Activa'
            ELSE 'Inactiva'
        END as Estado,
        CASE
            WHEN anc.Fin IS NULL OR anc.Fin >= '$hoy' THEN 'Activo'
            WHEN anc.Fin >= DATE_SUB('$hoy', INTERVAL 7 DAY) THEN 'Recién terminado'
            ELSE 'Inactivo'
        END as EstadoCargo
    FROM OperariosCategorias oc
    JOIN Operarios o ON oc.CodOperario = o.CodOperario
    JOIN CategoriasOperarios co ON oc.idCategoria = co.idCategoria
    JOIN (
        -- Subconsulta para obtener la asignación de cargo más reciente de cada operario
        SELECT a1.CodOperario, a1.Sucursal, a1.Fin
        FROM AsignacionNivelesCargos a1
        WHERE (a1.Fin IS NULL OR a1.Fin >= DATE_SUB('$hoy', INTERVAL 7 DAY))
        AND a1.Fecha = (
            SELECT MAX(a2.Fecha) 
            FROM AsignacionNivelesCargos a2 
            WHERE a2.CodOperario = a1.CodOperario
            AND (a2.Fin IS NULL OR a2.Fin >= DATE_SUB('$hoy', INTERVAL 7 DAY))
        )
    ) anc ON o.CodOperario = anc.CodOperario
    WHERE (anc.Sucursal NOT IN (0,1,6,8,15,18))
    ORDER BY o.Nombre, o.Apellido, oc.FechaInicio DESC
")->fetchAll();

// Obtener todos los operarios activos (no solo los con cargo 2 e incluyendo los que terminaron hace menos de 7 días)
$operarios = $conn->query("
    SELECT DISTINCT o.CodOperario, o.Nombre, o.Nombre2, o.Apellido, o.Apellido2 
    FROM Operarios o
    JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
    WHERE 
    -- o.Operativo = 1 AND
    (anc.Sucursal NOT IN (0,1,6,8,15,18))
    AND (anc.Fin IS NULL OR anc.Fin >= DATE_SUB(CURDATE(), INTERVAL 7 DAY))
    ORDER BY o.Nombre, o.Apellido
")->fetchAll();

// Obtener operarios sin categoría asignada (activa incluyendo los que terminaron hace menos de 7 días) - SIN DUPLICADOS
$operariosSinCategoria = $conn->query("
    SELECT DISTINCT o.CodOperario, o.Nombre, o.Nombre2, o.Apellido, o.Apellido2 
    FROM Operarios o
    JOIN (
        -- Subconsulta para obtener la asignación de cargo más reciente de cada operario
        SELECT a1.CodOperario, a1.CodNivelesCargos, a1.Sucursal, a1.Fin
        FROM AsignacionNivelesCargos a1
        WHERE (a1.Fin IS NULL OR a1.Fin >= DATE_SUB(CURDATE(), INTERVAL 7 DAY))
        AND a1.Fecha = (
            SELECT MAX(a2.Fecha) 
            FROM AsignacionNivelesCargos a2 
            WHERE a2.CodOperario = a1.CodOperario
            AND (a2.Fin IS NULL OR a2.Fin >= DATE_SUB(CURDATE(), INTERVAL 7 DAY))
        )
    ) anc ON o.CodOperario = anc.CodOperario
    WHERE 
    -- o.Operativo = 1 AND
    anc.CodNivelesCargos NOT IN (27,24,20)
    AND (anc.Sucursal NOT IN (0,1,6,8,15,18))
    AND NOT EXISTS (
        SELECT 1 
        FROM OperariosCategorias oc
        WHERE oc.CodOperario = o.CodOperario
        AND (oc.FechaFin IS NULL OR oc.FechaFin >= CURDATE())
    )
    ORDER BY o.Nombre, o.Apellido
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Categorías de Colaboradores</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Calibri', sans-serif;
            font-size: clamp(11px, 2vw, 16px) !important;
        }
        
        body {
            background-color: #F6F6F6;
            color: #333;
            padding: 5px;
        }
        
        .container {
            max-width: 100%;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 10px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        
        .title {
            color: #0E544C;
            font-size: 1.5rem !important;
        }
        
        .subtitle {
            color: #51B8AC;
            font-size: 1.2rem !important;
            margin-bottom: 20px;
        }
        
        .section {
            margin-bottom: 30px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .section-title {
            color: #0E544C;
            margin-bottom: 15px;
            font-size: 1.2rem !important;
        }
        
        .btn {
            padding: 8px 15px;
            background-color: #51B8AC;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #0E544C;
        }
        
        .btn-secondary {
            background-color: #6c757d;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .btn-danger {
            background-color: #dc3545;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
        }

        th {
            background-color: #0E544C;
            color: white;
        }
        
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #0E544C;
        }
        
        input, select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 100%;
        }
        
        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .form-inline {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .form-inline .form-group {
            flex: 1;
            margin-bottom: 0;
        }
        
        .actions {
            display: flex;
            gap: 5px;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 7px;
            border-radius: 10px;
            font-size: 0.8rem !important;
            font-weight: bold;
        }
        
        .badge-primary {
            background-color: #007bff;
            color: white;
        }
        
        .badge-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .badge-success {
            background-color: #28a745;
            color: white;
        }
        
        .badge-warning {
            background-color: #ffc107;
            color: #212529;
        }
        
        .badge-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .status-active {
            color: #28a745;
        }
        
        .status-inactive {
            color: #dc3545;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
            border-radius: 5px;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: black;
        }
        
        @media (max-width: 768px) {
            .form-inline {
                flex-direction: column;
                align-items: stretch;
            }
            
            .form-inline .form-group {
                margin-bottom: 15px;
            }
        }
        
        /* Nuevos estilos para la sección de fotos */
        .photo-preview {
            max-width: 200px;
            max-height: 200px;
            margin-top: 10px;
            display: none;
        }
        
        .camera-container {
            position: relative;
            margin-bottom: 15px;
        }
        
        #video {
            border: 2px solid #51B8AC;
            border-radius: 8px;
            margin: 10px 0;
            max-width: 100%;
        }
        
        #canvas {
            display: none;
        }
        
        .camera-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .camera-btn {
            background-color: #51B8AC;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .camera-btn:hover {
            background-color: #0E544C;
        }
        
        .photo-options {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .photo-thumbnail {
            position: relative;
            width: 100px;
            height: 100px;
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow: hidden;
            margin-right: 10px;
            margin-bottom: 10px;
            display: inline-block;
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
        
        .view-photo {
            position: absolute;
            bottom: 2px;
            right: 2px;
            background: #51B8AC;
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
        
        .photo-gallery {
            margin-top: 15px;
        }
        
        /* Modal para ver foto */
        .photo-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8);
        }
        
        .modal-content {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100%;
        }
        
        .modal-photo {
            max-width: 90%;
            max-height: 90%;
        }
        
        /* Nuevos estilos para el modal de asignación */
        .modal-asignacion {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow: auto;
        }
        
        .modal-content-asignacion {
            background-color: #fefefe;
            margin: 2% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 90%;
            max-width: 800px;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .modal-title {
            color: #0E544C;
            font-size: 1.3rem;
            margin: 0;
        }
        
        .close-modal {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close-modal:hover {
            color: #000;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        /*Repetido*/
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #0E544C;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .photo-section {
            grid-column: 1 / -1;
            margin-top: 15px;
            padding: 15px;
            border: 1px dashed #ccc;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        
        /* Estilos para el campo de búsqueda */
        #busquedaAsignaciones {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 100%;
        }
        
        /* Estilos para el modal de categorías */
        .modal-categoria {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow: auto;
        }
        
        .modal-content-categoria {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 90%;
            max-width: 500px;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        /* En tu sección de estilos CSS */
        .btn-nueva-categoria {
            margin-bottom: 20px;
            background-color: #51B8AC;
            color: white;
        }
        
        .btn-nueva-categoria:hover {
            background-color: #0E544C;
        }
        
        /* Modal de confirmación - Estilo en filas */
        .modal-confirmacion .modal-content {
            padding: 10px 15px;
            border-radius: 8px;
            max-width: 500px;
            width: 40%;
            max-height: auto;
            height: 40%;
            margin: 10% auto;
            display: flex;
            flex-direction: column;
            gap: 15px; /* Espacio uniforme entre elementos */
        }
        
        .modal-confirmacion h3 {
            margin: 0;
            color: #0E544C;
            font-size: 1.3rem;
            text-align: center;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .modal-confirmacion p {
            margin: 0;
            line-height: 1.5;
            font-size: 1rem;
            text-align: center;
            padding: 0 10px;
        }
        
        .modal-confirmacion .modal-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
            padding-top: 15px;
            margin-top: 10px;
            border-top: 1px solid #f0f0f0;
        }
        
        /* Ajustes para móviles */
        @media (max-width: 480px) {
            .modal-confirmacion .modal-content {
                padding: 15px;
                gap: 12px;
            }
            
            .modal-confirmacion .modal-actions {
                flex-direction: column;
                gap: 10px;
            }
            
            .modal-confirmacion .modal-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="title">Gestión de Categorías de Colaboradores</h1>
            <div class="user-info">
                <span><?= htmlspecialchars($usuario['Nombre'] . ' ' . $usuario['Apellido']) ?></span>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-sign-out-alt"></i> Regresar
                </a>
            </div>
        </div>
        
        <?php if ($mensaje): ?>
            <div class="alert alert-success">
                <?= $mensaje ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?= $error ?>
            </div>
        <?php endif; ?>
        
        <!-- Sección de Categorías -->
        <div class="section">
            <h2 class="section-title">Categorías Disponibles</h2>
            
            <?php if (verificarAccesoCargo([16]) || $esAdmin): ?>
                <!-- Botón para abrir modal de nueva categoría -->
                <button type="button" class="btn btn-nueva-categoria" onclick="abrirModalCategoria()">
                    <i class="fas fa-plus"></i> Nueva Categoría
                </button>
            <?php endif; ?>
            
            <!-- Tabla de categorías -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th style="display:none;">ID</th>
                            <th>Nombre</th>
                            <th>Peso</th>
                            <?php if (verificarAccesoCargo([16]) || $esAdmin): ?>
                                <th>Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categorias as $categoria): ?>
                            <tr>
                                <td style="display:none;"><?= $categoria['idCategoria'] ?></td>
                                <td><?= htmlspecialchars($categoria['NombreCategoria']) ?></td>
                                <td><?= $categoria['Peso'] ?></td>
                                <?php if (verificarAccesoCargo([16]) || $esAdmin): ?>
                                    <td class="actions">
                                        <button class="btn" onclick="editarCategoria(<?= $categoria['idCategoria'] ?>, '<?= htmlspecialchars($categoria['NombreCategoria']) ?>', <?= $categoria['Peso'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-danger" onclick="confirmarEliminarCategoria(<?= $categoria['idCategoria'] ?>, '<?= htmlspecialchars($categoria['NombreCategoria']) ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Sección de Asignación de Categorías a Colaboradores -->
        <div class="section">
            <h2 class="section-title">Asignación de Categorías a Colaboradores</h2>
            
            <div class="form-inline" style="margin-bottom: 15px;">
                <div class="form-group" style="flex: 1;">
                    <label for="busquedaAsignaciones">Colaborador:</label>
                    <input type="text" id="busquedaAsignaciones" placeholder="Nombre o Código" onkeyup="buscarAsignaciones()">
                </div>
            </div>
            
            <!-- Tabla de todos los operarios con su categoría -->
            <div class="table-container" id="tablaAsignacionesContainer">
                <table id="tablaAsignaciones">
                    <thead>
                        <tr>
                            <th>Colaborador</th>
                            <th>Categoría Actual</th>
                            <th>Peso</th>
                            <th>Fecha Inicio</th>
                            <th>Fecha Fin</th>
                            <th>Foto Examen</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Combinar operarios con y sin categoría
                        $todosOperarios = array_merge(
                            array_map(function($op) { 
                                $op['tiene_categoria'] = true; 
                                return $op; 
                            }, $asignaciones),
                            array_map(function($op) { 
                                $op['tiene_categoria'] = false; 
                                return $op; 
                            }, $operariosSinCategoria)
                        );
                        
                        foreach ($todosOperarios as $operario): 
                            $hoy = date('Y-m-d');
                            $nombreCompleto = htmlspecialchars(
                                $operario['Nombre'] . ' ' . 
                                (!empty($operario['Nombre2']) ? $operario['Nombre2'] . ' ' : '') . 
                                $operario['Apellido'] . ' ' . 
                                (!empty($operario['Apellido2']) ? $operario['Apellido2'] : '')
                            );
                            
                            if ($operario['tiene_categoria']) {
                                $activa = (!$operario['FechaFin'] || $operario['FechaFin'] >= $hoy) && $operario['FechaInicio'] <= $hoy;
                                $rutaFoto = $operario['FotoExamen'] ? 'uploads/categorias_examenes/' . $operario['FotoExamen'] : '';
                        ?>
                            <tr>
                                <td><?= $nombreCompleto ?> (<?= $operario['CodOperario'] ?>)</td>
                                <td><?= htmlspecialchars($operario['NombreCategoria']) ?></td>
                                <td><?= $operario['Peso'] ?></td>
                                <td><?= formatoFecha($operario['FechaInicio']) ?></td>
                                <td><?= $operario['FechaFin'] ? formatoFecha($operario['FechaFin']) : 'Indefinido' ?></td>
                                <td>
                                    <?php if ($operario['FotoExamen']): ?>
                                        <div class="photo-thumbnail">
                                            <img src="<?= $rutaFoto ?>" alt="Foto examen" style="max-width: 50px; max-height: 50px;">
                                            <button class="view-photo" onclick="verFotoModal('<?= $rutaFoto ?>')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        Sin foto
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($operario['Estado'] === 'Activa'): ?>
                                        <span class="status-active"><i class="fas fa-check-circle"></i> Activa</span>
                                    <?php else: ?>
                                        <span class="status-inactive"><i class="fas fa-times-circle"></i> Inactiva</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions">
                                    <button class="btn" onclick="editarAsignacion(
                                        <?= $operario['id'] ?>,
                                        <?= $operario['CodOperario'] ?>,
                                        <?= $operario['idCategoria'] ?>,
                                        '<?= $operario['FechaInicio'] ?>',
                                        '<?= $operario['FechaFin'] ?>',
                                        '<?= $operario['FotoExamen'] ?>'
                                    )">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button style="display:none;" class="btn btn-danger" onclick="confirmarEliminarAsignacion(<?= $operario['id'] ?>, '<?= $nombreCompleto ?>', '<?= htmlspecialchars($operario['NombreCategoria']) ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php } else { ?>
                            <tr>
                                <td><?= $nombreCompleto ?> (<?= $operario['CodOperario'] ?>)</td>
                                <td colspan="5" class="text-center">Sin categoría asignada</td>
                                <td class="actions">
                                    <button class="btn" onclick="asignarCategoria(
                                        <?= $operario['CodOperario'] ?>
                                    )">
                                        <i class="fas fa-plus"></i> Asignar
                                    </button>
                                </td>
                            </tr>
                        <?php } ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal para categorías -->
    <div id="modalCategoria" class="modal-asignacion">
        <div class="modal-content-asignacion">
            <div class="modal-header">
                <h2 class="modal-title" id="modalCategoriaTitulo">Nueva Categoría</h2>
                <span class="close-modal" onclick="cerrarModalCategoria()">&times;</span>
            </div>
            
            <form id="formCategoriaModal" method="post">
                <input type="hidden" id="id_categoria_modal" name="id_categoria" value="">
                <input type="hidden" id="accion_categoria_modal" name="accion_categoria" value="crear">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nombre_categoria_modal">Nombre de Categoría</label>
                        <input type="text" id="nombre_categoria_modal" name="nombre_categoria" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="peso_modal">Peso</label>
                        <input type="number" id="peso_modal" name="peso" step="0.1" min="0" max="10" required>
                    </div>
                </div>
                
                <div style="margin-top:20px; text-align:right;">
                    <button type="button" onclick="cerrarModalCategoria()" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn" id="btnGuardarCategoria">
                        <i class="fas fa-save"></i> Guardar Categoría
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal para asignación/edición de categorías -->
    <div id="modalAsignacion" class="modal-asignacion">
        <div class="modal-content-asignacion">
            <div class="modal-header">
                <h2 class="modal-title" id="modalAsignacionTitulo">Nueva Asignación de Categoría</h2>
                <span class="close-modal" onclick="cerrarModalAsignacion()">&times;</span>
            </div>
            
            <form id="formAsignacion" method="post" enctype="multipart/form-data" onsubmit="return validarFormulario()">
                <input type="hidden" id="id_asignacion" name="id_asignacion" value="">
                <input type="hidden" id="accion_asignacion" name="accion_asignacion" value="asignar">
                <input type="hidden" id="tiene_foto" name="tiene_foto" value="0">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="cod_operario">Colaborador</label>
                        <select id="cod_operario" name="cod_operario" required>
                            <option value="">Seleccionar colaborador</option>
                            <?php foreach ($operarios as $operario): ?>
                                <option value="<?= $operario['CodOperario'] ?>">
                                    <?= htmlspecialchars($operario['Nombre'] . ' ' . $operario['Apellido']) ?> (<?= $operario['CodOperario'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="id_categoria_asignar">Categoría</label>
                        <select id="id_categoria_asignar" name="id_categoria" required>
                            <option value="">Seleccionar categoría</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?= $categoria['idCategoria'] ?>">
                                    <?= htmlspecialchars($categoria['NombreCategoria']) ?> (Peso: <?= $categoria['Peso'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="fecha_inicio">Fecha Inicio</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="fecha_fin">Fecha Fin (opcional)</label>
                        <input type="date" id="fecha_fin" name="fecha_fin">
                    </div>
                </div>
                
                <!-- Sección de foto del examen -->
                <div class="photo-section">
                    <label>Foto del Examen de Categoría <span style="color:red;">*</span></label>
                    <div id="foto-error" class="error-message">Debe capturar o subir una foto del examen</div>
                    <div class="camera-container">
                        <select style="display:none;" id="selectorCamara" class="form-control">
                            <option value="">Seleccionar cámara...</option>
                        </select>
                        <video id="video" width="100%" height="auto" autoplay></video>
                        <canvas id="canvas" style="display:none;"></canvas>
                        
                        <div class="camera-buttons" style="margin-top:10px;">
                            <button type="button" id="capturarBtn" class="btn">
                                <i class="fas fa-camera"></i> Capturar Foto
                            </button>
                            <button type="button" id="reiniciarCamaraBtn" class="btn btn-secondary">
                                <i class="fas fa-sync-alt"></i> Reiniciar Cámara
                            </button>
                        </div>
                        
                        <div style="margin-top:10px;">
                            <label for="subirFoto" class="btn" style="display:inline-block;">
                                <i class="fas fa-upload"></i> Subir Foto
                                <input type="file" id="subirFoto" name="foto_examen" accept="image/*" capture="environment" style="display:none;">
                            </label>
                        </div>
                        
                        <div id="fotoPreviaContainer" style="margin-top:15px; display:none;">
                            <p>Foto capturada:</p>
                            <img id="fotoPrevia" style="max-width:100%; max-height:200px; border:1px solid #ddd; border-radius:5px;">
                            <div style="margin-top:10px;">
                                <button type="button" id="usarFotoBtn" class="btn">
                                    <i class="fas fa-check"></i> Usar esta Foto
                                </button>
                                <button type="button" onclick="cancelarFoto()" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancelar
                                </button>
                            </div>
                        </div>
                        
                        <div id="fotoAsignadaContainer" style="margin-top:15px; display:none;">
                            <p>Foto asignada:</p>
                            <img id="fotoAsignada" style="max-width:100%; max-height:200px; border:1px solid #ddd; border-radius:5px;">
                            <button type="button" onclick="eliminarFotoAsignada()" class="btn btn-danger" style="margin-top:10px;">
                                <i class="fas fa-trash"></i> Eliminar Foto
                            </button>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top:20px; text-align:right;">
                    <button type="button" onclick="cerrarModalAsignacion()" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn" id="btnGuardarAsignacion">
                        <i class="fas fa-save"></i> Guardar Asignación
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal para ver foto -->
    <div id="photoModal" class="photo-modal">
        <span class="close-modal" onclick="cerrarModalFoto()">&times;</span>
        <div class="modal-content">
            <img id="modalPhoto" class="modal-photo" src="" alt="Foto examen">
        </div>
    </div>
    
    <!-- Modal de confirmación para eliminar categoría -->
    <div id="modalEliminarCategoria" class="modal modal-confirmacion">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal('modalEliminarCategoria')">&times;</span>
            <h3>Confirmar Eliminación</h3>
            <p id="mensajeEliminarCategoria">¿Está seguro que desea eliminar esta categoría?</p>
            <form id="formEliminarCategoria" method="post">
                <input type="hidden" name="id_categoria" id="id_categoria_eliminar" value="">
                <input type="hidden" name="accion_categoria" value="eliminar">
                
                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalEliminarCategoria')">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal de confirmación para eliminar asignación -->
    <div id="modalEliminarAsignacion" class="modal modal-confirmacion">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal('modalEliminarAsignacion')">&times;</span>
            <h3>Confirmar Eliminación</h3>
            <p id="mensajeEliminarAsignacion">¿Está seguro que desea eliminar esta asignación de categoría?</p>
            <form id="formEliminarAsignacion" method="post">
                <input type="hidden" name="id_asignacion" id="id_asignacion_eliminar" value="">
                <input type="hidden" name="accion_asignacion" value="eliminar">
                
                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalEliminarAsignacion')">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Variables para la cámara
        let stream = null;
        let fotoCapturada = null;
        let fotoSubida = null;
        
        // Elementos del DOM
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const capturarBtn = document.getElementById('capturarBtn');
        const reiniciarCamaraBtn = document.getElementById('reiniciarCamaraBtn');
        const selectorCamara = document.getElementById('selectorCamara');
        const subirFotoInput = document.getElementById('subirFoto');
        const usarFotoBtn = document.getElementById('usarFotoBtn');
        const fotoPrevia = document.getElementById('fotoPrevia');
        const fotoAsignadaContainer = document.getElementById('fotoAsignadaContainer');
        const fotoAsignada = document.getElementById('fotoAsignada');
        const formAsignacion = document.getElementById('formAsignacion');
        
        // Función para abrir el modal de asignación
        function abrirModalAsignacion() {
            // Resetear el formulario
            document.getElementById('formAsignacion').reset();
            document.getElementById('id_asignacion').value = '';
            document.getElementById('accion_asignacion').value = 'asignar';
            document.getElementById('modalAsignacionTitulo').textContent = 'Nueva Asignación de Categoría';
            document.getElementById('fecha_inicio').value = new Date().toISOString().split('T')[0];
            
            // Resetear foto
            eliminarFotoAsignada();
            iniciarCamara();
            
            // Mostrar modal
            document.getElementById('modalAsignacion').style.display = 'block';
        }
        
        // Función para abrir modal de categoría
        function abrirModalCategoria(editar = false, id = null, nombre = '', peso = '') {
            if (editar) {
                document.getElementById('modalCategoriaTitulo').textContent = 'Editar Categoría';
                document.getElementById('id_categoria_modal').value = id;
                document.getElementById('nombre_categoria_modal').value = nombre;
                document.getElementById('peso_modal').value = peso;
                document.getElementById('accion_categoria_modal').value = 'editar';
            } else {
                document.getElementById('modalCategoriaTitulo').textContent = 'Nueva Categoría';
                document.getElementById('formCategoriaModal').reset();
                document.getElementById('id_categoria_modal').value = '';
                document.getElementById('accion_categoria_modal').value = 'crear';
            }
            
            document.getElementById('modalCategoria').style.display = 'block';
        }
        
        // Función para cerrar modal de categoría
        function cerrarModalCategoria() {
            document.getElementById('modalCategoria').style.display = 'none';
        }
        
        // Manejar envío del formulario de categoría modal
        document.getElementById('formCategoriaModal').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch(window.location.href, {  // Enviar a la misma página
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    window.location.reload(); // Recargar para ver cambios
                } else {
                    throw new Error('Error en la respuesta del servidor');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al guardar la categoría');
            });
        });
        
        // Actualiza los botones en la tabla de categorías para usar el modal
        function editarCategoria(id, nombre, peso) {
            abrirModalCategoria(true, id, nombre, peso);
        }
        
        // Función para buscar asignaciones
        function buscarAsignaciones() {
            const busqueda = document.getElementById('busquedaAsignaciones').value.toLowerCase();
            
            if (busqueda.length === 0) {
                // Si no hay búsqueda, mostrar todas las asignaciones
                cargarTablaAsignaciones(<?= json_encode($asignaciones) ?>);
                return;
            }
            
            // Realizar búsqueda con AJAX
            fetch('../../includes/ajax/buscar_asignaciones.php?q=' + encodeURIComponent(busqueda))
                .then(response => response.json())
                .then(data => {
                    cargarTablaAsignaciones(data);
                })
                .catch(error => {
                    console.error('Error en la búsqueda:', error);
                });
        }
        
        // Nueva función para asignar categoría a operario sin categoría
        function asignarCategoria(codOperario) {
            // Resetear el formulario
            document.getElementById('formAsignacion').reset();
            document.getElementById('id_asignacion').value = '';
            document.getElementById('accion_asignacion').value = 'asignar';
            document.getElementById('modalAsignacionTitulo').textContent = 'Asignar Categoría';
            document.getElementById('fecha_inicio').value = new Date().toISOString().split('T')[0];
            
            // Establecer el operario seleccionado y habilitar el select
            document.getElementById('cod_operario').value = codOperario;
            document.getElementById('cod_operario').disabled = false;
            
            // Resetear foto
            eliminarFotoAsignada();
            iniciarCamara();
            
            // Mostrar modal
            document.getElementById('modalAsignacion').style.display = 'block';
        }
        
        // Función para cargar datos en la tabla de asignaciones
        function cargarTablaAsignaciones(asignaciones) {
            const tabla = document.getElementById('tablaAsignaciones');
            const hoy = new Date().toISOString().split('T')[0];
            
            // Crear encabezados de tabla
            tabla.innerHTML = `
                <thead>
                    <tr>
                        <th>Colaborador</th>
                        <th>Sucursal</th>
                        <th>Categoría</th>
                        <th>Peso</th>
                        <th>Fecha Inicio</th>
                        <th>Fecha Fin</th>
                        <th>Foto Examen</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    ${asignaciones.map(asignacion => {
                        const activa = (!asignacion.FechaFin || asignacion.FechaFin >= hoy) && asignacion.FechaInicio <= hoy;
                        const rutaFoto = asignacion.FotoExamen ? 'uploads/categorias_examenes/' + asignacion.FotoExamen : '';
                        
                        return `
                            <tr>
                                <td>${escapeHtml(asignacion.Nombre + ' ' + asignacion.Apellido)} (${asignacion.CodOperario})</td>
                                <td>${escapeHtml(asignacion.nombre_sucursal)}</td>
                                <td>${escapeHtml(asignacion.NombreCategoria)}</td>
                                <td>${asignacion.Peso}</td>
                                <td>${formatoFecha(asignacion.FechaInicio)}</td>
                                <td>${asignacion.FechaFin ? formatoFecha(asignacion.FechaFin) : 'Indefinido'}</td>
                                <td>
                                    ${asignacion.FotoExamen ? `
                                        <div class="photo-thumbnail">
                                            <img src="${rutaFoto}" alt="Foto examen" style="max-width: 50px; max-height: 50px;">
                                            <button class="view-photo" onclick="verFotoModal('${rutaFoto}')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    ` : 'Sin foto'}
                                </td>
                                <td>
                                    ${activa ? `
                                        <span class="status-active"><i class="fas fa-check-circle"></i> Activa</span>
                                    ` : `
                                        <span class="status-inactive"><i class="fas fa-times-circle"></i> Inactiva</span>
                                    `}
                                </td>
                                <td class="actions">
                                    <button class="btn" onclick="editarAsignacion(
                                        ${asignacion.id},
                                        ${asignacion.CodOperario},
                                        '${asignacion.CodSucursal}',
                                        ${asignacion.idCategoria},
                                        '${asignacion.FechaInicio}',
                                        '${asignacion.FechaFin || ''}',
                                        '${asignacion.FotoExamen || ''}'
                                    )">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger" onclick="confirmarEliminarAsignacion(
                                        ${asignacion.id}, 
                                        '${escapeHtml(asignacion.Nombre + ' ' + asignacion.Apellido)}', 
                                        '${escapeHtml(asignacion.NombreCategoria)}'
                                    )">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    }).join('')}
                </tbody>
            `;
        }
        
        // Función auxiliar para escapar HTML
        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
        
        // Función para formatear fecha (similar a la de PHP)
        function formatoFecha(fecha) {
            if (!fecha) return '';
            const date = new Date(fecha);
            return date.toLocaleDateString('es-ES');
        }
        
        // Cargar tabla al inicio
        document.addEventListener('DOMContentLoaded', function() {
            cargarTablaAsignaciones(<?= json_encode($asignaciones) ?>);
        });
        
        // Función para listar cámaras disponibles
        async function listarCamaras() {
            try {
                const dispositivos = await navigator.mediaDevices.enumerateDevices();
                const camaras = dispositivos.filter(dispositivo => dispositivo.kind === 'videoinput');
                
                selectorCamara.innerHTML = '<option value="">Seleccionar cámara...</option>';
                
                camaras.forEach((camara, index) => {
                    const option = document.createElement('option');
                    option.value = camara.deviceId;
                    option.text = camara.label || `Cámara ${index + 1}`;
                    selectorCamara.appendChild(option);
                });
            } catch (error) {
                console.error("Error al listar cámaras:", error);
                alert("No se pudieron listar las cámaras disponibles.");
            }
        }
        
        // Función para iniciar la cámara
        async function iniciarCamara() {
            detenerCamara();
            
            try {
                const constraints = {
                    video: {
                        width: { ideal: 640 },
                        height: { ideal: 480 },
                        facingMode: 'environment' // Preferencia por cámara trasera en móviles
                    }
                };
                
                stream = await navigator.mediaDevices.getUserMedia(constraints);
                video.srcObject = stream;
                video.style.display = 'block';
                canvas.style.display = 'none';
                fotoPrevia.style.display = 'none';
            } catch (error) {
                console.error("Error al iniciar la cámara:", error);
                // Si falla con environment, intentar con user (frontal)
                try {
                    const constraints = {
                        video: {
                            width: { ideal: 640 },
                            height: { ideal: 480 },
                            facingMode: 'user'
                        }
                    };
                    
                    stream = await navigator.mediaDevices.getUserMedia(constraints);
                    video.srcObject = stream;
                    video.style.display = 'block';
                    canvas.style.display = 'none';
                    fotoPrevia.style.display = 'none';
                } catch (error) {
                    console.error("Error al iniciar cámara frontal:", error);
                    alert("No se pudo acceder a la cámara. Asegúrese de permitir el acceso.");
                }
            }
        }
        
        // Función para detener la cámara
        function detenerCamara() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
            video.srcObject = null;
        }
        
        // Función para capturar foto
        function capturarFoto() {
            if (!stream) return;
            
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            
            fotoCapturada = canvas.toDataURL('image/jpeg', 0.8);
            fotoPrevia.src = fotoCapturada;
            
            video.style.display = 'none';
            fotoPrevia.style.display = 'block';
            document.getElementById('fotoPreviaContainer').style.display = 'block';
            usarFotoBtn.disabled = false;
        }
        
        // Función para usar la foto capturada/subida
        function usarFoto() {
            if (!fotoCapturada && !fotoSubida) return;
            
            const fotoParaUsar = fotoSubida || fotoCapturada;
            document.getElementById('fotoAsignada').src = fotoParaUsar;
            document.getElementById('fotoAsignadaContainer').style.display = 'block';
            document.getElementById('foto-error').style.display = 'none';
            document.getElementById('tiene_foto').value = '1';
            
            // Crear un blob para enviar como archivo
            if (fotoSubida) {
                // Ya es un archivo, no necesitamos hacer nada
            } else {
                // Convertir data URL a blob
                const blob = dataURLtoBlob(fotoCapturada);
                const file = new File([blob], 'examen_categoria.jpg', { type: 'image/jpeg' });
                
                // Crear un nuevo DataTransfer para el archivo
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                document.getElementById('subirFoto').files = dataTransfer.files;
            }
            
            // Ocultar la previsualización
            document.getElementById('fotoPreviaContainer').style.display = 'none';
            // No reiniciamos la cámara aquí para permitir más fotos si es necesario
        }
        
        // Función para eliminar la foto asignada
        function eliminarFotoAsignada() {
            document.getElementById('fotoAsignada').src = '';
            document.getElementById('fotoAsignadaContainer').style.display = 'none';
            document.getElementById('tiene_foto').value = '0';
            document.getElementById('subirFoto').value = '';
            fotoCapturada = null;
            fotoSubida = null;
            reiniciarCamara();
        }
        
        // Función para cancelar la foto capturada/subida
        function cancelarFoto() {
            document.getElementById('fotoPreviaContainer').style.display = 'none';
            reiniciarCamara();
        }
        
        // Función para convertir data URL a blob
        function dataURLtoBlob(dataURL) {
            const arr = dataURL.split(',');
            const mime = arr[0].match(/:(.*?);/)[1];
            const bstr = atob(arr[1]);
            let n = bstr.length;
            const u8arr = new Uint8Array(n);
            
            while (n--) {
                u8arr[n] = bstr.charCodeAt(n);
            }
            
            return new Blob([u8arr], { type: mime });
        }
        
        // Función para reiniciar la cámara
        function reiniciarCamara() {
            detenerCamara();
            iniciarCamara(); // Eliminado el parámetro selectorCamara.value
            document.getElementById('fotoPreviaContainer').style.display = 'none';
            fotoCapturada = null;
            fotoSubida = null;
            document.getElementById('subirFoto').value = '';
        }
        
        // Función para ver foto en modal
        function verFotoModal(src) {
            const modal = document.getElementById('photoModal');
            const modalImg = document.getElementById('modalPhoto');
            
            modalImg.src = src;
            modal.style.display = 'block';
        }
        
        // Función para cerrar el modal de asignación
        function cerrarModalAsignacion() {
            detenerCamara();
            document.getElementById('modalAsignacion').style.display = 'none';
            // Habilitar select de operario al cerrar
            document.getElementById('cod_operario').disabled = false;
        }
        
        // Función para cerrar el modal de foto
        function cerrarModalFoto() {
            document.getElementById('photoModal').style.display = 'none';
        }
        
        // Event Listeners
        selectorCamara.addEventListener('change', () => {
            iniciarCamara(selectorCamara.value);
        });
        
        capturarBtn.addEventListener('click', capturarFoto);
        reiniciarCamaraBtn.addEventListener('click', reiniciarCamara);
        usarFotoBtn.addEventListener('click', usarFoto);
        
        subirFotoInput.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    fotoSubida = e.target.result;
                    fotoPrevia.src = fotoSubida;
                    fotoPrevia.style.display = 'block';
                    video.style.display = 'none';
                    usarFotoBtn.disabled = false;
                };
                
                reader.readAsDataURL(this.files[0]);
            }
        });
        
        // Validar formulario antes de enviar
        formAsignacion.addEventListener('submit', function(e) {
            if (!fotoAsignada.src && !document.getElementById('id_asignacion').value) {
                e.preventDefault();
                alert('Debe subir o capturar una foto del examen de categoría');
                return false;
            }
            return true;
        });
        
        // Prevenir envío del formulario con Enter
        document.addEventListener('DOMContentLoaded', function() {
            // Deshabilitar Enter en todos los inputs excepto textarea
            document.querySelectorAll('input:not([type="submit"]):not([type="button"]), select').forEach(input => {
                input.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        return false;
                    }
                });
            });
            
            // Validar foto al intentar enviar el formulario
            document.getElementById('formAsignacion').addEventListener('submit', function(e) {
                if (!validarFormulario()) {
                    e.preventDefault();
                    // Hacer scroll al error
                    document.getElementById('foto-error').scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'center'
                    });
                }
            });
        });
        
        // Funciones para manejar categorías
        function editarCategoria(id, nombre, peso) {
            document.getElementById('id_categoria').value = id;
            document.getElementById('nombre_categoria').value = nombre;
            document.getElementById('peso').value = peso;
            document.getElementById('accion_categoria').value = 'editar';
            document.getElementById('btnAccionCategoria').innerHTML = '<i class="fas fa-save"></i> Guardar Cambios';
            document.getElementById('btnCancelarEdicion').style.display = 'inline-block';
            
            // Scroll al formulario
            document.getElementById('formCategoria').scrollIntoView({ behavior: 'smooth' });
        }
        
        function confirmarEliminarCategoria(id, nombre) {
            document.getElementById('id_categoria_eliminar').value = id;
            document.getElementById('mensajeEliminarCategoria').innerHTML = 
                `¿Está seguro que desea eliminar la categoría <strong>${nombre}</strong>?`;
            document.getElementById('modalEliminarCategoria').style.display = 'block';
        }
        
        // Funciones para editar asignación (actualizada para manejar foto)
        function editarAsignacion(id, codOperario, idCategoria, fechaInicio, fechaFin, fotoExamen) {
            // Configurar formulario
            document.getElementById('id_asignacion').value = id;
            document.getElementById('cod_operario').value = codOperario;
            document.getElementById('id_categoria_asignar').value = idCategoria;
            document.getElementById('fecha_inicio').value = fechaInicio;
            document.getElementById('fecha_fin').value = fechaFin || '';
            document.getElementById('accion_asignacion').value = 'actualizar';
            document.getElementById('modalAsignacionTitulo').textContent = 'Editar Asignación de Categoría';
            
            // Deshabilitar el select de operario en modo edición
            document.getElementById('cod_operario').disabled = true;
            
            // Configurar foto existente si hay una
            if (fotoExamen) {
                fotoAsignada.src = 'uploads/categorias_examenes/' + fotoExamen;
                document.getElementById('fotoAsignadaContainer').style.display = 'block';
                document.getElementById('tiene_foto').value = '1';
                detenerCamara();
            } else {
                eliminarFotoAsignada();
                reiniciarCamara();
            }
            
            // Mostrar modal
            document.getElementById('modalAsignacion').style.display = 'block';
        }
        
        function confirmarEliminarAsignacion(id, nombreOperario, nombreCategoria) {
            document.getElementById('id_asignacion_eliminar').value = id;
            document.getElementById('mensajeEliminarAsignacion').innerHTML = 
                `¿Está seguro que desea eliminar la asignación de la categoría <strong>${nombreCategoria}</strong> al colaborador <strong>${nombreOperario}</strong>?`;
            document.getElementById('modalEliminarAsignacion').style.display = 'block';
        }
        
        // Función para validar el formulario antes de enviar
        function validarFormulario() {
            // Verificar si hay foto asignada
            const tieneFoto = document.getElementById('fotoAsignada').src || 
                             (document.getElementById('id_asignacion').value && 
                              !document.getElementById('fotoAsignadaContainer').style.display === 'none');
            
            if (!tieneFoto) {
                document.getElementById('foto-error').style.display = 'block';
                document.getElementById('fotoAsignadaContainer').style.border = '1px solid #dc3545';
                return false;
            }
            
            return true;
        }
        
        // Funciones generales
        function cerrarModal(id) {
            document.getElementById(id).style.display = 'none';
        }
        
        // Cancelar edición de categoría
        document.getElementById('btnCancelarEdicion').addEventListener('click', function() {
            document.getElementById('formCategoria').reset();
            document.getElementById('id_categoria').value = '';
            document.getElementById('accion_categoria').value = 'crear';
            document.getElementById('btnAccionCategoria').innerHTML = '<i class="fas fa-plus"></i> Agregar Categoría';
            this.style.display = 'none';
        });
        
        // Cancelar edición de asignación
        document.getElementById('btnCancelarEdicionAsignacion').addEventListener('click', function() {
            document.getElementById('formAsignacion').reset();
            document.getElementById('id_asignacion').value = '';
            document.getElementById('accion_asignacion').value = 'asignar';
            document.getElementById('btnAccionAsignacion').innerHTML = '<i class="fas fa-link"></i> Asignar Categoría';
            this.style.display = 'none';
            document.getElementById('fecha_inicio').value = '<?= date('Y-m-d') ?>';
        });
        
        // Validar que fecha fin sea posterior a fecha inicio
        document.getElementById('fecha_fin').addEventListener('change', function() {
            const fechaInicio = new Date(document.getElementById('fecha_inicio').value);
            const fechaFin = new Date(this.value);
            
            if (this.value && fechaFin < fechaInicio) {
                alert('La fecha fin no puede ser anterior a la fecha de inicio');
                this.value = '';
            }
        });
        
        // Cerrar modales al hacer clic fuera de ellos
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>