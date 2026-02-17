<?php
// compra_local_consolidado_pedidos.php

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('compra_local_consolidado_pedidos', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

// Verificar permiso de edición
$puedeEditar = tienePermiso('compra_local_consolidado_pedidos', 'edicion', $cargoOperario);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consolidado de Pedidos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/compra_local_consolidado_pedidos.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Consolidado de Pedidos'); ?>

            <div class="container-fluid p-3">
                <!-- Tabla Consolidada -->
                <div id="consolidado-container">
                    <div class="loader-container">
                        <div class="loader"></div>
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
                        Guía de Consolidado de Pedidos
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body p-3">
                                    <h6 class="text-primary border-bottom pb-2 fw-bold small">
                                        <i class="fas fa-calendar-alt me-2"></i> Lunes a Domingo
                                    </h6>
                                    <p class="x-small text-muted mb-0">
                                        Muestra una cuadrícula fija de los 7 días de la semana, alineada con la vista de
                                        las sucursales.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body p-3">
                                    <h6 class="text-success border-bottom pb-2 fw-bold small">
                                        <i class="fas fa-truck me-2"></i> Perspectiva de Pedido
                                    </h6>
                                    <p class="x-small text-muted mb-0">
                                        Los datos muestran el día en que se hizo el pedido (ej: el lunes muestra lo que
                                        llega el martes).
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info py-2 px-3 small mb-0">
                        <strong><i class="fas fa-info-circle me-1"></i> Nota:</strong>
                        Los totales por producto ayudan a planificar la producción y el despacho global del centro de
                        distribución.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Ajuste de z-index para evitar que el backdrop cubra el modal */
        #pageHelpModal {
            z-index: 1060 !important;
        }

        .modal-backdrop {
            z-index: 1050 !important;
        }
    </style>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const puedeEditar = <?php echo $puedeEditar ? 'true' : 'false'; ?>;
    </script>
    <script src="js/compra_local_consolidado_pedidos.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>