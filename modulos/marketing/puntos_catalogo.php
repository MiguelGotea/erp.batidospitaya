<?php
// puntos_catalogo.php

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('puntos_catalogo', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo de Recompensas - Club Pitaya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/modales_premium.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/fab_button.css">
    <link rel="stylesheet" href="css/puntos_reglas.css?v=<?php echo mt_rand(1, 10000); ?>"> <!-- Reusamos los estilos -->
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Catálogo de Recompensas'); ?>

            <div class="container-fluid p-3">
                <!-- Barra superior de pestañas informativas -->
                <ul class="nav nav-pills mb-4 modern-tabs">
                    <?php if (tienePermiso('puntos_reglas', 'vista', $cargoOperario)): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="puntos_reglas.php">
                            <i class="fas fa-coins me-2"></i>Cómo ganar puntos
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link active" href="puntos_catalogo.php">
                            <i class="fas fa-gift me-2"></i>Catálogo de Recompensas
                        </a>
                    </li>
                </ul>

                <div class="table-responsive bg-white rounded-3 shadow-sm border border-light">
                    <table class="table table-hover puntos-table mb-0" id="tablaCatalogo">
                        <thead>
                            <tr>
                                <th data-column="orden" data-type="number" style="width: 80px;">
                                    Orden
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="nombre" data-type="text">
                                    Recompensa
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="puntos_requeridos" data-type="number">
                                    Costo (Puntos)
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="producto_vinculado" data-type="text">
                                    Prod. Vinculado al POS
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="estado" data-type="list">
                                    Estado
                                </th>
                                <th style="width: 100px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaCatalogoBody">
                            <!-- Datos cargados vía AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para agregar/editar ítem -->
    <div class="modal fade" id="modalCatalogo" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg modal-moderno">
                <div class="modal-header border-0 py-3 px-4 header-premium">
                    <div class="d-flex align-items-center">
                        <div class="icon-circle me-3">
                            <i class="fas fa-box-open fs-4"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0" id="modalCatalogoTitulo">Configurar Premio</h5>
                            <p class="small mb-0 text-light opacity-75">Define lo que el cliente puede canjear</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-light">
                    <form id="formCatalogo">
                        <input type="hidden" id="itemId" name="id">

                        <div class="mb-3">
                            <label for="nombre" class="form-label small fw-bold text-muted text-uppercase">Nombre del Premio *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required placeholder="Ej: Batido 16oz">
                        </div>

                        <div class="mb-3">
                            <label for="productoSelect" class="form-label small fw-bold text-muted text-uppercase">Vincular a Producto POS (Opcional)</label>
                            <select class="form-select" id="productoSelect" name="id_producto_canjeable">
                                <option value="">Ninguno (Servicio o Premio Externo)</option>
                                <!-- AJAX -->
                            </select>
                            <small class="text-muted d-block mt-1">Si vinculas un producto, al canjearlo en caja rebajará inventario de este ítem.</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="puntos" class="form-label small fw-bold text-muted text-uppercase">Puntos Requeridos *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-star text-warning"></i></span>
                                    <input type="number" step="0.01" class="form-control" id="puntos" name="puntos_requeridos" required min="0">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="orden" class="form-label small fw-bold text-muted text-uppercase">Orden de Aparición</label>
                                <input type="number" class="form-control" id="orden" name="orden" value="0">
                            </div>
                        </div>
                        
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" id="activo" name="activo" checked>
                            <label class="form-check-label fw-bold" for="activo">Ítem Activo y Visible en el Catálogo</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 p-4 bg-white d-flex justify-content-between">
                    <button type="button" class="btn-modern btn-modern-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn-modern btn-modern-primary" onclick="guardarCatalogo()">
                        <i class="fas fa-save me-2"></i>Guardar Premio
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const permisoNuevoRegistro = <?php echo tienePermiso('puntos_catalogo', 'nuevo_registro', $cargoOperario) ? 'true' : 'false'; ?>;
        const permisoEditarRegistro = <?php echo tienePermiso('puntos_catalogo', 'editar_registro', $cargoOperario) ? 'true' : 'false'; ?>;
    </script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/puntos_catalogo.js?v=<?php echo mt_rand(1, 10000); ?>"></script>

    <?php if (tienePermiso('puntos_catalogo', 'nuevo_registro', $cargoOperario)): ?>
    <div class="fab-container">
        <div class="fab-options">
            <div class="fab-option" onclick="abrirModalNuevoItem()">
                <span class="fab-label">Nuevo Premio</span>
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
