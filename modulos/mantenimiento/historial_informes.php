<?php
// historial_informes.php

require_once 'models/Ticket.php';
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('agenda_mantenimiento', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

$puedeVerTodos = tienePermiso('agenda_mantenimiento', 'todos_colaboradores', $cargoOperario);
$esAdminCaja = tienePermiso('mantenimiento', 'validar_caja_chica', $cargoOperario);

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Informes Diarios</title>
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css">
    <link rel="stylesheet" href="../../core/assets/css/modales_premium.css">
    <style>
        :root {
            --color-header-tabla: #0E544C;
            --color-principal: #51B8AC;
        }

        .card-informe {
            transition: all 0.2s;
            border-radius: 12px;
        }

        .status-badge {
            font-weight: 600;
            font-size: 0.75rem;
            padding: 4px 12px;
            border-radius: 20px;
        }

        .table-premium thead th {
            background: var(--color-header-tabla) !important;
            color: white !important;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            border-top: none;
            position: relative;
            padding: 12px 15px;
        }

        .filter-icon {
            cursor: pointer;
            margin-left: 5px;
            opacity: 0.7;
            transition: 0.2s;
        }

        .filter-icon:hover,
        .filter-icon.active {
            opacity: 1;
            color: var(--color-principal);
        }

        .filter-icon.has-filter {
            color: #ffc107;
            opacity: 1;
        }

        .btn-action {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: 0.2s;
        }

        .btn-action:hover {
            transform: scale(1.1);
        }

        .pagination-btn {
            border: 1px solid #dee2e6;
            background: white;
            padding: 5px 12px;
            margin: 0 2px;
            border-radius: 6px;
            cursor: pointer;
            transition: 0.2s;
        }

        .pagination-btn.active {
            background: var(--color-header-tabla);
            color: white;
            border-color: var(--color-header-tabla);
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Control de Informes Diarios'); ?>

            <div class="container-fluid p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="fw-bold mb-1">Historial de Jornadas</h4>
                        <p class="text-muted small mb-0">Listado de reportes de mantenimiento y control de gastos</p>
                    </div>
                    <a href="agenda_colaborador.php" class="btn btn-primary rounded-pill px-4 shadow-sm" style="background-color: var(--color-principal); border: none;">
                        <i class="fas fa-plus me-2"></i>Nuevo Reporte de Hoy
                    </a>
                </div>

                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-premium mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4" data-column="fecha" data-type="daterange">
                                            Fecha
                                            <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                        </th>
                                        <th data-column="Nombre" data-type="list">
                                            Colaborador
                                            <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                        </th>
                                        <th>KM Recorrido</th>
                                        <th>Caja Chica</th>
                                        <th data-column="estado" data-type="list">
                                            Estado
                                            <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                        </th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaInformesBody">
                                    <!-- Cargado vía AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-0 p-3">
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <div class="d-flex align-items-center gap-2">
                                <label class="mb-0 small">Mostrar:</label>
                                <select class="form-select form-select-sm" id="registrosPorPagina" style="width: auto;" onchange="cambiarRegistrosPorPagina()">
                                    <option value="10">10</option>
                                    <option value="25" selected>25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                            </div>
                            <div id="paginacion" class="d-flex align-items-center"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL VALIDAR CAJA CHICA (ADMIN) -->
    <div class="modal fade" id="validarCajaModal" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-premium border-0 rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="fas fa-cash-register me-2 text-success"></i>Validar Caja Chica</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="formCaja">
                        <input type="hidden" name="informe_id" id="caja_informe_id">
                        <div class="mb-4">
                            <label class="form-label small fw-bold">Monto Entregado (Caja Chica) *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" name="monto" id="caja_monto" class="form-control form-control-lg" required>
                            </div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label small fw-bold">Foto del Voucher / Comprobante *</label>
                            <input type="file" name="foto_caja" id="caja_foto_input" class="form-control" accept="image/*" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 p-3 px-4 pb-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success rounded-pill px-4" onclick="guardarValidacionCaja()">Confirmar Entrega</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL ZOOM IMAGEN -->
    <div class="modal fade" id="zoomModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content bg-transparent border-0 shadow-none">
                <div class="modal-body text-center p-0">
                    <img id="zoomImg" src="" class="img-fluid rounded-4 shadow-lg">
                    <button type="button" class="btn btn-dark btn-sm rounded-circle position-absolute top-0 end-0 m-3" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Ayuda Universal -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1" aria-labelledby="pageHelpModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white" style="background-color: var(--color-header-tabla) !important;">
                    <h5 class="modal-title" id="pageHelpModalLabel">
                        <i class="fas fa-info-circle me-2"></i>
                        Guía de Historial de Informes Diarios
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-primary border-bottom pb-2 fw-bold">
                                        <i class="fas fa-list-alt me-2"></i> Visibilidad de Informes
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        - Los <strong>Colaboradores</strong> solo pueden ver sus propios informes.<br>
                                        - Los <strong>Administradores</strong> pueden ver el historial de todo el equipo utilizando los filtros en los encabezados.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-success border-bottom pb-2 fw-bold">
                                        <i class="fas fa-cash-register me-2"></i> Caja Chica
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Los administradores pueden validar el monto entregado y adjuntar el voucher una vez que el colaborador abre su jornada.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-warning border-bottom pb-2 fw-bold">
                                        <i class="fas fa-edit me-2"></i> Estados del Informe
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        - <strong>Abierto:</strong> El informe aún puede ser editado por el colaborador.<br>
                                        - <strong>Finalizado:</strong> El informe ya no es editable y está listo para auditoría.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-info border-bottom pb-2 fw-bold">
                                        <i class="fas fa-filter me-2"></i> Filtros de Encabezado
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Haz clic en el ícono de embudo (<i class="bi bi-funnel"></i>) en los encabezados de Fecha, Colaborador o Estado para filtrar y ordenar los datos dinámicamente.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const actualUserId = <?= $usuario['CodOperario'] ?>;
        const esAdminCaja = <?= $esAdminCaja ? 'true' : 'false' ?>;
    </script>
    <script src="js/historial_informes.js?v=<?= mt_rand(1, 10000) ?>"></script>
</body>

</html>