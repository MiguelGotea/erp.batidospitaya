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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/gestion_proyectos.css?v=<?php echo mt_rand(1, 10000); ?>">

    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
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
                        <div class="gantt-controls-left">
                            <!-- Title removed here as per user request -->
                        </div>
                        <div class="gantt-controls d-flex">
                            <div class="btn-group">
                                <button class="btn btn-sm btn-success" onclick="navegarGantt('anterior')">
                                    <i class="fas fa-chevron-left"></i> Anterior
                                </button>
                                <button class="btn btn-sm btn-success mx-1" onclick="irAHoy()">
                                    Hoy
                                </button>
                                <button class="btn btn-sm btn-success" onclick="navegarGantt('siguiente')">
                                    Siguiente <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div id="ganttContainer" class="gantt-wrapper">
                            <!-- Gantt content will be injected here via JS -->
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
                        <h5 class="mb-0 text-dark font-weight-bold"><i class="fas fa-history"></i> Proyectos Finalizados
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover custom-table" id="tablaHistorial">
                                <thead>
                                    <tr>
                                        <th data-column="cargo" data-type="list">
                                            Cargo <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                        </th>
                                        <th data-column="nombre" data-type="text">
                                            Proyecto <i class="bi bi-funnel filter-icon"
                                                onclick="toggleFilter(this)"></i>
                                        </th>
                                        <th data-column="fecha_inicio" data-type="daterange">
                                            Inicio <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                        </th>
                                        <th data-column="fecha_fin" data-type="daterange">
                                            Fin <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                        </th>
                                        <th data-column="descripcion" data-type="text">
                                            Descripción <i class="bi bi-funnel filter-icon"
                                                onclick="toggleFilter(this)"></i>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="historialBody">
                                    <!-- Datos vía AJAX -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación Estándar -->
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="d-flex align-items-center">
                                <label class="mb-0 mr-2">Mostrar:</label>
                                <select class="form-control form-control-sm" id="registrosPorPagina"
                                    style="width: auto;" onchange="cambiarRegistrosPorPagina()">
                                    <option value="25" selected>25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                            </div>
                            <div id="paginacion"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Proyecto (Nuevo/Editar) -->
    <div class="modal fade" id="modalProyecto" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title" id="modalTitulo">Proyecto</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <form id="formProyecto">
                        <input type="hidden" id="editProyectoId">
                        <input type="hidden" id="editProyectoPadreId">
                        <input type="hidden" id="editCargoId">
                        <input type="hidden" id="editEsSubproyecto">

                        <div class="form-group">
                            <label>Nombre del Proyecto *</label>
                            <input type="text" id="editNombre" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Descripción</label>
                            <textarea id="editDescripcion" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label>Fecha Inicio *</label>
                                    <input type="date" id="editFechaInicio" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label>Fecha Fin *</label>
                                    <input type="date" id="editFechaFin" class="form-control" required>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnGuardarProyecto">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const PERMISO_CREAR = <?php echo ($permisoCrear ? 'true' : 'false'); ?>;
    </script>
    <script src="js/gestion_proyectos.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>