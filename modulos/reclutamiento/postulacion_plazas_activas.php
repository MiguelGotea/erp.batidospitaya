<?php
// postulacion_plazas_activas.php

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('postulacion_plazas_activas', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plazas Activas - Reclutamiento</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/postulacion_plazas_activas.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Listado de Plazas Activas'); ?>

            <div class="container-fluid p-4">
                <!-- Indicadores Superiores -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm indicator-card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase mb-1" style="font-size: 0.75rem;">Plazas Abiertas</h6>
                                        <h3 class="mb-0 fw-bold" id="indicadorPlazasAbiertas">0</h3>
                                    </div>
                                    <div class="indicator-icon">
                                        <i class="bi bi-door-open fs-2 opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm indicator-card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase mb-1" style="font-size: 0.75rem;">En Entrevista</h6>
                                        <h3 class="mb-0 fw-bold" id="indicadorEnEntrevista">0</h3>
                                    </div>
                                    <div class="indicator-icon">
                                        <i class="bi bi-chat-dots fs-2 opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm indicator-card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase mb-1" style="font-size: 0.75rem;">En Elección</h6>
                                        <h3 class="mb-0 fw-bold" id="indicadorEnEleccion">0</h3>
                                    </div>
                                    <div class="indicator-icon">
                                        <i class="bi bi-person-check fs-2 opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm indicator-card bg-warning text-dark">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase mb-1" style="font-size: 0.75rem;">Total Cubiertas</h6>
                                        <h3 class="mb-0 fw-bold" id="indicadorTotalCubiertas">0</h3>
                                    </div>
                                    <div class="indicator-icon">
                                        <i class="bi bi-people fs-2 opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="tablaPlazas">
                                <thead>
                                    <tr>
                                        <th>Nombre de Puesto</th>
                                        <th>Área</th>
                                        <th style="width: 120px;">Plazas Abiertas</th>
                                        <th style="width: 120px;">CVs Recibidos</th>
                                        <th style="width: 130px;">Salario Propuesto</th>
                                        <th style="width: 100px;">Urgencia</th>
                                        <th style="width: 150px;">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaPlazasBody">
                                    <!-- Cargado dinámicamente -->
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="text-muted small">
                                Mostrando <span id="registrosMostrados">0</span> registros
                            </div>
                            <div>
                                <label class="mb-0 me-2">Mostrar:</label>
                                <select class="form-select form-select-sm d-inline-block" id="registrosPorPagina"
                                    style="width: auto;" onchange="cambiarRegistrosPorPagina()">
                                    <option value="10" selected>10</option>
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                </select>
                            </div>
                        </div>
                    </div>
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
                        Guía de Plazas Activas
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
                                        <i class="bi bi-eye me-2"></i> Ver Candidatos
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Haz clic en el botón "Ver" para revisar todos los CVs recibidos para una plaza
                                        específica.
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
                                        <strong>BAJA:</strong> Sin prisa<br>
                                        <strong>MEDIA:</strong> Moderada prioridad<br>
                                        <strong>ALTA:</strong> Alta prioridad<br>
                                        <strong>CRÍTICO:</strong> Atención inmediata
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-info border-bottom pb-2 fw-bold">
                                        <i class="bi bi-info-circle me-2"></i> Plazas Visibles
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Solo se muestran las plazas que están marcadas como visibles en la web y que
                                        tienen al menos una vacante abierta.
                                    </p>
                                </div>
                            </div>
                        </div>
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
    <script src="js/postulacion_plazas_activas.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>