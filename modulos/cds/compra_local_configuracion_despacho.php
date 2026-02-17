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
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body p-3">
                                    <h6 class="text-primary border-bottom pb-2 fw-bold small">
                                        <i class="fas fa-calendar-alt me-2"></i> Días de Entrega (🚚)
                                    </h6>
                                    <p class="x-small text-muted mb-0">
                                        Active el icono de camión en los días que la sucursal recibe el producto.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body p-3">
                                    <h6 class="text-success border-bottom pb-2 fw-bold small">
                                        <i class="fas fa-chart-line me-2"></i> Demanda Diaria
                                    </h6>
                                    <p class="x-small text-muted mb-0">
                                        Configure el consumo base y el factor de evento para cada día de la semana.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lógica de Cálculo -->
                    <div class="bg-white border rounded p-3 mb-3">
                        <h6 class="fw-bold text-dark border-bottom pb-2">
                            <i class="fas fa-calculator me-2 text-secondary"></i> Lógica del Stock Mínimo
                        </h6>
                        <p class="small mb-2">El <strong>Stock Mín</strong> sugerido en el registro de pedidos se
                            calcula sumando la demanda proyectada desde el momento del conteo hasta que llegue el
                            <u>siguiente</u> pedido.</p>

                        <div class="row g-2">
                            <div class="col-md-5">
                                <div class="p-2 border rounded bg-light h-100">
                                    <span class="badge bg-secondary mb-2">Fórmula Base</span>
                                    <div class="fw-bold x-small">Demanda (D) = (Consumo × Factor)</div>
                                    <hr class="my-1">
                                    <ul class="list-unstyled x-small mb-0">
                                        <li>• <strong>Demanda Hoy:</strong> Se calcula el 85% de D (desde 9 AM al
                                            cierre).</li>
                                        <li>• <strong>Cobertura:</strong> Suma de D de todos los días hasta la siguiente
                                            entrega.</li>
                                        <li>• <strong>Restricciones:</strong> Lead Time suma días; Vida Útil limita la
                                            suma.</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <div class="p-2 border rounded bg-info bg-opacity-10 h-100">
                                    <span class="badge bg-info text-dark mb-2">Ejemplo Práctico</span>
                                    <div class="x-small">
                                        <strong>Producto:</strong> Galonera de Leche (Consumo: 10, Factor: 1.2) →
                                        <strong>D = 12</strong><br>
                                        <strong>Escenario:</strong> Pedido de Lunes (llega Martes). Siguiente entrega:
                                        Jueves.<br>
                                        <div class="mt-1 p-1 bg-white rounded border">
                                            1. <strong>Hoy (Lun):</strong> 85% de 12 = <strong>10.2</strong><br>
                                            2. <strong>Cobertura (Mar+Mie+Jue):</strong> 12 + 12 + 12 =
                                            <strong>36</strong><br>
                                            3. <strong>Total:</strong> 10.2 + 36 = 46.2 → <strong>Stock Mín: 47</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
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