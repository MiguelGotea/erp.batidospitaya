<?php
// Solo iniciar sesión si no está ya activa
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'models/Ticket.php';
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

//******************************Estándar para header******************************
verificarAutenticacion();

$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso a formularios de mantenimiento (Código 14 y 19)
if (!verificarAccesoFormulariosMantenimiento($_SESSION['usuario_id'])) {
    header('Location: ../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

// Obtener sucursales permitidas para el usuario (nueva función)
$sucursalesPermitidas = obtenerSucursalesPermitidasMantenimiento($_SESSION['usuario_id']);

// Validar parámetros y determinar sucursal actual
if (empty($sucursalesPermitidas)) {
    die("No tienes sucursales asignadas. Contacta al administrador.");
}

// Si no viene parámetro de sucursal o no es válido, usar la primera permitida
if (!isset($_GET['cod_sucursal']) || !verificarAccesoSucursalMantenimiento($_SESSION['usuario_id'], $_GET['cod_sucursal'])) {
    $cod_sucursal = $sucursalesPermitidas[0]['codigo'];
    // Redirigir a la URL correcta
    header("Location: dashboard_sucursales.php?cod_operario=" . $_SESSION['usuario_id'] . "&cod_sucursal=" . $cod_sucursal);
    exit();
} else {
    $cod_sucursal = $_GET['cod_sucursal'];
}

// El código de operario siempre debe ser el del usuario logueado
$cod_operario = $_SESSION['usuario_id'];

// Verificar que el usuario tenga acceso a esta sucursal (usar nueva función)
if (!verificarAccesoSucursalMantenimiento($cod_operario, $cod_sucursal)) {
    die("No tienes acceso a esta sucursal.");
}

$ticket = new Ticket();

// Obtener información del operario y sucursal
global $db;
$operario = $db->fetchOne("SELECT Nombre FROM Operarios WHERE CodOperario = ?", [$cod_operario]);
$sucursal = $db->fetchOne("SELECT nombre FROM sucursales WHERE codigo = ?", [$cod_sucursal]);

// Obtener tickets según el nivel de acceso del usuario
if (verificarAccesoCargo([14])) {
    // Cargo 14 (Mantenimiento) - ve todos los tickets de todas las sucursales
    $tickets = $ticket->getAll(); // Sin filtros
} elseif (verificarAccesoCargo([19])) {
    // Cargo 19 (Jefe CDS) - solo ve tickets de sucursal 18
    $filters = ['cod_sucursal' => 18];
    $tickets = $ticket->getAll($filters);
} else {
    // Líderes - solo ven tickets de sus sucursales asignadas
    $filters = ['cod_sucursal' => $cod_sucursal];
    $tickets = $ticket->getAll($filters);
}

// Estadísticas
$stats = [
    'total' => count($tickets),
    'solicitado' => count(array_filter($tickets, fn($t) => $t['status'] === 'solicitado')),
    // 'clasificado' => count(array_filter($tickets, fn($t) => $t['status'] === 'clasificado')),
    'agendado' => count(array_filter($tickets, fn($t) => $t['status'] === 'agendado')),
    'finalizado' => count(array_filter($tickets, fn($t) => $t['status'] === 'finalizado'))
];

// Mostrar información de acceso actual
$accesoCompleto = verificarAccesoCargo([14]);
$accesoCDS = verificarAccesoCargo([19]);
$infoAcceso = '';

if ($accesoCompleto) {
    $infoAcceso = '<small class="text-info"><i class="fas fa-shield-alt me-1"></i>Acceso completo a todas las sucursales</small>';
} elseif ($accesoCDS) {
    $infoAcceso = '<small class="text-warning"><i class="fas fa-building me-1"></i>Acceso limitado a CDS (Sucursal 18)</small>';
} else {
    $infoAcceso = '<small class="text-success"><i class="fas fa-store me-1"></i>Acceso a sucursales asignadas</small>';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Solicitudes de Mantenimiento</title>
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
        }
        
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
        
        .user-info-panel {
            background: linear-gradient(135deg, #51B8AC 0%, #0E544C 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            backdrop-filter: blur(10px);
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
        
        .ticket-row {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .ticket-row:hover {
            background-color: #f8f9fa;
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
        
        .btn-primary {
            background-color: #51B8AC;
            border-color: #51B8AC;
        }
        
        .btn-primary:hover {
            background-color: #0E544C;
            border-color: #0E544C;
        }
        
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }
        
        .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
        }
        
        .new-ticket-buttons {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .fab-button {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: none;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
            transition: transform 0.2s;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .fab-button:hover {
            transform: scale(1.1);
        }
        
        .fab-mantenimiento {
            background: linear-gradient(135deg, #51B8AC 0%, #0E544C 100%);
        }
        
        .fab-equipos {
            background: linear-gradient(135deg, #0E544C 0%, #51B8AC 100%);
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
            
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .fab-button {
                width: 50px;
                height: 50px;
                font-size: 1.2rem;
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
            
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
            }
            
            .stat-card {
                padding: 10px;
            }
        }
        
        a.btn{
            text-decoration: none;
        }
        
        .no-tickets {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        
        .sucursal-selector {
            margin-right: 15px;
        }
        
        .sucursal-selector .form-select {
            border-color: #51B8AC;
            color: #0E544C;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .sucursal-selector {
                order: 1;
                width: 100%;
                margin-bottom: 10px;
            }
            
            .sucursal-selector .form-select {
                max-width: 100% !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-container">
                <div class="logo-container">
                    <img src="../../assets/img/Logo.svg" alt="Batidos Pitaya" class="logo">
                </div>
                
                <div class="buttons-container">                    
                    <?php if ($esAdmin || verificarAccesoCargo([5, 16, 35])): ?>
                        <a href="calendario.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'calendario.php' ? 'activo' : '' ?>">
                            <i class="fas fa-calendar-alt"></i> <span class="btn-text">Calendario</span>
                        </a>
                    <?php endif; ?>
                    
                    <a href="#" onclick="openMaintenanceForm()" class="btn-agregar">
                        <i class="fas fa-tools"></i> <span class="btn-text">Mantenimiento General</span>
                    </a>
                    
                    <a href="#" onclick="openEquipmentForm()" class="btn-agregar">
                        <i class="fas fa-laptop"></i> <span class="btn-text">Cambio de Equipos</span>
                    </a>
                    
                    <a href="dashboard_sucursales.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'dashboard_sucursales.php' ? 'activo' : '' ?>">
                        <i class="fas fa-sync-alt"></i> <span class="btn-text">Solicitudes</span>
                    </a>
                    
                    <a href="#" onclick="location.reload()" class="btn-agregar" style="display:none;">
                        <i class="fas fa-sync-alt"></i> <span class="btn-text">Solicitudes</span>
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
                    <a href="../index.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </header>
        
        <!-- Información del usuario -->
        <div class="user-info-panel">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-1">Solicitudes de Mantenimiento</h5>
                    <p class="mb-2"><?= htmlspecialchars($sucursal['nombre']) ?></p>
                    <?= $infoAcceso ?>
                </div>
                <div class="col-md-6">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h4 class="mb-0"><?= $stats['total'] ?></h4>
                            <small>Total</small>
                        </div>
                        <div class="stat-card">
                            <h4 class="mb-0"><?= $stats['agendado'] ?></h4>
                            <small>En Proceso</small>
                        </div>
                        <div class="stat-card">
                            <h4 class="mb-0"><?= $stats['finalizado'] ?></h4>
                            <small>Finalizados</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de tickets -->
        <div class="table-container">
            <div class="card shadow">

                <div class="card-body">
                    <?php if (empty($tickets)): ?>
                        <div class="no-tickets">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No tienes solicitudes de mantenimiento</h5>
                            <p class="text-muted">Usa los botones de arriba para crear tu primera solicitud</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table id="ticketsTable" class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Código</th>
                                        <th>Título</th>
                                        <th>Descripción</th>
                                        <th>Estado</th>
                                        <th>Categoría</th>
                                        <th>Fecha Programada</th>
                                        <th>Tipo</th>
                                        <th>Foto</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tickets as $t): ?>
                                    <tr class="ticket-row">
                                        <td>
                                            <strong><?= htmlspecialchars($t['codigo']) ?></strong>
                                            <br><small class="text-muted"><?= date('d/m/Y', strtotime($t['created_at'])) ?></small>
                                        </td>
                                        <td>
                                            <div style="max-width: 200px;">
                                                <?= htmlspecialchars($t['titulo']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="max-width: 200px;">
                                                <?= htmlspecialchars(substr($t['descripcion'], 0, 200)) ?>
                                                <?= strlen($t['descripcion']) > 200 ? '...' : '' ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= $t['status'] ?>">
                                                <?= ucfirst($t['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($t['tipo_caso_nombre'] ?? 'Sin asignar') ?>
                                        </td>
                                        <td>
                                            <?php if ($t['fecha_inicio'] && $t['fecha_final']): ?>
                                                <strong><?= date('d/m/Y', strtotime($t['fecha_inicio'])) ?></strong>
                                                <br><small>hasta <?= date('d/m/Y', strtotime($t['fecha_final'])) ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">Sin programar</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= $t['tipo_formulario'] === 'mantenimiento_general' ? 'bg-primary' : 'bg-info' ?>">
                                                <?= $t['tipo_formulario'] === 'mantenimiento_general' ? 'Mantenimiento' : 'Equipos' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $ticketFotos = $ticket->getFotos($t['id']);
                                            if (!empty($ticketFotos)): 
                                            ?>
                                                <div class="photo-gallery-preview" onclick="showPhotosModal(<?= $t['id'] ?>)" style="cursor: pointer;">
                                                    <img src="uploads/tickets/<?= $ticketFotos[0]['foto'] ?>" alt="Foto" class="ticket-photo" style="width: 40px; height: 40px; object-fit: cover; border-radius: 5px;">
                                                    <?php if (count($ticketFotos) > 1): ?>
                                                        <span class="badge bg-primary" style="position: absolute; bottom: 0; right: 0; font-size: 0.7rem;">
                                                            +<?= count($ticketFotos) - 1 ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <small class="text-muted">Sin fotos</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-success" onclick="openChat(<?= $t['id'] ?>)" title="Chat de seguimiento">
                                                <i class="fas fa-comments"></i>
                                            </button>
                                            <button class="btn btn-sm btn-info" onclick="viewTicket(<?= $t['id'] ?>)" title="Ver detalles">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Botones flotantes para nuevas solicitudes (backup para móviles) -->
    <div class="new-ticket-buttons d-md-none">
        <div class="text-end mb-2">
            <button class="fab-button fab-mantenimiento" onclick="openMaintenanceForm()" title="Mantenimiento General">
                <i class="fas fa-tools"></i>
            </button>
        </div>
        <div class="text-end">
            <button class="fab-button fab-equipos" onclick="openEquipmentForm()" title="Cambio de Equipos">
                <i class="fas fa-laptop"></i>
            </button>
        </div>
    </div>

    <!-- Modal para ver detalles -->
    <div class="modal fade" id="ticketModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalles de la Solicitud</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="ticketModalBody">
                    <!-- Contenido cargado dinámicamente -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <script>
        // Manejar cambio de sucursal
        document.getElementById('selectSucursal')?.addEventListener('change', function() {
            const nuevaSucursal = this.value;
            const url = `dashboard_sucursales.php?cod_operario=<?= $cod_operario ?>&cod_sucursal=${nuevaSucursal}`;
            window.location.href = url;
        });

        const codOperario = '<?= $cod_operario ?>';
        const codSucursal = '<?= $cod_sucursal ?>';
        
        $(document).ready(function() {
            // Inicializar DataTable
            $('#ticketsTable').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
                },
                order: [[0, 'desc']],
                pageLength: 15,
                columnDefs: [
                    { orderable: false, targets: [7] }
                ]
            });
            
            // Manejar clic en filas
            $('.ticket-row').click(function(e) {
                if (!$(e.target).closest('button').length && !$(e.target).closest('a').length) {
                    const ticketId = $(this).find('button[onclick*="viewTicket"]').attr('onclick').match(/\d+/)[0];
                    viewTicket(ticketId);
                }
            });
        });
        
        function openMaintenanceForm() {
            const url = `formulario_mantenimiento.php?cod_operario=${codOperario}&cod_sucursal=${codSucursal}`;
            window.location.href = url;
        }
        
        function openEquipmentForm() {
            const url = `formulario_equipos.php?cod_operario=${codOperario}&cod_sucursal=${codSucursal}`;
            window.location.href = url;
        }
        
        function openChat(ticketId) {
            const url = `chat.php?ticket_id=${ticketId}&emisor=solicitante`;
            window.location.href = url;
        }
        
        function viewTicket(id) {
            $.ajax({
                url: 'ajax/get_ticket_details_readonly.php',
                method: 'GET',
                data: { id: id },
                success: function(response) {
                    $('#ticketModalBody').html(response);
                    $('#ticketModal').modal('show');
                },
                error: function() {
                    alert('Error al cargar los detalles del ticket');
                }
            });
        }
        
        // Actualizar cada 60 segundos
        //setInterval(function() {
        //    if (!$('.modal.show').length) {
        //        location.reload();
        //    }
        //}, 60000);


        // Agregar la misma función showPhotosModal del dashboard_mantenimiento.php
        function showPhotosModal(ticketId) {
            $.ajax({
                url: 'ajax/get_ticket_photos.php',
                method: 'GET',
                data: { ticket_id: ticketId },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.fotos.length > 0) {
                        let html = '';
                        
                        html += '<div id="photosCarousel" class="carousel slide photos-carousel" data-bs-ride="false">';
                        html += '<div class="carousel-inner">';
                        
                        response.fotos.forEach((foto, index) => {
                            html += `<div class="carousel-item ${index === 0 ? 'active' : ''}">
                                <img src="uploads/tickets/${foto.foto}" class="d-block w-100" alt="Foto ${index + 1}" style="max-height: 500px; object-fit: contain;">
                                <div class="text-center mt-2">
                                    <small class="text-muted">Foto ${index + 1} de ${response.fotos.length}</small>
                                </div>
                            </div>`;
                        });
                        
                        html += '</div>';
                        
                        if (response.fotos.length > 1) {
                            html += `
                                <button class="carousel-control-prev" type="button" data-bs-target="#photosCarousel" data-bs-slide="prev">
                                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                </button>
                                <button class="carousel-control-next" type="button" data-bs-target="#photosCarousel" data-bs-slide="next">
                                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                </button>
                            `;
                            
                            html += '<div class="d-flex gap-2 mt-3 overflow-auto p-2">';
                            response.fotos.forEach((foto, index) => {
                                html += `
                                    <img src="uploads/tickets/${foto.foto}" 
                                        class="carousel-thumbnail ${index === 0 ? 'active' : ''}" 
                                        data-bs-target="#photosCarousel" 
                                        data-bs-slide-to="${index}"
                                        style="width: 80px; height: 80px; object-fit: cover; border: 2px solid ${index === 0 ? '#51B8AC' : '#dee2e6'}; border-radius: 5px; cursor: pointer;"
                                        alt="Thumbnail ${index + 1}">
                                `;
                            });
                            html += '</div>';
                        }
                        
                        html += '</div>';
                        
                        $('#photosGalleryBody').html(html);
                        
                        const carousel = document.getElementById('photosCarousel');
                        if (carousel) {
                            carousel.addEventListener('slid.bs.carousel', function (e) {
                                $('.carousel-thumbnail').css('border-color', '#dee2e6');
                                $(`.carousel-thumbnail[data-bs-slide-to="${e.to}"]`).css('border-color', '#51B8AC');
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