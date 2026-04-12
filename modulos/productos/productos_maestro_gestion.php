<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('producto_maestro', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos Maestro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/producto_maestro_gestion.css?v=<?php echo mt_rand(1, 10000); ?>">
    
    <script>
        // Permisos del usuario
        const PERMISOS_USUARIO = {
            puede_editar: <?php echo tienePermiso('producto_maestro', 'editar', $cargoOperario) ? 'true' : 'false'; ?>,
            puede_crear: <?php echo tienePermiso('producto_maestro', 'nuevo_registro', $cargoOperario) ? 'true' : 'false'; ?>
        };
    </script>
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    
    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Gestión de Productos Maestro'); ?>
            
            <div class="container-fluid p-3">
                <!-- Botón para agregar nuevo producto -->
                <?php if (tienePermiso('producto_maestro', 'nuevo_registro', $cargoOperario)): ?>
                <div class="mb-3">
                    <button class="btn btn-success" onclick="abrirModalNuevoProducto()">
                        <i class="bi bi-plus-circle"></i> Nuevo Producto
                    </button>
                </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-hover productos-table" id="tablaProductos">
                        <thead>
                            <tr>
                                <th data-column="Nombre" data-type="text">
                                    Nombre
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="SKU" data-type="text">
                                    SKU
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="Descripcion" data-type="text">
                                    Descripción
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="categoria_nombre" data-type="list">
                                    Categoría
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="fecha_creacion" data-type="daterange">
                                    Fecha Creación
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <?php if (tienePermiso('producto_maestro', 'editar', $cargoOperario)): ?>
                                <th data-column="Estado" data-type="list" style="width: 100px;">
                                    Estado
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th style="width: 120px;">Acciones</th>
                                <?php endif; ?>
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

    <!-- Modal para agregar/editar producto -->
    <div class="modal fade" id="modalProducto" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalProductoTitulo">Nuevo Producto Maestro</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formProducto">
                        <input type="hidden" id="productoId" name="id">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nombreProducto" class="form-label">Nombre *</label>
                                <input type="text" class="form-control" id="nombreProducto" name="Nombre" required maxlength="150">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="skuProducto" class="form-label">SKU *</label>
                                <input type="text" class="form-control" id="skuProducto" name="SKU" required maxlength="50">
                                <small class="text-muted">Código único del producto</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="descripcionProducto" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcionProducto" name="Descripcion" rows="3" maxlength="500"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="categoriaProducto" class="form-label">Categoría *</label>
                                <select class="form-select" id="categoriaProducto" name="Id_categoria" required>
                                    <option value="">Seleccione una categoría</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="estadoProducto" class="form-label">Estado</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="estadoProducto" name="Estado" checked>
                                    <label class="form-check-label" for="estadoProducto">
                                        <span id="estadoTexto">Activo</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarProducto()">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/producto_maestro_gestion.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>
</html>