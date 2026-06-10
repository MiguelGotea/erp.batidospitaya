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

// Acceso: requiere 'vista' O 'ver_completo'
$tieneVista      = tienePermiso('aprobacion_pedidos_access_host', 'vista',       $cargoOperario);
$puedeVerCompleto = tienePermiso('aprobacion_pedidos_access_host', 'ver_completo', $cargoOperario);

if (!$tieneVista && !$puedeVerCompleto) {
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
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/modales_premium.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/fab_button.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/gestion_anulaciones.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Aprobación de Anulaciones'); ?>

            <div class="container-fluid p-3">

                <!-- ── Tabla ────────────────────────────────── -->
                <div class="table-responsive">
                    <table class="table table-hover cupones-table" id="tablaAnulaciones">
                        <thead>
                            <tr>
                                <th data-column="CodPedido" data-type="text">
                                    Pedido
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="FechaPedido" data-type="date">
                                    Fecha Pedido
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
                                <th data-column="Modalidad">
                                    <div class="status-header-content">
                                        <span class="status-label">Mod.</span>
                                        <div class="estado-filter-circles">
                                            <i class="bi bi-pc-display filter-circle" data-mod="1"
                                                onclick="setModalidadFilter('1')" title="Local"></i>
                                            <i class="bi bi-globe filter-circle" data-mod="2"
                                                onclick="setModalidadFilter('2')" title="Web"></i>
                                        </div>
                                    </div>
                                </th>
                                <th data-column="Status">
                                    <div class="status-header-content">
                                        <span class="status-label">Status</span>
                                        <div class="estado-filter-circles">
                                            <i class="bi bi-hourglass-split filter-circle" data-state="0"
                                                onclick="setEstadoFilter('0')" title="Pendientes"></i>
                                            <i class="bi bi-check-circle-fill filter-circle" data-state="1"
                                                onclick="setEstadoFilter('1')" title="Aprobadas"></i>
                                            <i class="bi bi-x-circle-fill filter-circle" data-state="2"
                                                onclick="setEstadoFilter('2')" title="Rechazadas"></i>
                                        </div>
                                    </div>
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
                                <th class="text-center" style="width:90px;" title="Veredicto de la IA">
                                    <i class="bi bi-robot me-1"></i>IA
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

            </div><!-- /container-fluid -->
        </div><!-- /sub-container -->
    </div><!-- /main-container -->

    <!-- ═══════════════════════════════════════════════════════
     MODAL DE DECISIÓN (Aprobar / Rechazar)
════════════════════════════════════════════════════════ -->
    <div class="modal fade" id="modalDecision" data-bs-backdrop="static" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
                <div class="modal-header border-0 py-3 px-4" style="background: #0E544C; color: #fff;">
                    <div class="d-flex align-items-center">
                        <div class="bg-white bg-opacity-25 rounded-circle p-2 me-3 d-flex align-items-center justify-content-center"
                            style="width: 40px; height: 40px;">
                            <i class="bi bi-shield-check fs-4"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0" id="modalDecisionTitle">Revisar Solicitud</h5>
                            <p class="small mb-0 opacity-75">Valida los datos antes de proceder</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-light">

                    <!-- Motivo Declarado Card -->
                    <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px;">
                        <div class="card-body p-3 d-flex align-items-center">
                            <div class="rounded-3 p-3 me-3" style="background: #fff3cd; color: #856404;">
                                <i class="bi bi-chat-left-dots fs-4"></i>
                            </div>
                            <div class="flex-grow-1">
                                <label class="text-uppercase fw-bold text-muted small mb-1"
                                    style="letter-spacing: 1px;">Motivo Declarado</label>
                                <div class="fs-5 fw-semibold text-dark" id="dec_motivo">—</div>
                            </div>
                        </div>
                    </div>

                    <!-- Comparativa de Pedidos (Cards) -->
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="card h-100 border-0 shadow-sm overflow-hidden" style="border-radius: 12px;">
                                <div class="card-header border-0 bg-white pt-3 pb-0 px-3">
                                    <h6 class="text-danger text-uppercase fw-bold small mb-0 d-flex align-items-center">
                                        <span class="p-1 rounded bg-danger bg-opacity-10 me-2">
                                            <i class="bi bi-trash3"></i>
                                        </span>
                                        PEDIDO A ANULAR
                                    </h6>
                                </div>
                                <div class="card-body p-3">
                                    <div id="detallePedidoPrincipal">
                                        <div class="text-center py-5">
                                            <div class="spinner-border text-danger opacity-25"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6" id="colCambio" style="display:none">
                            <div class="card h-100 border-0 shadow-sm overflow-hidden" style="border-radius: 12px;">
                                <div class="card-header border-0 bg-white pt-3 pb-0 px-3">
                                    <h6
                                        class="text-primary text-uppercase fw-bold small mb-0 d-flex align-items-center">
                                        <span class="p-1 rounded bg-primary bg-opacity-10 me-2">
                                            <i class="bi bi-arrow-left-right"></i>
                                        </span>
                                        PEDIDO DE CAMBIO
                                    </h6>
                                </div>
                                <div class="card-body p-3">
                                    <div id="detallePedidoCambio">
                                        <div class="text-center py-5">
                                            <div class="spinner-border text-primary opacity-25"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6" id="colNoCambio">
                            <div class="h-100 d-flex flex-column align-items-center justify-content-center text-muted border border-2 rounded shadow-sm bg-white p-5"
                                style="border-style: dashed !important; border-radius: 12px !important; min-height: 300px;">
                                <i class="bi bi-info-circle fs-1 opacity-25 mb-3"></i>
                                <h6 class="fw-bold mb-1">Sin Pedido de Cambio</h6>
                                <p class="small text-center px-4 mb-0">Esta solicitud no incluye un pedido de reemplazo.
                                    Solo se procesará la anulación.</p>
                            </div>
                        </div>
                    </div>


                    <!-- Panel resultado IA -->
                    <div id="panelResultadoIA" class="mt-4" style="display:none;">
                        <div class="card border-0 shadow-sm" id="cardResultadoIA"
                            style="border-radius: 12px; border-left: 4px solid #6c757d !important;">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="rounded-3 p-2 me-2" style="background:#f0f9ff; color:#0369a1;">
                                        <i class="bi bi-robot fs-5"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold small text-uppercase text-muted"
                                            style="letter-spacing:1px;">Análisis de IA</div>
                                        <div id="ia_proveedor" class="small text-muted"></div>
                                    </div>
                                    <span id="ia_badge_decision" class="badge fs-6 px-3 py-2"></span>
                                </div>
                                <div class="border-top pt-2 mt-1">
                                    <p id="ia_comentario" class="mb-2 fw-medium" style="font-size:14px;"></p>
                                    <ul id="ia_puntos" class="small text-muted mb-0 ps-3"></ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Loader IA -->
                    <div id="loaderIA" class="mt-3 text-center" style="display:none;">
                        <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                        <span class="text-muted small">La IA está analizando la solicitud...</span>
                    </div>

                    <!-- Comentario para decisión -->
                    <?php if ($puedeAprobar): ?>
                        <div class="mt-4">
                            <div class="card border-0 shadow-sm" style="border-radius: 12px;">
                                <div class="card-body p-3">
                                    <label class="form-label small fw-bold text-muted text-uppercase mb-2">
                                        <i class="bi bi-pencil-square me-1"></i> Comentario de decisión (Opcional)
                                    </label>
                                    <textarea class="form-control border-light-subtle bg-light" id="dec_comentario" rows="3"
                                        placeholder="Escribe el motivo de tu decisión..."
                                        style="border-radius: 8px; resize: none;"></textarea>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer border-0 p-4 bg-white d-flex justify-content-between align-items-center">
                    <button class="btn btn-link text-muted text-decoration-none fw-bold" data-bs-dismiss="modal">
                        Cancelar y Volver
                    </button>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <?php if ($puedeAprobar): ?>
                            <!-- Botón IA -->
                            <button class="btn px-4 py-2 fw-bold d-flex align-items-center" id="btnConsultarIA"
                                style="background: linear-gradient(135deg,#0ea5e9,#6366f1); color:#fff; border-radius: 10px; border:none; box-shadow:0 2px 8px rgba(99,102,241,.3);"
                                onclick="consultarIA()" title="Solicitar análisis automático a la IA">
                                <i class="bi bi-robot me-2"></i> Consultar IA
                            </button>
                            <div class="vr mx-1" style="height:32px;"></div>
                            <button class="btn px-4 py-2 fw-bold d-flex align-items-center"
                                style="background:#fff1f2; color:#be123c; border: 1px solid #fda4af; border-radius: 10px;"
                                id="btnRechazar" onclick="ejecutarDecision('rechazar')">
                                <i class="bi bi-x-circle me-2"></i> Rechazar Solicitud
                            </button>
                            <button class="btn px-4 py-2 fw-bold d-flex align-items-center shadow-sm"
                                style="background:#0E544C; color:#fff; border-radius: 10px;" id="btnAprobar"
                                onclick="ejecutarDecision('aprobar')">
                                <i class="bi bi-check2-circle me-2"></i> Aprobar Solicitud
                            </button>
                        <?php endif; ?>
                    </div>
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
                                        <strong>vista</strong>: Solo ve solicitudes con <em>Fecha Pedido = hoy</em>.<br>
                                        <strong>ver_completo</strong>: Ve todas las solicitudes sin restricción de fecha.<br>
                                        <strong>aprobar</strong>: Aprobar / Rechazar solicitudes y crear anulaciones web.<br>
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

        /* Premium Modal Overrides */
        .modal-xl {
            max-width: 1140px;
        }

        .det-chip-premium {
            background: #ffffff;
            border: 1px solid #edf2f7;
            border-radius: 10px;
            padding: 10px 14px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
            transition: all 0.2s;
        }

        .det-chip-premium:hover {
            border-color: #cbd5e0;
            background: #f8fafc;
        }

        .table-premium {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
        }

        .table-premium thead th {
            background: #f8fafc;
            color: #4a5568;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px;
            border-bottom: 2px solid #edf2f7;
        }

        .table-premium tbody td {
            padding: 12px;
            border-bottom: 1px solid #edf2f7;
            vertical-align: middle;
            font-size: 13px;
        }

        .detalle-header-premium {
            background: #f1f5f9;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
        }

        .btn-modern {
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-modern:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .truncate-1 {
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const PUEDE_APROBAR     = <?php echo $puedeAprobar     ? 'true' : 'false'; ?>;
        const PUEDE_VER_COMPLETO = <?php echo $puedeVerCompleto ? 'true' : 'false'; ?>;
        const USUARIO_ACTUAL     = '<?php echo htmlspecialchars($usuario['Nombre'] . ' ' . $usuario['Apellido']); ?>';
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
            <div class="btn-floating-pitaya" title="Herramientas">
                <i class="fas fa-wrench"></i>
            </div>
        </div>
    <?php endif; ?>
    <!-- FAB Draggable: permite mover el botón flotante libremente en el viewport -->
    <script src="/core/assets/js/fab_button.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>