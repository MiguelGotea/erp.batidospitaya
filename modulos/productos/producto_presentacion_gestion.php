<?php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('producto_presentacion', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

// Verificar permiso para crear nuevo producto
$puedeCrear = tienePermiso('producto_presentacion', 'nuevo_registro', $cargoOperario);

// Verificar permiso para desactivar productos
$puedeDesactivar = tienePermiso('producto_presentacion', 'desactivar', $cargoOperario);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos de Presentación</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/producto_presentacion.css?v=<?php echo time(); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Gestión de Productos de Presentación'); ?>

            <div class="container-fluid p-3">
                <!-- Botón para agregar nuevo producto -->
                <?php if ($puedeCrear): ?>
                    <div class="mb-3">
                        <button class="btn btn-success" onclick="window.location.href='registro_producto_global.php'">
                            <i class="bi bi-plus-circle"></i> Nuevo Producto
                        </button>
                    </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-hover productos-table" id="tablaProductos">
                        <thead>
                            <tr>
                                <th data-column="SKU" data-type="text">
                                    SKU
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="Nombre" data-type="text">
                                    Nombre
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="unidad_nombre" data-type="list">
                                    Unidad
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="es_vendible" data-type="tristate" class="tristate-header">
                                    <div class="tristate-header-content">
                                        <span>Venta</span>
                                        <div class="tristate-toggle-group">
                                            <button class="tristate-btn" data-state="SI" data-column="es_vendible"
                                                onclick="setTriStateFilter(this, 'es_vendible', 'SI')">
                                                <i class="bi bi-check-circle-fill"></i>
                                            </button>
                                            <button class="tristate-btn" data-state="null" data-column="es_vendible"
                                                onclick="setTriStateFilter(this, 'es_vendible', null)">
                                                <i class="bi bi-dash-circle-fill"></i>
                                            </button>
                                            <button class="tristate-btn" data-state="NO" data-column="es_vendible"
                                                onclick="setTriStateFilter(this, 'es_vendible', 'NO')">
                                                <i class="bi bi-x-circle-fill"></i>
                                            </button>
                                        </div>
                                    </div>
                                </th>
                                <th data-column="es_comprable" data-type="tristate" class="tristate-header">
                                    <div class="tristate-header-content">
                                        <span>Compra</span>
                                        <div class="tristate-toggle-group">
                                            <button class="tristate-btn" data-state="SI" data-column="es_comprable"
                                                onclick="setTriStateFilter(this, 'es_comprable', 'SI')">
                                                <i class="bi bi-check-circle-fill"></i>
                                            </button>
                                            <button class="tristate-btn" data-state="null" data-column="es_comprable"
                                                onclick="setTriStateFilter(this, 'es_comprable', null)">
                                                <i class="bi bi-dash-circle-fill"></i>
                                            </button>
                                            <button class="tristate-btn" data-state="NO" data-column="es_comprable"
                                                onclick="setTriStateFilter(this, 'es_comprable', 'NO')">
                                                <i class="bi bi-x-circle-fill"></i>
                                            </button>
                                        </div>
                                    </div>
                                </th>
                                <th data-column="es_fabricable" data-type="tristate" class="tristate-header">
                                    <div class="tristate-header-content">
                                        <span>Fabricación</span>
                                        <div class="tristate-toggle-group">
                                            <button class="tristate-btn" data-state="SI" data-column="es_fabricable"
                                                onclick="setTriStateFilter(this, 'es_fabricable', 'SI')">
                                                <i class="bi bi-check-circle-fill"></i>
                                            </button>
                                            <button class="tristate-btn" data-state="null" data-column="es_fabricable"
                                                onclick="setTriStateFilter(this, 'es_fabricable', null)">
                                                <i class="bi bi-dash-circle-fill"></i>
                                            </button>
                                            <button class="tristate-btn" data-state="NO" data-column="es_fabricable"
                                                onclick="setTriStateFilter(this, 'es_fabricable', 'NO')">
                                                <i class="bi bi-x-circle-fill"></i>
                                            </button>
                                        </div>
                                    </div>
                                </th>
                                <th data-column="tiene_receta" data-type="tristate" class="tristate-header">
                                    <div class="tristate-header-content">
                                        <span>Receta</span>
                                        <div class="tristate-toggle-group">
                                            <button class="tristate-btn" data-state="SI" data-column="tiene_receta"
                                                onclick="setTriStateFilter(this, 'tiene_receta', 'SI')">
                                                <i class="bi bi-check-circle-fill"></i>
                                            </button>
                                            <button class="tristate-btn" data-state="null" data-column="tiene_receta"
                                                onclick="setTriStateFilter(this, 'tiene_receta', null)">
                                                <i class="bi bi-dash-circle-fill"></i>
                                            </button>
                                            <button class="tristate-btn" data-state="NO" data-column="tiene_receta"
                                                onclick="setTriStateFilter(this, 'tiene_receta', 'NO')">
                                                <i class="bi bi-x-circle-fill"></i>
                                            </button>
                                        </div>
                                    </div>
                                </th>
                                <th data-column="Activo" data-type="tristate" class="tristate-header">
                                    <div class="tristate-header-content">
                                        <span>Activo</span>
                                        <div class="tristate-toggle-group">
                                            <button class="tristate-btn" data-state="SI" data-column="Activo"
                                                onclick="setTriStateFilter(this, 'Activo', 'SI')">
                                                <i class="bi bi-check-circle-fill"></i>
                                            </button>
                                            <button class="tristate-btn" data-state="null" data-column="Activo"
                                                onclick="setTriStateFilter(this, 'Activo', null)">
                                                <i class="bi bi-dash-circle-fill"></i>
                                            </button>
                                            <button class="tristate-btn" data-state="NO" data-column="Activo"
                                                onclick="setTriStateFilter(this, 'Activo', 'NO')">
                                                <i class="bi bi-x-circle-fill"></i>
                                            </button>
                                        </div>
                                    </div>
                                </th>
                                <th style="width: 100px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaProductosBody">
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
                            <option value="500">500</option>
                        </select>
                        <span class="mb-0">registros</span>
                    </div>
                    <div id="paginacion"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Pasar permisos a JavaScript
        const PUEDE_DESACTIVAR = <?php echo $puedeDesactivar ? 'true' : 'false'; ?>;
    </script>
    <script src="js/producto_presentacion.js?v=<?php echo time(); ?>"></script>
</body>

</html>