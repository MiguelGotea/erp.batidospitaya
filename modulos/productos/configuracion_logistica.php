<?php
// configuracion_logistica.php

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('configuracion_logistica', 'vista', $cargoOperario)) {
    header('Location: ../../index.php');
    exit();
}

// Verificar permiso de edición
$puedeEditar = tienePermiso('configuracion_logistica', 'edicion', $cargoOperario);
$version = mt_rand(1, 10000);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Logística</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo $version; ?>">
    <link rel="stylesheet" href="css/configuracion_logistica.css?v=<?php echo $version; ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Configuración de Logística'); ?>

            <div class="container-fluid p-3">

                <!-- Tabs de Sucursales -->
                <ul class="nav nav-tabs mb-0" id="sucursalesTabs" role="tablist">
                    <!-- Generado dinámicamente por JS -->
                </ul>

                <!-- Contenido de Tabs -->
                <div class="tab-content" id="sucursalesTabContent">
                    <!-- Generado dinámicamente por JS -->
                </div>

            </div>
        </div>
    </div>

    <!-- Modal de Ayuda -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1" aria-labelledby="pageHelpModalLabel" aria-hidden="true"
        data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary-pitaya text-white">
                    <h5 class="modal-title" id="pageHelpModalLabel">
                        <i class="bi bi-info-circle me-2"></i>
                        Guía — Configuración de Logística
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-primary-pitaya border-bottom pb-2 fw-bold">
                                        <i class="bi bi-buildings me-2"></i> Tabs por Tienda
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Cada pestaña corresponde a una sucursal activa. Los cambios se aplican
                                        de forma independiente por tienda.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-success border-bottom pb-2 fw-bold">
                                        <i class="bi bi-table me-2"></i> Encabezado de Sucursal
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Los campos <strong>Días Stock Mínimo</strong> y <strong>Capacidad Congelados</strong>
                                        se aplican globalmente a toda la sucursal.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-warning-pitaya border-bottom pb-2 fw-bold">
                                        <i class="bi bi-tags me-2"></i> Categorías de Insumo
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        La tabla inferior configura los parámetros logísticos para cada
                                        <strong>categoría de insumo</strong> (A–G).
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-info border-bottom pb-2 fw-bold">
                                        <i class="bi bi-save me-2"></i> Guardado Automático
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Los cambios se guardan automáticamente al salir de cada campo
                                        (evento <em>blur</em>). Verifica el toast de confirmación.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm table-bordered small">
                            <thead class="table-dark">
                                <tr>
                                    <th>Letra</th>
                                    <th>Categoría</th>
                                    <th>Letra</th>
                                    <th>Categoría</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td><span class="badge-categoria badge-A">A</span></td><td>Frescos</td>
                                    <td><span class="badge-categoria badge-E">E</span></td><td>Fijos</td></tr>
                                <tr><td><span class="badge-categoria badge-B">B</span></td><td>Congelados</td>
                                    <td><span class="badge-categoria badge-F">F</span></td><td>Secos y Preparación</td></tr>
                                <tr><td><span class="badge-categoria badge-C">C</span></td><td>Fresas</td>
                                    <td><span class="badge-categoria badge-G">G</span></td><td>Productos de Mostrador</td></tr>
                                <tr><td><span class="badge-categoria badge-D">D</span></td><td>Desechables</td>
                                    <td></td><td></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Configuración Stock Grupo G -->
    <div class="modal fade" id="modalConfigGrupoG" tabindex="-1" aria-labelledby="modalConfigGrupoGLabel" aria-hidden="true"
        data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-warning-pitaya text-white" style="background-color: var(--pitaya-warning, #f0ad4e);">
                    <h5 class="modal-title" id="modalConfigGrupoGLabel">
                        <i class="bi bi-gear me-2"></i>
                        Stock Mínimo por Producto - Grupo G
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="p-3 bg-light border-bottom text-muted small">
                        Aquí puedes configurar un stock mínimo fijo en unidades para cada producto del Grupo G asociado a la sucursal activa. El cambio se guarda automáticamente al editar. Si lo dejas vacío, usará la configuración global de días de stock.
                    </div>
                    <div class="loader-container d-none p-4 text-center" id="loaderGrupoG">
                        <div class="loader"></div>
                        <span class="small text-muted d-block mt-2">Cargando productos...</span>
                    </div>
                    <div class="table-responsive p-3" id="contenedorTablaGrupoG">
                        <!-- Generado por JS -->
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const puedeEditar = <?php echo $puedeEditar ? 'true' : 'false'; ?>;
    </script>
    <script src="js/configuracion_logistica.js?v=<?php echo $version; ?>"></script>
</body>

</html>
