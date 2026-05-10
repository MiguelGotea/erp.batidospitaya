<?php
// postulacion_calendario.php

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
    <title>Calendario de Entrevistas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/postulacion_calendario.css?v=<?php echo mt_rand(1, 10000); ?>">
    <style>
        .rango-semana {
            font-size: 1.1rem;
            font-weight: 500;
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border-radius: 20px;
        }

        #filtroEntrevistador {
            min-width: 250px;
        }
    </style>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Calendario de Entrevistas'); ?>

            <div class="container-fluid p-4">
                <div class="row">
                    <!-- Calendario Principal -->
                    <div class="col-lg-8">
                        <div class="card shadow-sm">
                            <div
                                class="card-header bg-primary text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-3">
                                    <h5 class="mb-0" id="mesActual">Cargando...</h5>
                                    <div class="rango-semana d-none d-md-block" id="rangoSemana">
                                        <!-- Rango de fecha -->
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <?php
                                    $puedeFiltrar = in_array($cargoOperario, [13, 16, 49]) || tienePermiso('postulacion_calendario', 'filtrar_entrevistadores', $cargoOperario);
                                    if ($puedeFiltrar):
                                    ?>
                                        <select class="form-select form-select-sm" id="filtroEntrevistador">
                                            <option value="todos">Todos los Entrevistadores</option>
                                            <!-- Cargado dinámicamente -->
                                        </select>
                                    <?php endif; ?>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-light" onclick="cambiarSemana(-1)">
                                            <i class="fas fa-chevron-left"></i>
                                        </button>
                                        <button type="button" class="btn btn-light" onclick="irHoy()">Hoy</button>
                                        <button type="button" class="btn btn-light" onclick="cambiarSemana(1)">
                                            <i class="fas fa-chevron-right"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div id="calendario" class="calendario-container">
                                    <!-- Generado dinámicamente -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Panel de Agenda del Día -->
                    <div class="col-lg-4">
                        <div class="card shadow-sm sticky-top" style="top: 20px;">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0">
                                    <i class="bi bi-calendar-check me-2"></i>
                                    Agenda de Hoy
                                </h6>
                                <small id="fechaHoy"></small>
                            </div>
                            <div class="card-body p-2" id="agendaHoy" style="max-height: 600px; overflow-y: auto;">
                                <!-- Entrevistas del día -->
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
                        Guía del Calendario de Entrevistas
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
                                        <i class="bi bi-calendar3 me-2"></i> Vista de Calendario
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        El calendario muestra todas las entrevistas programadas. Puedes navegar entre
                                        meses usando las flechas.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-success border-bottom pb-2 fw-bold">
                                        <i class="bi bi-clock me-2"></i> Agenda de Hoy
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        El panel derecho muestra las entrevistas del día actual con todos sus detalles.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-info border-bottom pb-2 fw-bold">
                                        <i class="bi bi-bar-chart me-2"></i> Códigos de Color
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        <span class="badge bg-primary">Inducción</span> Proceso de incorporación<br>
                                        <span class="badge bg-success">Entrevista</span> Entrevista programada<br>
                                        <span class="badge bg-warning text-dark">Recordatorio</span> Recordatorio
                                        importante
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
    <script src="js/postulacion_calendario.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>