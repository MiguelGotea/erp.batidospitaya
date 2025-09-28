<?php
session_start();
require_once 'models/Ticket.php';

// Validar parámetros
if (!isset($_GET['cod_operario']) || !isset($_GET['cod_sucursal'])) {
    die("Parámetros requeridos faltantes");
}

$cod_operario = $_GET['cod_operario'];
$cod_sucursal = $_GET['cod_sucursal'];

$ticket = new Ticket();

// Obtener información del operario y sucursal
global $db;
$operario = $db->fetchOne("SELECT Nombre FROM Operarios WHERE CodOperario = ?", [$cod_operario]);
$sucursal = $db->fetchOne("SELECT nombre FROM sucursales WHERE codigo = ?", [$cod_sucursal]);

// Obtener tickets de la sucursal
$filters = [
    'cod_sucursal' => $cod_sucursal
];
$tickets = $ticket->getAll($filters);

// Estadísticas
$stats = [
    'total' => count($tickets),
    'solicitado' => count(array_filter($tickets, fn($t) => $t['status'] === 'solicitado')),
    'clasificado' => count(array_filter($tickets, fn($t) => $t['status'] === 'clasificado')),
    'agendado' => count(array_filter($tickets, fn($t) => $t['status'] === 'agendado')),
    'finalizado' => count(array_filter($tickets, fn($t) => $t['status'] === 'finalizado'))
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Solicitudes de Mantenimiento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .user-info {
            background: linear-gradient(135deg, #20c997 0%, #0d6efd 100%);
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
            background: white;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
        }
        .fab-button:hover {
            transform: scale(1.1);
        }
        .fab-mantenimiento {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .fab-equipos {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="fas fa-building me-2"></i>
                Panel de Solicitudes
            </span>
            <div class="navbar-nav flex-row">
                <a class="nav-link" href="#" onclick="location.reload()">
                    <i class="fas fa-sync-alt me-2"></i>Actualizar
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Información del usuario -->
        <div class="user-info">
            <div class="row">
                <div class="col-md-6">
                    <h5>
                        <i class="fas fa-user me-2"></i>
                        <?= htmlspecialchars($operario['Nombre'] ?? 'Usuario') ?>
                    </h5>
                    <p class="mb-0">
                        <i class="fas fa-map-marker-alt me-2"></i>
                        <?= htmlspecialchars($sucursal['nombre'] ?? 'Sucursal') ?>
                    </p>
                </div>
                <div class="col-md-6">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h4><?= $stats['total'] ?></h4>
                            <small>Total</small>
                        </div>
                        <div class="stat-card">
                            <h4><?= $stats['agendado'] ?></h4>
                            <small>En Proceso</small>
                        </div>
                        <div class="stat-card">
                            <h4><?= $stats['finalizado'] ?></h4>
                            <small>Finalizados</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de tickets -->
        <div class="card shadow">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="fas fa-list-alt me-2"></i>
                    Mis Solicitudes de Mantenimiento
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($tickets)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No tienes solicitudes de mantenimiento</h5>
                        <p class="text-muted">Usa los botones de abajo para crear tu primera solicitud</p>
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

    <!-- Botones flotantes para nuevas solicitudes -->
    <div class="new-ticket-buttons">
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

    <script>
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
        });
        
        function openMaintenanceForm() {
            const url = `formulario_mantenimiento.php?cod_operario=${codOperario}&cod_sucursal=${codSucursal}`;
            window.open(url, 'mantenimiento_form', 'width=900,height=700,scrollbars=yes,resizable=yes');
        }
        
        function openEquipmentForm() {
            const url = `formulario_equipos.php?cod_operario=${codOperario}&cod_sucursal=${codSucursal}`;
            window.open(url, 'equipos_form', 'width=900,height=700,scrollbars=yes,resizable=yes');
        }
        
        function openChat(ticketId) {
            const url = `chat.php?ticket_id=${ticketId}&emisor=solicitante`;
            window.open(url, 'chat_' + ticketId, 'width=800,height=600,scrollbars=yes,resizable=yes');
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
        setInterval(function() {
            if (!$('.modal.show').length) {
                location.reload();
            }
        }, 60000);
        
        // Manejar clic en filas
        $('.ticket-row').click(function(e) {
            if (!$(e.target).closest('button').length) {
                const ticketId = $(this).find('button[onclick*="viewTicket"]').attr('onclick').match(/\d+/)[0];
                viewTicket(ticketId);
            }
        });
    </script>
</body>
</html>