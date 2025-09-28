<?php
session_start();
require_once 'models/Ticket.php';

$ticket = new Ticket();
$tickets = $ticket->getAll();
$tipos_casos = $ticket->getTiposCasos();

// Obtener estadísticas
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
    <title>Dashboard de Mantenimiento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
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
        .bulk-actions {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="fas fa-tools me-2"></i>
                Sistema de Mantenimiento - Dashboard Principal
            </span>
            <div class="navbar-nav flex-row">
                <a class="nav-link me-3" href="calendario.php">
                    <i class="fas fa-calendar-alt me-2"></i>Calendario
                </a>
                <a class="nav-link" href="#" onclick="refreshData()">
                    <i class="fas fa-sync-alt me-2"></i>Actualizar
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Estadísticas -->
        <div class="stats-card">
            <h5><i class="fas fa-chart-bar me-2"></i>Estadísticas del Sistema</h5>
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
                    <h3><?= $stats['clasificado'] ?></h3>
                    <p>Clasificados</p>
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

        <!-- Acciones masivas -->
        <div class="bulk-actions" id="bulkActions">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <span id="selectedCount">0 tickets seleccionados</span>
                </div>
                <div class="col-md-6">
                    <div class="row">
                        <div class="col">
                            <input type="date" class="form-control form-control-sm" id="bulkFechaInicio" placeholder="Fecha Inicio">
                        </div>
                        <div class="col">
                            <input type="date" class="form-control form-control-sm" id="bulkFechaFinal" placeholder="Fecha Final">
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary btn-sm" onclick="bulkAssignDates()">
                        <i class="fas fa-calendar-plus me-2"></i>Asignar Fechas
                    </button>
                    <button class="btn btn-secondary btn-sm ms-2" onclick="clearSelection()">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabla de tickets -->
        <div class="card shadow">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Lista de Tickets de Mantenimiento
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="ticketsTable" class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>
                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                </th>
                                <th>Código</th>
                                <th>Título</th>
                                <th>Sucursal</th>
                                <th>Solicitante</th>
                                <th>Tipo</th>
                                <th>Urgencia</th>
                                <th>Estado</th>
                                <th>Categoría</th>
                                <th>F. Inicio</th>
                                <th>F. Final</th>
                                <th>Foto</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $t): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="form-check-input ticket-checkbox" value="<?= $t['id'] ?>">
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($t['codigo']) ?></strong>
                                    <br><small class="text-muted"><?= date('d/m/Y', strtotime($t['created_at'])) ?></small>
                                </td>
                                <td>
                                    <div style="max-width: 200px;">
                                        <?= htmlspecialchars($t['titulo']) ?>
                                        <br><small class="text-muted"><?= htmlspecialchars(substr($t['descripcion'], 0, 50)) ?>...</small>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($t['nombre_sucursal'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($t['nombre_operario'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge bg-info">
                                        <?= $t['tipo_formulario'] === 'mantenimiento_general' ? 'Mantenimiento' : 'Equipos' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($t['nivel_urgencia']): ?>
                                        <div class="urgency-bar">
                                            <div class="urgency-fill urgency-<?= $t['nivel_urgencia'] ?>" 
                                                 style="width: <?= $t['nivel_urgencia'] * 25 ?>%"></div>
                                        </div>
                                        <small>Nivel <?= $t['nivel_urgencia'] ?></small>
                                    <?php else: ?>
                                        <small class="text-muted">Sin clasificar</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $t['status'] ?>">
                                        <?= ucfirst($t['status']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($t['tipo_caso_nombre'] ?? 'Sin asignar') ?></td>
                                <td>
                                    <?= $t['fecha_inicio'] ? date('d/m/Y', strtotime($t['fecha_inicio'])) : '-' ?>
                                </td>
                                <td>
                                    <?= $t['fecha_final'] ? date('d/m/Y', strtotime($t['fecha_final'])) : '-' ?>
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
                                        <button class="btn btn-sm btn-primary" onclick="viewTicket(<?= $t['id'] ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-success" onclick="openChat(<?= $t['id'] ?>)">
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
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Fotografía del Ticket</h5>
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
        $(document).ready(function() {
            // Inicializar DataTable
            $('#ticketsTable').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
                },
                order: [[1, 'desc']],
                pageLength: 25,
                columnDefs: [
                    { orderable: false, targets: [0, 11, 12] }
                ]
            });
            
            // Manejar selección de checkboxes
            $('#selectAll').change(function() {
                $('.ticket-checkbox').prop('checked', $(this).is(':checked'));
                updateBulkActions();
            });
            
            $('.ticket-checkbox').change(function() {
                updateBulkActions();
                
                // Actualizar checkbox principal
                const totalCheckboxes = $('.ticket-checkbox').length;
                const checkedCheckboxes = $('.ticket-checkbox:checked').length;
                
                if (checkedCheckboxes === 0) {
                    $('#selectAll').prop('indeterminate', false);
                    $('#selectAll').prop('checked', false);
                } else if (checkedCheckboxes === totalCheckboxes) {
                    $('#selectAll').prop('indeterminate', false);
                    $('#selectAll').prop('checked', true);
                } else {
                    $('#selectAll').prop('indeterminate', true);
                }
            });
        });
        
        function updateBulkActions() {
            const checkedBoxes = $('.ticket-checkbox:checked');
            const count = checkedBoxes.length;
            
            if (count > 0) {
                $('#bulkActions').show();
                $('#selectedCount').text(count + ' tickets seleccionados');
            } else {
                $('#bulkActions').hide();
            }
        }
        
        function clearSelection() {
            $('.ticket-checkbox').prop('checked', false);
            $('#selectAll').prop('checked', false);
            $('#selectAll').prop('indeterminate', false);
            updateBulkActions();
        }
        
        function bulkAssignDates() {
            const selectedIds = [];
            $('.ticket-checkbox:checked').each(function() {
                selectedIds.push($(this).val());
            });
            
            const fechaInicio = $('#bulkFechaInicio').val();
            const fechaFinal = $('#bulkFechaFinal').val();
            
            if (!fechaInicio || !fechaFinal) {
                alert('Debe seleccionar ambas fechas');
                return;
            }
            
            if (fechaInicio > fechaFinal) {
                alert('La fecha de inicio no puede ser mayor a la fecha final');
                return;
            }
            
            if (selectedIds.length === 0) {
                alert('Debe seleccionar al menos un ticket');
                return;
            }
            
            // Enviar solicitud AJAX
            $.ajax({
                url: 'ajax/bulk_assign_dates.php',
                method: 'POST',
                data: {
                    ticket_ids: selectedIds,
                    fecha_inicio: fechaInicio,
                    fecha_final: fechaFinal
                },
                success: function(response) {
                    if (response.success) {
                        alert('Fechas asignadas exitosamente');
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error en la comunicación con el servidor');
                }
            });
        }
        
        function viewTicket(id) {
            $.ajax({
                url: 'ajax/get_ticket_details.php',
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
        
        function openChat(ticketId) {
            window.open('chat.php?ticket_id=' + ticketId + '&emisor=mantenimiento', 
                       'chat_' + ticketId, 
                       'width=800,height=600,scrollbars=yes,resizable=yes');
        }
        
        function showPhotoModal(photoSrc) {
            $('#modalPhoto').attr('src', photoSrc);
            $('#photoModal').modal('show');
        }
        
        function refreshData() {
            location.reload();
        }
        
        // Actualizar cada 30 segundos
        setInterval(function() {
            // Solo actualizar si no hay modales abiertos
            if (!$('.modal.show').length) {
                refreshData();
            }
        }, 30000);
    </script>
</body>
</html>