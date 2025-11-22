<?php
// Solo iniciar sesión si no está ya activa
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'models/Ticket.php';
require_once 'models/Chat.php';
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';
// Incluir el menú lateral
require_once '../../includes/menu_lateral.php';
// Incluir el header universal
require_once '../../includes/header_universal.php';

//******************************Estándar para header******************************
verificarAutenticacion();

$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';
// Obtener cargo del operario para el menú
$cargoOperario = $usuario['CodNivelesCargos'];
// Verificar acceso al módulo Mantenimiento (Código 14)
//verificarAccesoCargo(14, 16, 35);

// Verificar acceso al módulo
// FORMA CORRECTA - pasar como array
if (!verificarAccesoCargo([11, 14, 16, 35]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);

//******************************Estándar para header, termina******************************

$ticket = new Ticket();
$chat_model = new Chat();
$tickets = $ticket->getAll();
$tipos_casos = $ticket->getTiposCasos();



// Procesar envío de mensaje desde el sidebar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ticket_id_chat'])) {
    try {
        $ticket_id_chat = intval($_POST['ticket_id_chat']);
        $mensaje = trim($_POST['mensaje'] ?? '');
        $foto = null;
        
        // Manejar subida de foto
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/chat/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $extension = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $foto = 'chat_' . $ticket_id_chat . '_' . time() . '.' . $extension;
            move_uploaded_file($_FILES['foto']['tmp_name'], $uploadDir . $foto);
        }
        
        // Manejar foto desde cámara
        if (!empty($_POST['foto_camera'])) {
            $uploadDir = 'uploads/chat/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $img_data = $_POST['foto_camera'];
            $img_data = str_replace('data:image/jpeg;base64,', '', $img_data);
            $img_data = str_replace(' ', '+', $img_data);
            $data = base64_decode($img_data);
            
            $foto = 'chat_camera_' . $ticket_id_chat . '_' . time() . '.jpg';
            file_put_contents($uploadDir . $foto, $data);
        }
        
        if (!empty($mensaje) || $foto) {
            $chat_model->addMessage($ticket_id_chat, 'mantenimiento', 'Área de Mantenimiento', $mensaje, $foto);
            
            // Redirect para evitar reenvío
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
        
    } catch (Exception $e) {
        $error_chat = "Error al enviar mensaje: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Mantenimiento</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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
            margin: 0;
            padding: 0;
        }
        
        /* Layout principal sin afectar header */
        .main-layout {
            position: relative;
        }
        
        .main-content {
            width: 100%;
        }
        
        /* Solo afectar la sección de tabla */
        .table-section {
            transition: margin-right 0.3s ease;
        }
        
        .table-section.chat-open{
            margin-right: 400px;
        }
        
        /* Chat Sidebar */
        .chat-sidebar {
            position: fixed;
            right: -400px;
            top: 0;
            width: 400px;
            height: 100vh;
            background: white;
            box-shadow: -2px 0 10px rgba(0,0,0,0.1);
            transition: right 0.3s ease;
            z-index: 1000;
            display: flex;
            flex-direction: column;
        }
        
        .chat-sidebar.open {
            right: 0;
        }
        
        /* Ajustar sidebar para que comience desde donde termina el header/stats */
        .chat-sidebar.positioned {
            top: auto;
            bottom: 0;
        }

        .chat-header {
            background: linear-gradient(135deg, #51B8AC 0%, #0E544C 100%);
            color: white;
            padding: 15px;
            flex-shrink: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .chat-header h5 {
            margin: 0;
            font-size: 1rem !important;
        }
        
        .chat-close-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .chat-close-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .pinned-message-sidebar {
            background: #fff3cd;
            border-bottom: 1px solid #ffecb5;
            padding: 10px;
            flex-shrink: 0;
            font-size: 0.85rem !important;
        }
        
        .chat-messages-sidebar {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
            background: #f8f9fa;
        }
        
        .message {
            margin-bottom: 15px;
            display: flex;
            align-items: flex-start;
        }
        
        .message.own {
            flex-direction: row-reverse;
        }
        
        .message-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            flex-shrink: 0;
            margin: 0 8px;
            font-size: 0.9rem !important;
        }
        
        .avatar-mantenimiento {
            background: #6f42c1;
        }
        
        .avatar-solicitante {
            background: #20c997;
        }
        
        .message-content {
            max-width: 70%;
            background: white;
            border-radius: 12px;
            padding: 8px 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            font-size: 0.9rem !important;
            position: relative;
        }
        
        .message.own .message-content {
            background: #51B8AC;
            color: white;
        }
        
        .message-pin-btn {
            position: absolute;
            top: -25px;
            right: 5px;
            display: none;
            z-index: 10;
        }
        
        .message:hover .message-pin-btn {
            display: block;
        }
        
        .message-time {
            font-size: 0.75rem !important;
            color: #6c757d;
            margin-top: 3px;
        }
        
        .message.own .message-time {
            color: rgba(255,255,255,0.8);
        }
        
        .message-photo {
            max-width: 150px;
            max-height: 150px;
            border-radius: 8px;
            margin-top: 5px;
            cursor: pointer;
        }
        
        .chat-input-sidebar {
            background: white;
            padding: 12px;
            border-top: 1px solid #dee2e6;
            flex-shrink: 0;
        }
        
        .chat-input-sidebar textarea {
            font-size: 0.9rem !important;
        }

        /* Container principal */
        .container {
            max-width: 100%;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 10px;
        }
        
        .title {
            color: #0E544C;
            font-size: 1.5rem !important;
            margin-bottom: 20px;
        }
        
        .btn-primary {
            background-color: #51B8AC;
            border-color: #51B8AC;
        }
        .btn-primary:hover {
            background-color: #0E544C;
            border-color: #0E544C;
        }
        
        .stat-item {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        
        .urgency-bar {
            width: 100%;
            height: 20px;
            background: #e9ecef;
            border-radius: 10px;
            position: relative;
            overflow: hidden;
        }
        
        .urgency-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s ease;
        }
        
        .urgency-1 { background: #28a745; }
        .urgency-2 { background: #ffc107; }
        .urgency-3 { background: #fd7e14; }
        .urgency-4 { background: #dc3545; }

        /* Botones de urgencia en la tabla */
        .urgency-selector {
            min-width: 140px;
        }
        
        .btn-urgency {
            width: 28px;
            height: 28px;
            padding: 0;
            border: 2px solid transparent;
            border-radius: 4px;
            font-size: 0.85rem !important;
            font-weight: 600;
            color: white;
            cursor: pointer;
            transition: all 0.2s ease;
            background-color: #dee2e6;
            color: #6c757d;
        }
        
        .btn-urgency:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
            filter: brightness(1.1);
        }
        
        .btn-urgency.urgency-btn-1 {
            background-color: #d4edda;
            color: #155724;
        }
        
        .btn-urgency.urgency-btn-1.selected {
            background-color: #28a745;
            color: white;
            border-color: #ffffff;
            box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.3);
        }
        
        .btn-urgency.urgency-btn-2 {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .btn-urgency.urgency-btn-2.selected {
            background-color: #ffc107;
            color: #000;
            border-color: #ffffff;
            box-shadow: 0 0 0 2px rgba(255, 193, 7, 0.3);
        }
        
        .btn-urgency.urgency-btn-3 {
            background-color: #ffe5d0;
            color: #8a3a00;
        }
        
        .btn-urgency.urgency-btn-3.selected {
            background-color: #fd7e14;
            color: white;
            border-color: #ffffff;
            box-shadow: 0 0 0 2px rgba(253, 126, 20, 0.3);
        }
        
        .btn-urgency.urgency-btn-4 {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .btn-urgency.urgency-btn-4.selected {
            background-color: #dc3545;
            color: white;
            border-color: #ffffff;
            box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.3);
        }

        .status-badge {
            font-size: 0.8em;
            padding: 0.4em 0.8em;
            border-radius: 20px;
        }
        
        .status-solicitado { background: #6c757d; color: white; }
        .status-clasificado { background: #0dcaf0; color: white; }
        .status-agendado { background: #fd7e14; color: white; }
        .status-finalizado { background: #198754; color: white; }
        
        .ticket-photo {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 5px;
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
            vertical-align: middle;
        }

        th {
            background-color: #0E544C;
            color: white;
            text-align: center;
            padding: 12px 8px;
        }
        
        tr:nth-child(even) {
            background-color: #f2f2f2;
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
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        
        .modal-title {
            color: #0E544C;
            font-size: 1.2rem !important;
            font-weight: bold;
        }
        
        .camera-preview {
            width: 100%;
            max-width: 100%;
            height: 150px;
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 10px 0;
        }
        
        #videoSidebar, #canvasSidebar {
            max-width: 100%;
            height: auto;
        }

        /* Estilos para los filtros tipo Excel */
        .filter-dropdown {
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1050;
            min-width: 250px;
            max-width: 350px;
            display: none;
        }
        
        .filter-dropdown.show {
            display: block;
        }
        
        .filter-header {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
            background: #f8f9fa;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .filter-header h6 {
            margin: 0;
            font-size: 0.95rem !important;
            font-weight: 600;
            color: #0E544C;
        }
        
        .filter-search {
            padding: 10px 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .filter-search input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 0.9rem !important;
        }
        
        .filter-search input:focus {
            outline: none;
            border-color: #51B8AC;
            box-shadow: 0 0 0 0.2rem rgba(81, 184, 172, 0.25);
        }
        
        .filter-options {
            max-height: 300px;
            overflow-y: auto;
            padding: 8px 0;
        }
        
        .filter-option {
            padding: 8px 15px;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-option:hover {
            background: #f8f9fa;
        }
        
        .filter-option input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #51B8AC;
        }
        
        .filter-option label {
            margin: 0;
            cursor: pointer;
            flex: 1;
            font-size: 0.9rem !important;
        }
        
        .filter-actions {
            padding: 10px 15px;
            border-top: 1px solid #e9ecef;
            display: flex;
            gap: 8px;
            background: #f8f9fa;
            border-radius: 0 0 8px 8px;
        }
        
        .filter-actions button {
            flex: 1;
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            font-size: 0.85rem !important;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .filter-actions .btn-apply {
            background: #51B8AC;
            color: white;
        }
        
        .filter-actions .btn-apply:hover {
            background: #0E544C;
        }
        
        .filter-actions .btn-clear {
            background: #6c757d;
            color: white;
        }
        
        .filter-actions .btn-clear:hover {
            background: #5a6268;
        }
        
        .filter-icon {
            cursor: pointer;
            margin-left: 8px;
            color: #6c757d;
            font-size: 0.9rem !important;
            transition: color 0.2s;
        }
        
        .filter-icon:hover {
            color: #51B8AC;
        }
        
        .filter-icon.active {
            color: #51B8AC;
            font-weight: bold;
        }
        
        .filter-count {
            display: inline-block;
            background: #51B8AC;
            color: white;
            border-radius: 10px;
            padding: 2px 8px;
            font-size: 0.75rem !important;
            margin-left: 5px;
            font-weight: 600;
        }
        
        /* Badge para filtros activos */
        .active-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .filter-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #51B8AC;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem !important;
        }
        
        .filter-badge .remove-filter {
            cursor: pointer;
            margin-left: 4px;
            font-weight: bold;
        }
        
        .filter-badge .remove-filter:hover {
            color: #ffcccc;
        }
        
        th {
            position: relative;
            white-space: nowrap;
        }
        
        /* Estilos existentes del documento original */
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
            overflow-x: hidden;
        }
        
        .container {
            max-width: 100%;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 10px;
        }

        .table-responsive {
            margin-top: 20px;
        }

        .photo-gallery-preview {
            position: relative;
            display: inline-block;
        }

        .photo-count {
            position: absolute;
            bottom: 5px;
            right: 5px;
            font-size: 0.75rem !important;
            padding: 3px 6px;
        }

        .ticket-photo {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
            border: 2px solid #dee2e6;
            transition: transform 0.2s;
        }

        .ticket-photo:hover {
            transform: scale(1.1);
        }

        /* Estilos para el carousel de fotos en modal */
        .photos-carousel {
            max-width: 100%;
            margin: 0 auto;
        }

        .photos-carousel img {
            max-height: 500px;
            width: 100%;
            object-fit: contain;
        }

        .carousel-thumbnails {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            overflow-x: auto;
            padding: 10px 0;
        }

        .carousel-thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border: 2px solid #dee2e6;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.2s;
            flex-shrink: 0;
        }

        .carousel-thumbnail:hover,
        .carousel-thumbnail.active {
            border-color: #51B8AC;
            transform: scale(1.05);
        }

        @media (max-width: 768px) {
            .chat-sidebar {
                width: 100%;
                right: -100%;
            }
            
            .table-section.chat-open{
                margin-right: 0;
            }

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
        
        a.btn{
            text-decoration: none;
        }
    </style>
</head>
<body>
    <!-- Renderizar menú lateral -->
    <?php echo renderMenuLateral($cargoOperario); ?>
    
    <!-- Contenido principal -->
    <div class="main-container">   
        <div class="contenedor-principal"> 
            <!-- todo el contenido existente -->

            <div class="main-layout">
                <!-- Contenido Principal -->
                <div class="main-content" id="mainContent">
                    <div class="container">
                        <!-- Renderizar header universal -->
                        <?php echo renderHeader($usuario, $esAdmin, 'Solicitudes de Mantenimiento'); ?>

                        <!-- Filtros Activos -->
                        <div id="activeFiltersContainer" style="display: none;">
                            <div class="active-filters" id="activeFilters"></div>
                        </div>

                        <!-- Tabla de tickets -->
                        <div class="table-section" id="tableSection">
                            <div class="table-container">
                                <div class="card shadow">
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table id="ticketsTable" class="table table-striped table-hover">
                                                <thead class="table-dark">
                                                    <tr>
                                                        <th>Solicitado</th>
                                                        <th>Título</th>
                                                        <th>Descripción</th>
                                                        <th>
                                                            Sucursal
                                                            <i class="fas fa-filter filter-icon" data-column="sucursal"></i>
                                                        </th>
                                                        <th>
                                                            Tipo
                                                            <i class="fas fa-filter filter-icon" data-column="tipo"></i>
                                                        </th>
                                                        <th>
                                                            Urgencia
                                                            <i class="fas fa-filter filter-icon" data-column="urgencia"></i>
                                                        </th>
                                                        <th>
                                                            Estado
                                                            <i class="fas fa-filter filter-icon" data-column="estado"></i>
                                                        </th>
                                                        <th>Foto</th>
                                                        <th></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($tickets as $t): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?= date('d/m/Y', strtotime($t['created_at'])) ?></strong>
                                                        </td>
                                                        <td>
                                                            <div style="max-width: 200px;">
                                                                <?= htmlspecialchars($t['titulo']) ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div style="max-width: 200px;">
                                                                <?= htmlspecialchars($t['descripcion']) ?>
                                                            </div>
                                                        </td>
                                                        <td><?= htmlspecialchars($t['nombre_sucursal'] ?? 'N/A') ?></td>
                                                        <td><?= $t['tipo_formulario'] === 'mantenimiento_general' ? 'Mantenimiento' : 'Equipos' ?></td>
                                                        <td>
                                                            <div class="d-flex gap-1 justify-content-center align-items-center urgency-selector">
                                                                <button type="button" class="btn-urgency urgency-btn-1 <?= ($t['nivel_urgencia'] == 1) ? 'selected' : '' ?>" 
                                                                        onclick="setUrgencyLevel(<?= $t['id'] ?>, 1)" title="Baja">
                                                                    1
                                                                </button>
                                                                <button type="button" class="btn-urgency urgency-btn-2 <?= ($t['nivel_urgencia'] == 2) ? 'selected' : '' ?>" 
                                                                        onclick="setUrgencyLevel(<?= $t['id'] ?>, 2)" title="Media">
                                                                    2
                                                                </button>
                                                                <button type="button" class="btn-urgency urgency-btn-3 <?= ($t['nivel_urgencia'] == 3) ? 'selected' : '' ?>" 
                                                                        onclick="setUrgencyLevel(<?= $t['id'] ?>, 3)" title="Alta">
                                                                    3
                                                                </button>
                                                                <button type="button" class="btn-urgency urgency-btn-4 <?= ($t['nivel_urgencia'] == 4) ? 'selected' : '' ?>" 
                                                                        onclick="setUrgencyLevel(<?= $t['id'] ?>, 4)" title="Crítica">
                                                                    4
                                                                </button>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="status-badge status-<?= $t['status'] ?>">
                                                                <?= ucfirst($t['status']) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            $ticketFotos = $ticket->getFotos($t['id']);
                                                            if (!empty($ticketFotos)): 
                                                            ?>
                                                                <div class="photo-gallery-preview" onclick="showPhotosModal(<?= $t['id'] ?>)" style="cursor: pointer;">
                                                                    <img src="uploads/tickets/<?= $ticketFotos[0]['foto'] ?>" alt="Foto" class="ticket-photo">
                                                                    <?php if (count($ticketFotos) > 1): ?>
                                                                        <span class="badge bg-primary photo-count">+<?= count($ticketFotos) - 1 ?></span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php else: ?>
                                                                <small class="text-muted">Sin fotos</small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group-vertical" role="group">
                                                                <button class="btn btn-sm btn-success" onclick="openChatSidebar(<?= $t['id'] ?>)">
                                                                    <i class="fas fa-comments"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Fin table-section -->
                    </div>
                </div>
                
                <!-- Chat Sidebar -->
                <div class="chat-sidebar" id="chatSidebar">
                    <div class="chat-header">
                        <div>
                            <h5 id="chatTitle">
                                <i class="fas fa-comments me-2"></i>
                                Selecciona un ticket
                            </h5>
                            <small id="chatSubtitle"></small>
                        </div>
                        <button class="chat-close-btn" onclick="closeChatSidebar()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div id="pinnedMessageSidebar" class="pinned-message-sidebar" style="display: none;"></div>
                    
                    <div class="chat-messages-sidebar" id="chatMessagesSidebar">
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-comments fa-3x mb-3"></i>
                            <p>Selecciona un ticket para ver el chat</p>
                        </div>
                    </div>
                    
                    <div class="chat-input-sidebar" id="chatInputSidebar" style="display: none;">
                        <form method="POST" enctype="multipart/form-data" id="chatFormSidebar">
                            <input type="hidden" id="ticket_id_chat" name="ticket_id_chat">
                            <input type="hidden" id="foto_camera_sidebar" name="foto_camera">
                            
                            <div class="row align-items-end g-2">
                                <div class="col">
                                    <textarea class="form-control form-control-sm" id="mensajeSidebar" name="mensaje" 
                                            placeholder="Escribe tu mensaje..." rows="2" 
                                            onkeypress="handleKeyPressSidebar(event)"></textarea>
                                    <input type="file" id="fotoSidebar" name="foto" accept="image/*" style="display: none;">
                                </div>
                                <div class="col-auto">
                                    <div class="btn-group-vertical btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('fotoSidebar').click()" title="Subir foto">
                                            <i class="fas fa-image"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-success btn-sm" onclick="toggleCameraSidebar()" title="Tomar foto">
                                            <i class="fas fa-camera"></i>
                                        </button>
                                        <button type="submit" class="btn btn-primary btn-sm" title="Enviar">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="photoPreviewSidebar" style="display: none;" class="mt-2">
                                <img id="previewImgSidebar" src="" alt="Preview" class="img-thumbnail" style="max-width: 150px;">
                                <button type="button" class="btn btn-sm btn-danger ms-2" onclick="removePhotoSidebar()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            
                            <div class="camera-preview mt-2" id="cameraPreviewSidebar" style="display: none;">
                                <video id="videoSidebar" autoplay></video>
                                <canvas id="canvasSidebar" style="display: none;"></canvas>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>                                                        
    </div>  

    <!-- Modal para ver detalles del ticket -->
    <div class="modal fade" id="ticketModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalles del Ticket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="ticketModalBody">
                    <!-- Contenido cargado dinámicamente -->
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para mostrar fotos -->
    <div class="modal fade" id="photoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Imagen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalPhoto" src="" alt="Foto" class="img-fluid">
                </div>
            </div>
        </div>
    </div>


    <!-- Dropdown de filtros (se clonará para cada columna) -->
    <div id="filterDropdownTemplate" class="filter-dropdown">
        <div class="filter-header">
            <h6></h6>
            <i class="fas fa-times" style="cursor: pointer; color: #6c757d;"></i>
        </div>
        <div class="filter-search">
            <input type="text" placeholder="Buscar...">
        </div>
        <div class="filter-options"></div>
        <div class="filter-actions">
            <button class="btn-apply">Aplicar</button>
            <button class="btn-clear">Limpiar</button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    
    <script>

        let currentTicketId = null;
        let streamSidebar = null;
        let chatUpdateInterval = null;
        let table;
        let activeFilters = {
            sucursal: [],
            tipo: [],
            urgencia: [],
            estado: []
        };

        $(document).ready(function() {
            // Inicializar DataTable con ordenamiento de fechas corregido
            table = $('#ticketsTable').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
                },
                order: [[0, 'desc']], // Ordenar por primera columna (fecha) descendente
                pageLength: 25,
                columnDefs: [
                    { 
                        // Columna 0 - Fecha (convertir para ordenamiento)
                        targets: 0,
                        type: 'date-eu', // Tipo europeo para dd/mm/yyyy
                        render: function(data, type, row) {
                            if (type === 'sort' || type === 'type') {
                                // Convertir dd/mm/yyyy a yyyy-mm-dd para ordenamiento
                                const parts = data.split('/');
                                if (parts.length === 3) {
                                    return parts[2] + '-' + parts[1] + '-' + parts[0];
                                }
                            }
                            return data; // Para display, mantener formato original
                        }
                    },
                    { 
                        orderable: false, 
                        targets: [7,8] // Columnas de foto y acciones no ordenables
                    }
                ]
            });

           // Leer filtros de la URL
            readFiltersFromURL();
            
            // Event listeners para los iconos de filtro
            $('.filter-icon').on('click', function(e) {
                e.stopPropagation();
                const column = $(this).data('column');
                const rect = this.getBoundingClientRect();
                showFilterDropdown(column, rect);
            });

            // Cerrar dropdown al hacer click fuera
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.filter-dropdown, .filter-icon').length) {
                    $('.filter-dropdown').removeClass('show');
                }
            });
            
            // Actualizar badges de filtros activos
            updateActiveFiltersBadges();
            
            // Manejar archivo de imagen en sidebar
            $('#fotoSidebar').on('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        showPreviewSidebar(e.target.result);
                    };
                    reader.readAsDataURL(file);
                }
            });
        });


        function showFilterDropdown(column, rect) {
            // Cerrar otros dropdowns
            $('.filter-dropdown').removeClass('show');
            
            // Crear nuevo dropdown
            const dropdown = $('#filterDropdownTemplate').clone();
            dropdown.attr('id', 'filterDropdown_' + column);
            dropdown.removeClass('filter-dropdown');
            dropdown.addClass('filter-dropdown show');
            
            // Posicionar dropdown
            dropdown.css({
                top: rect.bottom + window.scrollY + 5 + 'px',
                left: rect.left + window.scrollX - 100 + 'px'
            });
            
            // Configurar header
            dropdown.find('.filter-header h6').text('Filtrar ' + column.charAt(0).toUpperCase() + column.slice(1));
            
            // Obtener valores únicos de la columna
            const columnIndex = getColumnIndex(column);
            const uniqueValues = getUniqueColumnValues(columnIndex);
            
            // Poblar opciones
            const optionsContainer = dropdown.find('.filter-options');
            optionsContainer.empty();
            
            uniqueValues.forEach(value => {
                const isChecked = activeFilters[column].includes(value);
                const option = $(`
                    <div class="filter-option">
                        <input type="checkbox" id="filter_${column}_${value}" ${isChecked ? 'checked' : ''}>
                        <label for="filter_${column}_${value}">${value}</label>
                    </div>
                `);
                optionsContainer.append(option);
            });
            
            // Event listener para búsqueda
            dropdown.find('.filter-search input').on('input', function() {
                const searchTerm = $(this).val().toLowerCase();
                dropdown.find('.filter-option').each(function() {
                    const text = $(this).find('label').text().toLowerCase();
                    $(this).toggle(text.includes(searchTerm));
                });
            });
            
            // Event listener para cerrar
            dropdown.find('.filter-header .fa-times').on('click', function() {
                dropdown.remove();
            });
            
            // Event listener para aplicar
            dropdown.find('.btn-apply').on('click', function() {
                applyFilter(column, dropdown);
                dropdown.remove();
            });
            
            // Event listener para limpiar
            dropdown.find('.btn-clear').on('click', function() {
                clearFilter(column);
                dropdown.remove();
            });
            
            // Event listener para checkboxes
            dropdown.find('.filter-option').on('click', function(e) {
                if (e.target.tagName !== 'INPUT') {
                    const checkbox = $(this).find('input[type="checkbox"]');
                    checkbox.prop('checked', !checkbox.prop('checked'));
                }
            });
            
            // Agregar al body
            $('body').append(dropdown);
        }
        
        function getColumnIndex(column) {
            const columns = {
                'sucursal': 3,
                'tipo': 4,
                'urgencia': 5,
                'estado': 6
            };
            return columns[column];
        }

        function getUniqueColumnValues(columnIndex) {
            const values = new Set();
            
            if (columnIndex === 5) { // Urgencia - leer directamente del DOM
                table.rows().every(function() {
                    const row = this.node();
                    const cell = $(row).find('td').eq(columnIndex);
                    
                    // Buscar el botón seleccionado
                    const selectedBtn = cell.find('.btn-urgency.selected');
                    if (selectedBtn.length > 0) {
                        const level = selectedBtn.text().trim();
                        const urgencyMap = {
                            '1': 'No urgente',
                            '2': 'Medio',
                            '3': 'Urgente',
                            '4': 'Crítico'
                        };
                        values.add(urgencyMap[level] || level);
                    } else {
                        // Si no hay botón seleccionado = No categorizado
                        values.add('No categorizado');
                    }
                });
            } else {
                table.column(columnIndex).data().each(function(value) {
                    let cleanValue = value.toString().trim();
                    
                    if (columnIndex === 6) { // Estado
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = cleanValue;
                        const badge = tempDiv.querySelector('.status-badge');
                        if (badge) {
                            cleanValue = badge.textContent.trim();
                        }
                    }
                    
                    if (cleanValue) {
                        values.add(cleanValue);
                    }
                });
            }
            
            return Array.from(values).sort();
        }
        
        function applyFilter(column, dropdown) {
            const selectedValues = [];
            dropdown.find('.filter-option input:checked').each(function() {
                selectedValues.push($(this).next('label').text());
            });
            
            activeFilters[column] = selectedValues;
            applyAllFilters();
            updateFilterIcons();
            updateActiveFiltersBadges();
        }
        
        function clearFilter(column) {
            activeFilters[column] = [];
            applyAllFilters();
            updateFilterIcons();
            updateActiveFiltersBadges();
        }
        
        function applyAllFilters() {
            $.fn.dataTable.ext.search.pop(); // Remover filtro anterior
            
            $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                // Verificar cada filtro activo
                for (let column in activeFilters) {
                    if (activeFilters[column].length > 0) {
                        const columnIndex = getColumnIndex(column);
                        let cellValue = '';
                        
                        if (column === 'urgencia') {
                            // Leer directamente del DOM
                            const row = table.row(dataIndex).node();
                            const cell = $(row).find('td').eq(columnIndex);
                            const selectedBtn = cell.find('.btn-urgency.selected');
                            
                            if (selectedBtn.length > 0) {
                                const level = selectedBtn.text().trim();
                                const urgencyMap = {
                                    '1': 'No urgente',
                                    '2': 'Medio',
                                    '3': 'Urgente',
                                    '4': 'Crítico'
                                };
                                cellValue = urgencyMap[level] || level;
                            } else {
                                // Si no hay seleccionado = No categorizado
                                cellValue = 'No categorizado';
                            }
                        } else {
                            cellValue = data[columnIndex].trim();
                            
                            if (column === 'estado') {
                                const tempDiv = document.createElement('div');
                                tempDiv.innerHTML = cellValue;
                                const badge = tempDiv.querySelector('.status-badge');
                                if (badge) {
                                    cellValue = badge.textContent.trim();
                                }
                            }
                        }
                        
                        if (!activeFilters[column].includes(cellValue)) {
                            return false;
                        }
                    }
                }
                return true;
            });
            
            table.draw();
        }
        
        function updateFilterIcons() {
            $('.filter-icon').each(function() {
                const column = $(this).data('column');
                const hasActiveFilter = activeFilters[column].length > 0;
                
                $(this).toggleClass('active', hasActiveFilter);
                
                // Remover contador previo
                $(this).next('.filter-count').remove();
                
                // Agregar contador si hay filtros
                if (hasActiveFilter) {
                    $(this).after(`<span class="filter-count">${activeFilters[column].length}</span>`);
                }
            });
        }
        
        function updateActiveFiltersBadges() {
            const container = $('#activeFilters');
            container.empty();
            
            let hasFilters = false;
            
            for (let column in activeFilters) {
                if (activeFilters[column].length > 0) {
                    hasFilters = true;
                    activeFilters[column].forEach(value => {
                        const badge = $(`
                            <div class="filter-badge">
                                <span>${column.charAt(0).toUpperCase() + column.slice(1)}: ${value}</span>
                                <span class="remove-filter" data-column="${column}" data-value="${value}">×</span>
                            </div>
                        `);
                        container.append(badge);
                    });
                }
            }
            
            // Agregar botón para limpiar todos
            if (hasFilters) {
                const clearAllBtn = $(`
                    <div class="filter-badge" style="background: #6c757d; cursor: pointer;" id="clearAllFilters">
                        <i class="fas fa-times-circle"></i>
                        <span>Limpiar todos</span>
                    </div>
                `);
                container.append(clearAllBtn);
            }
            
            $('#activeFiltersContainer').toggle(hasFilters);
            
            // Event listener para remover filtros individuales
            $('.remove-filter').on('click', function() {
                const column = $(this).data('column');
                const value = $(this).data('value');
                activeFilters[column] = activeFilters[column].filter(v => v !== value);
                applyAllFilters();
                updateFilterIcons();
                updateActiveFiltersBadges();
            });
            
            // Event listener para limpiar todos
            $('#clearAllFilters').on('click', function() {
                activeFilters = {
                    sucursal: [],
                    tipo: [],
                    urgencia: [],
                    estado: []
                };
                applyAllFilters();
                updateFilterIcons();
                updateActiveFiltersBadges();
            });
        }

        function showPhotosModal(ticketId) {
            $.ajax({
                url: 'ajax/get_ticket_photos.php',
                method: 'GET',
                data: { ticket_id: ticketId },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.fotos.length > 0) {
                        let html = '';
                        
                        // Crear carousel
                        html += '<div id="photosCarousel" class="carousel slide photos-carousel" data-bs-ride="false">';
                        html += '<div class="carousel-inner">';
                        
                        response.fotos.forEach((foto, index) => {
                            html += `<div class="carousel-item ${index === 0 ? 'active' : ''}">
                                <img src="uploads/tickets/${foto.foto}" class="d-block w-100" alt="Foto ${index + 1}">
                                <div class="text-center mt-2">
                                    <small class="text-muted">Foto ${index + 1} de ${response.fotos.length}</small>
                                </div>
                            </div>`;
                        });
                        
                        html += '</div>';
                        
                        // Controles del carousel
                        if (response.fotos.length > 1) {
                            html += `
                                <button class="carousel-control-prev" type="button" data-bs-target="#photosCarousel" data-bs-slide="prev">
                                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                    <span class="visually-hidden">Anterior</span>
                                </button>
                                <button class="carousel-control-next" type="button" data-bs-target="#photosCarousel" data-bs-slide="next">
                                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                    <span class="visually-hidden">Siguiente</span>
                                </button>
                            `;
                            
                            // Thumbnails
                            html += '<div class="carousel-thumbnails">';
                            response.fotos.forEach((foto, index) => {
                                html += `
                                    <img src="uploads/tickets/${foto.foto}" 
                                        class="carousel-thumbnail ${index === 0 ? 'active' : ''}" 
                                        data-bs-target="#photosCarousel" 
                                        data-bs-slide-to="${index}"
                                        alt="Thumbnail ${index + 1}">
                                `;
                            });
                            html += '</div>';
                        }
                        
                        html += '</div>';
                        
                        $('#photosGalleryBody').html(html);
                        
                        // Event listener para actualizar thumbnails activos
                        const carousel = document.getElementById('photosCarousel');
                        if (carousel) {
                            carousel.addEventListener('slid.bs.carousel', function (e) {
                                $('.carousel-thumbnail').removeClass('active');
                                $(`.carousel-thumbnail[data-bs-slide-to="${e.to}"]`).addClass('active');
                            });
                        }
                        
                        new bootstrap.Modal(document.getElementById('photosGalleryModal')).show();
                    } else {
                        alert('No hay fotos disponibles para este ticket');
                    }
                },
                error: function() {
                    alert('Error al cargar las fotos');
                }
            });
        }
        
        // ========== FUNCIONES PARA FILTROS EXTERNOS ==========
        
        function readFiltersFromURL() {
            const urlParams = new URLSearchParams(window.location.search);
            
            // Leer cada parámetro de filtro
            ['sucursal', 'tipo', 'urgencia', 'estado'].forEach(column => {
                const values = urlParams.getAll(column);
                if (values.length > 0) {
                    activeFilters[column] = values;
                }
            });
            
            // Aplicar filtros si hay alguno
            if (Object.values(activeFilters).some(arr => arr.length > 0)) {
                applyAllFilters();
                updateFilterIcons();
                updateActiveFiltersBadges();
            }
        }
        
        // Función para generar URL con filtros (útil para otros indicadores)
        function generateFilterURL(filters) {
            const baseURL = window.location.pathname;
            const params = new URLSearchParams();
            
            for (let column in filters) {
                if (Array.isArray(filters[column])) {
                    filters[column].forEach(value => {
                        params.append(column, value);
                    });
                } else if (filters[column]) {
                    params.append(column, filters[column]);
                }
            }
            
            return baseURL + '?' + params.toString();
        }
        
        // Funciones del Chat Sidebar
        function openChatSidebar(ticketId) {
            currentTicketId = ticketId;
            
            // Calcular posición del sidebar (desde donde termina el header/stats)
            const tableSection = document.getElementById('tableSection');
            const rect = tableSection.getBoundingClientRect();
            const sidebar = document.getElementById('chatSidebar');
            
            // Posicionar sidebar desde donde comienza la tabla
            sidebar.style.top = rect.top + 'px';
            sidebar.style.height = 'calc(100vh - ' + rect.top + 'px)';

            // Abrir sidebar
            $('#chatSidebar').addClass('open');
            $('#tableSection').addClass('chat-open');
            
            // Cargar mensajes del ticket
            loadChatMessages(ticketId);
            
            // Mostrar input de chat
            $('#chatInputSidebar').show();
            $('#ticket_id_chat').val(ticketId);
            
            // Iniciar actualización automática
            if (chatUpdateInterval) {
                clearInterval(chatUpdateInterval);
            }
            chatUpdateInterval = setInterval(function() {
                loadChatMessages(ticketId, true);
            }, 5000);
        }
        
        function closeChatSidebar() {
            $('#chatSidebar').removeClass('open');
            $('#tableSection').removeClass('chat-open');
            currentTicketId = null;
            
            // Detener actualización automática
            if (chatUpdateInterval) {
                clearInterval(chatUpdateInterval);
                chatUpdateInterval = null;
            }
            
            // Limpiar formulario
            $('#mensajeSidebar').val('');
            removePhotoSidebar();
            stopCameraSidebar();
        }

        // Reposicionar sidebar al hacer scroll o resize
        window.addEventListener('scroll', function() {
            if (currentTicketId && $('#chatSidebar').hasClass('open')) {
                const tableSection = document.getElementById('tableSection');
                const rect = tableSection.getBoundingClientRect();
                const sidebar = document.getElementById('chatSidebar');
                
                const topPosition = Math.max(0, rect.top);
                sidebar.style.top = topPosition + 'px';
                sidebar.style.height = 'calc(100vh - ' + topPosition + 'px)';
            }
        });
        
        window.addEventListener('resize', function() {
            if (currentTicketId && $('#chatSidebar').hasClass('open')) {
                const tableSection = document.getElementById('tableSection');
                const rect = tableSection.getBoundingClientRect();
                const sidebar = document.getElementById('chatSidebar');
                
                sidebar.style.top = rect.top + 'px';
                sidebar.style.height = 'calc(100vh - ' + rect.top + 'px)';
            }
        });
        
        function loadChatMessages(ticketId, isUpdate = false) {
            $.ajax({
                url: 'ajax/get_chat_sidebar.php',
                method: 'GET',
                data: { ticket_id: ticketId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Actualizar título
                        if (!isUpdate) {
                            $('#chatTitle').html('<i class="fas fa-comments me-2"></i>' + response.ticket.titulo);
                            $('#chatSubtitle').text(response.ticket.area_equipo);
                        }
                        
                        // Actualizar mensaje pinned
                        if (response.pinned_message) {
                            let pinnedHtml = '<div class="d-flex justify-content-between align-items-start">';
                            pinnedHtml += '<div style="flex: 1;">';
                            pinnedHtml += '<i class="fas fa-thumbtack me-2"></i>';
                            pinnedHtml += '<strong>' + response.pinned_message.emisor_nombre + ':</strong>';
                            pinnedHtml += '<p class="mb-0 mt-1" style="font-size: 0.85rem !important;">' + response.pinned_message.mensaje.replace(/\n/g, '<br>') + '</p>';
                            if (response.pinned_message.foto) {
                                pinnedHtml += '<img src="uploads/chat/' + response.pinned_message.foto + '" class="message-photo mt-2" style="max-width: 100px;" onclick="showPhotoModal(\'uploads/chat/' + response.pinned_message.foto + '\')">';
                            }
                            pinnedHtml += '</div>';
                            pinnedHtml += '<button class="btn btn-sm btn-outline-secondary" onclick="unpinMessageSidebar(' + response.pinned_message.id + ')">';
                            pinnedHtml += '<i class="fas fa-times"></i></button>';
                            pinnedHtml += '</div>';
                            $('#pinnedMessageSidebar').html(pinnedHtml).show();
                        } else {
                            $('#pinnedMessageSidebar').hide();
                        }
                        
                        // Renderizar mensajes
                        let messagesHtml = '';
                        response.messages.forEach(function(msg) {
                            const isOwn = msg.emisor_tipo === 'mantenimiento';
                            const avatarClass = msg.emisor_tipo === 'mantenimiento' ? 'avatar-mantenimiento' : 'avatar-solicitante';
                            const avatarLetter = msg.emisor_tipo === 'mantenimiento' ? 'M' : 'U';
                            
                            messagesHtml += '<div class="message ' + (isOwn ? 'own' : '') + '">';
                            messagesHtml += '<div class="message-avatar ' + avatarClass + '">' + avatarLetter + '</div>';
                            messagesHtml += '<div class="message-content">';
                            
                            // Botón para fijar
                            if (isOwn) {
                                messagesHtml += '<div class="message-pin-btn">';
                                messagesHtml += '<button class="btn btn-sm btn-outline-warning" onclick="pinMessageSidebar(' + msg.id + ')" title="Fijar mensaje">';
                                messagesHtml += '<i class="fas fa-thumbtack"></i></button></div>';
                            }
                            
                            messagesHtml += '<div class="fw-bold" style="font-size: 0.85rem !important;">' + msg.emisor_nombre + '</div>';
                            
                            if (msg.mensaje) {
                                messagesHtml += '<div style="font-size: 0.9rem !important;">' + msg.mensaje.replace(/\n/g, '<br>') + '</div>';
                            }
                            
                            if (msg.foto) {
                                messagesHtml += '<img src="uploads/chat/' + msg.foto + '" class="message-photo" onclick="showPhotoModal(\'uploads/chat/' + msg.foto + '\')">';
                            }
                            
                            messagesHtml += '<div class="message-time">' + formatDateTime(msg.created_at) + '</div>';
                            messagesHtml += '</div></div>';
                        });
                        
                        const chatContainer = $('#chatMessagesSidebar');
                        const wasAtBottom = chatContainer[0].scrollHeight - chatContainer.scrollTop() <= chatContainer.outerHeight() + 50;
                        
                        chatContainer.html(messagesHtml);
                        
                        // Auto-scroll solo si estaba al final o es primera carga
                        if (!isUpdate || wasAtBottom) {
                            chatContainer.scrollTop(chatContainer[0].scrollHeight);
                        }
                        
                    }
                },
                error: function() {
                    if (!isUpdate) {
                        alert('Error al cargar el chat');
                    }
                }
            });
        }
        
        function formatDateTime(dateStr) {
            const date = new Date(dateStr);
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            return day + '/' + month + '/' + year + ' ' + hours + ':' + minutes;
        }
        
        // Enviar mensaje con Enter
        function handleKeyPressSidebar(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                $('#chatFormSidebar').submit();
            }
        }
        
        // Validación y envío del formulario
        $('#chatFormSidebar').on('submit', function(e) {
            e.preventDefault();
            
            const mensaje = $('#mensajeSidebar').val().trim();
            const tieneArchivo = $('#fotoSidebar')[0].files.length > 0;
            const tieneCamera = $('#foto_camera_sidebar').val() !== '';
            
            if (!mensaje && !tieneArchivo && !tieneCamera) {
                alert('Debe escribir un mensaje o adjuntar una foto');
                return;
            }
            
            // Enviar formulario
            const formData = new FormData(this);
            
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function() {
                    // Limpiar formulario
                    $('#mensajeSidebar').val('');
                    removePhotoSidebar();
                    
                    // Recargar mensajes
                    loadChatMessages(currentTicketId);
                },
                error: function() {
                    alert('Error al enviar el mensaje');
                }
            });
        });
        
        // Funciones de cámara
        function toggleCameraSidebar() {
            if (streamSidebar) {
                stopCameraSidebar();
            } else {
                startCameraSidebar();
            }
        }
        
        function startCameraSidebar() {
            navigator.mediaDevices.getUserMedia({ video: true })
                .then(function(mediaStream) {
                    streamSidebar = mediaStream;
                    const video = document.getElementById('videoSidebar');
                    video.srcObject = streamSidebar;
                    document.getElementById('cameraPreviewSidebar').style.display = 'block';
                    
                    if (!document.getElementById('captureBtnSidebar')) {
                        const captureBtn = document.createElement('button');
                        captureBtn.type = 'button';
                        captureBtn.id = 'captureBtnSidebar';
                        captureBtn.className = 'btn btn-success btn-sm mt-2';
                        captureBtn.innerHTML = '<i class="fas fa-camera me-2"></i>Capturar';
                        captureBtn.addEventListener('click', capturePhotoSidebar);
                        document.getElementById('cameraPreviewSidebar').appendChild(captureBtn);
                    }
                })
                .catch(function(err) {
                    alert('Error al acceder a la cámara: ' + err.message);
                });
        }
        
        function capturePhotoSidebar() {
            const video = document.getElementById('videoSidebar');
            const canvas = document.getElementById('canvasSidebar');
            const context = canvas.getContext('2d');
            
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            context.drawImage(video, 0, 0);
            
            const dataURL = canvas.toDataURL('image/jpeg');
            document.getElementById('foto_camera_sidebar').value = dataURL;
            
            showPreviewSidebar(dataURL);
            stopCameraSidebar();
        }
        
        function stopCameraSidebar() {
            if (streamSidebar) {
                streamSidebar.getTracks().forEach(track => track.stop());
                streamSidebar = null;
            }
            document.getElementById('cameraPreviewSidebar').style.display = 'none';
            const captureBtn = document.getElementById('captureBtnSidebar');
            if (captureBtn) captureBtn.remove();
        }
        
        function showPreviewSidebar(src) {
            document.getElementById('previewImgSidebar').src = src;
            document.getElementById('photoPreviewSidebar').style.display = 'block';
        }
        
        function removePhotoSidebar() {
            document.getElementById('photoPreviewSidebar').style.display = 'none';
            document.getElementById('fotoSidebar').value = '';
            document.getElementById('foto_camera_sidebar').value = '';
            stopCameraSidebar();
        }
        
        // Fijar/desfijar mensajes
        function pinMessageSidebar(messageId) {
            $.ajax({
                url: 'ajax/pin_message.php',
                method: 'POST',
                data: {
                    message_id: messageId,
                    ticket_id: currentTicketId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        loadChatMessages(currentTicketId);
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error en la comunicación con el servidor');
                }
            });
        }
        
        function unpinMessageSidebar(messageId) {
            $.ajax({
                url: 'ajax/unpin_message.php',
                method: 'POST',
                data: { message_id: messageId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        loadChatMessages(currentTicketId);
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error en la comunicación con el servidor');
                }
            });
        }
        
        
        function showPhotoModal(photoSrc) {
            $('#modalPhoto').attr('src', photoSrc);
            new bootstrap.Modal(document.getElementById('photoModal')).show();
        }
        
        function refreshData() {
            location.reload();
        }
        
        // Función para establecer nivel de urgencia desde la tabla
        function setUrgencyLevel(ticketId, level) {
            // Guardar referencia al botón clickeado
            const clickedButton = event.target;
            const container = clickedButton.closest('.urgency-selector');
                        
            $.ajax({
                url: 'ajax/update_urgency.php',
                method: 'POST',
                data: {
                    ticket_id: ticketId,
                    nivel_urgencia: level
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Actualizar visualmente los botones - PRIMERO remover selección de todos
                        container.querySelectorAll('.btn-urgency').forEach(btn => {
                            btn.classList.remove('selected');
                        });
                        
                        // LUEGO agregar selección solo al botón clickeado
                        clickedButton.classList.add('selected');
                        
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error en la comunicación con el servidor');
                }
            });
        }
        
        // Función para mostrar notificaciones
        function showNotification(message, type = 'info') {
            const alertClass = type === 'success' ? 'alert-success' : 'alert-info';
            const notification = $('<div class="alert ' + alertClass + ' alert-dismissible fade show position-fixed" role="alert" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">' +
                message +
                '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                '</div>');
            
            $('body').append(notification);
            
            setTimeout(function() {
                notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }

    </script>

    <!-- Modal para galería de fotos -->
    <div class="modal fade" id="photosGalleryModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-images me-2"></i>
                        Fotografías del Ticket
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="photosGalleryBody">
                    <!-- Contenido cargado dinámicamente -->
                </div>
            </div>
        </div>
    </div>

</body>
</html>