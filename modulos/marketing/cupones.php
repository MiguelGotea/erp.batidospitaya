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
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/cupones.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    
    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Gestión de Cupones'); ?>
            
            <div class="container-fluid p-3">
                <!-- Botón para agregar nuevo cupón -->
                <?php if (tienePermiso('cupones', 'nuevo_registro', $cargoOperario)): ?>
                <div class="mb-3">
                    <button class="btn btn-success" onclick="abrirModalNuevoCupon()">
                        <i class="bi bi-plus-circle"></i> Nuevo Cupón
                    </button>
                </div>
                <?php endif; ?>

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
                        <select class="form-select form-select-sm" id="registrosPorPagina" style="width: auto;" onchange="cambiarRegistrosPorPagina()">
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
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCuponTitulo">Nuevo Cupón</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formCupon">
                        <input type="hidden" id="cuponId" name="id">
                        
                        <div class="mb-3">
                            <label for="numeroCupon" class="form-label">Número de Cupón *</label>
                            <input type="text" class="form-control" id="numeroCupon" name="numero_cupon" readonly>
                            <small class="text-muted">Se genera automáticamente</small>
                        </div>

                        <div class="mb-3">
                            <label for="montoCupon" class="form-label">Monto *</label>
                            <input type="number" class="form-control" id="montoCupon" name="monto" required min="0">
                        </div>

                        <div class="mb-3">
                            <label for="fechaCaducidad" class="form-label">Fecha de Caducidad *</label>
                            <input type="date" class="form-control" id="fechaCaducidad" name="fecha_caducidad" required>
                        </div>

                        <div class="mb-3">
                            <label for="observaciones" class="form-label">Observaciones</label>
                            <textarea class="form-control" id="observaciones" name="observaciones" rows="3" placeholder="Notas adicionales sobre el cupón"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarCupon()">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/cupones.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>
</html>