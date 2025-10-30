<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'models/Ticket.php';
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

verificarAutenticacion();

$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

if (!verificarAccesoCargo([5, 11, 14, 16, 35]) && !$esAdmin) {
    header('Location: ../index.php');
    exit();
}

$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);

$ticket = new Ticket();
$colaboradoresDisponibles = $ticket->getColaboradoresDisponibles();

// Filtro de colaborador
$colaborador_filtro = isset($_GET['colaborador']) ? intval($_GET['colaborador']) : null;

// Obtener tickets del colaborador
$tickets = [];
if ($colaborador_filtro) {
    $tickets = $ticket->getTicketsPorColaborador($colaborador_filtro, date('Y-m-d'));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda de Colaboradores</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
        
        .filter-section {
            background: linear-gradient(135deg, #51B8AC 0%, #0E544C 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .ticket-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .ticket-card {
            background: white;
            border-left: 5px solid;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        
        .ticket-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .ticket-urgencia-1 { border-left-color: #28a745; }
        .ticket-urgencia-2 { border-left-color: #ffc107; }
        .ticket-urgencia-3 { border-left-color: #fd7e14; }
        .ticket-urgencia-4 { border-left-color: #dc3545; }
        .ticket-equipos { border-left-color: #dc3545; }
        
        .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }
        
        .ticket-date {
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 6px;
            font-weight: bold;
            color: #0E544C;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }
        
        .status-agendado { background: #fd7e14; color: white; }
        .status-finalizado { background: #198754; color: white; }
        
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
                    <a href="calendario.php" class="btn-agregar">
                        <i class="fas fa-calendar-alt"></i> <span class="btn-text">Calendario</span>
                    </a>
                    
                    <a href="agenda_colaborador.php" class="btn-agregar activo">
                        <i class="fas fa-tasks"></i> <span class="btn-text">Agenda Colaboradores</span>
                    </a>
                    
                    <a href="dashboard_mantenimiento.php" class="btn-agregar">
                        <i class="fas fa-list"></i> <span class="btn-text">Solicitudes</span>
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
                        <small><?= htmlspecialchars($cargoUsuario) ?></small>
                    </div>
                    <a href="../index.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </header>

        <div class="filter-section">
            <h4 class="mb-3">
                <i class="fas fa-filter me-2"></i>
                Filtrar Agenda por Colaborador
            </h4>
            <form method="GET" class="row g-3">
                <div class="col-md-8">
                    <select name="colaborador" class="form-select" required>
                        <option value="">Seleccionar colaborador...</option>
                        <?php foreach ($colaboradoresDisponibles as $col): ?>
                            <option value="<?= $col['CodOperario'] ?>" 
                                    <?= $colaborador_filtro == $col['CodOperario'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($col['Nombre'] . ' ' . ($col['Apellido'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-light w-100">
                        <i class="fas fa-search me-2"></i>Buscar
                    </button>
                </div>
            </form>
        </div>

        <?php if ($colaborador_filtro): ?>
            <div class="mb-3">
                <h5>
                    <i class="fas fa-calendar-check me-2"></i>
                    Tickets programados (<?= count($tickets) ?>)
                </h5>
            </div>

            <?php if (empty($tickets)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No hay tickets asignados a este colaborador</p>
                </div>
            <?php else: ?>
                <div class="ticket-list">
                    <?php foreach ($tickets as $t): ?>
                        <?php
                        $borderClass = $t['tipo_formulario'] === 'cambio_equipos' ? 
                            'ticket-equipos' : 
                            'ticket-urgencia-' . ($t['nivel_urgencia'] ?? '0');
                        ?>
                        <div class="ticket-card <?= $borderClass ?>" onclick="verDetalleTicket(<?= $t['id'] ?>)">
                            <div class="ticket-header">
                                <div>
                                    <h6 class="mb-1">
                                        <i class="fas fa-<?= $t['tipo_formulario'] === 'cambio_equipos' ? 'laptop' : 'tools' ?> me-2"></i>
                                        <?= htmlspecialchars($t['titulo']) ?>
                                    </h6>
                                    <small class="text-muted">
                                        <i class="fas fa-map-marker-alt me-1"></i>
                                        <?= htmlspecialchars($t['nombre_sucursal']) ?>
                                    </small>
                                </div>
                                <span class="status-badge status-<?= $t['status'] ?>">
                                    <?= ucfirst($t['status']) ?>
                                </span>
                            </div>
                            
                            <div class="mb-2">
                                <?= htmlspecialchars(substr($t['descripcion'], 0, 150)) ?>
                                <?= strlen($t['descripcion']) > 150 ? '...' : '' ?>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="ticket-date">
                                    <i class="fas fa-calendar me-2"></i>
                                    <?= date('d/m/Y', strtotime($t['fecha_inicio'])) ?>
                                    <?php if ($t['fecha_final'] != $t['fecha_inicio']): ?>
                                        - <?= date('d/m/Y', strtotime($t['fecha_final'])) ?>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($t['total_fotos'] > 0): ?>
                                    <small class="text-muted">
                                        <i class="fas fa-images me-1"></i>
                                        <?= $t['total_fotos'] ?> foto(s)
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-user-friends fa-3x text-muted mb-3"></i>
                <p class="text-muted">Selecciona un colaborador para ver su agenda</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        function verDetalleTicket(ticketId) {
            window.location.href = 'dashboard_mantenimiento.php#ticket-' + ticketId;
        }
    </script>
</body>
</html>