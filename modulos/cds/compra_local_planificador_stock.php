<?php
// compra_local_planificador_stock.php

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('compra_local_planificador_stock', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

$puedeEditar = tienePermiso('compra_local_planificador_stock', 'edicion', $cargoOperario);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planificador de Stock Mínimo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
        rel="stylesheet" />
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/compra_local_planificador_stock.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Planificador de Stock Mínimo'); ?>

            <div class="container-fluid p-3">
                <!-- Selector de Producto -->
                <div class="card shadow-sm border-0 mb-4 planner-search-card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <label class="form-label fw-bold mb-2">Simular Producto:</label>
                                <select class="form-select" id="product-planner-search" style="width: 100%;">
                                    <option value="">Busque un producto para iniciar la simulación...</option>
                                </select>
                            </div>
                            <div class="col-md-4 text-end pt-4">
                                <button class="btn btn-outline-danger" onclick="limpiarPlanificador()">
                                    <i class="fas fa-trash-alt me-2"></i>Limpiar Todo
                                </button>
                                <button class="btn btn-primary" data-bs-toggle="modal"
                                    data-bs-target="#plannerHelpModal">
                                    <i class="fas fa-info-circle me-2"></i>Ayuda
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Simulación -->
                <div id="planner-container">
                    <!-- Dinámico con JS -->
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Ayuda -->
    <div class="modal fade" id="plannerHelpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Guía del Planificador</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6 class="fw-bold text-primary">Conceptos Clave</h6>
                    <ul class="small">
                        <li><strong>Frecuencia:</strong> Plazo entre pedidos (ej: 1 semana = pedido semanal).</li>
                        <li><strong>Gap:</strong> Días que deben cubrirse (Frecuencia * 7).</li>
                        <li><strong>Cálculo:</strong>
                            <code>(Consumo × Factor) × min(Gap + Contingencia, Vida Útil)</code></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="js/compra_local_planificador_stock.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>