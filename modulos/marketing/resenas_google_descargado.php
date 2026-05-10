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

$puedeActualizar  = tienePermiso('resenas_google_descargado', 'actualizacion', $cargoOperario);
$puedeManejarBot  = tienePermiso('configuracion_bot_resenasgoogle', 'vista', $cargoOperario);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reseñas de Google - Historial</title>


    <!-- CSS Standard -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <!-- ERP Styles -->
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/resenas_google_descargado.css?v=<?php echo mt_rand(1, 10000); ?>">

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Reseñas de Google - Historial'); ?>

            <div class="container-fluid p-4">

                <?php if ($puedeManejarBot): ?>
                <!-- ── Panel de Control del Bot GMB ─────────────────────────── -->
                <div class="card border-0 shadow-sm mb-4" id="gmbBotPanel">
                    <div class="card-body py-3 px-4">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="gmb-bot-icon">
                                    <i class="fab fa-google" style="font-size:1.4rem; color:#4285F4;"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold mb-0" style="font-size:.95rem;">Bot de Sincronización Google Reviews</div>
                                    <div class="text-muted" style="font-size:.80rem;" id="gmbLastSyncInfo">
                                        <span class="spinner-border spinner-border-sm me-1" role="status"></span>Cargando estado...
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-secondary" id="btnVerLog" onclick="verLogSync()" style="display:none;">
                                    <i class="fas fa-list-alt me-1"></i> Ver Log
                                </button>
                                <button class="btn btn-sm btn-pitaya" id="btnSyncNow" onclick="sincronizarAhora()">
                                    <i class="fas fa-sync-alt me-1"></i> Sincronizar Ahora
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Log Sync -->
                <div class="modal fade" id="logSyncModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content border-0 shadow">
                            <div class="modal-header" style="background:#1a1a2e; color:#fff;">
                                <h6 class="modal-title mb-0"><i class="fas fa-terminal me-2"></i>Log del último Sync</h6>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body p-0">
                                <pre id="logSyncContent" style="background:#1a1a2e; color:#a8ff78; font-size:.78rem; margin:0; padding:1rem; min-height:200px; max-height:500px; overflow-y:auto;">Cargando...</pre>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="row mb-3">
                    <div class="col-12"></div>
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
                                        <i class="fas fa-sync me-2"></i> Sincronización Automática
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        El bot sincroniza automáticamente las reseñas cada día a las 2am.
                                        También puedes usar <strong>"Sincronizar Ahora"</strong> para
                                        forzar una sincronización inmediata.
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
        .btn-pitaya {
            background: linear-gradient(135deg, #51B8AC, #0E544C);
            color: #fff;
            border: none;
            transition: opacity .2s;
        }
        .btn-pitaya:hover {
            opacity: .85;
            color: #fff;
        }
        #gmbBotPanel {
            border-left: 4px solid #4285F4 !important;
        }
        .gmb-bot-icon {
            width: 38px; height: 38px;
            background: #f0f4ff;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
        }
    </style>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/resenas_google_descargado.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>