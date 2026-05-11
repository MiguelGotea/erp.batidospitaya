<?php
// cupones.php

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('cupones', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Cupones</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/modales_premium.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/fab_button.css">
    <link rel="stylesheet" href="css/cupones.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Gestión de Cupones'); ?>

            <div class="container-fluid p-3">
                <!-- Botón para agregar nuevo cupón - MOVIDO A FAB -->

                <div class="table-responsive">
                    <table class="table table-hover cupones-table" id="tablaCupones">
                        <thead>
                            <tr>
                                <th data-column="numero_cupon" data-type="text">
                                    Número de Cupón
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="monto" data-type="number">
                                    Monto
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="fecha_caducidad" data-type="daterange">
                                    Fecha Caducidad
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="fecha_registro" data-type="daterange">
                                    Fecha Registro
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="nombre_sucursal" data-type="text">
                                    Sucursal
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="cod_pedido" data-type="text">
                                    Nº Pedido
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="observaciones" data-type="text">
                                    Observaciones
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="aplicado" data-type="list">
                                    Estado
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th style="width: 150px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaCuponesBody">
                            <!-- Datos cargados vía AJAX -->
                        </tbody>
                    </table>
                </div>

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

    <!-- Modal para agregar/editar cupón -->
    <div class="modal fade" id="modalCupon" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
                <div class="modal-header border-0 py-3 px-4" style="background: #0E544C; color: #fff;">
                    <div class="d-flex align-items-center">
                        <div class="bg-white bg-opacity-25 rounded-circle p-2 me-3 d-flex align-items-center justify-content-center"
                            style="width: 40px; height: 40px;">
                            <i class="fas fa-ticket-alt fs-4"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0" id="modalCuponTitulo">Nuevo Cupón</h5>
                            <p class="small mb-0 opacity-75">Configura los detalles del cupón de descuento</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-light">
                    <form id="formCupon">
                        <input type="hidden" id="cuponId" name="id">

                        <div class="mb-3">
                            <label for="numeroCupon" class="form-label small fw-bold text-muted text-uppercase">Número
                                de Cupón *</label>
                            <input type="text" class="form-control" id="numeroCupon" name="numero_cupon" readonly
                                style="background-color: #e9ecef;">
                            <small class="text-muted">Se genera automáticamente</small>
                        </div>

                        <div class="mb-3">
                            <label for="montoCupon" class="form-label small fw-bold text-muted text-uppercase">Monto
                                *</label>
                            <div class="input-group">
                                <span class="input-group-text">C$</span>
                                <input type="number" class="form-control" id="montoCupon" name="monto" required min="0">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="fechaCaducidad" class="form-label small fw-bold text-muted text-uppercase">Fecha
                                de Caducidad *</label>
                            <input type="date" class="form-control" id="fechaCaducidad" name="fecha_caducidad" required>
                        </div>

                        <div class="mb-3">
                            <label for="observaciones"
                                class="form-label small fw-bold text-muted text-uppercase">Observaciones</label>
                            <textarea class="form-control" id="observaciones" name="observaciones" rows="3"
                                placeholder="Notas adicionales sobre el cupón" style="resize: none;"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 p-4 bg-white d-flex justify-content-between">
                    <button type="button" class="btn-modern btn-modern-secondary"
                        data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn-modern btn-modern-primary" onclick="guardarCupon()">
                        <i class="fas fa-save me-2"></i>Guardar Cupón
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/cupones.js?v=<?php echo mt_rand(1, 10000); ?>"></script>

    <!-- Botón Flotante con opciones -->
    <?php if (tienePermiso('cupones', 'nuevo_registro', $cargoOperario)): ?>
        <div class="fab-container">
            <div class="fab-options">
                <div class="fab-option" onclick="abrirModalNuevoCupon()">
                    <span class="fab-label">Nuevo Cupón</span>
                    <div class="fab-icon-holder"><i class="fas fa-plus"></i></div>
                </div>
            </div>
            <div class="btn-floating-pitaya" title="Nuevo Cupón" onclick="abrirModalNuevoCupon()">
                <i class="fas fa-plus"></i>
            </div>
        </div>
    <?php endif; ?>
</body>

</html>