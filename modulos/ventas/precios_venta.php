<?php
// precios_venta.php

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('precios_venta', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Precios de Venta</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/modales_premium.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/fab_button.css">
    <link rel="stylesheet" href="css/precios_venta.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Control de Precios de Venta'); ?>

            <div class="container-fluid p-3">
                <div class="table-responsive">
                    <table class="table table-hover precios-table" id="tablaPrecios">
                        <thead>
                            <tr>
                                <th data-column="sku" data-type="text">
                                    SKU
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="nombre_producto" data-type="text">
                                    Producto
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="precio_global" data-type="number">
                                    Precio Global Actual
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="fecha_desde" data-type="daterange">
                                    Vigente Desde
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="tiene_overrides" data-type="list">
                                    Overrides Sucursales
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th style="width: 150px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaPreciosBody">
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

    <!-- Modal para agregar/editar precio -->
    <div class="modal fade" id="modalPrecio" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
                <div class="modal-header border-0 py-3 px-4" style="background: #0E544C; color: #fff;">
                    <div class="d-flex align-items-center">
                        <div class="bg-white bg-opacity-25 rounded-circle p-2 me-3 d-flex align-items-center justify-content-center"
                            style="width: 40px; height: 40px;">
                            <i class="fas fa-tag fs-4"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0" id="modalPrecioTitulo">Fijar Nuevo Precio</h5>
                            <p class="small mb-0 opacity-75">Configura el precio global o por sucursal</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-light">
                    <form id="formPrecio">
                        <div class="mb-3">
                            <label for="productoPresentacion" class="form-label small fw-bold text-muted text-uppercase">Producto *</label>
                            <select class="form-select" id="productoPresentacion" name="id_producto_presentacion" required>
                                <option value="">Seleccione un producto</option>
                                <!-- Cargado vía AJAX o poblado al editar -->
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="sucursalSelect" class="form-label small fw-bold text-muted text-uppercase">Sucursal (Vacío = Global)</label>
                            <select class="form-select" id="sucursalSelect" name="cod_sucursal">
                                <option value="">Global (Aplica a todas)</option>
                                <!-- Cargado vía AJAX -->
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="montoPrecio" class="form-label small fw-bold text-muted text-uppercase">Precio *</label>
                            <div class="input-group">
                                <span class="input-group-text">C$</span>
                                <input type="number" step="0.01" class="form-control" id="montoPrecio" name="precio" required min="0">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="fechaDesde" class="form-label small fw-bold text-muted text-uppercase">Vigente Desde *</label>
                            <input type="date" class="form-control" id="fechaDesde" name="fecha_desde" required>
                            <small class="text-muted">El precio anterior vigente perderá vigencia un día antes de esta fecha.</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 p-4 bg-white d-flex justify-content-between">
                    <button type="button" class="btn-modern btn-modern-secondary"
                        data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn-modern btn-modern-primary" onclick="guardarPrecio()">
                        <i class="fas fa-save me-2"></i>Guardar Precio
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para ver Historial de Precios -->
    <div class="modal fade" id="modalHistorial" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
                <div class="modal-header border-0 py-3 px-4" style="background: #111827; color: #fff;">
                    <div class="d-flex align-items-center">
                        <div class="bg-white bg-opacity-25 rounded-circle p-2 me-3 d-flex align-items-center justify-content-center"
                            style="width: 40px; height: 40px;">
                            <i class="fas fa-history fs-4"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0" id="modalHistorialTitulo">Historial de Precios</h5>
                            <p class="small mb-0 opacity-75" id="modalHistorialSubtitulo">Producto XYZ</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-light">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered bg-white">
                            <thead class="table-light">
                                <tr>
                                    <th>Alcance</th>
                                    <th>Precio (C$)</th>
                                    <th>Vigente Desde</th>
                                    <th>Vigente Hasta</th>
                                    <th>Registrado El</th>
                                </tr>
                            </thead>
                            <tbody id="tablaHistorialBody">
                                <!-- Cargado vía AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const permisoNuevoRegistro = <?php echo tienePermiso('precios_venta', 'nuevo_registro', $cargoOperario) ? 'true' : 'false'; ?>;
        const permisoVistaHistorial = <?php echo tienePermiso('precios_venta', 'vista_historial', $cargoOperario) ? 'true' : 'false'; ?>;
    </script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/precios_venta.js?v=<?php echo mt_rand(1, 10000); ?>"></script>

    <!-- Botón Flotante con opciones -->
    <?php if (tienePermiso('precios_venta', 'nuevo_registro', $cargoOperario)): ?>
    <div class="fab-container">
        <div class="fab-options">
            <div class="fab-option" onclick="abrirModalNuevoPrecio()">
                <span class="fab-label">Fijar Nuevo Precio</span>
                <div class="fab-icon-holder"><i class="fas fa-plus"></i></div>
            </div>
        </div>
        <div class="btn-floating-pitaya" title="Herramientas">
            <i class="fas fa-wrench"></i>
        </div>
    </div>
    <?php endif; ?>
</body>

</html>
