<?php
/**
 * modulos/sistemas/gestion_anulaciones.php
 * Gestión de Anulaciones de Pedidos Access ↔ Host
 * Herramienta estándar ERP Batidos Pitaya
 */

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('aprobacion_pedidos_access_host', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

$puedeAprobar = tienePermiso('aprobacion_pedidos_access_host', 'aprobar', $cargoOperario);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aprobación de Anulaciones · Pitaya ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/modales_premium.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/fab_button.css">
    <link rel="stylesheet" href="css/gestion_anulaciones.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Aprobación de Anulaciones'); ?>

            <div class="container-fluid p-3">

                <!-- ── Stats cards ─────────────────────────── -->
                <div class="row g-3 mb-3" id="statsRow">
                    <div class="col-6 col-md-3">
                        <div class="card border-0 shadow-sm h-100 stat-card">
                            <div class="card-body text-center py-3">
                                <div class="stat-val text-secondary" id="statTotal">—</div>
                                <div class="stat-lbl">Total</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card border-0 shadow-sm h-100 stat-card">
                            <div class="card-body text-center py-3">
                                <div class="stat-val text-warning" id="statPendientes">—</div>
                                <div class="stat-lbl">Pendientes</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card border-0 shadow-sm h-100 stat-card">
                            <div class="card-body text-center py-3">
                                <div class="stat-val text-success" id="statAprobadas">—</div>
                                <div class="stat-lbl">Aprobadas</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card border-0 shadow-sm h-100 stat-card">
                            <div class="card-body text-center py-3">
                                <div class="stat-val text-primary" id="statEjecutadas">—</div>
                                <div class="stat-lbl">Ejecutadas en Tienda</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Tabla ────────────────────────────────── -->
                <div class="table-responsive">
                    <table class="table table-hover cupones-table" id="tablaAnulaciones">
                        <thead>
                            <tr>
                                <th data-column="CodAnulacionHost" data-type="number" style="width: 80px;">
                                    #
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="CodPedido" data-type="text">
                                    Pedido
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="Sucursal" data-type="list">
                                    Suc.
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="HoraSolicitada" data-type="daterange">
                                    Solicitado
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="Status" data-type="list">
                                    Status
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="Motivo" data-type="text">
                                    Motivo
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="AprobadoPor" data-type="text">
                                    Aprobado por
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="EjecutadoEnTienda" data-type="list">
                                    Tienda
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th style="width: 150px;" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
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

            </div><!-- /container-fluid -->
        </div><!-- /sub-container -->
    </div><!-- /main-container -->

    <!-- ═══════════════════════════════════════════════════════
     MODAL DE DECISIÓN (Aprobar / Rechazar)
════════════════════════════════════════════════════════ -->
    <div class="modal fade" id="modalDecision" data-bs-backdrop="static" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background:#0E544C; color:#fff;">
                    <h5 class="modal-title" id="modalDecisionTitle">
                        <i class="bi bi-clipboard-check me-2"></i>Revisar Solicitud
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Info básica -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <div class="card border-0 bg-light text-center p-2">
                                <div class="small text-muted">Pedido Principal</div>
                                <div class="fw-bold fs-4 text-danger" id="dec_codPedido">—</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 bg-light text-center p-2">
                                <div class="small text-muted">Pedido Cambio</div>
                                <div class="fw-bold fs-4 text-primary" id="dec_codCambio">—</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 bg-light text-center p-2">
                                <div class="small text-muted">Sucursal</div>
                                <div class="fw-bold fs-4" style="color:#0E544C" id="dec_sucursal">—</div>
                            </div>
                        </div>
                    </div>

                    <!-- Motivo -->
                    <div class="alert alert-warning py-2 mb-3">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <strong>Motivo declarado:</strong> <span id="dec_motivo">—</span>
                    </div>

                    <!-- Pestañas Pedido / Cambio -->
                    <ul class="nav nav-tabs mb-3" id="tabsDetalle">
                        <li class="nav-item">
                            <a class="nav-link active" id="tab-pedido-link" href="#"
                                onclick="verDetallePedido('pedido'); return false;">
                                <i class="bi bi-receipt me-1"></i>Detalle Pedido a Anular
                            </a>
                        </li>
                        <li class="nav-item" id="tabCambioItem" style="display:none">
                            <a class="nav-link" id="tab-cambio-link" href="#"
                                onclick="verDetallePedido('cambio'); return false;">
                                <i class="bi bi-arrow-left-right me-1"></i>Pedido de Cambio
                            </a>
                        </li>
                    </ul>

                    <!-- Detalle factura -->
                    <div id="detalleFactura">
                        <div class="text-center py-4 text-muted" id="detallePlaceholder">
                            <div class="spinner-border spinner-border-sm"></div> Cargando detalle...
                        </div>
                        <div id="detalleContenido" style="display:none"></div>
                    </div>

                    <!-- Comentario para decisión -->
                    <?php if ($puedeAprobar): ?>
                        <hr>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Comentario de decisión <span
                                    class="text-muted">(opcional)</span></label>
                            <textarea class="form-control form-control-sm" id="dec_comentario" rows="2"
                                placeholder="Motivo de aprobación o rechazo..."></textarea>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer border-0">
                    <button class="btn-modern btn-modern-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <?php if ($puedeAprobar): ?>
                        <button class="btn-modern" style="background:#dc3545;color:#fff" id="btnRechazar"
                            onclick="ejecutarDecision('rechazar')">
                            <i class="bi bi-x-circle me-1"></i>Rechazar
                        </button>
                        <button class="btn-modern btn-modern-primary" id="btnAprobar" onclick="ejecutarDecision('aprobar')">
                            <i class="bi bi-check-circle me-1"></i>Aprobar
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
     MODAL NUEVA ANULACIÓN WEB
════════════════════════════════════════════════════════ -->
    <?php if ($puedeAprobar): ?>
        <div class="modal fade" id="modalNuevaAnulacion" data-bs-backdrop="static" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header" style="background:#218838; color:#fff;">
                        <h5 class="modal-title">
                            <i class="bi bi-plus-circle me-2"></i>Nueva Solicitud de Anulación (Web)
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Sucursal</label>
                                <select class="form-select form-select-sm" id="new_sucursal" onchange="buscarPedidoWeb()">
                                    <option value="">Seleccionar...</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Nº de Pedido</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" class="form-control" id="new_codPedido" placeholder="Ej: 1234">
                                    <button class="btn btn-outline-secondary" onclick="buscarPedidoWeb()">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Motivo</label>
                                <input type="text" class="form-control form-control-sm" id="new_motivo"
                                    placeholder="Motivo de la anulación">
                            </div>
                        </div>
                        <!-- Vista previa del pedido -->
                        <div id="newPedidoPreview" style="display:none">
                            <div class="alert alert-info py-2 mb-2">
                                <i class="bi bi-info-circle me-1"></i>
                                Verifica que este sea el pedido correcto antes de enviar.
                            </div>
                            <div id="newPedidoDetalle"></div>
                        </div>
                        <div id="newPedidoEmpty" class="text-center text-muted py-3" style="display:none">
                            <i class="bi bi-search fs-2 opacity-25"></i>
                            <div class="small mt-1">No se encontró el pedido.</div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button class="btn-modern btn-modern-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn-modern btn-modern-primary" id="btnEnviarAnulacionWeb"
                            onclick="enviarAnulacionWeb()" disabled>
                            <i class="bi bi-send me-1"></i>Enviar Solicitud
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════════
     MODAL DE AYUDA (obligatorio ERP)
════════════════════════════════════════════════════════ -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1" aria-labelledby="pageHelpModalLabel" aria-hidden="true"
        data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="pageHelpModalLabel">
                        <i class="fas fa-info-circle me-2"></i>Guía de Aprobación de Anulaciones
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-primary border-bottom pb-2 fw-bold">
                                        <i class="fas fa-sync-alt me-2"></i>Flujo de Sincronización
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Las tiendas envían sus solicitudes desde Access (Status=0).
                                        El panel ERP muestra las pendientes para aprobar o rechazar.
                                        Access detecta la resolución cada 60s y ejecuta la anulación localmente.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-success border-bottom pb-2 fw-bold">
                                        <i class="fas fa-check-circle me-2"></i>Cómo Aprobar
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Haz clic en el ícono de lupa para ver el detalle del pedido.
                                        Compara el <strong>Pedido a Anular</strong> y el <strong>Pedido de
                                            Cambio</strong> con el motivo declarado.
                                        Luego elige Aprobar o Rechazar.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-warning border-bottom pb-2 fw-bold">
                                        <i class="fas fa-globe me-2"></i>Nueva Anulación Web
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Permite crear una solicitud directamente desde el ERP sin necesitar Access.
                                        Busca el pedido por número y sucursal, agrega el motivo y envía.
                                        Access la ejecutará automáticamente.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-danger border-bottom pb-2 fw-bold">
                                        <i class="fas fa-shield-alt me-2"></i>Permisos
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        <strong>vista</strong>: Ver solicitudes.<br>
                                        <strong>aprobar</strong>: Aprobar / Rechazar solicitudes y crear anulaciones
                                        web.
                                        Solo usuarios autorizados ven los botones de acción.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info py-2 px-3 small">
                        <strong><i class="fas fa-info-circle me-1"></i>Auto-refresh:</strong>
                        La tabla se actualiza automáticamente cada 60 segundos para mostrar nuevas solicitudes en tiempo
                        real.
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

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const PUEDE_APROBAR = <?php echo $puedeAprobar ? 'true' : 'false'; ?>;
        const USUARIO_ACTUAL = '<?php echo htmlspecialchars($usuario['Nombre'] . ' ' . $usuario['Apellido']); ?>';
    </script>
    <script src="js/gestion_anulaciones.js?v=<?php echo mt_rand(1, 10000); ?>"></script>

    <!-- Botón Flotante con opciones -->
    <?php if ($puedeAprobar): ?>
        <div class="fab-container">
            <div class="fab-options">
                <div class="fab-option" onclick="abrirModalNuevaAnulacion()">
                    <span class="fab-label">Nueva Anulación</span>
                    <div class="fab-icon-holder"><i class="fas fa-plus"></i></div>
                </div>
            </div>
            <div class="btn-floating-pitaya" title="Nueva Anulación">
                <i class="fas fa-plus"></i>
            </div>
        </div>
    <?php endif; ?>
</body>

</html>