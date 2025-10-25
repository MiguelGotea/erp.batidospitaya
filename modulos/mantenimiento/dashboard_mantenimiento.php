<?php
// Solo iniciar sesión si no está ya activa
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'models/Ticket.php';
require_once 'models/Chat.php';
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

//******************************Estándar para header******************************
verificarAutenticacion();

$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

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

// Obtener estadísticas
$stats = [
    'total' => count($tickets),
    'solicitado' => count(array_filter($tickets, fn($t) => $t['status'] === 'solicitado')),
    // 'clasificado' => count(array_filter($tickets, fn($t) => $t['status'] === 'clasificado')),
    'agendado' => count(array_filter($tickets, fn($t) => $t['status'] === 'agendado')),
    'finalizado' => count(array_filter($tickets, fn($t) => $t['status'] === 'finalizado'))
];

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
            color: #333;
            padding: 5px;
            overflow-x: hidden;
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
        
        .title {
            color: #0E544C;
            font-size: 1.5rem !important;
            margin-bottom: 20px;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #51B8AC 0%, #0E544C 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
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
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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
    <div class="main-layout">
        <!-- Contenido Principal -->
        <div class="main-content" id="mainContent">
            <div class="container">
                <header>
                    <div class="header-container">
                        <div class="logo-container">
                            <img src="../../assets/img/Logo.svg" alt="Batidos Pitaya" class="logo">
                        </div>
                        
                        <div class="buttons-container">
                            <?php if ($esAdmin || verificarAccesoCargo([5, 11, 14, 16, 35])): ?>
                                <a href="calendario.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'calendario.php' ? 'activo' : '' ?>">
                                    <i class="fas fa-calendar-alt"></i> <span class="btn-text">Calendario</span>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($esAdmin || verificarAccesoCargo([5, 14, 16, 35])): ?>
                                <a href="formulario_mantenimiento.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'formulario_mantenimiento.php' ? 'activo' : '' ?>">
                                    <i class="fas fa-tools"></i> <span class="btn-text">Mantenimiento General</span>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($esAdmin || verificarAccesoCargo([5, 14, 16, 35])): ?>
                                <a href="formulario_equipos.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'formulario_equipos.php' ? 'activo' : '' ?>">
                                    <i class="fas fa-laptop"></i> <span class="btn-text">Cambio de Equipos</span>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($esAdmin || verificarAccesoCargo([11, 14, 16, 35])): ?>
                                <a href="dashboard_mantenimiento.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'dashboard_mantenimiento.php' ? 'activo' : '' ?>">
                                    <i class="fas fa-sync-alt"></i> <span class="btn-text">Solicitudes</span>
                                </a>
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

                <!-- Estadísticas -->
                <div class="stats-card">
                    <div class="stats-grid">
                        <div class="stat-item">
                            <h3><?= $stats['total'] ?></h3>
                            <p>Total Tickets</p>
                        </div>
                        <div class="stat-item">
                            <h3><?= $stats['solicitado'] ?></h3>
                            <p>Solicitados</p>
                        </div>
                        <div class="stat-item">
                            <h3><?= $stats['agendado'] ?></h3>
                            <p>Agendados</p>
                        </div>
                        <div class="stat-item">
                            <h3><?= $stats['finalizado'] ?></h3>
                            <p>Finalizados</p>
                        </div>
                    </div>
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
                                                <th>Descripcion</th>
                                                <th>Sucursal</th>
                                                <th>Tipo</th>
                                                <th>Urgencia</th>
                                                <th>Estado</th>
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
                                                    <?php if ($t['foto']): ?>
                                                        <img src="uploads/tickets/<?= $t['foto'] ?>" alt="Foto" class="ticket-photo" 
                                                            onclick="showPhotoModal('uploads/tickets/<?= $t['foto'] ?>')">
                                                    <?php else: ?>
                                                        <small class="text-muted">Sin foto</small>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    
    <script>

        let currentTicketId = null;
        let streamSidebar = null;
        let chatUpdateInterval = null;

        $(document).ready(function() {
            // Inicializar DataTable
            $('#ticketsTable').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
                },
                order: [[0, 'desc']],
                pageLength: 25,
                columnDefs: [
                    { orderable: false, targets: [7] }
                ]
            });
            
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
                        
                        // Mostrar botón de pin al hover
                        $('.message.own').hover(
                            function() { $(this).find('.message-pin-btn').show(); },
                            function() { $(this).find('.message-pin-btn').hide(); }
                        );
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
</body>
</html>