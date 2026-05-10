<?php
//ini_set('display_errors', 1);
//error_reporting(E_ALL);

require_once '../../core/auth/auth.php';
require_once '../../core/permissions/permissions.php';
require_once 'includes/funciones_compras.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';


$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Obtener información del usuario actual
$cargosUsuario = obtenerCargosUsuario($_SESSION['usuario_id']);

if (!tienePermiso('historial_solicitudes_cotizacion', 'vista', $cargoOperario)) {
    header('Location: /index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Solicitudes de Cotización</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/fab_button.css">
    <link rel="stylesheet" href="css/historial_solicitudes.css?v=<?php echo time(); ?>">
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    
    <div class="main-container">
        <div class="contenedor-principal">
            <?php echo renderHeader($usuario, false, 'Solicitudes de Cotización'); ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($_SESSION['success']);
    unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php
endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($_SESSION['error']);
    unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php
endif; ?>
            
            <div class="container-fluid p-3">

                <!-- Botón Flotante Nueva Solicitud -->
                <?php if (tienePermiso('historial_solicitudes_cotizacion', 'boton_nuevo', $cargoOperario)): ?>
                <a href="solicitud_cotizacion.php" class="btn-floating-pitaya" title="Nueva Solicitud de Cotización">
                    <i class="fas fa-plus"></i>
                </a>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-hover solicitudes-table" id="tablaSolicitudes">
                        <thead>
                            <tr>
                                <th data-column="codigo" data-type="text">
                                    Código
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="fecha_solicitud" data-type="daterange">
                                    Fecha
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="solicitante_nombre" data-type="text">
                                    Solicitante
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="productos_resumen" data-type="text">
                                    Productos
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="estado" data-type="list">
                                    Estado
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="gerente_aprobador_nombre" data-type="list">
                                    Gerencia
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="updated_at" data-type="daterange">
                                    Última Actualización
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th style="width: 200px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaSolicitudesBody">
                            <!-- Datos cargados vía AJAX -->
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div class="d-flex align-items-center gap-2">
                        <label class="mb-0">Mostrar:</label>
                        <select class="form-select form-select-sm" id="registrosPorPagina" style="width: auto;" onchange="cambiarRegistrosPorPagina()">
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        <span class="mb-0">registros</span>
                    </div>
                    <div id="paginacion"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para acciones -->
    <div class="modal fade" id="actionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="procesar_accion_solicitud.php" id="actionForm">
                    <input type="hidden" name="solicitud_id" id="solicitudId">
                    <input type="hidden" name="accion" id="accionInput">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Confirmar Acción</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="observaciones_accion" class="form-label">Observaciones (opcional):</label>
                            <textarea class="form-control" id="observaciones_accion" name="observaciones_accion" 
                                      rows="4" placeholder="Explique la razón de esta acción..."></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Cancelar
                        </button>
                        <button type="submit" class="btn" id="modalActionBtn">
                            Confirmar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const esAdmin = false;
        const usuarioId = <?php echo $_SESSION['usuario_id']; ?>;
    </script>
    <script src="js/historial_solicitudes.js?v=<?php echo time(); ?>"></script>

    <!-- Modal de Ayuda -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-info-circle me-2"></i>
                        Guía — Historial de Solicitudes
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-primary fw-bold border-bottom pb-2">
                                        <i class="fas fa-filter me-2"></i> ¿Qué solicitudes puedo ver?
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        - <strong>Gerencia:</strong> Tiene acceso a todas las solicitudes del sistema.<br>
                                        - <strong>Gestión de Pedidos y Otros Cargos:</strong> Pueden ver sus propias solicitudes y todas aquellas que ya han sido <strong>Aprobadas</strong> o <strong>Finalizadas</strong>.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-success fw-bold border-bottom pb-2">
                                        <i class="fas fa-check-double me-2"></i> ¿Dónde están las acciones?
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Para garantizar una revisión profesional, las acciones de <strong>Aprobar</strong> o <strong>Rechazar</strong> se realizan exclusivamente dentro del <strong>Detalle de la Solicitud</strong>. Esto asegura que se revisen todos los productos y fotos antes de tomar una decisión.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-warning fw-bold border-bottom pb-2">
                                        <i class="fas fa-stream me-2"></i> Flujo de Estados
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Las solicitudes inician como <strong>Pendientes</strong> para revisión de Gerencia. Si se aprueban, pasan a <strong>Aprobada</strong> para que el personal encargado gestione el pedido. Finalmente, se marcan como <strong>Finalizada</strong> cuando la compra concluye o <strong>Cancelada</strong> si se desiste de ella.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>