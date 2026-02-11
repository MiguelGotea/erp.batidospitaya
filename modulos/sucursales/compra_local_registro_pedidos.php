<?php
// compra_local_registro_pedidos.php

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';
require_once '../../core/helpers/funciones.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('compra_local_registro_pedidos', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

// Verificar permiso de edición
$puedeEditar = tienePermiso('compra_local_registro_pedidos', 'edicion', $cargoOperario);

// Obtener sucursal del usuario
$sucursales = obtenerSucursalesUsuario($usuario['CodOperario']);
$codigoSucursal = !empty($sucursales) ? $sucursales[0]['codigo'] : null;

if (!$codigoSucursal) {
    die('Error: No se pudo determinar la sucursal del usuario.');
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Pedidos de Insumos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/compra_local_registro_pedidos.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Registro de Pedidos de Insumos'); ?>

            <div class="container-fluid p-3">
                <!-- Información de Sucursal -->
                <div class="alert alert-info mb-3">
                    <i class="bi bi-building"></i>
                    <strong>Sucursal:</strong>
                    <?php echo htmlspecialchars($sucursales[0]['nombre']); ?>
                </div>

                <!-- Tabla de Productos -->
                <div id="productos-container">
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
                        Guía de Registro de Pedidos
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
                                        <i class="fas fa-calendar-week me-2"></i> Calendario Semanal
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        El calendario muestra los días de la semana actual. Solo puede registrar
                                        pedidos para los días configurados en el plan de despacho.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-success border-bottom pb-2 fw-bold">
                                        <i class="fas fa-edit me-2"></i> Registrar Cantidades
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Haga clic en las celdas habilitadas para ingresar la cantidad de pedido. Los
                                        cambios se guardan automáticamente.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-info border-bottom pb-2 fw-bold">
                                        <i class="fas fa-clock me-2"></i> Historial de Cambios
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Pase el cursor sobre una celda con pedido para ver la fecha y hora de la última
                                        modificación.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-warning border-bottom pb-2 fw-bold">
                                        <i class="fas fa-exclamation-triangle me-2"></i> Días Deshabilitados
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Las celdas en gris indican días sin entrega programada. No puede registrar
                                        pedidos en estos días.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info py-2 px-3 small">
                        <strong><i class="fas fa-info-circle me-1"></i> Nota:</strong>
                        <br>
                        Solo se actualiza la fecha de modificación cuando cambia realmente el valor de la cantidad.
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
        const codigoSucursal = '<?php echo $codigoSucursal; ?>';
    </script>
    <script src="js/compra_local_registro_pedidos.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>