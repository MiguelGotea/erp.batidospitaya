<?php
// gestion_proyectos.php
// Vista principal del Sistema de Gestión de Proyectos Gantt

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar permiso de vista
if (!tienePermiso('gestion_proyectos', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

$permisoCrear = tienePermiso('gestion_proyectos', 'crear_proyecto', $cargoOperario);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Proyectos Gantt | Batidos Pitaya</title>

    <!-- CSS Dependencies -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/gestion_proyectos.css?v=<?php echo mt_rand(1, 10000); ?>">

    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Gestión de Proyectos Gantt'); ?>

            <div class="container-fluid py-4">

                <!-- GANTT SECTION -->
                <div class="card shadow-sm border-0 mb-5">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                        <h5 class="mb-0 text-dark font-weight-bold">Diagrama de Gantt - Planificación Estratégica</h5>
                        <div class="gantt-controls d-flex gap-2">
                            <button class="btn btn-sm btn-outline-secondary" onclick="navegarGantt('anterior')">
                                <i class="fas fa-chevron-left"></i> Mes Anterior
                            </button>
                            <button class="btn btn-sm btn-outline-primary mx-2" onclick="irAHoy()">
                                Hoy
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="navegarGantt('siguiente')">
                                Mes Siguiente <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div id="ganttContainer" class="gantt-wrapper">
                            <!-- Gantt content injected via JS -->
                            <div class="gantt-loader text-center py-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="sr-only">Cargando...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- HISTORY SECTION -->
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 text-dark font-weight-bold">Historial de Proyectos Finalizados</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3 align-items-end">
                            <div class="col-md-2">
                                <label class="small font-weight-bold">Registros:</label>
                                <select id="historialLimit" class="form-control form-control-sm"
                                    onchange="cargarHistorial(1)">
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                            </div>
                            <!-- Filtros rápidos -->
                            <div class="col-md-10 text-right">
                                <button class="btn btn-light btn-sm border" onclick="toggleFiltrosHistorial()">
                                    <i class="fas fa-filter"></i> Filtros Avanzados
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover table-sm custom-table" id="tablaHistorial">
                                <thead>
                                    <tr class="bg-light">
                                        <th data-col="cargo" class="sortable">Cargo <i
                                                class="fas fa-sort small text-muted"></i></th>
                                        <th data-col="nombre" class="sortable">Nombre <i
                                                class="fas fa-sort small text-muted"></i></th>
                                        <th data-col="fecha_inicio" class="sortable">Inicio <i
                                                class="fas fa-sort small text-muted"></i></th>
                                        <th data-col="fecha_fin" class="sortable">Fin <i
                                                class="fas fa-sort small text-muted"></i></th>
                                        <th>Descripción</th>
                                    </tr>
                                    <tr id="filtrosHistorialRow" style="display:none;">
                                        <th><input type="text" class="form-control form-control-sm filter-input"
                                                data-filter="cargo" placeholder="Filtrar..."></th>
                                        <th><input type="text" class="form-control form-control-sm filter-input"
                                                data-filter="nombre" placeholder="Filtrar..."></th>
                                        <th><input type="date" class="form-control form-control-sm filter-input"
                                                data-filter="inicio_desde"></th>
                                        <th><input type="date" class="form-control form-control-sm filter-input"
                                                data-filter="fin_hasta"></th>
                                        <th><input type="text" class="form-control form-control-sm filter-input"
                                                data-filter="descripcion" placeholder="Buscar..."></th>
                                    </tr>
                                </thead>
                                <tbody id="historialBody">
                                    <!-- Content injected via JS -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación -->
                        <nav id="historialPagination" class="d-flex justify-content-between align-items-center mt-3">
                            <div class="small text-muted" id="historialStats">Mostrando 0 de 0</div>
                            <ul class="pagination pagination-sm mb-0">
                                <!-- Pages injected via JS -->
                            </ul>
                        </nav>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- MODALES -->
    <!-- Edit project modal (Inline is preferred, but for long descriptions a modal is better) -->
    <div class="modal fade" id="modalProyecto" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-dark text-white border-0">
                    <h5 class="modal-title" id="modalProyectoTitulo">Detalles del Proyecto</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="formProyecto">
                        <input type="hidden" id="editProyectoId">
                        <div class="form-group">
                            <label class="font-weight-bold">Nombre</label>
                            <input type="text" id="editNombre" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Descripción</label>
                            <textarea id="editDescripcion" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="form-group col-6">
                                <label class="font-weight-bold">Inicio</label>
                                <input type="date" id="editFechaInicio" class="form-control" required>
                            </div>
                            <div class="form-group col-6">
                                <label class="font-weight-bold">Fin</label>
                                <input type="date" id="editFechaFin" class="form-control" required>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" id="btnGuardarProyecto">Guardar Cambios</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JS Dependencies -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Permisos y variables de sesión pasadas a JS
        const PERMISO_CREAR = <?php echo $permisoCrear ? 'true' : 'false'; ?>;
        const CARGO_USUARIO = <?php echo $cargoOperario; ?>;
    </script>
    <script src="js/gestion_proyectos.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>