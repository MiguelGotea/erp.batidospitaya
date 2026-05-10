<?php
// postulacion_panel_control.php

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';
require_once '../../core/helpers/config.php'; //Se encarga de restar 6 horas a los registros de hora para la bd

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('postulacion_panel_control', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

$puedeEditar = tienePermiso('postulacion_panel_control', 'editar', $cargoOperario);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Control de Personal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/postulacion_panel_control.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Panel de Control de Personal'); ?>

            <div class="container-fluid p-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <!-- Perfiles PDF Globales (Solo para Sucursales) -->
                        <div id="globalPdfsContainer" class="row mb-4 d-none">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <div class="card bg-light border-0">
                                    <div class="card-body d-flex align-items-center justify-content-between py-2">
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-primary text-white p-2 me-3">
                                                <i class="bi bi-person-badge"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-bold">Perfil PDF Global (Vendedores)</h6>
                                                <small class="text-muted">Aplica a todas las sucursales</small>
                                            </div>
                                        </div>
                                        <div id="globalVendedoresBtns"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light border-0">
                                    <div class="card-body d-flex align-items-center justify-content-between py-2">
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-success text-white p-2 me-3">
                                                <i class="bi bi-person-check"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-bold">Perfil PDF Global (Líderes)</h6>
                                                <small class="text-muted">Aplica a todas las sucursales</small>
                                            </div>
                                        </div>
                                        <div id="globalLideresBtns"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Navegación por pestañas -->
                        <ul class="nav nav-tabs nav-fill mb-4" id="panelTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="sucursales-tab" data-bs-toggle="tab"
                                    data-bs-target="#sucursales" type="button" role="tab">
                                    <i class="bi bi-shop me-2"></i>Sucursales
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="administrativo-tab" data-bs-toggle="tab"
                                    data-bs-target="#administrativo" type="button" role="tab">
                                    <i class="bi bi-building me-2"></i>Administrativo
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="produccion-tab" data-bs-toggle="tab"
                                    data-bs-target="#produccion" type="button" role="tab">
                                    <i class="bi bi-truck me-2"></i>CDS
                                </button>
                            </li>
                        </ul>

                        <!-- Contenido de las pestañas -->
                        <div class="tab-content" id="panelTabsContent">
                            <!-- Pestaña Sucursales -->
                            <div class="tab-pane fade show active" id="sucursales" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover align-middle" id="tablaSucursales">
                                        <thead class="table-header text-center">
                                            <tr>
                                                <th style="width: 200px;">Grupo</th>
                                                <th style="width: 100px;">Obligatorio</th>
                                                <th style="width: 100px;">Plazas a Cubrir</th>
                                                <th style="width: 100px;">Personal Contratado</th>
                                                <th style="width: 100px;">Web</th>
                                                <th style="width: 120px;">Salario</th>
                                                <th style="width: 150px;" class="d-none">Urgencia</th>
                                                <th style="width: 80px;">Banner</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tablaSucursalesBody">
                                            <!-- Cargado dinámicamente -->
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-muted small mt-2">
                                    <i class="bi bi-info-circle"></i> Mostrando <span id="countSucursales">0</span>
                                    sucursales configuradas
                                </div>
                            </div>

                            <!-- Pestaña Administrativo -->
                            <div class="tab-pane fade" id="administrativo" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover align-middle"
                                        id="tablaAdministrativo">
                                        <thead class="table-header">
                                            <tr>
                                                <th>Cargo</th>
                                                <th style="width: 100px;">Obligatorio</th>
                                                <th style="width: 120px;">Plazas a Cubrir</th>
                                                <th style="width: 100px;">
                                                    <i class="bi bi-info-circle" data-bs-toggle="tooltip"
                                                        title="Plazas Activas Actuales"></i>
                                                    Personal Contratado
                                                </th>
                                                <th style="width: 120px;">Web</th>
                                                <th style="width: 120px;">Salario</th>
                                                <th style="width: 150px;">Urgencia</th>
                                                <th style="width: 80px;">PDF</th>
                                                <th style="width: 80px;">Banner</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tablaAdministrativoBody">
                                            <!-- Cargado dinámicamente -->
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-muted small mt-2">
                                    <i class="bi bi-info-circle"></i> Mostrando <span id="countAdministrativo">0</span>
                                    cargos de administración configurados
                                </div>
                            </div>

                            <!-- Pestaña Producción -->
                            <div class="tab-pane fade" id="produccion" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover align-middle" id="tablaProduccion">
                                        <thead class="table-header">
                                            <tr>
                                                <th>Cargo</th>
                                                <th style="width: 100px;">Obligatorio</th>
                                                <th style="width: 110px;">Plazas a Cubrir</th>
                                                <th style="width: 100px;">
                                                    <i class="bi bi-info-circle" data-bs-toggle="tooltip"
                                                        title="Plazas Activas Actuales"></i>
                                                    Personal Contratado
                                                </th>
                                                <th style="width: 150px;">Web</th>
                                                <th style="width: 120px;">Salario</th>
                                                <th style="width: 150px;">Urgencia</th>
                                                <th style="width: 80px;">PDF</th>
                                                <th style="width: 80px;">Banner</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tablaProduccionBody">
                                            <!-- Cargado dinámicamente -->
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-muted small mt-2">
                                    <i class="bi bi-info-circle"></i> Mostrando <span id="countProduccion">0</span>
                                    cargos de CDS configurados
                                </div>
                            </div>
                        </div>

                        <form id="formUploadPDF" style="display: none;">
                            <input type="file" id="inputUploadPDF" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp">
                        </form>

                        <!-- Form oculto para subir Banner -->
                        <form id="formUploadBanner" style="display: none;">
                            <input type="file" id="inputUploadBanner" accept=".jpg,.png,.jpeg">
                        </form>

                        <!-- Botón de guardar (solo si tiene permiso) -->
                        <?php if ($puedeEditar): ?>
                            <div class="text-end mt-4">
                                <button class="btn btn-success btn-lg" onclick="guardarCambios()">
                                    <i class="bi bi-save me-2"></i>Guardar Cambios
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
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
                        Guía del Panel de Control de Personal
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-primary border-bottom pb-2 fw-bold">
                                        <i class="bi bi-shop me-2"></i> Pestaña Sucursales
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Configura la cantidad de vendedores y líderes por sucursal. Los valores
                                        representan el total de personas necesarias en cada tienda.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-primary border-bottom pb-2 fw-bold">
                                        <i class="bi bi-building me-2"></i> Pestaña Administrativo
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Define la cantidad necesaria de cada cargo administrativo y si es obligatorio
                                        mantener esa plaza cubierta.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-primary border-bottom pb-2 fw-bold">
                                        <i class="bi bi-gear-fill me-2"></i> Pestaña Producción
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Gestiona los cargos de producción. <strong>Cantidad</strong> es la base
                                        necesaria, <strong>Plazas a Cubrir</strong> permite abrir más plazas temporalmente, y
                                        <strong>Personal Contratado</strong> muestra cuántos están actualmente en el cargo.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-warning border-bottom pb-2 fw-bold">
                                        <i class="bi bi-toggle-on me-2"></i> Plazas Obligatorias
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Cuando una plaza es obligatoria y las posiciones cubiertas son menores a lo
                                        definido, automáticamente se habilita la plaza para reclutamiento.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info py-2 px-3 small">
                        <strong><i class="fas fa-info-circle me-1"></i> Nota:</strong>
                        <br>
                        Los cambios solo se guardan al presionar el botón "Guardar Cambios". La columna
                        <strong>Personal Contratado/Activas</strong> se actualiza automáticamente según los operarios activos en el
                        sistema.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        #pageHelpModal {
            z-index: 1060 !important;
        }

        .modal-backdrop {
            z-index: 1050 !important;
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const puedeEditar = <?php echo $puedeEditar ? 'true' : 'false'; ?>;
    </script>
    <script src="js/postulacion_panel_control.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>