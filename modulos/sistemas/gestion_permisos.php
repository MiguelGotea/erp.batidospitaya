<?php
// gestion_permisos.php
$version = mt_rand(1, 10000);

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso a esta herramienta
if (!tienePermiso('gestion_permisos', 'vista', $cargoOperario)) {
    header('Location: ../../index.php');
    exit();
}

// Verificar si puede crear nuevas acciones
$puedeCrearAcciones = tienePermiso('gestion_permisos', 'crear_accion', $cargoOperario);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Permisos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo $version; ?>">
    <link rel="stylesheet" href="css/gestion_permisos.css?v=<?php echo $version; ?>">
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    
    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Gestión de Permisos'); ?>
            
            <div class="container-fluid p-3">
                <div class="row">
                    <!-- Sidebar: Lista de herramientas -->
                    <div class="col-md-3 sidebar-herramientas">
                        <div class="card">
                            <div class="card-header bg-primary-custom text-white">
                                <i class="bi bi-shield-lock"></i> Componentes del Sistema
                            </div>
                            
                            <!-- PESTAÑAS CUSTOM -->
                            <div class="tabs-container-custom">
                                <button class="tab-btn-custom tab-active" id="tab-herramientas" onclick="cambiarTab('herramientas')">
                                    <i class="bi bi-tools"></i><br>Herramientas
                                </button>
                                <button class="tab-btn-custom" id="tab-indicadores" onclick="cambiarTab('indicadores')">
                                    <i class="bi bi-graph-up"></i><br>Indicadores
                                </button>
                                <button class="tab-btn-custom" id="tab-balances" onclick="cambiarTab('balances')">
                                    <i class="bi bi-calculator"></i><br>Balances
                                </button>
                            </div>
                            
                            <div class="card-body p-0">
                                <!-- Tab Herramientas -->
                                <div class="tab-content-custom tab-content-active" id="content-herramientas">
                                    <div class="p-3 border-bottom">
                                        <input type="text" class="form-control form-control-sm buscar-input" data-tipo="herramientas" placeholder="Buscar herramienta...">
                                    </div>
                                    <div class="tree-container" id="treeHerramientas">
                                        <div class="text-center p-4">
                                            <div class="spinner-border text-primary" role="status"></div>
                                            <p class="mt-2 text-muted">Cargando herramientas...</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Tab Indicadores -->
                                <div class="tab-content-custom" id="content-indicadores">
                                    <div class="p-3 border-bottom">
                                        <input type="text" class="form-control form-control-sm buscar-input" data-tipo="indicadores" placeholder="Buscar indicador...">
                                    </div>
                                    <div class="tree-container" id="treeIndicadores">
                                        <div class="text-center p-4">
                                            <div class="spinner-border text-primary" role="status"></div>
                                            <p class="mt-2 text-muted">Cargando indicadores...</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Tab Balances -->
                                <div class="tab-content-custom" id="content-balances">
                                    <div class="p-3 border-bottom">
                                        <input type="text" class="form-control form-control-sm buscar-input" data-tipo="balances" placeholder="Buscar balance...">
                                    </div>
                                    <div class="tree-container" id="treeBalances">
                                        <div class="text-center p-4">
                                            <div class="spinner-border text-primary" role="status"></div>
                                            <p class="mt-2 text-muted">Cargando balances...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Panel principal: Permisos -->
                    <div class="col-md-9">
                        <div class="card">
                            <div class="card-header bg-primary-custom text-white d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-gear-fill"></i> <span id="herramientaSeleccionadaNombre">Seleccione una herramienta</span>
                                </div>
                                <div id="headerActions" style="display: none;">
                                    <?php if ($puedeCrearAcciones): ?>
                                    <button class="btn btn-sm btn-light" onclick="abrirModalNuevaAccion()">
                                        <i class="bi bi-plus-circle"></i> Nueva Acción
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Descripción -->
                            <div id="herramientaDescripcion" class="p-3 border-bottom bg-light">
                                <div class="mb-2">
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle"></i> <strong>Descripción:</strong> <span id="descripcionTexto"></span>
                                    </small>
                                </div>
                                <div>
                                    <small class="text-muted">
                                        <i class="bi bi-link-45deg"></i> <strong>URL:</strong> <code id="urlRealTexto" class="text-muted"></code>
                                    </small>
                                </div>
                            </div>
                            
                            <div class="card-body" id="panelPermisos">
                                <div class="empty-state">
                                    <i class="bi bi-arrow-left-circle display-1 text-muted"></i>
                                    <p class="lead text-muted mt-3">Seleccione una herramienta del menú lateral</p>
                                </div>
                            </div>
                        </div>

                        <!-- Botón guardar -->
                        <div class="floating-save-btn" id="btnGuardarFlotante" style="display: none;">
                            <button class="btn btn-success btn-lg" onclick="guardarCambiosPermisos()">
                                <i class="bi bi-save"></i> Guardar Cambios
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Nueva Acción -->
    <div class="modal fade" id="modalNuevaAccion" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Crear Nueva Acción</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formNuevaAccion">
                        <input type="hidden" id="toolIdNuevaAccion" name="tool_id">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> <strong>Herramienta:</strong> <span id="nombreHerramientaModal"></span>
                        </div>
                        <div class="mb-3">
                            <label for="nombreAccion" class="form-label">Nombre de la Acción *</label>
                            <input type="text" class="form-control" id="nombreAccion" name="nombre_accion" required placeholder="ej: exportar, aprobar">
                            <small class="text-muted">Use minúsculas y guiones bajos</small>
                        </div>
                        <div class="mb-3">
                            <label for="descripcionAccion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcionAccion" name="descripcion" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" onclick="guardarNuevaAccion()">
                        <i class="bi bi-plus-circle"></i> Crear Acción
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const PUEDE_CREAR_ACCIONES = <?php echo $puedeCrearAcciones ? 'true' : 'false'; ?>;
    </script>
    <script src="js/gestion_permisos.js?v=<?php echo $version; ?>"></script>
</body>
</html>