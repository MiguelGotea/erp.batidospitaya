<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
// solicitudes_vacaciones.php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';

// Verificar conexión
if (!$conn) {
    die("Error de conexión a la base de datos");
}

// Verificar autenticación

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Obtener cargos del usuario
$cargosUsuario = obtenerCargosUsuario($_SESSION['usuario_id']);
$esCargo11 = in_array(11, $cargosUsuario);
$esCargo13 = in_array(13, $cargosUsuario);
$esCargo28 = in_array(28, $cargosUsuario);
$esCargo16 = in_array(16, $cargosUsuario); // Gerencia

// Obtener sucursales para filtros
if ($esCargo13 || $esCargo28 || $esCargo16) {
    $sucursales = obtenerTodasSucursales();
} else {
    $sucursales = obtenerSucursalesUsuario($_SESSION['usuario_id']);
}

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'ajax/solicitudes_vacaciones_procesar_accion.php';
    procesarAccionSolicitud();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitudes de Vacaciones</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/solicitudes_vacaciones.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    
    <div class="main-container">
        <div class="contenedor-principal">
            <?php echo renderHeader($usuario, false, 'Solicitudes de Vacaciones'); ?>
            
            <div class="container-fluid p-3">
                <?php if (isset($_SESSION['exito'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= $_SESSION['exito'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['exito']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?= $_SESSION['error'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                
                <!-- Botón Nueva Solicitud -->
                <div class="mb-3">
                    <button class="btn btn-success" onclick="mostrarModalNuevaSolicitud()">
                        <i class="bi bi-plus-circle"></i> Nueva Solicitud
                    </button>
                </div>

                <!-- Tabla con filtros -->
                <div class="table-responsive">
                    <table class="table table-hover solicitudes-table" id="tablaSolicitudes">
                        <thead>
                            <tr>
                                <th data-column="colaborador" data-type="text">
                                    Colaborador
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="sucursal" data-type="list">
                                    Sucursal
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="fecha_inicio" data-type="daterange">
                                    Fecha Inicio
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="fecha_fin" data-type="daterange">
                                    Fecha Fin
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th>Tipo</th>
                                <th data-column="estado" data-type="list">
                                    Estado
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="fecha_solicitud" data-type="daterange">
                                    Fecha Solicitud
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th>Foto</th>
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

    <!-- Modal Nueva Solicitud -->
    <div class="modal fade" id="modalNuevaSolicitud" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nueva Solicitud de Vacaciones</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" enctype="multipart/form-data" action="solicitudes_vacaciones.php">
                    <input type="hidden" name="accion" value="nueva_solicitud">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="solicitud_operario" class="form-label">Colaborador *</label>
                            <select id="solicitud_operario" name="cod_operario" class="form-select" required>
                                <option value="">Seleccione un colaborador</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="solicitud_fecha_inicio" class="form-label">Fecha Inicio *</label>
                            <input type="date" id="solicitud_fecha_inicio" name="fecha_inicio" class="form-control" 
                                   min="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="solicitud_fecha_fin" class="form-label">Fecha Fin *</label>
                            <input type="date" id="solicitud_fecha_fin" name="fecha_fin" class="form-control" 
                                   min="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="solicitud_observaciones" class="form-label">Observaciones (Opcional)</label>
                            <textarea id="solicitud_observaciones" name="observaciones" class="form-control" rows="3" 
                                      placeholder="Explique el motivo de la solicitud (opcional)..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="solicitud_foto" class="form-label">Foto de Soporte (Opcional)</label>
                            <input type="file" id="solicitud_foto" name="foto_soporte" class="form-control" accept="image/*">
                            <small class="text-muted">Máximo 5MB</small>
                        </div>
                        
                        <div id="info-rango-solicitud" class="info-resumen" style="display: none;">
                            <p><strong>Resumen del rango seleccionado:</strong></p>
                            <p id="info-dias-totales-solicitud">Días totales: 0</p>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Enviar Solicitud</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Aprobar -->
    <div class="modal fade" id="modalAprobar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tituloAprobar">Aprobar Solicitud</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <input type="hidden" name="accion" id="accionAprobar" value="">
                    <input type="hidden" name="id_solicitud" id="idSolicitudAprobar" value="">
                    
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> ¿Está seguro de aprobar esta solicitud?
                        </div>
                        <div class="info-resumen" id="infoAprobar"></div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Confirmar Aprobación</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Rechazar -->
    <div class="modal fade" id="modalRechazar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Rechazar Solicitud</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <input type="hidden" name="accion" value="rechazar">
                    <input type="hidden" name="id_solicitud" id="idSolicitudRechazar" value="">
                    
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> ¿Está seguro de rechazar esta solicitud?
                        </div>
                        <div class="info-resumen" id="infoRechazar"></div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Confirmar Rechazo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Variables de permisos para JS
        const PERMISOS_USUARIO = {
            esCargo11: <?= $esCargo11 ? 'true' : 'false' ?>,
            esCargo13: <?= $esCargo13 ? 'true' : 'false' ?>,
            esCargo28: <?= $esCargo28 ? 'true' : 'false' ?>,
            esCargo16: <?= $esCargo16 ? 'true' : 'false' ?>,
            usuarioId: <?= $_SESSION['usuario_id'] ?>
        };
    </script>
    <script src="js/solicitudes_vacaciones.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>
</html>