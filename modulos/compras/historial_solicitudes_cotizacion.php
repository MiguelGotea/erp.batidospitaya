<?php
//ini_set('display_errors', 1);
//error_reporting(E_ALL);

require_once '../../includes/funciones.php';
require_once '../../includes/auth.php';
require_once '../../core/permissions/permissions.php';
require_once 'includes/funciones_compras.php';
require_once '../../includes/menu_lateral.php';
require_once '../../includes/header_universal.php';

verificarAutenticacion();

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Obtener información del usuario actual
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';
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
    <link rel="stylesheet" href="css/historial_solicitudes.css?v=<?php echo time(); ?>">
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    
    <div class="main-container">
        <div class="contenedor-principal">
            <?php echo renderHeader($usuario, $esAdmin, 'Solicitudes de Cotización'); ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="container-fluid p-3">
                <!-- Botón para nueva solicitud -->
                <?php if (tienePermiso('historial_solicitudes_cotizacion', 'boton_nuevo', $cargoOperario)): ?>
                <div class="mb-3">
                    <a href="solicitud_cotizacion.php" class="btn btn-success">
                        <i class="bi bi-plus-circle"></i> Nueva Solicitud
                    </a>
                </div>
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
        const esAdmin = <?php echo $esAdmin ? 'true' : 'false'; ?>;
        const usuarioId = <?php echo $_SESSION['usuario_id']; ?>;
    </script>
    <script src="js/historial_solicitudes.js?v=<?php echo time(); ?>"></script>
</body>
</html>