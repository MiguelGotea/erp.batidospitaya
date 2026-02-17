<?php
// compra_local_configuracion_despacho.php

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('compra_local_configuracion_despacho', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}


// Verificar permiso de edición
$puedeEditar = tienePermiso('compra_local_configuracion_despacho', 'edicion', $cargoOperario);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Plan de Despacho</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
        rel="stylesheet" />
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/compra_local_configuracion_despacho.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Configuración de Plan de Despacho'); ?>

            <div class="container-fluid p-3">
                <!-- Tabs de Sucursales -->
                <ul class="nav nav-tabs mb-3" id="sucursalesTabs" role="tablist">
                    <!-- Tabs generadas dinámicamente -->
                </ul>

                <!-- Contenido de Tabs -->
                <div class="tab-content" id="sucursalesTabContent">
                    <!-- Contenido generado dinámicamente -->
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
                        Guía de Configuración de Plan de Despacho
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Guía rápida de uso -->
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body p-3">
                                    <h6 class="text-primary border-bottom pb-2 fw-bold small">
                                        <i class="fas fa-calendar-alt me-2"></i> Días de Entrega (🚚)
                                    </h6>
                                    <p class="x-small text-muted mb-0">
                                        Haga clic en el icono de cada día para activar o desactivar la entrega
                                        programada para esa sucursal. Los cambios se guardan automáticamente.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lógica de Cálculo -->
                    <div class="bg-white border rounded p-3 mb-3">
                        <h6 class="fw-bold text-dark border-bottom pb-2">
                            <i class="fas fa-clipboard-check me-2 text-secondary"></i> Gestión de Pedidos
                        </h6>
                        <p class="small mb-0">Esta herramienta define los días en que cada sucursal puede realizar
                            pedidos de productos locales. Los días marcados con el icono de check verde indican que hay
                            un despacho programado para ese día.</p>
                    </div>

                    <div class="alert alert-info py-2 px-3 small mb-0">
                        <strong><i class="fas fa-info-circle me-1"></i> Importante:</strong>
                        Los cambios se guardan automáticamente al perder el foco en los campos de entrada.
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
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const puedeEditar = <?php echo $puedeEditar ? 'true' : 'false'; ?>;
    </script>
    <script src="js/compra_local_configuracion_despacho.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>