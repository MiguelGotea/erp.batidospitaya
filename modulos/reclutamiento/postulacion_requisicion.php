<?php
// postulacion_requisicion.php

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('postulacion_requisicion', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

$puedeCrear = tienePermiso('postulacion_requisicion', 'crear', $cargoOperario);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requisición de Personal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/postulacion_requisicion.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Requisición de Personal'); ?>

            <div class="container-fluid p-3">
                <!-- Botón Nueva Solicitud -->
                <?php if ($puedeCrear): ?>
                    <div class="mb-3">
                        <a href="postulacion_requisicion_nueva.php" class="btn btn-success">
                            <i class="bi bi-plus-circle me-2"></i>Nueva Solicitud
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Tabla de requisiciones -->
                <div class="table-responsive">
                    <table class="table table-hover" id="tablaRequisiciones">
                        <thead>
                            <tr>
                                <th data-column="id" data-type="number">
                                    ID
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="nombre_cargo" data-type="text">
                                    Cargo
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>

                                <th data-column="cantidad" data-type="number">
                                    Cantidad
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="sucursal_nombre" data-type="text">
                                    Sucursal
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="nivel_urgencia" data-type="list">
                                    Urgencia
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="fecha_creacion" data-type="daterange">
                                    Fecha Solicitud
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="status" data-type="list">
                                    Estado
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th style="width: 150px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaRequisicionesBody">
                            <!-- Cargado dinámicamente -->
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div class="d-flex align-items-center gap-2">
                        <label class="mb-0">Mostrar:</label>
                        <select class="form-select form-select-sm" id="registrosPorPagina" style="width: auto;"
                            onchange="cambiarRegistrosPorPagina()">
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

    <!-- Modal de Ayuda -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1" aria-labelledby="pageHelpModalLabel" aria-hidden="true"
        data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="pageHelpModalLabel">
                        <i class="fas fa-info-circle me-2"></i>
                        Guía de Requisición de Personal
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-primary border-bottom pb-2 fw-bold">
                                        <i class="bi bi-plus-circle me-2"></i> Crear Solicitud
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Haz clic en el botón "Nueva Solicitud" para abrir el formulario de requisición
                                        de personal.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-warning border-bottom pb-2 fw-bold">
                                        <i class="bi bi-exclamation-triangle me-2"></i> Niveles de Urgencia
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        <strong>No urgente:</strong> Sin prisa<br>
                                        <strong>Medio:</strong> Moderada prioridad<br>
                                        <strong>Urgente:</strong> Alta prioridad<br>
                                        <strong>Crítico:</strong> Atención inmediata
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-success border-bottom pb-2 fw-bold">
                                        <i class="bi bi-check-circle me-2"></i> Proceso de Aprobación
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Una vez enviada la solicitud, será revisada por gerencia. Podrás ver el estado y
                                        los comentarios en el detalle de cada requisición.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-info border-bottom pb-2 fw-bold">
                                        <i class="bi bi-eye me-2"></i> Ver Detalles
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Haz clic en el ícono del ojo en la columna de acciones para ver todos los
                                        detalles de una requisición.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info py-2 px-3 small">
                        <strong><i class="fas fa-info-circle me-1"></i> Nota:</strong>
                        <br>
                        Las solicitudes aprobadas habilitan automáticamente la plaza para postulaciones. Puedes ver
                        todas tus solicitudes en esta misma página.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        #pageHelpModal {
            z-index: 1060 !important;
        }

        .modal-backdrop {
            z-index: 1050 !important;
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/postulacion_requisicion.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>