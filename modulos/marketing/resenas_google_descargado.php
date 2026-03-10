<?php
/**
 * Herramienta de Visualización de Reseñas de Google
 * Batidos Pitaya ERP
 */

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar permiso de vista
if (!tienePermiso('resenas_google_descargado', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

$puedeActualizar = tienePermiso('resenas_google_descargado', 'actualizacion', $cargoOperario);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reseñas de Google - Historial</title>


    <!-- CSS Standard -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <!-- ERP Styles -->
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/resenas_google_descargado.css?v=<?php echo mt_rand(1, 10000); ?>">

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Reseñas de Google - Historial'); ?>

            <div class="container-fluid p-4">
                <div class="row mb-4">
                    <div class="col-12 text-end">
                        <?php if ($puedeActualizar): ?>
                            <button class="btn btn-primary px-4 py-2" id="btnActualizar" onclick="actualizarResenas()">
                                <i class="fas fa-sync-alt me-2"></i> Actualizar Datos
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card border-0 shadow-sm resenas-container">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-resenas mb-0" id="tablaResenasGoogle">
                                <thead>
                                    <tr>
                                        <th style="width: 12%;" data-column="locationId" data-type="list">
                                            Sucursal
                                            <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                        </th>
                                        <th style="width: 10%;" data-column="reviewerName" data-type="text">
                                            Usuario
                                            <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                        </th>
                                        <th style="width: 8%;" class="text-center" data-column="starRating"
                                            data-type="list">
                                            Calif.
                                            <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                        </th>
                                        <th style="width: 20%;" data-column="comment" data-type="text">
                                            Comentario
                                            <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                        </th>
                                        <th style="width: 10%;" class="text-center" data-column="createTime"
                                            data-type="daterange">
                                            Fecha
                                            <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                        </th>
                                        <th style="width: 8%;" class="text-center" data-column="createTime"
                                            data-type="sort-only">
                                            Hora
                                            <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                        </th>
                                        <th style="width: 20%;" data-column="reviewReplyComment" data-type="text">
                                            Respuesta
                                            <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                        </th>
                                        <th style="width: 12%;" class="text-center" data-column="reviewReplyUpdateTime"
                                            data-type="daterange">
                                            Fecha Rpta
                                            <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyResenas">
                                    <!-- Datos cargados via AJAX -->
                                    <tr>
                                        <td colspan="8" class="text-center py-5">
                                            <div class="spinner-border text-primary" role="status" id="loaderResenas">
                                                <span class="visually-hidden">Cargando...</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Paginación Estándar -->
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

    <!-- Modal de Ayuda Universal -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1" aria-labelledby="pageHelpModalLabel" aria-hidden="true"
        data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="pageHelpModalLabel">
                        <i class="fas fa-info-circle me-2"></i>
                        Guía de Reseñas de Google
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
                                        <i class="fas fa-list-ul me-2"></i> Visualización y Filtros
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Puedes filtrar las reseñas por sucursal, usuario, calificación o fecha usando
                                        los iconos de embudo en los encabezados.
                                        Usa la paginación inferior para navegar entre registros.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-success border-bottom pb-2 fw-bold">
                                        <i class="fas fa-sync me-2"></i> Actualización
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        El botón <strong>"Actualizar Datos"</strong> activa un script en la nube que
                                        sincroniza
                                        las nuevas reseñas directamente a esta tabla.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="alert alert-info py-2 px-3 small border-0">
                                <strong><i class="fas fa-info-circle me-1"></i> Nota:</strong>
                                <br>
                                Las reseñas se vinculan a las sucursales mediante el código de Google Business
                                configurado en el catálogo de sucursales.
                            </div>
                        </div>
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

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/resenas_google_descargado.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>